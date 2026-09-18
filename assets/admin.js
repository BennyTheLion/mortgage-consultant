var DAY_NAMES = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'];
var currentSettings = {};

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

function adminLogout() {
  api('api/logout.php', { method: 'POST' }).then(function () { location.href = 'admin.php'; });
}

function showTab(tab) {
  var item = document.querySelector('.admin-menu-item[data-tab="' + tab + '"]');
  document.querySelectorAll('.admin-menu-item').forEach(function (t) { t.classList.toggle('active', t.dataset.tab === tab); });
  document.querySelectorAll('.admin-section').forEach(function (p) { p.classList.toggle('hidden', p.dataset.panel !== tab); });
  if (item) document.getElementById('admin-current-tab').textContent = item.textContent;
  closeAdminMenu();
}

function toggleAdminMenu() {
  var open = document.getElementById('admin-menu-dropdown').classList.toggle('hidden') === false;
  document.getElementById('admin-menu-btn').setAttribute('aria-expanded', open ? 'true' : 'false');
}
function closeAdminMenu() {
  document.getElementById('admin-menu-dropdown').classList.add('hidden');
  document.getElementById('admin-menu-btn').setAttribute('aria-expanded', 'false');
}

/* ── Settings ────────────────────────────────────────────────────────── */
function loadSettings() {
  api('api/settings.php').then(function (res) {
    currentSettings = res.data;
    document.getElementById('s-owner-name').value = currentSettings.owner_name || '';
    document.getElementById('s-tagline').value = currentSettings.tagline || '';
    document.getElementById('s-about-text').value = currentSettings.about_text || '';
    var swatch = document.getElementById('s-accent-swatch');
    if (swatch) swatch.style.background = currentSettings.accent_color || '#cda15c';
    if (currentSettings.accent_color) applyAccentColor(currentSettings.accent_color);
    document.getElementById('s-phone').value = currentSettings.phone || '';
    document.getElementById('s-whatsapp').value = currentSettings.whatsapp_phone || '';
    document.getElementById('s-email').value = currentSettings.email || '';
    document.getElementById('s-address').value = currentSettings.address || '';
    document.getElementById('s-instagram').value = currentSettings.instagram_url || '';
    document.getElementById('s-facebook').value = currentSettings.facebook_url || '';
    document.getElementById('s-tiktok').value = currentSettings.tiktok_url || '';
    document.getElementById('s-interval').value = currentSettings.slot_interval_minutes || 30;
    document.getElementById('s-privacy').value = currentSettings.legal_privacy_text || '';
    document.getElementById('s-terms').value = currentSettings.legal_terms_text || '';
    document.getElementById('s-mail-enabled').checked = currentSettings.mail_enabled === '1';
    document.getElementById('s-admin-email').value = currentSettings.admin_notification_email || '';
    document.getElementById('s-smtp-host').value = currentSettings.smtp_host || '';
    document.getElementById('s-smtp-port').value = currentSettings.smtp_port || '587';
    document.getElementById('s-smtp-username').value = currentSettings.smtp_username || '';
    document.getElementById('s-smtp-password').value = currentSettings.smtp_password || '';
    document.getElementById('s-smtp-secure').value = currentSettings.smtp_secure || 'tls';
    document.getElementById('s-smtp-from-email').value = currentSettings.smtp_from_email || '';
    document.getElementById('s-smtp-from-name').value = currentSettings.smtp_from_name || '';
    renderHoursEditor(currentSettings.working_hours || {});
  });
}

function renderHoursEditor(hours) {
  var el = document.getElementById('hours-editor');
  el.innerHTML = '';
  for (var i = 0; i < 7; i++) {
    var d = hours[String(i)] || { closed: true, open: '10:00', close: '19:00' };
    var row = document.createElement('div');
    row.className = 'day-row';
    row.innerHTML =
      '<span class="day-name">' + DAY_NAMES[i] + '</span>' +
      '<input type="time" id="hour-open-' + i + '" value="' + (d.open || '10:00') + '">' +
      '<input type="time" id="hour-close-' + i + '" value="' + (d.close || '19:00') + '">' +
      '<label style="display:flex;align-items:center;gap:4px;font-size:12px;white-space:nowrap;">' +
      '<input type="checkbox" id="hour-closed-' + i + '" ' + (d.closed ? 'checked' : '') + '> סגור</label>';
    el.appendChild(row);
  }
}

function collectWorkingHours() {
  var hours = {};
  for (var i = 0; i < 7; i++) {
    hours[String(i)] = {
      open: document.getElementById('hour-open-' + i).value,
      close: document.getElementById('hour-close-' + i).value,
      closed: document.getElementById('hour-closed-' + i).checked,
    };
  }
  return hours;
}

function saveSettings() {
  var payload = {
    owner_name: document.getElementById('s-owner-name').value,
    tagline: document.getElementById('s-tagline').value,
    about_text: document.getElementById('s-about-text').value,
    phone: document.getElementById('s-phone').value,
    whatsapp_phone: document.getElementById('s-whatsapp').value,
    email: document.getElementById('s-email').value,
    address: document.getElementById('s-address').value,
    instagram_url: document.getElementById('s-instagram').value,
    facebook_url: document.getElementById('s-facebook').value,
    tiktok_url: document.getElementById('s-tiktok').value,
    slot_interval_minutes: document.getElementById('s-interval').value,
    working_hours: collectWorkingHours(),
    legal_privacy_text: document.getElementById('s-privacy').value,
    legal_terms_text: document.getElementById('s-terms').value,
    mail_enabled: document.getElementById('s-mail-enabled').checked ? '1' : '0',
    admin_notification_email: document.getElementById('s-admin-email').value,
    smtp_host: document.getElementById('s-smtp-host').value,
    smtp_port: document.getElementById('s-smtp-port').value,
    smtp_username: document.getElementById('s-smtp-username').value,
    smtp_password: document.getElementById('s-smtp-password').value,
    smtp_secure: document.getElementById('s-smtp-secure').value,
    smtp_from_email: document.getElementById('s-smtp-from-email').value,
    smtp_from_name: document.getElementById('s-smtp-from-name').value,
  };
  api('api/settings.php', { method: 'POST', body: JSON.stringify(payload) }).then(function (res) {
    if (res.ok) toast('נשמר בהצלחה'); else toast(res.data.error || 'שגיאה');
  });
}

/* ── Services ────────────────────────────────────────────────────────── */
function loadServices() {
  api('api/services.php').then(function (res) {
    var list = document.getElementById('services-list');
    list.innerHTML = '';
    (res.data || []).forEach(function (s) {
      var row = document.createElement('div');
      row.className = 'booking-row';
      row.innerHTML =
        '<span>' + s.name + ' · ' + s.duration_minutes + ' דק׳' + (s.price ? ' · ' + s.price : '') + (s.active ? '' : ' (מוסתר)') + '</span>' +
        '<span style="display:flex;gap:6px;">' +
        '<button class="btn btn-sm btn-ghost" data-edit="' + s.id + '">עריכה</button>' +
        '<button class="btn btn-sm btn-danger" data-del="' + s.id + '">מחיקה</button></span>';
      list.appendChild(row);
      row.querySelector('[data-edit]').onclick = function () { editService(s); };
      row.querySelector('[data-del]').onclick = function () { deleteService(s.id); };
    });
  });
}

function editService(s) {
  document.getElementById('svc-id').value = s.id;
  document.getElementById('svc-name').value = s.name;
  document.getElementById('svc-duration').value = s.duration_minutes;
  document.getElementById('svc-price').value = s.price || '';
}
function resetServiceForm() {
  document.getElementById('svc-id').value = '';
  document.getElementById('svc-name').value = '';
  document.getElementById('svc-duration').value = 30;
  document.getElementById('svc-price').value = '';
}
function saveService() {
  var payload = {
    id: document.getElementById('svc-id').value || null,
    name: document.getElementById('svc-name').value.trim(),
    duration_minutes: document.getElementById('svc-duration').value,
    price: document.getElementById('svc-price').value.trim(),
    active: 1,
  };
  if (!payload.name) { toast('נא להזין שם שירות'); return; }
  api('api/services.php', { method: 'POST', body: JSON.stringify(payload) }).then(function (res) {
    if (res.ok) { toast('נשמר'); resetServiceForm(); loadServices(); } else toast(res.data.error || 'שגיאה');
  });
}
function deleteService(id) {
  if (!confirm('למחוק שירות זה?')) return;
  api('api/services.php?id=' + id, { method: 'DELETE' }).then(function () { loadServices(); });
}

/* ── Gallery ─────────────────────────────────────────────────────────── */
function loadGalleryAdmin() {
  ['image', 'video'].forEach(function (type) {
    api('api/gallery.php?type=' + type).then(function (res) {
      var grid = document.getElementById(type + '-grid');
      grid.innerHTML = '';
      (res.data || []).forEach(function (item) {
        var tile = document.createElement('div');
        tile.className = 'media-tile';
        tile.innerHTML = (type === 'image' ? '<img src="' + item.url + '">' : '<video src="' + item.url + '" muted></video>') +
          '<button class="del" data-id="' + item.id + '">✕</button>';
        grid.appendChild(tile);
        tile.querySelector('.del').onclick = function () { deleteMedia(type, item.id); };
      });
    });
  });
}

function uploadMedia(type, input) {
  if (!input.files || !input.files.length) return;
  var files = Array.prototype.slice.call(input.files);
  var total = files.length;
  var uploadBox = document.querySelector('label[for="' + type + '-upload"]');
  var originalText = uploadBox ? uploadBox.textContent : '';

  function uploadNext(i, okCount, failMessages) {
    if (i >= total) {
      input.value = '';
      if (uploadBox) uploadBox.textContent = originalText;
      loadGalleryAdmin();
      if (okCount > 0) toast(okCount + ' מתוך ' + total + ' הועלו בהצלחה');
      if (failMessages.length) toast(failMessages[0]);
      return;
    }
    if (uploadBox) uploadBox.textContent = 'מעלה ' + (i + 1) + ' מתוך ' + total + '...';
    var fd = new FormData();
    fd.append('file', files[i]);
    api('api/gallery.php?type=' + type, { method: 'POST', body: fd }).then(function (res) {
      if (res.ok) {
        uploadNext(i + 1, okCount + 1, failMessages);
      } else {
        failMessages.push((res.data && res.data.error) || 'שגיאה בהעלאת קובץ ' + (i + 1));
        // Stop early if the limit was reached — further files will fail the same way.
        if (res.data && res.data.error && res.data.error.indexOf('מכסה') !== -1) {
          input.value = '';
          if (uploadBox) uploadBox.textContent = originalText;
          loadGalleryAdmin();
          toast(okCount + ' הועלו, נעצר: הגעתם למכסה המקסימלית');
          return;
        }
        uploadNext(i + 1, okCount, failMessages);
      }
    });
  }

  uploadNext(0, 0, []);
}

function deleteMedia(type, id) {
  if (!confirm('למחוק קובץ זה?')) return;
  api('api/gallery.php?type=' + type + '&id=' + id, { method: 'DELETE' }).then(function () { loadGalleryAdmin(); });
}

/* ── Advisor portrait (shown next to the about text on the site) ────── */
function loadPortrait() {
  api('api/portrait.php').then(function (res) {
    var wrap = document.getElementById('portrait-preview-wrap');
    var img = document.getElementById('portrait-preview');
    if (res.data && res.data.exists) {
      img.src = res.data.url + '?v=' + Date.now();
      wrap.classList.remove('hidden');
    } else {
      wrap.classList.add('hidden');
      img.src = '';
    }
  });
}

function uploadPortrait(input) {
  if (!input.files || !input.files.length) return;
  var fd = new FormData();
  fd.append('file', input.files[0]);
  api('api/portrait.php', { method: 'POST', body: fd }).then(function (res) {
    input.value = '';
    if (res.ok) { toast('תמונת היועץ נשמרה'); loadPortrait(); }
    else toast(res.data.error || 'שגיאה בהעלאת התמונה');
  });
}

function deletePortrait() {
  if (!confirm('להסיר את תמונת היועץ?')) return;
  api('api/portrait.php', { method: 'DELETE' }).then(function () {
    toast('התמונה הוסרה');
    loadPortrait();
  });
}
/* ── Logo (shown in the site header + footer brand) ─────────────────── */
function loadLogo() {
  api('api/logo.php').then(function (res) {
    var wrap = document.getElementById('logo-preview-wrap');
    var img = document.getElementById('logo-preview');
    if (res.data && res.data.exists) {
      img.src = res.data.url + '?v=' + Date.now();
      wrap.classList.remove('hidden');
    } else {
      wrap.classList.add('hidden');
      img.src = '';
    }
  });
}

function uploadLogo(input) {
  if (!input.files || !input.files.length) return;
  var fd = new FormData();
  fd.append('file', input.files[0]);
  api('api/logo.php', { method: 'POST', body: fd }).then(function (res) {
    input.value = '';
    if (res.ok) { toast('הלוגו נשמר'); loadLogo(); }
    else toast(res.data.error || 'שגיאה בהעלאת הלוגו');
  });
}

function deleteLogo() {
  if (!confirm('להסיר את הלוגו?')) return;
  api('api/logo.php', { method: 'DELETE' }).then(function () {
    toast('הלוגו הוסר');
    loadLogo();
  });
}
/* ── Bookings ────────────────────────────────────────────────────────── */
function loadBookings() {
  api('api/booking.php').then(function (res) {
    var list = document.getElementById('bookings-list');
    list.innerHTML = '';
    var rows = res.data || [];
    if (!rows.length) { list.innerHTML = '<div style="opacity:.6;font-size:14px;">אין פגישות קרובות</div>'; return; }
    rows.forEach(function (b) {
      var row = document.createElement('div');
      row.className = 'card';
      row.style.cssText = 'padding:14px;margin-bottom:10px;';
      var cancelled = b.status === 'cancelled';
      row.innerHTML =
        '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;' + (cancelled ? 'opacity:.45;' : '') + '">' +
        '<div style="font-size:13.5px;line-height:1.6;">' +
        '<div style="font-weight:700;">' + b.service_name + (cancelled ? ' · בוטל' : '') + '</div>' +
        '<div>' + b.customer_name + ' · ' + b.customer_phone + (b.customer_email ? ' · ' + b.customer_email : '') + '</div>' +
        '</div>' +
        '<div style="text-align:left;font-size:13px;white-space:nowrap;">' + b.booking_date + '<br>' + b.booking_time.slice(0, 5) + '</div>' +
        '</div>' +
        (cancelled ? '' :
          '<div style="display:flex;gap:6px;margin-top:10px;">' +
          '<button class="btn btn-sm btn-ghost" data-resched="' + b.id + '">עדכון מועד</button>' +
          '<button class="btn btn-sm btn-danger" data-cancel="' + b.id + '">ביטול פגישה</button>' +
          '</div>' +
          '<div class="hidden" id="resched-form-' + b.id + '" style="margin-top:12px;"></div>'
        );
      list.appendChild(row);

      if (!cancelled) {
        row.querySelector('[data-cancel]').onclick = function () { cancelBooking(b.id); };
        row.querySelector('[data-resched]').onclick = function () { toggleReschedule(b); };
      }
    });
  });
}

function cancelBooking(id) {
  if (!confirm('לבטל פגישה זו? תישלח הודעת ביטול ללקוח ולעסק.')) return;
  api('api/booking.php', { method: 'PUT', body: JSON.stringify({ id: id, action: 'cancel' }) }).then(function (res) {
    if (res.ok) { toast('הפגישה בוטלה'); loadBookings(); } else toast(res.data.error || 'שגיאה');
  });
}

// Calendar-based reschedule: pick a date, then only genuinely open slots (via availability.php,
// excluding this booking's own current slot) are offered — same UX as the public booking flow.
function toggleReschedule(b) {
  var container = document.getElementById('resched-form-' + b.id);
  if (!container.classList.contains('hidden')) { container.classList.add('hidden'); container.innerHTML = ''; return; }
  container.classList.remove('hidden');
  container.innerHTML =
    '<div class="field"><label>תאריך חדש</label><input type="date" id="resched-date-' + b.id + '"></div>' +
    '<div class="section-title">שעות פנויות</div>' +
    '<div id="resched-slots-' + b.id + '" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;"></div>' +
    '<button class="btn btn-primary btn-sm" style="margin-top:12px;" id="resched-save-' + b.id + '" disabled>שמירה</button>';

  var dateInput = document.getElementById('resched-date-' + b.id);
  var today = new Date();
  dateInput.min = today.toISOString().slice(0, 10);
  dateInput.max = new Date(today.getTime() + 30 * 86400000).toISOString().slice(0, 10);
  dateInput.value = b.booking_date;

  var chosenTime = null;
  var saveBtn = document.getElementById('resched-save-' + b.id);

  function loadSlots() {
    var slotsEl = document.getElementById('resched-slots-' + b.id);
    slotsEl.innerHTML = '<div style="grid-column:1/-1;font-size:13px;color:oklch(0.95 0.015 90 / 0.5);">טוען שעות...</div>';
    chosenTime = null;
    saveBtn.disabled = true;
    api('api/availability.php?date=' + dateInput.value + '&service_id=' + b.service_id + '&exclude_booking_id=' + b.id).then(function (res) {
      var slots = (res.data && res.data.slots) || [];
      slotsEl.innerHTML = '';
      if (!slots.length) {
        slotsEl.innerHTML = '<div style="grid-column:1/-1;font-size:13.5px;color:oklch(0.95 0.015 90 / 0.5);">אין שעות פנויות בתאריך זה</div>';
        return;
      }
      var currentTime = b.booking_time.slice(0, 5);
      slots.forEach(function (s) {
        var isCurrent = dateInput.value === b.booking_date && s.time === currentTime;
        var div = document.createElement('div');
        div.className = 'slot' + (isCurrent ? ' selected' : '');
        div.textContent = s.time;
        div.onclick = function () {
          chosenTime = s.time;
          Array.prototype.forEach.call(slotsEl.children, function (el) { el.classList.remove('selected'); });
          div.classList.add('selected');
          saveBtn.disabled = false;
        };
        slotsEl.appendChild(div);
        if (isCurrent) { chosenTime = s.time; saveBtn.disabled = false; }
      });
    });
  }

  dateInput.addEventListener('change', loadSlots);
  loadSlots();

  saveBtn.onclick = function () {
    if (!chosenTime) return;
    rescheduleBooking(b.id, dateInput.value, chosenTime);
  };
}

function rescheduleBooking(id, date, time) {
  if (!date || !time) { toast('נא לבחור תאריך ושעה'); return; }
  api('api/booking.php', { method: 'PUT', body: JSON.stringify({ id: id, action: 'reschedule', date: date, time: time }) }).then(function (res) {
    if (res.ok) { toast('הפגישה עודכנה'); loadBookings(); } else toast(res.data.error || 'שגיאה');
  });
}

function sendTestMail() {
  var to = document.getElementById('test-mail-to').value.trim();
  var resultEl = document.getElementById('test-mail-result');
  if (!to) { toast('נא להזין כתובת אימייל'); return; }
  resultEl.textContent = 'שומר הגדרות ושולח...';
  resultEl.style.color = '';

  // Save current form values first so the test always reflects what's on screen,
  // not just what was last saved.
  var payload = {
    mail_enabled: document.getElementById('s-mail-enabled').checked ? '1' : '0',
    admin_notification_email: document.getElementById('s-admin-email').value,
    smtp_host: document.getElementById('s-smtp-host').value,
    smtp_port: document.getElementById('s-smtp-port').value,
    smtp_username: document.getElementById('s-smtp-username').value,
    smtp_password: document.getElementById('s-smtp-password').value,
    smtp_secure: document.getElementById('s-smtp-secure').value,
    smtp_from_email: document.getElementById('s-smtp-from-email').value,
    smtp_from_name: document.getElementById('s-smtp-from-name').value,
  };

  api('api/settings.php', { method: 'POST', body: JSON.stringify(payload) }).then(function () {
    return api('api/test_mail.php', { method: 'POST', body: JSON.stringify({ to: to }) });
  }).then(function (res) {
    if (res.ok) {
      resultEl.style.color = 'var(--accent)';
      resultEl.textContent = '✔ נשלח בהצלחה! בדקו את תיבת הדואר (וגם את התיקיית ספאם).';
    } else {
      resultEl.style.color = 'oklch(0.6 0.19 25)';
      resultEl.textContent = '✕ ' + (res.data.error || 'שליחה נכשלה');
    }
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

/* ── Push notifications (advisor gets notified on new/changed bookings) ───
   VAPID_PUBLIC_KEY is injected by admin.php from config.php's VAPID_PUBLIC_KEY constant. */

function urlBase64ToUint8Array(base64String) {
  var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  var rawData = window.atob(base64);
  var outputArray = new Uint8Array(rawData.length);
  for (var i = 0; i < rawData.length; i++) outputArray[i] = rawData.charCodeAt(i);
  return outputArray;
}

function setPushButtonState(state) {
  var btn = document.getElementById('push-toggle-btn');
  if (!btn) return;
  if (state === 'unsupported') {
    btn.textContent = 'התראות דחיפה לא נתמכות בדפדפן זה';
    btn.disabled = true;
  } else if (state === 'denied') {
    btn.textContent = 'התראות חסומות — יש לאשר בהגדרות הדפדפן';
    btn.disabled = true;
  } else if (state === 'on') {
    btn.textContent = '🔔 התראות דחיפה פעילות (לחיצה לביטול)';
    btn.classList.add('btn-primary');
  } else {
    btn.textContent = '🔕 הפעלת התראות דחיפה למכשיר זה';
    btn.classList.remove('btn-primary');
  }
}

function initPush() {
  var btn = document.getElementById('push-toggle-btn');
  if (!btn) return;
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    setPushButtonState('unsupported');
    return;
  }
  if (Notification.permission === 'denied') {
    setPushButtonState('denied');
    return;
  }

  navigator.serviceWorker.register('sw.js').then(function (reg) {
    return reg.pushManager.getSubscription();
  }).then(function (sub) {
    setPushButtonState(sub ? 'on' : 'off');
    btn.onclick = function () { togglePush(sub); };
  }).catch(function () {
    setPushButtonState('off');
    btn.onclick = function () { togglePush(null); };
  });
}

function togglePush(existingSub) {
  if (existingSub) {
    existingSub.unsubscribe().then(function () {
      return api('api/push.php', { method: 'DELETE', body: JSON.stringify({ endpoint: existingSub.endpoint }) });
    }).then(function () {
      setPushButtonState('off');
      toast('התראות דחיפה כבויות');
      initPush();
    });
    return;
  }

  navigator.serviceWorker.register('sw.js').then(function (reg) {
    return reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
    });
  }).then(function (sub) {
    return api('api/push.php', { method: 'POST', body: JSON.stringify(sub.toJSON()) });
  }).then(function () {
    setPushButtonState('on');
    toast('התראות דחיפה פעילות');
    initPush();
  }).catch(function (err) {
    toast('לא ניתן להפעיל התראות: ' + (err && err.message ? err.message : 'שגיאה'));
    setPushButtonState(Notification.permission === 'denied' ? 'denied' : 'off');
  });
}

document.addEventListener('DOMContentLoaded', function () {
  loadSettings();
  loadServices();
  loadGalleryAdmin();
  loadPortrait();
  loadLogo();
  loadBookings();
  initPush();
});
