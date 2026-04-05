(function () {
  function getPage() {
    return document.body.getAttribute('data-page') || '';
  }

  function readFilters(form) {
    return {
      search: form.search.value.trim(),
      job_type: form.job_type.value,
      location: form.location.value.trim(),
      min_salary: form.min_salary.value,
      max_salary: form.max_salary.value,
      experience: form.experience.value
    };
  }

  function renderJobs(container, jobs) {
    if (!jobs.length) {
      container.innerHTML = '<p class="muted">No jobs found.</p>';
      return;
    }
    container.innerHTML = jobs.map(function (job) {
      return JobPortalCommon.jobCard(job, true);
    }).join('');
  }

  function loadJobs(query, jobsSelector, paginationSelector, onRetry) {
    var jobsEl = document.querySelector(jobsSelector);
    var paginationEl = document.querySelector(paginationSelector);
    var paginationBar = document.getElementById('paginationBar');
    var resultsCount = document.getElementById('resultsCount');
    
    // Use portal-style skeleton if available
    if (typeof skeletonCards === 'function') {
      jobsEl.innerHTML = skeletonCards(6);
    } else {
      jobsEl.innerHTML = '<p class="muted">Loading jobs...</p>';
    }
    
    JobPortalAPI.getJobs(query)
      .then(function (res) {
        var jobs = res.data || [];
        // Use portal-style jobCard if available, otherwise fallback
        if (jobs.length && typeof jobCard === 'function') {
          jobsEl.innerHTML = jobs.map(jobCard).join('');
          if (typeof initReveal === 'function') initReveal();
        } else if (jobs.length) {
          renderJobs(jobsEl, jobs);
        } else {
          jobsEl.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><p>No jobs found matching your criteria.</p></div>';
        }
        
        // Update results count
        if (resultsCount) {
          var total = res.pagination ? res.pagination.total : jobs.length;
          resultsCount.textContent = total + ' position' + (total !== 1 ? 's' : '') + ' found';
        }
        
        // Handle pagination
        if (paginationEl && res.pagination) {
          paginationEl.textContent = 'Page ' + res.pagination.current_page + ' of ' + (res.pagination.total_pages || 1);
          if (paginationBar && res.pagination.total_pages > 1) {
            paginationBar.style.display = '';
          }
        }
      })
      .catch(function (err) {
        jobsEl.innerHTML = '';
        JobPortalCommon.showAlert('#jobsAlert', (err.message || 'Unable to load jobs') + ' ', 'danger');
        var alertBox = document.querySelector('#jobsAlert .alert');
        if (alertBox && onRetry) {
          alertBox.innerHTML += '<button type="button" id="retryJobsLoad" class="btn btn-sm btn-light ms-2">Retry</button>';
          var retryBtn = document.getElementById('retryJobsLoad');
          if (retryBtn) retryBtn.addEventListener('click', onRetry);
        }
      });
  }

  function initHome() {
    var list = document.getElementById('featuredJobs');
    if (!list) return;
    JobPortalAPI.getJobs({ page: 1, limit: 6 })
      .then(function (res) {
        renderJobs(list, res.data || []);
      })
      .catch(function () {
        list.innerHTML = '<p class="muted">Unable to load jobs right now.</p>';
      });
  }

  function initJobsList() {
    var form = document.getElementById('filterForm');
    if (!form) return;
    var page = 1;

    function refresh() {
      var query = readFilters(form);
      query.page = page;
      query.limit = 10;
      loadJobs(query, '#jobsList', '#jobsPagination', refresh);
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      page = 1;
      refresh();
    });

    document.getElementById('clearFilters').addEventListener('click', function () {
      form.reset();
      page = 1;
      refresh();
    });

    document.getElementById('prevPage').addEventListener('click', function () {
      if (page > 1) {
        page -= 1;
        refresh();
      }
    });

    document.getElementById('nextPage').addEventListener('click', function () {
      page += 1;
      refresh();
    });

    refresh();
  }

  function initJobDetails() {
    var holder = document.getElementById('jobDetails');
    if (!holder) return;
    var query = JobPortalCommon.parseQuery();
    if (!query.id) {
      holder.innerHTML = '<p class="text-muted">Missing job id.</p>';
      return;
    }

    JobPortalAPI.getJobDetails(query.id)
      .then(function (res) {
        var job = res.data;
        var user = JobPortalCommon.getUser();
        var action = '';
        
        if (!user) {
          action = '<a class="btn btn-primary btn-lg btn-block" href="login.html">Login to Apply</a>';
        } else if (user.role === 'job_seeker') {
          action = '<a class="btn btn-primary btn-lg btn-block" href="apply.html?job_id=' + job.id + '">Apply Now →</a>';
        } else if (user.role === 'employer') {
          // Check if this is the employer's own job
          if (Number(job.employer_id) === Number(user.id)) {
            action = '<div class="flex flex-col gap-2">'
              + '<a class="btn btn-accent btn-block" href="dashboard.html">Manage Applications</a>'
              + '<button class="btn btn-ghost btn-block" onclick="alert(\'Edit job feature coming soon\')">Edit Job</button>'
              + '</div>';
          } else {
            action = '<div style="padding:14px;background:var(--bg);border-radius:8px;text-align:center;font-size:13px;color:var(--ink-2);">👀 View only<br><small>Employers cannot apply</small></div>';
          }
        } else if (user.role === 'admin') {
          action = '<div class="flex flex-col gap-2">'
            + '<button class="btn btn-warn btn-block" onclick="toggleJobStatus(' + job.id + ', ' + !job.is_active + ')">' 
            + (job.is_active ? 'Deactivate Job' : 'Activate Job') 
            + '</button>'
            + '<button class="btn btn-ghost btn-block" style="color:var(--error)" onclick="deleteJob(' + job.id + ')">Delete Job</button>'
            + '</div>';
        }
        
        // Format salary using portal style
        var salaryText = JobPortalCommon.formatMoney(job.salary_min, job.salary_max, job.salary_currency);
        
        holder.innerHTML = '<div class="details-layout">'
          + '<div>'
          + '<h1 class="job-detail-title">' + JobPortalCommon.escapeHtml(job.title) + '</h1>'
          + '<p class="job-detail-company">' + JobPortalCommon.escapeHtml(job.company_name || 'Company') + '</p>'
          + '<div class="flex gap-2 flex-wrap mb-4">'
          + (job.job_type ? '<span class="badge">' + JobPortalCommon.escapeHtml(job.job_type.replace('_', ' ')) + '</span>' : '')
          + (job.location ? '<span class="badge badge-gray">' + JobPortalCommon.escapeHtml(job.location) + '</span>' : '')
          + (salaryText && salaryText !== 'Not specified' ? '<span class="badge badge-green">' + JobPortalCommon.escapeHtml(salaryText) + '</span>' : '')
          + (job.is_active ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-gray">Closed</span>')
          + '</div>'
          + '<div class="detail-section"><h3>Description</h3><p>' + JobPortalCommon.escapeHtml(job.description || 'Not specified') + '</p></div>'
          + '<div class="detail-section"><h3>Requirements</h3><p>' + JobPortalCommon.escapeHtml(job.requirements || 'Not specified') + '</p></div>'
          + '<div class="detail-section"><h3>Responsibilities</h3><p>' + JobPortalCommon.escapeHtml(job.responsibilities || 'Not specified') + '</p></div>'
          + '<div class="detail-section"><h3>Skills Required</h3><p>' + JobPortalCommon.escapeHtml((Array.isArray(job.skills_required) ? job.skills_required.join(', ') : job.skills_required) || 'Not specified') + '</p></div>'
          + '</div>'
          + '<div class="sticky-sidebar">'
          + '<div class="card">'
          + '<div class="card-header"><h3>Job Details</h3></div>'
          + '<div class="card-body">'
          + '<div class="meta-list">'
          + '<div class="meta-item"><div class="meta-icon">💼</div><div><div class="meta-label">Type</div><div class="meta-val">' + JobPortalCommon.escapeHtml((job.job_type || 'full_time').replace('_', ' ')) + '</div></div></div>'
          + '<div class="meta-item"><div class="meta-icon">📍</div><div><div class="meta-label">Location</div><div class="meta-val">' + JobPortalCommon.escapeHtml(job.location || 'Remote') + '</div></div></div>'
          + '<div class="meta-item"><div class="meta-icon">💰</div><div><div class="meta-label">Salary</div><div class="meta-val">' + JobPortalCommon.escapeHtml(salaryText) + '</div></div></div>'
          + '<div class="meta-item"><div class="meta-icon">⏰</div><div><div class="meta-label">Experience</div><div class="meta-val">' + JobPortalCommon.escapeHtml(job.experience_required || '0') + ' years</div></div></div>'
          + '<div class="meta-item"><div class="meta-icon">📅</div><div><div class="meta-label">Deadline</div><div class="meta-val">' + JobPortalCommon.escapeHtml(JobPortalCommon.formatDate(job.application_deadline) || 'Open') + '</div></div></div>'
          + '</div>'
          + (action ? '<div class="divider"></div>' + action : '')
          + '</div>'
          + '</div>'
          + '</div>'
          + '</div>';
      })
      .catch(function (err) {
        holder.innerHTML = '';
        JobPortalCommon.showAlert('#detailsAlert', err.message || 'Unable to load job details', 'danger');
      });
  }

  // Admin functions for job details page
  window.toggleJobStatus = function(jobId, isActive) {
    if (!confirm('Are you sure you want to ' + (isActive ? 'activate' : 'deactivate') + ' this job?')) return;
    JobPortalAPI.updateAdminJobStatus({ job_id: jobId, is_active: isActive })
      .then(function() {
        location.reload();
      })
      .catch(function(err) {
        alert(err.message || 'Failed to update job status');
      });
  };

  window.deleteJob = function(jobId) {
    if (!confirm('Are you sure you want to permanently delete this job? This cannot be undone.')) return;
    JobPortalAPI.deleteJob(jobId)
      .then(function() {
        alert('Job deleted successfully');
        location.href = 'dashboard.html';
      })
      .catch(function(err) {
        alert(err.message || 'Failed to delete job');
      });
  };

  function initApply() {
    var user = JobPortalCommon.ensureAuth(['job_seeker']);
    var form = document.getElementById('applyForm');
    if (!form || !user) return;
    var submitting = false;

    var query = JobPortalCommon.parseQuery();
    var jobId = query.job_id || '';
    document.getElementById('job_id').value = jobId;

    if (jobId) {
      JobPortalAPI.getJobDetails(jobId)
        .then(function (res) {
          var panel = document.getElementById('jobSummaryPanel');
          if (panel) panel.innerHTML = '<strong>' + JobPortalCommon.escapeHtml(res.data.title) + '</strong> at ' + JobPortalCommon.escapeHtml(res.data.company_name || 'Company');
        })
        .catch(function () {
          var panel = document.getElementById('jobSummaryPanel');
          if (panel) panel.textContent = 'Unable to load job summary';
        });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (submitting) return;
      JobPortalCommon.clearAlert('#applyAlert');
      var payload = new FormData(form);
      submitting = true;
      JobPortalCommon.setFormLoading(form, true, 'Submitting...');
      JobPortalAPI.applyToJob(payload)
        .then(function () {
          JobPortalCommon.showAlert('#applyAlert', 'Application submitted successfully.', 'success');
          form.reset();
          document.getElementById('job_id').value = jobId;
        })
        .catch(function (err) {
          JobPortalCommon.showAlert('#applyAlert', err.message || 'Application failed', 'danger');
        })
        .finally(function () {
          submitting = false;
          JobPortalCommon.setFormLoading(form, false);
        });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    JobPortalCommon.renderNav();
    var page = getPage();
    if (page === 'home') initHome();
    if (page === 'jobs') initJobsList();
    if (page === 'job-details') initJobDetails();
    if (page === 'apply') initApply();
  });
})();
