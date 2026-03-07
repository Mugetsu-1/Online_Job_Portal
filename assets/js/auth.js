(function () {
  function isNumericPassword(password) {
    return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,32}$/.test(password || '');
  }

  function isValidPhone(phone) {
    if (!phone) return true;
    return /^(?:9841|9746)\d{6}$/.test(phone);
  }

  function initLogin() {
    var form = document.getElementById('loginForm');
    if (!form) return;
    var submitting = false;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (submitting) return;
      JobPortalCommon.clearAlert('#authAlert');
      var payload = {
        email: document.getElementById('email').value.trim(),
        password: document.getElementById('password').value
      };
      submitting = true;
      JobPortalCommon.setFormLoading(form, true, 'Logging in...');
      JobPortalAPI.login(payload)
        .then(function (res) {
          var user = res.user || (res.data && res.data.user);
          if (!user) {
            throw new Error('Invalid login response');
          }
          JobPortalCommon.setUser(user);
          location.href = 'dashboard.html';
        })
        .catch(function (err) {
          JobPortalCommon.showAlert('#authAlert', err.message || 'Login failed', 'danger');
        })
        .finally(function () {
          submitting = false;
          JobPortalCommon.setFormLoading(form, false);
        });
    });
  }

  function roleFields(role) {
    var seeker = document.getElementById('jobSeekerFields');
    var employer = document.getElementById('employerFields');
    if (seeker) seeker.style.display = role === 'job_seeker' ? 'block' : 'none';
    if (employer) employer.style.display = role === 'employer' ? 'block' : 'none';
  }

  function initRegister() {
    var form = document.getElementById('registerForm');
    if (!form) return;
    var submitting = false;

    var roleSelect = document.getElementById('role');
    roleFields(roleSelect.value);
    roleSelect.addEventListener('change', function () {
      roleFields(roleSelect.value);
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (submitting) return;
      JobPortalCommon.clearAlert('#authAlert');
      var password = document.getElementById('password').value;
      if (!isNumericPassword(password)) {
        JobPortalCommon.showAlert('#authAlert', 'Password must be 8-32 chars with uppercase, lowercase, number, and special symbol.', 'danger');
        return;
      }
      var phone = document.getElementById('phone').value.trim();
      if (!isValidPhone(phone)) {
        JobPortalCommon.showAlert('#authAlert', 'Phone must be 10 digits and start with 9841 or 9746.', 'danger');
        return;
      }
      var role = roleSelect.value;
      var payload = {
        email: document.getElementById('email').value.trim(),
        password: password,
        full_name: document.getElementById('full_name').value.trim(),
        phone: phone,
        role: role
      };

      if (role === 'job_seeker') {
        payload.skills = document.getElementById('skills').value.trim();
        payload.experience_years = document.getElementById('experience_years').value || null;
        payload.education = document.getElementById('education').value.trim();
      } else {
        payload.company_name = document.getElementById('company_name').value.trim();
        payload.company_website = document.getElementById('company_website').value.trim();
      }

      submitting = true;
      JobPortalCommon.setFormLoading(form, true, 'Registering...');
      JobPortalAPI.register(payload)
        .then(function () {
          JobPortalCommon.showAlert('#authAlert', 'Registration successful. You can login now.', 'success');
          form.reset();
          roleSelect.value = role;
          roleFields(role);
        })
        .catch(function (err) {
          JobPortalCommon.showAlert('#authAlert', err.message || 'Registration failed', 'danger');
        })
        .finally(function () {
          submitting = false;
          JobPortalCommon.setFormLoading(form, false);
        });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    JobPortalCommon.renderNav();
    initLogin();
    initRegister();
  });
})();
