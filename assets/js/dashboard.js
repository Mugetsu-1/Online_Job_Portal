(function () {
  function renderAlertRetry(message, buttonId, buttonLabel, onRetry) {
    JobPortalCommon.showAlert('#dashboardAlert', message + ' ', 'danger');
    var alertBox = document.querySelector('#dashboardAlert .alert');
    if (!alertBox) return;
    alertBox.innerHTML += '<button type="button" id="' + buttonId + '" class="btn btn-sm btn-light ms-2">' + buttonLabel + '</button>';
    var retryBtn = document.getElementById(buttonId);
    if (retryBtn) retryBtn.addEventListener('click', onRetry);
  }

  function statusPill(isActive) {
    var label = isActive ? 'Active' : 'Inactive';
    var css = isActive ? 'active' : 'inactive';
    return '<span class="status-pill ' + css + '">' + label + '</span>';
  }

  function rowMeta(parts) {
    return '<p class="row-card-meta">' + parts.filter(Boolean).map(JobPortalCommon.escapeHtml).join(' | ') + '</p>';
  }

  function adminStatCard(label, value) {
    return '<div class="summary-card"><div class="summary-label">' + JobPortalCommon.escapeHtml(label) + '</div><div class="summary-value">' + JobPortalCommon.escapeHtml(value) + '</div></div>';
  }

  function loadEmployerJobs() {
    JobPortalAPI.getJobs({ page: 1, limit: 50 }).then(function (res) {
      var user = JobPortalCommon.getUser();
      var owned = (res.data || []).filter(function (job) {
        return Number(job.employer_id) === Number(user.id);
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
      renderAlertRetry(err.message || 'Unable to load jobs', 'retryJobsPanelLoad', 'Retry', loadEmployerJobs);
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
        renderAlertRetry(err.message || 'Unable to load applications', 'retryAppsLoad', 'Retry', loadApplications);
      });
  }

  function loadAdminStats() {
    JobPortalAPI.getAdminStats()
      .then(function (res) {
        var stats = res.data || {};
        var host = document.getElementById('adminStatsPanel');
        host.innerHTML =
          adminStatCard('Users', stats.total_users || 0) +
          adminStatCard('Job Seekers', stats.total_job_seekers || 0) +
          adminStatCard('Employers', stats.total_employers || 0) +
          adminStatCard('Jobs', stats.total_jobs || 0) +
          adminStatCard('Active Jobs', stats.active_jobs || 0) +
          adminStatCard('Applications', stats.total_applications || 0) +
          adminStatCard('Pending', stats.pending_applications || 0) +
          adminStatCard('Active Users', stats.active_users || 0);
      })
      .catch(function (err) {
        renderAlertRetry(err.message || 'Unable to load admin stats', 'retryAdminStats', 'Retry', loadAdminStats);
      });
  }

  function loadAdminJobs() {
    JobPortalAPI.getAdminJobs({ page: 1, limit: 50 })
      .then(function (res) {
        var jobs = res.data || [];
        var host = document.getElementById('adminJobsPanel');
        if (!jobs.length) {
          host.innerHTML = '<p class="muted">No jobs found.</p>';
          return;
        }

        host.innerHTML = jobs.map(function (job) {
          var employerName = job.company_name || job.employer_name || 'Employer';
          var meta = [
            employerName,
            job.location || 'Location not set',
            job.job_type || 'job'
          ];
          if (job.application_deadline) {
            meta.push('Deadline: ' + JobPortalCommon.formatDate(job.application_deadline));
          }
          meta.push('Applications: ' + String(job.applications_count || 0));

          var toggleLabel = job.is_active ? 'Deactivate' : 'Activate';
          var toggleClass = job.is_active ? 'btn-outline-warning' : 'btn-outline-success';

          return '<div class="row-card">'
            + '<div>'
            + '<div class="row-card-title"><strong>' + JobPortalCommon.escapeHtml(job.title) + '</strong>' + statusPill(job.is_active) + '</div>'
            + rowMeta(meta)
            + '</div>'
            + '<div class="row-actions">'
            + '<button class="btn ' + toggleClass + ' btn-sm" data-toggle-job="' + job.id + '" data-next-active="' + (job.is_active ? '0' : '1') + '">' + toggleLabel + '</button>'
            + '<button class="btn btn-outline-danger btn-sm" data-admin-delete-job="' + job.id + '">Delete</button>'
            + '</div>'
            + '</div>';
        }).join('');

        host.querySelectorAll('[data-toggle-job]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var nextActive = btn.getAttribute('data-next-active') === '1';
            var actionLabel = nextActive ? 'activate' : 'deactivate';
            if (!confirm('Do you want to ' + actionLabel + ' this job?')) return;
            JobPortalAPI.updateAdminJobStatus({
              job_id: Number(btn.getAttribute('data-toggle-job')),
              is_active: nextActive
            }).then(function () {
              loadAdminStats();
              loadAdminJobs();
            }).catch(function (err) {
              JobPortalCommon.showAlert('#dashboardAlert', err.message || 'Unable to update job status', 'danger');
            });
          });
        });

        host.querySelectorAll('[data-admin-delete-job]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            if (!confirm('Delete this job permanently?')) return;
            JobPortalAPI.deleteJob(btn.getAttribute('data-admin-delete-job'))
              .then(function () {
                loadAdminStats();
                loadAdminJobs();
              })
              .catch(function (err) {
                JobPortalCommon.showAlert('#dashboardAlert', err.message || 'Delete failed', 'danger');
              });
          });
        });
      })
      .catch(function (err) {
        renderAlertRetry(err.message || 'Unable to load admin jobs', 'retryAdminJobs', 'Retry', loadAdminJobs);
      });
  }

  function loadAdminUsers() {
    JobPortalAPI.getAdminUsers({ page: 1, limit: 50 })
      .then(function (res) {
        var users = res.data || [];
        var currentUser = JobPortalCommon.getUser();
        var host = document.getElementById('adminUsersPanel');
        if (!users.length) {
          host.innerHTML = '<p class="muted">No users found.</p>';
          return;
        }

        host.innerHTML = users.map(function (user) {
          var roleLabel = String(user.role || '').replace('_', ' ');
          var details = [
            user.email || '',
            roleLabel,
            'Joined: ' + JobPortalCommon.formatDate(user.created_at)
          ];

          if (user.role === 'employer') {
            details.push('Jobs: ' + String(user.jobs_posted || 0));
          } else if (user.role === 'job_seeker') {
            details.push('Applications: ' + String(user.applications_submitted || 0));
          }

          var action = '<span class="text-secondary small">No action</span>';
          if (Number(user.id) === Number(currentUser.id)) {
            action = '<span class="text-secondary small">Current admin</span>';
          } else if (user.role !== 'admin') {
            var nextActive = !user.is_active;
            var buttonLabel = user.is_active ? 'Deactivate' : 'Activate';
            var buttonClass = user.is_active ? 'btn-outline-warning' : 'btn-outline-success';
            action = '<button class="btn ' + buttonClass + ' btn-sm" data-toggle-user="' + user.id + '" data-next-active="' + (nextActive ? '1' : '0') + '">' + buttonLabel + '</button>';
          }

          return '<div class="row-card">'
            + '<div>'
            + '<div class="row-card-title"><strong>' + JobPortalCommon.escapeHtml(user.full_name || 'User') + '</strong>' + statusPill(user.is_active) + '</div>'
            + rowMeta(details)
            + '</div>'
            + '<div class="row-actions">' + action + '</div>'
            + '</div>';
        }).join('');

        host.querySelectorAll('[data-toggle-user]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var nextActive = btn.getAttribute('data-next-active') === '1';
            var actionLabel = nextActive ? 'activate' : 'deactivate';
            if (!confirm('Do you want to ' + actionLabel + ' this user account?')) return;
            JobPortalAPI.updateAdminUserStatus({
              user_id: Number(btn.getAttribute('data-toggle-user')),
              is_active: nextActive
            }).then(function () {
              loadAdminStats();
              loadAdminUsers();
            }).catch(function (err) {
              JobPortalCommon.showAlert('#dashboardAlert', err.message || 'Unable to update user status', 'danger');
            });
          });
        });
      })
      .catch(function (err) {
        renderAlertRetry(err.message || 'Unable to load admin users', 'retryAdminUsers', 'Retry', loadAdminUsers);
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
    var jobsSection = document.getElementById('jobsSection');
    var applicationsSection = document.getElementById('applicationsSection');
    var adminSummarySection = document.getElementById('adminSummarySection');
    var adminJobsSection = document.getElementById('adminJobsSection');
    var adminUsersSection = document.getElementById('adminUsersSection');

    if (user.role === 'employer') {
      createSection.style.display = 'block';
      jobsSection.style.display = 'block';
      applicationsSection.style.display = 'block';
      loadEmployerJobs();
      loadApplications();
    } else if (user.role === 'job_seeker') {
      createSection.style.display = 'none';
      jobsSection.style.display = 'none';
      applicationsSection.style.display = 'block';
      loadApplications();
    } else {
      createSection.style.display = 'none';
      jobsSection.style.display = 'none';
      applicationsSection.style.display = 'none';
    }

    if (user.role === 'admin') {
      adminSummarySection.style.display = 'block';
      adminJobsSection.style.display = 'block';
      adminUsersSection.style.display = 'block';
      loadAdminStats();
      loadAdminJobs();
      loadAdminUsers();
    }

    initCreateJob();
  });
})();
