/*
 * InspAya Consult: small progressive enhancements. The site works fully
 * without JavaScript (the menu simply wraps). This adds:
 * - the collapsible menu on small screens (Menu button with aria-expanded;
 *   Escape closes it and returns focus to the button);
 * - the "Copy link" button on articles (FR-BLOG-05);
 * - focus on a form's error summary after a failed submission.
 */
(function () {
  'use strict';

  document.documentElement.classList.add('js');

  var toggle = document.querySelector('.nav-toggle');
  var panel = toggle && document.getElementById(toggle.getAttribute('aria-controls'));

  if (toggle && panel) {
    var setOpen = function (open) {
      panel.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    toggle.addEventListener('click', function () {
      setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    panel.addEventListener('keydown', function (event) {
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

  document.querySelectorAll('[data-copy-url]').forEach(function (button) {
    if (!navigator.clipboard) {
      return;
    }

    var status = document.getElementById(button.getAttribute('aria-describedby'));
    button.hidden = false;

    button.addEventListener('click', function () {
      navigator.clipboard.writeText(button.getAttribute('data-copy-url')).then(
        function () { if (status) { status.textContent = 'Link copied to clipboard.'; } },
        function () { if (status) { status.textContent = 'Could not copy the link.'; } }
      );
    });
  });
})();
