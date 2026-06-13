/*
 * main.js — shared frontend interactions (vanilla JS, no dependencies, no build step)
 *
 * Local Business Website System — shared frontend design system.
 * Implements (Req 11.3, 11.5, 11.6, 19.4):
 *   - Nav hamburger toggle with aria-expanded
 *   - Client-side required-field hints + disable-on-submit (server stays source of truth)
 *   - Testimonial slider: prev/next, dot indicators, auto-advance (respects prefers-reduced-motion)
 *   - FAQ accordion: expand/collapse, single-open, keyboard accessible, toggles aria-expanded
 *
 * Progressive enhancement: every feature guards for the presence of its markup and
 * degrades gracefully when JavaScript is unavailable. Markup hooks use `data-*`
 * attributes so the CSS/markup (task 7.1, header.php, footer.php) can attach behavior
 * without coupling to specific class names.
 */
(function () {
  'use strict';

  /**
   * True when the visitor has requested reduced motion (Req 11.6).
   * Read lazily so a runtime preference change is honored where supported.
   */
  function prefersReducedMotion() {
    return (
      typeof window.matchMedia === 'function' &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
  }

  /* ---------------------------------------------------------------------------
   * 1. Nav hamburger toggle (Req 11.3)
   * Markup contract:
   *   <button class="nav-toggle" data-nav-toggle aria-controls="primary-nav"
   *           aria-expanded="false">…</button>
   *   <nav id="primary-nav" class="nav__menu" data-nav-menu>…</nav>
   * ------------------------------------------------------------------------- */
  function initNavToggle() {
    var toggles = document.querySelectorAll('[data-nav-toggle]');
    if (!toggles.length) return;

    Array.prototype.forEach.call(toggles, function (toggle) {
      // Resolve the controlled menu via aria-controls, falling back to a sibling
      // marked with [data-nav-menu].
      var controlledId = toggle.getAttribute('aria-controls');
      var menu = controlledId
        ? document.getElementById(controlledId)
        : document.querySelector('[data-nav-menu]');
      if (!menu) return;

      // Ensure a sensible initial state.
      if (!toggle.hasAttribute('aria-expanded')) {
        toggle.setAttribute('aria-expanded', 'false');
      }

      function setExpanded(expanded) {
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        menu.classList.toggle('is-open', expanded);
        // Reflect open state on the toggle for styling the hamburger icon.
        toggle.classList.toggle('is-active', expanded);
      }

      function isExpanded() {
        return toggle.getAttribute('aria-expanded') === 'true';
      }

      toggle.addEventListener('click', function () {
        setExpanded(!isExpanded());
      });

      // Close the menu on Escape for keyboard users.
      menu.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' || event.key === 'Esc') {
          setExpanded(false);
          toggle.focus();
        }
      });
    });
  }

  /* ---------------------------------------------------------------------------
   * 2. Form handling: required-field hints + disable-on-submit (Req 11.5)
   * The server remains the source of truth for validation; this is only a
   * fast, accessible client-side hint layer.
   * Markup contract: standard forms; fields marked with the `required`
   * attribute participate. Opt out per-form with [data-no-enhance].
   * ------------------------------------------------------------------------- */
  function initFormHandling() {
    var forms = document.querySelectorAll('form');
    if (!forms.length) return;

    Array.prototype.forEach.call(forms, function (form) {
      if (form.hasAttribute('data-no-enhance')) return;

      var requiredFields = form.querySelectorAll(
        'input[required], select[required], textarea[required]'
      );

      // Clear a field's invalid hint as the visitor corrects it.
      Array.prototype.forEach.call(requiredFields, function (field) {
        field.addEventListener('input', function () {
          clearFieldHint(field);
        });
        field.addEventListener('change', function () {
          clearFieldHint(field);
        });
      });

      form.addEventListener('submit', function (event) {
        var firstInvalid = null;

        Array.prototype.forEach.call(requiredFields, function (field) {
          if (isFieldEmpty(field)) {
            showFieldHint(field);
            if (!firstInvalid) firstInvalid = field;
          } else {
            clearFieldHint(field);
          }
        });

        if (firstInvalid) {
          // Block submission and surface the first problem (Req 11.5).
          event.preventDefault();
          firstInvalid.focus();
          return;
        }

        // Valid: disable the submit control(s) to prevent double submission.
        disableSubmitControls(form);
      });
    });
  }

  function isFieldEmpty(field) {
    if (field.type === 'checkbox' || field.type === 'radio') {
      return !field.checked;
    }
    return field.value.trim() === '';
  }

  function showFieldHint(field) {
    field.setAttribute('aria-invalid', 'true');
    field.classList.add('is-invalid');

    var hint = getHintElement(field);
    if (hint) {
      hint.textContent =
        field.getAttribute('data-required-message') || 'This field is required.';
      hint.hidden = false;
    }
  }

  function clearFieldHint(field) {
    field.removeAttribute('aria-invalid');
    field.classList.remove('is-invalid');

    var hint = getHintElement(field, true);
    if (hint) {
      hint.textContent = '';
      hint.hidden = true;
    }
  }

  /**
   * Find (or create, unless readOnly) the live-region hint element associated
   * with a field. Reused across submits so hints are not duplicated.
   */
  function getHintElement(field, readOnly) {
    var hintId = field.getAttribute('data-hint-id');
    if (hintId) {
      var existing = document.getElementById(hintId);
      if (existing || readOnly) return existing;
    }

    if (readOnly) return null;

    var id =
      (field.id || field.name || 'field') + '-hint-' + Math.random().toString(36).slice(2, 8);
    var hint = document.createElement('span');
    hint.className = 'field-hint';
    hint.id = id;
    hint.setAttribute('role', 'alert');
    hint.hidden = true;
    field.setAttribute('data-hint-id', id);

    // Associate the hint with the field for assistive technology.
    var describedBy = field.getAttribute('aria-describedby');
    field.setAttribute('aria-describedby', describedBy ? describedBy + ' ' + id : id);

    // Insert immediately after the field (or its wrapping label parent).
    if (field.parentNode) {
      field.parentNode.insertBefore(hint, field.nextSibling);
    }
    return hint;
  }

  function disableSubmitControls(form) {
    var controls = form.querySelectorAll(
      'button[type="submit"], input[type="submit"]'
    );
    Array.prototype.forEach.call(controls, function (control) {
      control.disabled = true;
      control.classList.add('is-submitting');
      if (control.tagName === 'BUTTON' && !control.hasAttribute('data-original-label')) {
        control.setAttribute('data-original-label', control.innerHTML);
      }
    });
  }

  /* ---------------------------------------------------------------------------
   * 3. Testimonial slider (Req 11.3, 11.6)
   * Markup contract:
   *   <div class="slider" data-slider [data-slider-interval="6000"]>
   *     <div class="slider__track" data-slider-track>
   *       <div class="slider__slide">…</div> … (one per testimonial)
   *     </div>
   *     <button data-slider-prev>…</button>
   *     <button data-slider-next>…</button>
   *     <div class="slider__dots" data-slider-dots></div> (dots generated here)
   *   </div>
   * ------------------------------------------------------------------------- */
  function initSliders() {
    var sliders = document.querySelectorAll('[data-slider]');
    if (!sliders.length) return;
    Array.prototype.forEach.call(sliders, function (slider) {
      setupSlider(slider);
    });
  }

  function setupSlider(slider) {
    var track =
      slider.querySelector('[data-slider-track]') ||
      slider.querySelector('.slider__track');
    if (!track) return;

    var slides = Array.prototype.slice.call(
      track.querySelectorAll('[data-slider-slide], .slider__slide')
    );
    if (slides.length === 0) return;

    var prevBtn = slider.querySelector('[data-slider-prev]');
    var nextBtn = slider.querySelector('[data-slider-next]');
    var dotsContainer = slider.querySelector('[data-slider-dots], .slider__dots');

    var current = 0;
    var autoTimer = null;
    var intervalMs = parseInt(slider.getAttribute('data-slider-interval'), 10) || 6000;

    // Build dot indicators (Req 11.3).
    var dots = [];
    if (dotsContainer) {
      dotsContainer.innerHTML = '';
      slides.forEach(function (_slide, index) {
        var dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'slider__dot';
        dot.setAttribute('aria-label', 'Go to testimonial ' + (index + 1));
        dot.addEventListener('click', function () {
          goTo(index);
          restartAuto();
        });
        dotsContainer.appendChild(dot);
        dots.push(dot);
      });
    }

    function render() {
      slides.forEach(function (slide, index) {
        var isActive = index === current;
        slide.classList.toggle('is-active', isActive);
        // Hide inactive slides from layout and assistive tech.
        slide.hidden = !isActive;
        slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
      });
      dots.forEach(function (dot, index) {
        var isActive = index === current;
        dot.classList.toggle('is-active', isActive);
        dot.setAttribute('aria-current', isActive ? 'true' : 'false');
      });
    }

    function goTo(index) {
      var count = slides.length;
      current = ((index % count) + count) % count; // wrap both directions
      render();
    }

    function next() {
      goTo(current + 1);
    }

    function prev() {
      goTo(current - 1);
    }

    function stopAuto() {
      if (autoTimer !== null) {
        window.clearInterval(autoTimer);
        autoTimer = null;
      }
    }

    function startAuto() {
      // No auto-advance under reduced motion (Req 11.6) or with a single slide.
      if (prefersReducedMotion() || slides.length < 2) return;
      stopAuto();
      autoTimer = window.setInterval(next, intervalMs);
    }

    function restartAuto() {
      stopAuto();
      startAuto();
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        next();
        restartAuto();
      });
    }
    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        prev();
        restartAuto();
      });
    }

    // Pause auto-advance while the visitor interacts with the slider.
    slider.addEventListener('mouseenter', stopAuto);
    slider.addEventListener('mouseleave', startAuto);
    slider.addEventListener('focusin', stopAuto);
    slider.addEventListener('focusout', startAuto);

    render();
    startAuto();
  }

  /* ---------------------------------------------------------------------------
   * 4. FAQ accordion (Req 11.3, 19.4)
   * Single-open, keyboard accessible, toggles aria-expanded.
   * Markup contract:
   *   <div class="accordion" data-accordion [data-accordion-multi]>
   *     <div class="accordion__item">
   *       <button class="accordion__trigger" data-accordion-trigger
   *               aria-expanded="false" aria-controls="faq-1">Question</button>
   *       <div id="faq-1" class="accordion__panel" data-accordion-panel>Answer</div>
   *     </div> …
   *   </div>
   * Native <button> elements provide built-in keyboard activation (Enter/Space);
   * arrow keys move between triggers for richer keyboard support.
   * ------------------------------------------------------------------------- */
  function initAccordions() {
    var accordions = document.querySelectorAll('[data-accordion]');
    if (!accordions.length) return;
    Array.prototype.forEach.call(accordions, function (accordion) {
      setupAccordion(accordion);
    });
  }

  function setupAccordion(accordion) {
    var triggers = Array.prototype.slice.call(
      accordion.querySelectorAll('[data-accordion-trigger], .accordion__trigger')
    );
    if (triggers.length === 0) return;

    // Default to single-open; opt into multi-open with [data-accordion-multi].
    var allowMultiple = accordion.hasAttribute('data-accordion-multi');

    function panelFor(trigger) {
      var id = trigger.getAttribute('aria-controls');
      if (id) {
        var byId = document.getElementById(id);
        if (byId) return byId;
      }
      // Fallback: next sibling marked as a panel.
      var sibling = trigger.nextElementSibling;
      while (sibling) {
        if (
          sibling.hasAttribute('data-accordion-panel') ||
          sibling.classList.contains('accordion__panel')
        ) {
          return sibling;
        }
        sibling = sibling.nextElementSibling;
      }
      return null;
    }

    function setOpen(trigger, open) {
      var panel = panelFor(trigger);
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (panel) {
        panel.hidden = !open;
        panel.classList.toggle('is-open', open);
      }
    }

    triggers.forEach(function (trigger, index) {
      // Normalize initial state.
      var initiallyOpen = trigger.getAttribute('aria-expanded') === 'true';
      setOpen(trigger, initiallyOpen);

      trigger.addEventListener('click', function () {
        var isOpen = trigger.getAttribute('aria-expanded') === 'true';

        if (!allowMultiple && !isOpen) {
          // Single-open: close every other trigger first.
          triggers.forEach(function (other) {
            if (other !== trigger) setOpen(other, false);
          });
        }
        setOpen(trigger, !isOpen);
      });

      // Arrow-key / Home / End navigation between triggers (keyboard a11y).
      trigger.addEventListener('keydown', function (event) {
        var target = null;
        switch (event.key) {
          case 'ArrowDown':
            target = triggers[(index + 1) % triggers.length];
            break;
          case 'ArrowUp':
            target = triggers[(index - 1 + triggers.length) % triggers.length];
            break;
          case 'Home':
            target = triggers[0];
            break;
          case 'End':
            target = triggers[triggers.length - 1];
            break;
          default:
            return;
        }
        if (target) {
          event.preventDefault();
          target.focus();
        }
      });
    });
  }

  /* ---------------------------------------------------------------------------
   * Bootstrap
   * ------------------------------------------------------------------------- */
  function init() {
    initNavToggle();
    initFormHandling();
    initSliders();
    initAccordions();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
