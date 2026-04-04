/* portal-common.js – shared nav, utilities */

function buildNav() {
  const user = getUser();
  const page = document.body.dataset.page || '';
  const links = [
    { href: 'index.html',   label: 'Home',      key: 'home' },
    { href: 'jobs.html',    label: 'Jobs',       key: 'jobs' },
    { href: 'dashboard.html', label: 'Dashboard', key: 'dashboard', auth: true },
    { href: 'profile.html', label: 'Profile',    key: 'profile', auth: true },
  ];

  let linksHtml = links.map(l => {
    if (l.auth && !user) return '';
    const active = page === l.key ? 'active' : '';
    return `<a href="${l.href}" class="${active}">${l.label}</a>`;
  }).join('');

  if (user) {
    linksHtml += `<button class="btn btn-ghost btn-sm" onclick="portalLogout()">Logout</button>`;
  } else {
    linksHtml += `<a href="login.html" class="btn btn-ghost btn-sm">Login</a>
                  <a href="register.html" class="btn btn-primary btn-sm">Sign up</a>`;
  }

  const nav = document.createElement('nav');
  nav.className = 'portal-nav';
  nav.innerHTML = `
    <a href="index.html" class="nav-brand">Job<span>Portal</span></a>
    <div class="nav-links">${linksHtml}</div>`;
  document.getElementById('topNav').appendChild(nav);
}

function getUser() {
  try { return JSON.parse(localStorage.getItem('jp_user')); } catch { return null; }
}
function getToken() { return localStorage.getItem('portal_token'); }

function portalLogout() {
  localStorage.removeItem('jp_user');
  localStorage.removeItem('portal_token');
  window.location.href = 'login.html';
}

function showAlert(containerId, msg, type = 'error') {
  const map = { error: 'alert-error', success: 'alert-success', info: 'alert-info', warn: 'alert-warn' };
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = `<div class="alert ${map[type] || 'alert-info'}">${msg}</div>`;
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function clearAlert(containerId) {
  const el = document.getElementById(containerId);
  if (el) el.innerHTML = '';
}

function formatSalary(min, max, currency = 'USD') {
  const fmt = n => n >= 1000 ? (n/1000).toFixed(0) + 'k' : n;
  if (min && max) return `${currency} ${fmt(min)}–${fmt(max)}`;
  if (min) return `${currency} ${fmt(min)}+`;
  if (max) return `Up to ${currency} ${fmt(max)}`;
  return '';
}

function formatJobType(t) {
  return ({ full_time:'Full Time', part_time:'Part Time', contract:'Contract', internship:'Internship', remote:'Remote' })[t] || t;
}

function timeAgo(dateStr) {
  const diff = Date.now() - new Date(dateStr).getTime();
  const d = Math.floor(diff / 86400000);
  if (d === 0) return 'Today';
  if (d === 1) return 'Yesterday';
  if (d < 30) return `${d}d ago`;
  if (d < 365) return `${Math.floor(d/30)}mo ago`;
  return `${Math.floor(d/365)}y ago`;
}

function statusBadge(status) {
  const map = {
    pending:  ['badge-warn', 'Pending'],
    reviewed: ['badge', 'Reviewed'],
    accepted: ['badge-green', 'Accepted'],
    rejected: ['badge-red', 'Rejected'],
    active:   ['badge-green', 'Active'],
    closed:   ['badge-gray', 'Closed'],
  };
  const [cls, label] = map[status] || ['badge-gray', status];
  return `<span class="badge ${cls}">${label}</span>`;
}

function jobCard(job) {
  const salary = formatSalary(job.salary_min, job.salary_max, job.salary_currency);
  return `
  <a class="job-card reveal" href="jobs_details.html?id=${job.id}">
    <div class="job-card-title">${job.title}</div>
    <div class="job-card-company">${job.employer_name || job.company_name || 'Company'}</div>
    <div class="job-card-meta">
      ${job.job_type ? `<span class="badge">${formatJobType(job.job_type)}</span>` : ''}
      ${job.location ? `<span class="badge badge-gray">${job.location}</span>` : ''}
      ${salary ? `<span class="badge badge-green">${salary}</span>` : ''}
    </div>
    <div class="job-card-desc">${job.description || ''}</div>
    <div class="flex items-center justify-between" style="margin-top:14px">
      <span class="text-sm text-muted">${timeAgo(job.created_at)}</span>
      <span class="text-sm" style="color:var(--accent);font-weight:500">View →</span>
    </div>
  </a>`;
}

function skeletonCards(n = 6) {
  return Array.from({length: n}, () => `
    <div class="job-card" style="pointer-events:none">
      <div class="skeleton mb-2" style="height:18px;width:70%"></div>
      <div class="skeleton mb-3" style="height:13px;width:40%"></div>
      <div class="flex gap-2 mb-3">
        <div class="skeleton" style="height:22px;width:70px;border-radius:100px"></div>
        <div class="skeleton" style="height:22px;width:60px;border-radius:100px"></div>
      </div>
      <div class="skeleton mb-2" style="height:13px;width:100%"></div>
      <div class="skeleton" style="height:13px;width:80%"></div>
    </div>`).join('');
}

function initReveal() {
  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); } });
  }, { threshold: .1 });
  document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
}

document.addEventListener('DOMContentLoaded', () => {
  // Render the navigation bar
  buildNav();
  setTimeout(initReveal, 100);
});
