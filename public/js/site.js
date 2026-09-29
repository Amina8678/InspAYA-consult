/*
 * InspAya Consult: small progressive enhancements. The site works fully
 * without JavaScript (the menu simply wraps). This adds:
 * - the collapsible menu on small screens (Menu button with aria-expanded;
 *   Escape closes it and returns focus to the button);
 * - the Services submenu toggle (same pattern: a real button, aria-expanded,
 *   Escape closes it and returns focus; without JS the submenu is just
 *   always visible, so this only has to enhance, never gate, access to it);
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

  document.querySelectorAll('.nav-submenu-toggle').forEach(function (submenuToggle) {
    var submenu = document.getElementById(submenuToggle.getAttribute('aria-controls'));
    if (!submenu) {
      return;
    }

    var setSubmenuOpen = function (open) {
      submenu.classList.toggle('is-open', open);
      submenuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    submenuToggle.addEventListener('click', function () {
      setSubmenuOpen(submenuToggle.getAttribute('aria-expanded') !== 'true');
    });

    // Listens on the shared <li>, not just the submenu list: right after
    // opening, focus is still on the toggle button itself (it isn't moved
    // into the list), so a listener on the list alone would miss Escape
    // pressed at that point.
    (submenuToggle.closest('li') || submenu).addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && submenuToggle.getAttribute('aria-expanded') === 'true') {
        setSubmenuOpen(false);
        submenuToggle.focus();
      }
    });

    // A click anywhere outside this item closes it, so it never sits open
    // and in the way once the visitor has moved on.
    document.addEventListener('click', function (event) {
      if (submenuToggle.getAttribute('aria-expanded') === 'true' && !submenuToggle.contains(event.target) && !submenu.contains(event.target)) {
        setSubmenuOpen(false);
      }
    });
  });

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
