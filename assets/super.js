function api(url, opts) {
  opts = opts || {};
  if (opts.body && !(opts.body instanceof FormData)) {
    opts.headers = Object.assign({ 'Content-Type': 'application/json' }, opts.headers || {});
  }
  return fetch(url, opts).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); });
}

function toast(msg) {
  var t = document.createElement('div');
  t.className = 'toast';
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(function () { t.remove(); }, 2200);
}

function superLogout() {
  api('api/logout.php', { method: 'POST' }).then(function () { location.href = 'super.php'; });
}

function showSuperTab(tab) {
  document.querySelectorAll('.admin-menu-item').forEach(function (t) { t.classList.toggle('active', t.dataset.tab === tab); });
  document.querySelectorAll('.admin-section').forEach(function (p) { p.classList.toggle('hidden', p.dataset.panel !== tab); });
  if (tab === 'bookings') loadAllBookings();
}

function suggestSlug() {
  var name = document.getElementById('nt-name').value;
  var slugField = document.getElementById('nt-slug');
  if (slugField.dataset.touched === '1') return; // don't overwrite a manually-edited slug
  var slug = name
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, '')
    .trim()
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-');
  slugField.value = slug;
}

function createTenant() {
  var payload = {
    name: document.getElementById('nt-name').value.trim(),
    slug: document.getElementById('nt-slug').value.trim(),
    admin_username: document.getElementById('nt-admin-username').value.trim(),
    admin_password: document.getElementById('nt-admin-password').value,
    accent_color: document.getElementById('nt-accent-color').value,
  };
  var errEl = document.getElementById('nt-error');
  errEl.textContent = '';
  api('api/tenants.php', { method: 'POST', body: JSON.stringify(payload) }).then(function (res) {
    if (res.ok) {
      toast('האתר נוצר בהצלחה');
      ['nt-name', 'nt-slug', 'nt-admin-username', 'nt-admin-password'].forEach(function (id) {
        document.getElementById(id).value = '';
      });
      document.getElementById('nt-slug').dataset.touched = '0';
      document.getElementById('nt-accent-color').value = '#cda15c';
      document.getElementById('nt-accent-color-hex').value = '#cda15c';
      loadTenants();
    } else {
      errEl.textContent = res.data.error || 'שגיאה';
    }
  });
}

function setTenantColor(id, hex) {
  if (!/^#[0-9a-fA-F]{6}$/.test(hex)) { toast('צבע לא תקין'); return; }
  api('api/tenants.php', { method: 'PUT', body: JSON.stringify({ id: id, action: 'set_color', accent_color: hex }) }).then(function (res) {
    if (res.ok) { toast('הצבע עודכן'); loadTenants(); }
    else toast(res.data.error || 'שגיאה');
  });
}

function loadTenants() {
  api('api/tenants.php').then(function (res) {
    var list = document.getElementById('tenants-list');
    var tenants = res.data || [];
    if (!tenants.length) { list.innerHTML = '<div style="opacity:.6;font-size:14px;">אין עדיין אתרים</div>'; return; }
    list.innerHTML = '';
    tenants.forEach(function (t) {
      var row = document.createElement('div');
      row.className = 'card';
      row.style.cssText = 'padding:14px 16px; margin-bottom:10px;';
      var statusLabel = t.status === 'active' ? 'פעיל' : 'מושהה';
      var statusColor = t.status === 'active' ? 'var(--accent)' : 'oklch(0.6 0.19 25)';
      row.innerHTML =
        '<div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">' +
        '<div>' +
        '<div style="font-weight:700; font-size:15px;">' + escapeHtml(t.name) + '</div>' +
        '<div style="font-size:12.5px; opacity:.6; margin-top:2px;">/site/' + escapeHtml(t.slug) + '/ · מנהל: ' + escapeHtml(t.admin_username || '—') + '</div>' +
        '<div style="font-size:12.5px; opacity:.6; margin-top:2px;">' + t.services_count + ' שירותים · ' + t.upcoming_count + ' פגישות קרובות</div>' +
        '</div>' +
        '<div style="font-size:12.5px; font-weight:700; color:' + statusColor + '; white-space:nowrap;">' + statusLabel + '</div>' +
        '</div>' +
        '<div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; align-items:center;">' +
        '<a class="btn btn-sm btn-ghost" href="site.php?slug=' + encodeURIComponent(t.slug) + '" target="_blank">צפייה באתר</a>' +
        '<button class="btn btn-sm btn-ghost" data-toggle="' + t.id + '" data-status="' + t.status + '">' + (t.status === 'active' ? 'השהיה' : 'הפעלה') + '</button>' +
        '<button class="btn btn-sm btn-danger" data-delete="' + t.id + '">מחיקה</button>' +
        '<label style="display:flex; align-items:center; gap:6px; font-size:12px; opacity:.7; margin-inline-start:auto;">צבע' +
        '<input type="color" data-color="' + t.id + '" value="' + (t.accent_color || '#cda15c') + '" style="width:30px; height:30px; padding:2px; border-radius:6px; cursor:pointer; border:1px solid oklch(0.95 0.015 90 / 0.2);"></label>' +
        '</div>';
      list.appendChild(row);
      row.querySelector('[data-toggle]').onclick = function () { toggleTenant(t.id, t.status); };
      row.querySelector('[data-delete]').onclick = function () { deleteTenant(t.id, t.name); };
      row.querySelector('[data-color]').onchange = function (e) { setTenantColor(t.id, e.target.value); };
    });
  });
}

function toggleTenant(id, currentStatus) {
  var action = currentStatus === 'active' ? 'suspend' : 'activate';
  api('api/tenants.php', { method: 'PUT', body: JSON.stringify({ id: id, action: action }) }).then(function (res) {
    if (res.ok) { toast(action === 'suspend' ? 'האתר הושהה' : 'האתר הופעל'); loadTenants(); }
    else toast(res.data.error || 'שגיאה');
  });
}

function deleteTenant(id, name) {
  if (!confirm('למחוק לצמיתות את "' + name + '"? כל הנתונים, השירותים, הפגישות והתמונות יימחקו ולא ניתן לשחזר.')) return;
  api('api/tenants.php?id=' + id, { method: 'DELETE' }).then(function (res) {
    if (res.ok) { toast('האתר נמחק'); loadTenants(); }
    else toast(res.data.error || 'שגיאה');
  });
}

function loadAllBookings() {
  api('api/tenants.php?view=bookings').then(function (res) {
    var list = document.getElementById('all-bookings-list');
    var rows = res.data || [];
    if (!rows.length) { list.innerHTML = '<div style="opacity:.6;font-size:14px;">אין פגישות קרובות בכל האתרים</div>'; return; }
    list.innerHTML = '';
    rows.forEach(function (b) {
      var row = document.createElement('div');
      row.className = 'booking-row';
      var cancelled = b.status !== 'confirmed';
      row.style.opacity = cancelled ? '.5' : '1';
      row.innerHTML =
        '<span>' + escapeHtml(b.tenant_name) + ' · ' + escapeHtml(b.service_name) + ' · ' + escapeHtml(b.customer_name) + '</span>' +
        '<span>' + b.booking_date + ' ' + b.booking_time.slice(0, 5) + (cancelled ? ' (בוטל)' : '') + '</span>';
      list.appendChild(row);
    });
  });
}

function togglePasswordVisibility(inputId, btn) {
  var input = document.getElementById(inputId);
  var isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  btn.innerHTML = isHidden
    ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.94 10.94 0 0112 20c-7 0-11-7-11-7a21.6 21.6 0 015.06-6.06M9.9 4.24A10.94 10.94 0 0112 4c7 0 11 7 11 7a21.6 21.6 0 01-3.22 4.34M1 1l22 22"/><path d="M14.12 14.12a3 3 0 11-4.24-4.24"/></svg>'
    : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
}

function escapeHtml(s) {
  var d = document.createElement('div');
  d.textContent = s == null ? '' : String(s);
  return d.innerHTML;
}

document.addEventListener('DOMContentLoaded', function () {
  if (document.getElementById('tenants-list')) loadTenants();
  var slugField = document.getElementById('nt-slug');
  if (slugField) slugField.addEventListener('input', function () { slugField.dataset.touched = '1'; });

  var picker = document.getElementById('nt-accent-color');
  var hexInput = document.getElementById('nt-accent-color-hex');
  if (picker && hexInput) {
    picker.addEventListener('input', function () { hexInput.value = picker.value; });
    hexInput.addEventListener('input', function () {
      if (/^#[0-9a-fA-F]{6}$/.test(hexInput.value)) picker.value = hexInput.value;
    });
  }
});
