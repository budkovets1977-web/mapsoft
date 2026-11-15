/* ==========================================================================
   МинскЛайн — Booking UI 2026 (behaviours)
   --------------------------------------------------------------------------
   Progressive enhancement only. Everything degrades gracefully: if this file
   fails to load, the form still works through its plain HTML/Vue bindings.
   Loaded with `defer`, so it runs after the DOM is parsed and after Vue has
   mounted (both are `defer`-ordered before it).
   ========================================================================== */
(function () {
  'use strict';

  var root = document.getElementById('apps');
  if (!root) return;

  /* Deliberately no `var form = root.querySelector('.reservation_form')` here.
     This file is `defer`red and therefore runs before Vue mounts, and Vue's
     in-place mount recreates every node under #apps — a reference taken now
     is a detached element. Everything inside the booking widget is either
     delegated from `document` or re-queried at call time. */
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ----------------------------------------------------------------------
     1. Phone mask — +375 XX XXX XX XX
     ---------------------------------------------------------------------- */
  var PHONE_PLACEHOLDER = '+375 __ ___ __ __';

  function formatPhone(raw) {
    var d = String(raw || '').replace(/\D/g, '');

    /* The 375 prefix is added only when the digits cannot still grow into a
       different shape. The rules are length-based, so they run on every
       keystroke and used to fire mid-number:
         - digits that already carry 375 are left completely alone. Without
           this, typing 375445814680 hit the nine-digit rule on the 9th
           keystroke ("37544581" + "4"), prepended a SECOND 375 and produced
           "+375 37 544 58 14" — 17 characters, i.e. maxlength, so the last
           three digits could not be typed at all;
         - a leading 0 marks the ten-digit form and must not be read as the
           nine-digit one: "029323232" is only the first nine digits of
           0293232323, and prepending there yielded "+375 02 932 32 32";
         - "80…" is the unambiguous long form of a local number;
         - nine digits with no leading 0 is a complete local number.
       Everything else is still being typed and is displayed as typed. */
    if (d.indexOf('375') !== 0) {
      if (d.indexOf('80') === 0) d = '375' + d.slice(2);
      else if (d.length === 10 && d.charAt(0) === '0') d = '375' + d.slice(1);
      else if (d.length === 9 && d.charAt(0) !== '0') d = '375' + d;
    }

    d = d.slice(0, 12);
    if (!d) return '';

    var out = '+' + d.slice(0, 3);
    var rest = d.slice(3);
    var groups = [2, 3, 2, 2];
    for (var i = 0, pos = 0; i < groups.length && pos < rest.length; i++) {
      out += ' ' + rest.slice(pos, pos + groups[i]);
      pos += groups[i];
    }
    return out;
  }

  function digitsOf(value) {
    return String(value || '').replace(/\D/g, '');
  }

  function isPhoneField(el) {
    return !!el && (el.id === 'bk-phone' || el.id === 'bk-travel-phone');
  }

  /* Delegated from `document`, NOT bound to the input.
     defer order is vue.min.js → axios.js → this file, and the inline Vue
     bootstrap in index.php waits for DOMContentLoaded — so this code runs
     BEFORE Vue mounts. Vue's in-place mount throws away and recreates every
     node under #apps, and a listener bound to the pre-mount <input> dies with
     that node: the mask applied its placeholder (a setAttribute on a node that
     was about to be replaced) and then formatted nothing at all. Delegation is
     the same fix the OTP handlers in section 2 use, and the same reason stated
     there. */
  document.addEventListener('input', function (e) {
    if (!isPhoneField(e.target)) return;
    var input = e.target;
    /* Assign only when the formatted form actually differs. Writing the same
       string back resets the caret to the end on every keystroke, which is why
       this is a comparison and not an unconditional write. The cap at 12 digits
       lives inside formatPhone, so an over-long paste gets trimmed here too
       instead of leaving 14 stray digits in the field. */
    var next = formatPhone(input.value);
    if (next !== input.value) input.value = next;
  }, true);

  /* No focusin pre-fill any more. Seeding "+375 " on focus looked helpful but
     fought the mask: a user who then typed the full number *with* the prefix
     (the natural thing to do, "+375445814680") produced "+375 375..." and the
     field filled with 17 characters before the real digits were in. The
     placeholder already advertises the format as "+375 __ ___ __ __", and the
     mask resolves the prefix by itself — after nine local digits, after "80…",
     or immediately when the digits already start with 375. */

  document.addEventListener('focusout', function (e) {
    if (!isPhoneField(e.target)) return;
    var input = e.target;
    var next = digitsOf(input.value).length >= 12 ? formatPhone(input.value) : '';
    if (next === input.value) return;
    input.value = next;
    /* Vue owns this field through v-model, so a bare DOM write is reverted by
       its next render — the model still holds the old value and puts it back.
       Dispatching input is what makes v-model read the new one, which is what
       makes "clear an unfinished number on blur" actually stick. */
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });

  /* Attributes have to be re-applied after Vue mounts: the template in
     index.php carries its own placeholder, so the node Vue creates comes back
     with "+375 29 000 00 00" instead of the mask's hint. */
  function primePhoneFields() {
    var ids = ['bk-phone', 'bk-travel-phone'];
    for (var i = 0; i < ids.length; i++) {
      var el = document.getElementById(ids[i]);
      if (!el) continue;
      el.setAttribute('placeholder', PHONE_PLACEHOLDER);
      el.setAttribute('autocapitalize', 'off');
      el.setAttribute('spellcheck', 'false');
      el.setAttribute('enterkeyhint', 'done');
    }
  }

  primePhoneFields();
  document.addEventListener('DOMContentLoaded', primePhoneFields);

  /* ----------------------------------------------------------------------
     2. OTP fields — digits only, keep maxlength in sync
     ----------------------------------------------------------------------
     The code inputs live behind v-if, so they do not exist yet when this file
     runs. Delegated from `document` rather than from the form element: the
     form reference captured at load time is a DETACHED node (see section 3),
     so a listener on it would never see an event from the live tree. */
  /* The SMS carries 4 digits. Keep this in step with maxlength="4" and
     placeholder="0000" in index.php — they were all written as 5. */
  var OTP_LENGTH = 4;

  function otpInput(target) {
    if (!target || !target.id) return null;
    return /^bk-(travel-)?code$/.test(target.id) ? target : null;
  }

  document.addEventListener('input', function (e) {
    var input = otpInput(e.target);
    if (!input) return;
    var cleaned = digitsOf(input.value).slice(0, OTP_LENGTH);
    if (input.value !== cleaned) input.value = cleaned;
  }, true);

  document.addEventListener('keydown', function (e) {
    var input = otpInput(e.target);
    if (!input) return;
    if (e.key === 'Enter') {
      e.preventDefault();
      var box = input.closest('.code_form');
      var btn = box && box.querySelector('.form_btn:not(.disabled)');
      if (btn) btn.click();
    }
  });

  /* ----------------------------------------------------------------------
     3. Progress steps
     ----------------------------------------------------------------------
     Nothing from inside #apps may be cached in a variable here. This file is
     `defer`red, so it runs BEFORE Vue mounts, and Vue's in-place mount throws
     away and recreates every node under the root: a `querySelector` result
     taken now points at a detached element, its setAttribute silently updates
     a node that is no longer in the document, and the feature looks simply
     inert. That is why the steps rail never advanced and the mobile sticky bar
     never appeared. Every lookup is redone per pass instead. */
  function setSteps() {
    var app = document.getElementById('apps');
    if (!app || !app.__vue__) return;
    var vm = app.__vue__;

    var liveForm = app.querySelector('.reservation_form');
    if (!liveForm) return;

    var steps = liveForm.querySelectorAll('[data-steps] li');
    if (!steps.length) return;
    var sticky = liveForm.querySelector('.bk-sticky');
    var submitBtn = liveForm.querySelector('[save-btn-line] .form_btn');

    var route = vm.city_a != null && vm.city_b != null && !!vm.date;
    var trip = route && vm.reis.length > 0 && vm.idReis != null && vm.idStopFrom != null && vm.idStopTo != null;
    var passenger = vm.second_name.length !== 0 && vm.number_phone.length >= 12;
    var confirm = passenger && vm.enableCode;

    var flags = [route, trip, passenger, confirm];
    var active = 0;
    for (var i = 0; i < flags.length; i++) {
      if (flags[i]) active = i + 1;
    }

    steps.forEach(function (li, idx) {
      li.setAttribute('data-state', idx + 1 <= active ? 'done' : 'todo');
    });
    if (active >= 1) steps[active - 1].setAttribute('data-state', 'active');

    /* Sticky bar appears once the passenger block is filled */
    if (sticky) {
      var show = passenger;
      if (show !== (sticky.getAttribute('data-visible') === 'true')) {
        sticky.setAttribute('data-visible', show ? 'true' : 'false');
        sticky.setAttribute('aria-hidden', show ? 'false' : 'true');
      }
      /* Reflect the live button state */
      var sb = sticky.querySelector('.form_btn');
      if (sb) {
        sb.classList.toggle('disabled', !vm.canBook);
        sb.setAttribute('aria-disabled', vm.canBook ? 'false' : 'true');
      }
    }

    if (submitBtn) {
      submitBtn.setAttribute('data-busy', vm.loading ? 'true' : 'false');
    }
  }

  /* Hook into Vue's reactivity so the rail stays in sync without polling.

     This cannot be wired at load time: the file is `defer`red, so it runs
     BEFORE the inline bootstrap in index.php mounts Vue on DOMContentLoaded,
     and `#apps.__vue__` does not exist yet. An `if (root.__vue__)` check here
     was therefore always false — the steps rail never advanced and the mobile
     sticky bar never appeared. Try now, then on DOMContentLoaded, and only
     fall back to raw event delegation if Vue never arrives. */
  var wired = false;

  function wire() {
    var app = document.getElementById('apps');
    if (!app || !app.__vue__) return false;
    if (wired) return true;
    wired = true;
    app.__vue__.$watch(function () {
      return [
        this.city_a, this.city_b, this.date, this.reis && this.reis.length,
        this.idReis, this.idStopFrom, this.idStopTo,
        this.second_name, this.number_phone, this.place, this.price,
        this.enableCode, this.loading
      ].join('|');
    }, setSteps, { immediate: true });
    return true;
  }

  if (!wire()) {
    document.addEventListener('DOMContentLoaded', function () {
      if (wire()) return;
      /* Vue did not load: the static markup is still the live tree, so
         delegation keeps the rail and the sticky bar working. */
      setSteps();
      document.addEventListener('input', setSteps, true);
      document.addEventListener('change', setSteps, true);
    });
  }

  /* ----------------------------------------------------------------------
     4. Smooth anchor scrolling (replaces the jQuery easing plugin)
     ---------------------------------------------------------------------- */

  /* The offset has to be the navbar's REAL height: the file below toggles
     .top-nav-collapse, and that state has half the padding, so a hardcoded
     number goes stale the moment either padding changes. +4px keeps the
     target from touching the bar's bottom edge. */
  function scrollOffset() {
    var bar = document.querySelector('.fixed-top');
    var h = bar ? bar.getBoundingClientRect().height : 0;
    return (h || 72) + 4;
  }

  document.addEventListener('click', function (e) {
    var link = e.target.closest ? e.target.closest('a.page-scroll') : null;
    if (!link) return;
    var href = link.getAttribute('href') || '';
    if (href.charAt(0) !== '#' || href.length < 2) return;
    var target = document.getElementById(href.slice(1));
    if (!target) return;
    e.preventDefault();
    var top = target.getBoundingClientRect().top + window.pageYOffset - scrollOffset();
    window.scrollTo({ top: top, behavior: reduceMotion ? 'auto' : 'smooth' });
    if (history.replaceState) history.replaceState(null, '', href);
  });

  /* ----------------------------------------------------------------------
     5. Navbar shrink on scroll (vanilla, no jQuery scroll handler)
     ---------------------------------------------------------------------- */
  var nav = document.querySelector('.fixed-top');
  if (nav) {
    var ticking = false;
    var sync = function () {
      nav.classList.toggle('top-nav-collapse', window.pageYOffset > 60);
      ticking = false;
    };
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; window.requestAnimationFrame(sync); }
    }, { passive: true });
    sync();
  }

  /* ----------------------------------------------------------------------
     6. Mobile offcanvas menu (Bootstrap 4 offcanvas, done without jQuery)

     Kept in step with a real disclosure widget: the trigger's accessible name
     and aria-expanded are in the markup from the start, Escape closes, a click
     outside closes, Tab is kept inside while open, and focus returns to the
     trigger afterwards. Without those, the panel was a keyboard trap: open it
     with Enter and there was no way out except the links themselves.
     ---------------------------------------------------------------------- */
  var panel = document.querySelector('.offcanvas-collapse');
  var toggle = document.querySelector('[data-toggle="offcanvas"]');

  if (panel && toggle) {
    var isOpen = function () { return panel.classList.contains('open'); };
    var focusables = function () {
      return Array.prototype.filter.call(
        panel.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])'),
        /* Видимость проверяем через getClientRects(), а не через offsetParent:
           у любого position: fixed элемента offsetParent равен null, и такой
           элемент молча выпадал бы из списка. */
        function (el) {
          if (el.hasAttribute('hidden') || el.getAttribute('aria-hidden') === 'true') return false;
          return el.getClientRects().length > 0;
        }
      );
    };

    var setOpen = function (open) {
      /* Where the focus is has to be read BEFORE the class comes off: the panel
         is display:none while closed, so closing it first drops the focus to
         <body> and the "was it inside?" test then always answers no. */
      var hadFocusInside = !!(document.activeElement && panel.contains(document.activeElement));
      panel.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
      if (open) {
        var first = focusables()[0];
        if (first) first.focus();
      } else if (hadFocusInside) {
        toggle.focus();
      }
    };

    toggle.addEventListener('click', function () { setOpen(!isOpen()); });

    /* Click on a link inside the panel closes it. */
    panel.addEventListener('click', function (e) {
      if (e.target.closest('a')) setOpen(false);
    });

    /* Click outside the panel closes it. */
    document.addEventListener('click', function (e) {
      if (!isOpen()) return;
      if (panel.contains(e.target) || toggle.contains(e.target)) return;
      setOpen(false);
    });

    document.addEventListener('keydown', function (e) {
      if (!isOpen()) return;

      if (e.key === 'Escape' || e.key === 'Esc') {
        e.preventDefault();
        setOpen(false);
        return;
      }

      if (e.key !== 'Tab') return;
      var list = focusables();
      if (!list.length) return;
      var first = list[0];
      var last = list[list.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });
  }

  /* ----------------------------------------------------------------------
     7. Open the calendar on a click anywhere in the field
     ----------------------------------------------------------------------
     The `min` attribute is deliberately NOT set here: index.php binds
     :min="today" on the same input and Vue owns it. Two writers were a race —
     at 23:59 they could disagree by a day, and Vue's value should win anyway
     because it is evaluated at mount.

     Chrome and Edge only open the date picker when you hit the little calendar
     indicator on the right; a click anywhere else on the field just puts a caret
     in it, which reads as "the date box is broken". showPicker() is the
     supported way to ask for it.

     Delegated from `document` rather than bound to the input directly. This
     file is deferred, so it runs before Vue mounts, and Vue's in-place mount
     throws away and recreates the nodes under #apps — a listener bound to the
     pre-mount <input id="bk-date"> is silently lost when that happens. The same
     reason the OTP handlers above listen on `document`.

     showPicker() throws InvalidStateError when a picker is already showing,
     which is exactly what happens if the click landed on the native indicator.
     That is the normal path, so the throw is swallowed rather than reported.
     Browsers without showPicker() (Safari) already open their own picker on any
     click in the field, so this is a no-op there. */
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || t.id !== 'bk-date' || typeof t.showPicker !== 'function') return;
    try {
      t.showPicker();
    } catch (err) {
      /* A picker is already open, or this click was not user-activated. */
    }
  });
})();
