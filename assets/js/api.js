(function () {
  var configuredBase = window.API_BASE_URL || localStorage.getItem('jp_api_base');
  var inferredBase = '';
  if (location.protocol.indexOf('http') === 0) {
    var path = location.pathname;
    if (path.indexOf('/frontend/') !== -1) {
      inferredBase = location.origin + path.split('/frontend/')[0] + '/backend';
    } else if (path.indexOf('/backend/') !== -1) {
      inferredBase = location.origin + path.split('/backend/')[0] + '/backend';
    } else {
      inferredBase = location.origin + '/backend';
    }
  } else {
    inferredBase = 'http://localhost:8000';
  }

  var baseUrl = (configuredBase || inferredBase).replace(/\/+$/, '');

  function withQuery(path, query) {
    if (!query) return path;
    var params = new URLSearchParams();
    Object.keys(query).forEach(function (key) {
      var value = query[key];
      if (value !== undefined && value !== null && value !== '') params.append(key, value);
    });
    var qs = params.toString();
    if (!qs) return path;
    return path + (path.indexOf('?') === -1 ? '?' : '&') + qs;
  }

  function parseResponse(res) {
    return res.text().then(function (text) {
      var data = {};
      try {
        data = text ? JSON.parse(text) : {};
      } catch (e) {
        data = { success: false, message: 'Invalid server response' };
      }
      if (!res.ok || data.success === false) {
        var error = new Error(data.message || 'Request failed');
        error.status = res.status;
        error.data = data;
        throw error;
      }
      return data;
    });
  }

  function getCookie(name) {
    var escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + escaped + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
  }

  function request(path, options) {
    var opts = options || {};
    var url = baseUrl + '/' + path.replace(/^\/+/, '');
    if (opts.query) url = withQuery(url, opts.query);

    var fetchOpts = {
      method: opts.method || 'GET',
      credentials: 'include',
      headers: {}
    };
    var method = (fetchOpts.method || 'GET').toUpperCase();
    if (method === 'POST' || method === 'PUT' || method === 'DELETE') {
      var csrf = getCookie('jp_csrf');
      if (csrf) fetchOpts.headers['X-CSRF-Token'] = csrf;
    }

    if (opts.body !== undefined && opts.body !== null) {
      if (opts.body instanceof FormData) {
        fetchOpts.body = opts.body;
      } else {
        fetchOpts.headers['Content-Type'] = 'application/json';
        fetchOpts.body = JSON.stringify(opts.body);
      }
    }

    return fetch(url, fetchOpts).then(parseResponse);
  }

  var api = {
    getBaseUrl: function () {
      return baseUrl;
    },
    setBaseUrl: function (next) {
      baseUrl = (next || '').replace(/\/+$/, '');
      localStorage.setItem('jp_api_base', baseUrl);
    },
    login: function (payload) {
      return request('auth/login.php', { method: 'POST', body: payload });
    },
    register: function (payload) {
      return request('auth/register.php', { method: 'POST', body: payload });
    },
    logout: function () {
      return request('auth/logout.php', { method: 'POST' });
    },
    getJobs: function (query) {
      return request('jobs/fetch_jobs.php', { query: query });
    },
    getJobDetails: function (id) {
      return request('jobs/job_details.php', { query: { id: id } });
    },
    createJob: function (payload) {
      return request('jobs/create_job.php', { method: 'POST', body: payload });
    },
    updateJob: function (payload) {
      return request('jobs/update_job.php', { method: 'PUT', body: payload });
    },
    deleteJob: function (id) {
      return request('jobs/delete_job.php', { method: 'DELETE', query: { id: id } });
    },
    applyToJob: function (payload) {
      return request('applications/apply.php', { method: 'POST', body: payload });
    },
    getMyApplications: function (query) {
      return request('applications/my_applications.php', { query: query });
    },
    updateApplicationStatus: function (payload) {
      return request('applications/update_status.php', { method: 'PUT', body: payload });
    },
    withdrawApplication: function (id) {
      return request('applications/withdraw.php', { method: 'DELETE', query: { id: id } });
    },
    getProfile: function () {
      return request('users/profile.php');
    },
    updateProfile: function (payload) {
      return request('users/update_profile.php', { method: 'POST', body: payload });
    },
    changePassword: function (payload) {
      return request('users/change_password.php', { method: 'PUT', body: payload });
    }
  };

  window.JobPortalAPI = api;
})();
