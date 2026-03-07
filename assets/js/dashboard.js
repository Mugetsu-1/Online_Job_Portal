(function () {
  function loadEmployerJobs() {
    JobPortalAPI.getJobs({ page: 1, limit: 50 }).then(function (res) {
      var user = JobPortalCommon.getUser();
      var owned = (res.data || []).filter(function (job) {
        return (job.company_name || '').toLowerCase() === (user.company_name || '').toLowerCase();
      });
      var host = document.getElementById('jobsPanel');
      if (!owned.length) {
        host.innerHTML = '<p class="muted">No jobs posted yet.</p>';
        return;
      }
      host.innerHTML = owned.map(function (job) {
        return '<div class="row-card"><div><strong>' + JobPortalCommon.escapeHtml(job.title) + '</strong><p class="text-secondary mb-0">' + JobPortalCommon.escapeHtml(job.location || '-') + '</p></div>'
          + '<div class="row-actions"><button class="btn btn-outline-danger btn-sm" data-delete-job="' + job.id + '">Delete</button></div></div>';
      }).join('');

      host.querySelectorAll('[data-delete-job]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (!confirm('Delete this job permanently?')) return;
          var id = btn.getAttribute('data-delete-job');
          var row = btn.closest('.row-card');
          if (row) row.remove();
          JobPortalAPI.deleteJob(id)
            .then(function () {
              loadEmployerJobs();
            })
            .catch(function (err) {
              JobPortalCommon.showAlert('#dashboardAlert', err.message || 'Delete failed', 'danger');
              loadEmployerJobs();
            });
        });
      });
    }).catch(function (err) {
      JobPortalCommon.showAlert('#dashboardAlert', (err.message || 'Unable to load jobs') + ' ', 'danger');
      var alertBox = document.querySelector('#dashboardAlert .alert');
      if (alertBox) {
        alertBox.innerHTML += '<button type="button" id="retryJobsPanelLoad" class="btn btn-sm btn-light ms-2">Retry</button>';
        var retryBtn = document.getElementById('retryJobsPanelLoad');
        if (retryBtn) retryBtn.addEventListener('click', loadEmployerJobs);
      }
    });
  }

  function loadApplications() {
    JobPortalAPI.getMyApplications({ page: 1, limit: 50 })
      .then(function (res) {
        var user = JobPortalCommon.getUser();
        var host = document.getElementById('applicationsPanel');
        var apps = res.data || [];
        if (!apps.length) {
          host.innerHTML = '<p class="muted">No applications found.</p>';
          return;
        }

        host.innerHTML = apps.map(function (app) {
          var action = '';
          if (user.role === 'job_seeker') {
            action = '<button class="btn btn-outline-secondary btn-sm" data-withdraw="' + app.id + '">Withdraw</button>';
          } else {
            action = '<select class="form-select form-select-sm" data-status="' + app.id + '"><option value="pending">pending</option><option value="reviewed">reviewed</option><option value="shortlisted">shortlisted</option><option value="rejected">rejected</option><option value="accepted">accepted</option></select>';
          }
          return '<div class="row-card"><div><strong>' + JobPortalCommon.escapeHtml(app.job_title || '') + '</strong><p class="text-secondary mb-0">Status: ' + JobPortalCommon.escapeHtml(app.status) + ' | Applied: ' + JobPortalCommon.escapeHtml(JobPortalCommon.formatDate(app.applied_at)) + '</p></div><div class="row-actions">' + action + '</div></div>';
        }).join('');

        host.querySelectorAll('[data-withdraw]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            if (!confirm('Withdraw this application?')) return;
            var row = btn.closest('.row-card');
            if (row) row.remove();
            JobPortalAPI.withdrawApplication(btn.getAttribute('data-withdraw'))
              .then(function () {
                loadApplications();
              })
              .catch(function (err) {
                JobPortalCommon.showAlert('#dashboardAlert', err.message || 'Withdraw failed', 'danger');
                loadApplications();
              });
          });
        });

        host.querySelectorAll('[data-status]').forEach(function (select) {
          var current = apps.find(function (a) { return String(a.id) === select.getAttribute('data-status'); });
          if (current) select.value = current.status;
          select.addEventListener('change', function () {
            if (!confirm('Change application status to "' + select.value + '"?')) {
              if (current) select.value = current.status;
              return;
            }
            JobPortalAPI.updateApplicationStatus({
              application_id: Number(select.getAttribute('data-status')),
              status: select.value
            }).then(function () {
              loadApplications();
            }).catch(function (err) {
              JobPortalCommon.showAlert('#dashboardAlert', err.message || 'Update failed', 'danger');
            });
          });
        });
      })
      .catch(function (err) {
        JobPortalCommon.showAlert('#dashboardAlert', (err.message || 'Unable to load applications') + ' ', 'danger');
        var alertBox = document.querySelector('#dashboardAlert .alert');
        if (alertBox) {
          alertBox.innerHTML += '<button type="button" id="retryAppsLoad" class="btn btn-sm btn-light ms-2">Retry</button>';
          var retryBtn = document.getElementById('retryAppsLoad');
          if (retryBtn) retryBtn.addEventListener('click', loadApplications);
        }
      });
  }

  function initCreateJob() {
    var form = document.getElementById('createJobForm');
    if (!form) return;
    var submitting = false;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (submitting) return;
      JobPortalCommon.clearAlert('#dashboardAlert');
      var payload = {
        title: form.title.value.trim(),
        description: form.description.value.trim(),
        requirements: form.requirements.value.trim(),
        responsibilities: form.responsibilities.value.trim(),
        job_type: form.job_type.value,
        location: form.location.value.trim(),
        salary_min: form.salary_min.value || null,
        salary_max: form.salary_max.value || null,
        salary_currency: form.salary_currency.value.trim() || 'USD',
        experience_required: form.experience_required.value || null,
        education_required: form.education_required.value.trim(),
        skills_required: form.skills_required.value.trim(),
        application_deadline: form.application_deadline.value || null,
        positions_available: form.positions_available.value || 1
      };
      submitting = true;
      JobPortalCommon.setFormLoading(form, true, 'Creating...');
      JobPortalAPI.createJob(payload)
        .then(function () {
          form.reset();
          JobPortalCommon.showAlert('#dashboardAlert', 'Job created successfully.', 'success');
          loadEmployerJobs();
        })
        .catch(function (err) {
          JobPortalCommon.showAlert('#dashboardAlert', err.message || 'Create job failed', 'danger');
        })
        .finally(function () {
          submitting = false;
          JobPortalCommon.setFormLoading(form, false);
        });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    JobPortalCommon.renderNav();
    var user = JobPortalCommon.ensureAuth(['job_seeker', 'employer', 'admin']);
    if (!user) return;

    document.getElementById('welcomeText').textContent = user.full_name + ' (' + user.role + ')';
    var createSection = document.getElementById('createJobSection');
    if (user.role === 'employer') {
      createSection.style.display = 'block';
      loadEmployerJobs();
    } else {
      createSection.style.display = 'none';
      document.getElementById('jobsSection').style.display = 'none';
    }

    if (user.role !== 'admin') {
      loadApplications();
    } else {
      document.getElementById('applicationsPanel').innerHTML = '<p class="muted">Admin tools are not exposed in this UI.</p>';
    }

    initCreateJob();
  });
})();
