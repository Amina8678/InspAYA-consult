/*
 * InspAya Consult: small progressive enhancements. The site works fully
 * without JavaScript; this adds the "Copy link" button (FR-BLOG-05) and moves
 * focus to a form's error summary after a failed submission.
 */
(function () {
  'use strict';

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
