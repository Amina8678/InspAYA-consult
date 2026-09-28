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

  // Media picker: refresh the preview when a different image is chosen.
  document.querySelectorAll('select[data-media-preview]').forEach(function (select) {
    var preview = document.getElementById(select.getAttribute('data-media-preview'));
    if (!preview) {
      return;
    }

    select.addEventListener('change', function () {
      var option = select.options[select.selectedIndex];
      var url = option && option.getAttribute('data-url');
      preview.textContent = '';

      if (url) {
        var img = document.createElement('img');
        img.src = url;
        img.alt = 'Preview: ' + (option.getAttribute('data-alt') || 'selected image');
        img.width = 160;
        img.height = 90;
        preview.appendChild(img);
      } else {
        var p = document.createElement('p');
        p.className = 'muted small';
        p.textContent = 'No image selected.';
        preview.appendChild(p);
      }
    });
  });

  var summary = document.getElementById('error-summary');
  if (summary) {
    summary.focus();
  }
})();
