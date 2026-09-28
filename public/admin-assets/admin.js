/*
 * InspAya CMS: progressive enhancements. Everything works without JS.
 * - Collapsible sidebar on small screens (button with aria-expanded; Escape
 *   closes it and returns focus to the button).
 * - Focus the error summary after a failed form submission.
 */
(function () {
  'use strict';

  document.documentElement.classList.add('js');

  var toggle = document.querySelector('.nav-toggle');
  var nav = toggle && document.getElementById(toggle.getAttribute('aria-controls'));

  if (toggle && nav) {
    var setOpen = function (open) {
      nav.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    toggle.addEventListener('click', function () {
      setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    nav.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        setOpen(false);
        toggle.focus();
      }
    });
  }

  var summary = document.getElementById('error-summary');
  if (summary) {
    summary.focus();
  }
})();
