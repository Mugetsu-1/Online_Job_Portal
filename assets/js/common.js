(function () {
  var USER_KEY = 'jp_user';

  function getUser() {
    var raw = localStorage.getItem(USER_KEY);
    if (!raw) return null;
    try {
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function setUser(user) {
    localStorage.setItem(USER_KEY, JSON.stringify(user));
  }

  function clearUser() {
    localStorage.removeItem(USER_KEY);
  }

  function setCookie(name, value, days) {
    var maxAge = '';
    if (typeof days === 'number' && days > 0) {
      maxAge = '; max-age=' + String(days * 24 * 60 * 60);
    }
    document.cookie = encodeURIComponent(name) + '=' + encodeURIComponent(value || '') + '; path=/' + maxAge + '; samesite=lax';
  }

  function getCookie(name) {
    var escaped = encodeURIComponent(name).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + escaped + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
  }

  function deleteCookie(name) {
    document.cookie = encodeURIComponent(name) + '=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; samesite=lax';
  }

  function isLoggedIn() {
    return !!getUser();
  }

  function ensureAuth(roles) {
    var user = getUser();
    if (!user) {
      location.href = 'login.html';
      return null;
    }
    if (roles && roles.length && roles.indexOf(user.role) === -1) {
      location.href = 'dashboard.html';
      return null;
    }
    return user;
  }

  function parseQuery() {
    var params = new URLSearchParams(location.search);
    var result = {};
    params.forEach(function (value, key) {
      result[key] = value;
    });
    return result;
  }

  function showAlert(target, message, type) {
    var el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) return;
    var cssType = type || 'info';
    el.innerHTML = '<div class="alert alert-' + cssType + '">' + escapeHtml(message) + '</div>';
  }

  function clearAlert(target) {
    var el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) return;
    el.innerHTML = '';
  }

  function setButtonLoading(button, isLoading, loadingText) {
    if (!button) return;
    if (isLoading) {
      if (!button.getAttribute('data-original-text')) {
        button.setAttribute('data-original-text', button.textContent || '');
      }
      button.disabled = true;
      button.textContent = loadingText || 'Please wait...';
      return;
    }
    button.disabled = false;
    var original = button.getAttribute('data-original-text');
    if (original !== null) {
      button.textContent = original;
      button.removeAttribute('data-original-text');
    }
  }

  function setFormLoading(form, isLoading, loadingText) {
    if (!form) return;
    var submitBtn = form.querySelector('button[type="submit"]');
    setButtonLoading(submitBtn, isLoading, loadingText);
  }

  function formatMoney(min, max, currency) {
    if (!min && !max) return 'Not specified';
    var fmt = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });
    var c = currency || 'USD';
    if (min && max) return c + ' ' + fmt.format(min) + ' - ' + fmt.format(max);
    if (min) return c + ' ' + fmt.format(min) + '+';
    return 'Up to ' + c + ' ' + fmt.format(max);
  }

  function formatDate(dateText) {
    if (!dateText) return '-';
    var date = new Date(dateText);
    if (Number.isNaN(date.getTime())) return dateText;
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function navTemplate() {
    var user = getUser();
    var links = [
      '<a class="nav-link" href="index.html">Home</a>',
      '<a class="nav-link" href="jobs.html">Jobs</a>'
    ];

    if (user) {
      links.push('<a class="nav-link" href="dashboard.html">Dashboard</a>');
      links.push('<a class="nav-link" href="profile.html">Profile</a>');
      links.push('<button id="logoutBtn" class="btn btn-outline-danger btn-sm ms-2">Logout</button>');
    } else {
      links.push('<a class="nav-link" href="login.html">Login</a>');
      links.push('<a class="btn btn-primary btn-sm ms-2" href="register.html">Register</a>');
    }

    return '<nav class="navbar bg-white border-bottom sticky-top">'
      + '<div class="container d-flex flex-wrap gap-2 py-2">'
      + '<a class="navbar-brand fw-bold me-auto" href="index.html">JobPortal</a>'
      + '<div class="navbar-nav flex-row flex-wrap align-items-center gap-1">' + links.join('') + '</div>'
      + '</div>'
      + '</nav>';
  }

  function renderNav() {
    var host = document.getElementById('topNav');
    if (!host) return;
    host.innerHTML = navTemplate();
    var btn = document.getElementById('logoutBtn');
    if (btn) {
      btn.addEventListener('click', function () {
        JobPortalAPI.logout()
          .catch(function () {})
          .finally(function () {
            clearUser();
            location.href = 'login.html';
          });
      });
    }
  }

  function jobCard(job, includeActions) {
    var apply = includeActions ? '<a class="btn btn-primary btn-sm" href="jobs_details.html?id=' + job.id + '">View Details</a>' : '';
    return '<article class="card shadow-sm h-100 job-card">'
      + '<div class="card-body d-flex flex-column">'
      + '<h5 class="card-title mb-1">' + escapeHtml(job.title) + '</h5>'
      + '<p class="text-secondary small mb-2">' + escapeHtml(job.company_name || 'Company') + ' | ' + escapeHtml(job.location || 'Remote') + '</p>'
      + '<p class="chips mb-2"><span class="badge text-bg-primary">' + escapeHtml(job.job_type || 'full_time') + '</span><span class="badge text-bg-light border">' + escapeHtml(formatMoney(job.salary_min, job.salary_max, job.salary_currency)) + '</span></p>'
      + '<p class="card-text flex-grow-1">' + escapeHtml((job.description || '').slice(0, 160)) + ((job.description || '').length > 160 ? '...' : '') + '</p>'
      + '<div class="card-actions mt-2">' + apply + '<span class="text-secondary small">Posted ' + escapeHtml(formatDate(job.created_at)) + '</span></div>'
      + '</div>'
      + '</article>';
  }

  window.JobPortalCommon = {
    getUser: getUser,
    setUser: setUser,
    clearUser: clearUser,
    setCookie: setCookie,
    getCookie: getCookie,
    deleteCookie: deleteCookie,
    isLoggedIn: isLoggedIn,
    ensureAuth: ensureAuth,
    parseQuery: parseQuery,
    showAlert: showAlert,
    clearAlert: clearAlert,
    setButtonLoading: setButtonLoading,
    setFormLoading: setFormLoading,
    formatMoney: formatMoney,
    formatDate: formatDate,
    escapeHtml: escapeHtml,
    renderNav: renderNav,
    jobCard: jobCard
  };
})();
