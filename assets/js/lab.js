(function () {
  var logEl;
  var cookieResultEl;
  var windowStatusEl;

  function $(id) {
    return document.getElementById(id);
  }

  function logEvent(message) {
    if (!logEl) return;
    logEl.textContent = message + ' | ' + new Date().toLocaleTimeString();
  }

  function onWindowLoad() {
    logEvent('Window load event fired');
    if (windowStatusEl) {
      windowStatusEl.textContent = 'loaded (' + window.innerWidth + 'x' + window.innerHeight + ')';
    }
  }

  function onWindowResize() {
    if (windowStatusEl) {
      windowStatusEl.textContent = 'resized (' + window.innerWidth + 'x' + window.innerHeight + ')';
    }
  }

  function handleInlineSubmit(event) {
    event.preventDefault();
    logEvent('Form submit event fired');
    return false;
  }

  function drawCanvas() {
    var canvas = $('canvasDemo');
    if (!canvas || !canvas.getContext) return;
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#e8f0ff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.fillStyle = '#0d6efd';
    ctx.fillRect(14, 14, 90, 40);

    ctx.fillStyle = '#198754';
    ctx.beginPath();
    ctx.arc(165, 58, 28, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = '#1a2536';
    ctx.font = '14px sans-serif';
    ctx.fillText('Canvas Demo', 14, 94);
  }

  function initEvents() {
    var keyboardField = $('keyboardField');
    var selectField = $('selectField');
    var mouseArea = $('mouseArea');

    if (keyboardField) {
      keyboardField.addEventListener('keydown', function (e) {
        logEvent('Keyboard event: keydown -> ' + e.key);
      });
    }

    if (selectField) {
      selectField.addEventListener('change', function () {
        logEvent('Form change event: ' + selectField.value);
      });
    }

    if (mouseArea) {
      mouseArea.addEventListener('mousemove', function () {
        logEvent('Mouse move event');
      });
      mouseArea.addEventListener('click', function () {
        logEvent('Mouse click event');
      });
      mouseArea.addEventListener('mouseenter', function () {
        mouseArea.classList.add('bg-light');
      });
      mouseArea.addEventListener('mouseleave', function () {
        mouseArea.classList.remove('bg-light');
      });
    }
  }

  function initPopups() {
    var alertBtn = $('alertBtn');
    var confirmBtn = $('confirmBtn');
    var promptBtn = $('promptBtn');
    if (!alertBtn || !confirmBtn || !promptBtn) return;

    alertBtn.addEventListener('click', function () {
      alert('Alert box example from JavaScript.');
    });

    confirmBtn.addEventListener('click', function () {
      var ok = confirm('Confirm box example. Continue?');
      logEvent('Confirm result: ' + (ok ? 'OK' : 'Cancel'));
    });

    promptBtn.addEventListener('click', function () {
      var value = prompt('Prompt box example: enter any short text', '');
      logEvent('Prompt result: ' + (value || '(empty/cancelled)'));
    });
  }

  function setDemoCookie() {
    if (window.JobPortalCommon && JobPortalCommon.setCookie) {
      JobPortalCommon.setCookie('web_lab_topic', 'javascript_cookie_demo', 7);
    } else {
      document.cookie = 'web_lab_topic=javascript_cookie_demo; path=/; max-age=604800';
    }
    cookieResultEl.textContent = 'Cookie set successfully.';
  }

  function readDemoCookie() {
    var value = '';
    if (window.JobPortalCommon && JobPortalCommon.getCookie) {
      value = JobPortalCommon.getCookie('web_lab_topic');
    } else {
      var match = document.cookie.match(/(?:^|;\s*)web_lab_topic=([^;]+)/);
      value = match ? decodeURIComponent(match[1]) : '';
    }
    cookieResultEl.textContent = value ? ('Cookie value: ' + value) : 'Cookie not found.';
  }

  function clearDemoCookie() {
    if (window.JobPortalCommon && JobPortalCommon.deleteCookie) {
      JobPortalCommon.deleteCookie('web_lab_topic');
    } else {
      document.cookie = 'web_lab_topic=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
    }
    cookieResultEl.textContent = 'Cookie cleared.';
  }

  function initCookieButtons() {
    var setBtn = $('setCookieBtn');
    var readBtn = $('readCookieBtn');
    var clearBtn = $('clearCookieBtn');
    if (!setBtn || !readBtn || !clearBtn) return;
    setBtn.addEventListener('click', setDemoCookie);
    readBtn.addEventListener('click', readDemoCookie);
    clearBtn.addEventListener('click', clearDemoCookie);
  }

  function initJQueryDemo() {
    if (!window.jQuery) return;

    window.jQuery(document).ready(function () {
      window.jQuery('#jqSelectorDemo .jq-item:odd').addClass('jq-highlight');

      window.jQuery('#jqToggleBtn').on('click', function () {
        window.jQuery('#jqEffectsBox').slideToggle(180).fadeToggle(180).fadeToggle(180);
      });

      window.jQuery('#jqEffectsBox').on('mouseenter', function () {
        window.jQuery(this).stop(true, true).fadeTo(120, 0.65);
      }).on('mouseleave', function () {
        window.jQuery(this).stop(true, true).fadeTo(120, 1);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (window.JobPortalCommon) {
      JobPortalCommon.renderNav();
    }
    logEl = $('eventLog');
    cookieResultEl = $('cookieResult');
    windowStatusEl = $('windowStatus');

    drawCanvas();
    initEvents();
    initPopups();
    initCookieButtons();
    initJQueryDemo();
  });

  window.WebLab = {
    logEvent: logEvent,
    onWindowLoad: onWindowLoad,
    onWindowResize: onWindowResize,
    handleInlineSubmit: handleInlineSubmit
  };
})();
