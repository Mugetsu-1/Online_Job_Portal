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
    jobsEl.innerHTML = '<p class="muted">Loading jobs...</p>';
    JobPortalAPI.getJobs(query)
      .then(function (res) {
        renderJobs(jobsEl, res.data || []);
        if (paginationEl && res.pagination) {
          paginationEl.textContent = 'Page ' + res.pagination.current_page + ' of ' + (res.pagination.total_pages || 1);
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
      holder.innerHTML = '<p class="muted">Missing job id.</p>';
      return;
    }

    JobPortalAPI.getJobDetails(query.id)
      .then(function (res) {
        var job = res.data;
        var user = JobPortalCommon.getUser();
        var action = '<a class="btn btn-primary" href="apply.html?job_id=' + job.id + '">Apply Now</a>';
        if (!user || user.role !== 'job_seeker') {
          action = '<a class="btn btn-primary" href="login.html">Login as Job Seeker to Apply</a>';
        }
        holder.innerHTML = '<article class="details-card">'
          + '<h1>' + JobPortalCommon.escapeHtml(job.title) + '</h1>'
          + '<p class="muted">' + JobPortalCommon.escapeHtml(job.company_name || 'Company') + ' | ' + JobPortalCommon.escapeHtml(job.location || 'Remote') + '</p>'
          + '<p class="chips"><span class="badge text-bg-primary">' + JobPortalCommon.escapeHtml(job.job_type) + '</span><span class="badge text-bg-light border">' + JobPortalCommon.escapeHtml(JobPortalCommon.formatMoney(job.salary_min, job.salary_max, job.salary_currency)) + '</span><span class="badge text-bg-light border">Experience: ' + JobPortalCommon.escapeHtml(job.experience_required || 0) + ' years</span></p>'
          + '<h3>Description</h3><p>' + JobPortalCommon.escapeHtml(job.description || '') + '</p>'
          + '<h3>Requirements</h3><p>' + JobPortalCommon.escapeHtml(job.requirements || 'Not specified') + '</p>'
          + '<h3>Responsibilities</h3><p>' + JobPortalCommon.escapeHtml(job.responsibilities || 'Not specified') + '</p>'
          + '<h3>Skills</h3><p>' + JobPortalCommon.escapeHtml((job.skills_required || []).join(', ') || 'Not specified') + '</p>'
          + '<p class="muted">Deadline: ' + JobPortalCommon.escapeHtml(JobPortalCommon.formatDate(job.application_deadline)) + '</p>'
          + '<div class="card-actions">' + action + '</div>'
          + '</article>';
      })
      .catch(function (err) {
        holder.innerHTML = '';
        JobPortalCommon.showAlert('#detailsAlert', err.message || 'Unable to load job details', 'danger');
      });
  }

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
          document.getElementById('jobSummary').innerHTML = '<strong>' + JobPortalCommon.escapeHtml(res.data.title) + '</strong> at ' + JobPortalCommon.escapeHtml(res.data.company_name || 'Company');
        })
        .catch(function () {
          document.getElementById('jobSummary').textContent = 'Unable to load job summary';
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
