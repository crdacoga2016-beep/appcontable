const API = 'https://lonmar.lat/api/index.php';

const App = {
  token: localStorage.getItem('token') || '',
  user: JSON.parse(localStorage.getItem('user') || 'null'),
  clients: [],
  receipts: [],
  currentClient: null,
  filter: { start: '', end: '', text: '' },
  chart: null,
  editingId: null,
  pendingFile: null,

  /* ---------------- utilidades ---------------- */
  esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  },
  money(n) {
    return '$ ' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
  },
  today() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  },
  toast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast ' + (type || '');
    clearTimeout(this._toastT);
    this._toastT = setTimeout(() => t.classList.add('hidden'), 2600);
  },

  async request(action, opts) {
    opts = opts || {};
    const headers = {};
    if (!opts.form && opts.body) headers['Content-Type'] = 'application/json';
    if (this.token) headers['Authorization'] = 'Bearer ' + this.token;
    try {
      const res = await fetch(API + '?action=' + action, {
        method: opts.method || 'GET',
        headers,
        body: opts.form ? opts.body : (opts.body ? JSON.stringify(opts.body) : null)
      });
      const data = await res.json();
      if (data && data.success === false && /token/i.test(data.message || '')) {
        this.logout();
      }
      return data;
    } catch (e) {
      this.toast('Sin conexión con el servidor', 'err');
      return { success: false };
    }
  },

  /* ---------------- auth ---------------- */
  init() {
    document.getElementById('form-login').addEventListener('submit', (e) => {
      e.preventDefault();
      this.login(document.getElementById('login-email').value.trim(), document.getElementById('login-password').value);
    });
    document.getElementById('form-register').addEventListener('submit', (e) => {
      e.preventDefault();
      this.register();
    });
    document.getElementById('form-client').addEventListener('submit', (e) => {
      e.preventDefault();
      this.saveClient();
    });
    document.getElementById('form-upload').addEventListener('submit', (e) => {
      e.preventDefault();
      this.saveReceipt();
    });
    ['up-file-camera', 'up-file-gallery'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('change', (e) => this.previewFile(e));
    });
    document.getElementById('report-client').addEventListener('change', () => this.renderReportSummary());

    window.addEventListener('popstate', () => this.hideViewer());

    if (this.token && this.user) {
      this.showApp();
    } else {
      document.getElementById('view-auth').classList.remove('hidden');
    }
  },

  authTab(which) {
    document.getElementById('tab-login').classList.toggle('active', which === 'login');
    document.getElementById('tab-register').classList.toggle('active', which === 'register');
    document.getElementById('form-login').classList.toggle('hidden', which !== 'login');
    document.getElementById('form-register').classList.toggle('hidden', which !== 'register');
  },

  async login(email, password) {
    const r = await this.request('login', { method: 'POST', body: { email, password } });
    if (r.success) {
      this.token = r.token;
      this.user = r.user;
      localStorage.setItem('token', r.token);
      localStorage.setItem('user', JSON.stringify(r.user));
      this.showApp();
    } else {
      this.toast(r.message || 'No se pudo iniciar sesión', 'err');
    }
  },

  async register() {
    const body = {
      name: document.getElementById('reg-name').value.trim(),
      email: document.getElementById('reg-email').value.trim(),
      phone: document.getElementById('reg-phone').value.trim(),
      password: document.getElementById('reg-password').value
    };
    const r = await this.request('register', { method: 'POST', body });
    if (r.success) {
      this.toast('Cuenta creada, ya puedes entrar', 'ok');
      this.authTab('login');
      document.getElementById('login-email').value = body.email;
    } else {
      this.toast(r.message || 'No se pudo crear la cuenta', 'err');
    }
  },

  logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    this.token = '';
    this.user = null;
    location.reload();
  },

  showApp() {
    document.getElementById('view-auth').classList.add('hidden');
    document.getElementById('view-app').classList.remove('hidden');
    document.getElementById('topbar-sub').textContent = this.user ? this.user.name : '';
    this.nav('dashboard');
    this.loadAll();
  },

  /* ---------------- navegación ---------------- */
  nav(screen) {
    ['dashboard', 'clients', 'client', 'reports'].forEach(s => {
      const el = document.getElementById('screen-' + s);
      if (el) el.classList.toggle('hidden', s !== screen);
    });
    document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.tab === screen));
    const titles = { dashboard: 'Inicio', clients: 'Clientes', client: 'Cliente', reports: 'Reportes' };
    document.getElementById('topbar-title').textContent = titles[screen] || 'AppContable';
    document.getElementById('fab-upload').classList.toggle('hidden', screen !== 'client');
    if (screen === 'clients') this.renderClients();
    if (screen === 'reports') this.renderReportSummary();
    window.scrollTo(0, 0);
  },

  /* ---------------- datos ---------------- */
  async loadAll() {
    const [c, r] = await Promise.all([this.request('clients'), this.request('receipts')]);
    this.clients = (c && c.clients) || [];
    this.receipts = (r && r.receipts) || [];
    this.renderDashboard();
    this.renderClients();
    this.fillClientSelects();
  },

  renderDashboard() {
    const total = this.receipts.reduce((s, x) => s + Number(x.amount || 0), 0);
    document.getElementById('kpi-total').textContent = this.money(total);
    document.getElementById('kpi-total-sub').textContent = this.receipts.length + ' comprobantes';
    document.getElementById('kpi-clients').textContent = this.clients.length;

    const recent = this.receipts.slice(0, 6);
    const el = document.getElementById('recent-list');
    el.innerHTML = recent.length ? recent.map(x => `
      <div class="row">
        <div class="avatar">${this.esc((x.client_name || '?').charAt(0).toUpperCase())}</div>
        <div class="row-main">
          <div class="row-title">${this.esc(x.client_name || 'Sin cliente')}</div>
          <div class="row-sub">${this.esc(x.receipt_date)} · ${this.esc(x.method)}</div>
        </div>
        <div class="row-amount">${this.money(x.amount)}<small>COP</small></div>
      </div>`).join('') : `
      <div class="empty"><i class="fas fa-inbox"></i><h4>Sin movimientos</h4><p>Sube tu primer comprobante desde un cliente.</p></div>`;

    this.renderChart();
  },

  renderChart() {
    const byMonth = {};
    this.receipts.forEach(x => {
      const m = (x.receipt_date || '').slice(0, 7);
      if (!m) return;
      byMonth[m] = (byMonth[m] || 0) + Number(x.amount || 0);
    });
    const labels = Object.keys(byMonth).sort();
    const data = labels.map(l => byMonth[l]);
    const ctx = document.getElementById('chart-months');
    if (!ctx || typeof Chart === 'undefined') return;
    if (this.chart) this.chart.destroy();
    this.chart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels.map(l => l.slice(5) + '/' + l.slice(2, 4)),
        datasets: [{
          data,
          backgroundColor: 'rgba(99,102,241,.75)',
          hoverBackgroundColor: '#4338ca',
          borderRadius: 6,
          maxBarThickness: 34
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => this.money(c.raw) } } },
        scales: {
          y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: v => (v >= 1000000 ? (v / 1000000) + 'M' : (v / 1000) + 'k'), font: { size: 10 } } },
          x: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
      }
    });
  },

  renderClients() {
    const q = (document.getElementById('client-search').value || '').toLowerCase().trim();
    const list = this.clients.filter(c =>
      !q || (c.name || '').toLowerCase().includes(q) || (c.phone || '').includes(q)
    );
    const el = document.getElementById('clients-list');
    el.innerHTML = list.length ? list.map(c => {
      const mine = this.receipts.filter(r => Number(r.client_id) === Number(c.id));
      const total = c.total != null ? Number(c.total) : mine.reduce((s, x) => s + Number(x.amount || 0), 0);
      const count = c.receipts_count != null ? c.receipts_count : mine.length;
      return `
      <div class="client-card" onclick="App.openClient(${c.id})">
        <div class="avatar">${this.esc((c.name || '?').charAt(0).toUpperCase())}</div>
        <div class="client-info">
          <h4>${this.esc(c.name)}</h4>
          <p>${this.esc(c.phone || 'Sin teléfono')}</p>
        </div>
        <div class="client-total">
          <div class="amount">${this.money(total)}</div>
          <div class="count">${count} compr.</div>
        </div>
      </div>`;
    }).join('') : `
      <div class="empty"><i class="fas fa-user-plus"></i><h4>${q ? 'Sin resultados' : 'Aún no hay clientes'}</h4><p>${q ? 'Prueba con otro nombre.' : 'Crea tu primer cliente con el botón +.'}</p></div>`;
  },

  fillClientSelects() {
    const opts = this.clients.map(c => `<option value="${c.id}">${this.esc(c.name)}</option>`).join('');
    const up = document.getElementById('up-client');
    const rep = document.getElementById('report-client');
    if (up) up.innerHTML = opts || '<option value="">Crea un cliente primero</option>';
    if (rep) rep.innerHTML = '<option value="0">Todos los clientes</option>' + opts;
  },

  /* ---------------- cliente ---------------- */
  openClient(id) {
    const c = this.clients.find(x => Number(x.id) === Number(id));
    if (!c) return;
    this.currentClient = c;
    this.filter = { start: '', end: '', text: '' };
    document.getElementById('client-avatar').textContent = (c.name || '?').charAt(0).toUpperCase();
    document.getElementById('client-name').textContent = c.name;
    document.getElementById('client-phone').textContent = c.phone || 'Sin teléfono';
    document.getElementById('filter-start').value = '';
    document.getElementById('filter-end').value = '';
    document.getElementById('receipt-search').value = '';
    document.querySelectorAll('#quick-chips .chip').forEach((b, i) => b.classList.toggle('active', i === 0));
    this.nav('client');
    this.renderReceipts();
  },

  clientReceipts() {
    if (!this.currentClient) return [];
    return this.receipts.filter(r => Number(r.client_id) === Number(this.currentClient.id));
  },

  quickFilter(kind, btn) {
    document.querySelectorAll('#quick-chips .chip').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    const d = new Date();
    const iso = (dt) => dt.toISOString().slice(0, 10);
    let start = '', end = '';
    if (kind === 'today') { start = end = iso(d); }
    if (kind === 'week') { const s = new Date(d); s.setDate(s.getDate() - 6); start = iso(s); end = iso(d); }
    if (kind === 'month') { start = iso(new Date(d.getFullYear(), d.getMonth(), 1)); end = iso(d); }
    this.filter.start = start;
    this.filter.end = end;
    document.getElementById('filter-start').value = start;
    document.getElementById('filter-end').value = end;
    this.renderReceipts();
  },

  applyDateFilter() {
    this.filter.start = document.getElementById('filter-start').value;
    this.filter.end = document.getElementById('filter-end').value;
    document.querySelectorAll('#quick-chips .chip').forEach(b => b.classList.remove('active'));
    this.renderReceipts();
  },

  clearFilters() {
    this.filter = { start: '', end: '', text: '' };
    document.getElementById('filter-start').value = '';
    document.getElementById('filter-end').value = '';
    document.getElementById('receipt-search').value = '';
    document.querySelectorAll('#quick-chips .chip').forEach((b, i) => b.classList.toggle('active', i === 0));
    this.renderReceipts();
  },

  renderReceipts() {
    const text = (document.getElementById('receipt-search').value || '').toLowerCase().trim();
    let list = this.clientReceipts();
    if (this.filter.start) list = list.filter(r => r.receipt_date >= this.filter.start);
    if (this.filter.end) list = list.filter(r => r.receipt_date <= this.filter.end);
    if (text) list = list.filter(r =>
      (r.receipt_date || '').includes(text) ||
      (r.method || '').toLowerCase().includes(text) ||
      (r.note || '').toLowerCase().includes(text) ||
      String(r.amount).includes(text)
    );

    const total = list.reduce((s, x) => s + Number(x.amount || 0), 0);
    document.getElementById('client-total').textContent = this.money(total);
    document.getElementById('client-count').textContent = list.length;

    const el = document.getElementById('receipts-list');
    el.innerHTML = list.length ? list.map(r => `
      <div class="receipt-card">
        ${r.file_url
          ? `<img class="thumb" src="${this.esc(r.file_url)}" alt="comprobante" loading="lazy" onclick="App.viewImage('${this.esc(r.file_url)}')">`
          : `<div class="thumb-ph"><i class="fas fa-image"></i></div>`}
        <div class="receipt-main">
          <div class="t">${this.money(r.amount)} COP</div>
          <div class="s">${this.esc(r.receipt_date)}</div>
          <span class="badge">${this.esc(r.method)}</span>
          ${r.note ? `<div class="s" style="margin-top:3px">${this.esc(r.note)}</div>` : ''}
        </div>
        <div class="receipt-side">
          <div class="amt">${this.money(r.amount)}</div>
          <div class="receipt-actions">
            <button class="mini edit" onclick="App.editReceipt(${r.id})" title="Editar"><i class="fas fa-pen"></i></button>
            <button class="mini danger" onclick="App.deleteReceipt(${r.id})" title="Eliminar"><i class="fas fa-trash-can"></i></button>
          </div>
        </div>
      </div>`).join('') : `
      <div class="empty"><i class="fas fa-receipt"></i><h4>Sin comprobantes</h4><p>Usa el botón de cámara para subir el primero.</p></div>`;
  },

  viewImage(url) {
    const v = document.getElementById('viewer');
    const img = document.getElementById('viewer-img');
    img.classList.remove('zoomed');
    img.src = url;
    v.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    if (!(history.state && history.state.viewer)) history.pushState({ viewer: 1 }, '');
  },
  closeViewer() {
    this.hideViewer();
    if (history.state && history.state.viewer) history.replaceState(null, '');
  },
  hideViewer() {
    const v = document.getElementById('viewer');
    const img = document.getElementById('viewer-img');
    if (!v || v.classList.contains('hidden')) return;
    v.classList.add('hidden');
    img.classList.remove('zoomed');
    img.src = '';
    document.body.style.overflow = '';
  },
  viewerBackdrop(e) {
    if (e.target && e.target.id === 'viewer') this.closeViewer();
  },
  toggleZoom(e) {
    e.stopPropagation();
    e.target.classList.toggle('zoomed');
  },

  async deleteReceipt(id) {
    if (!confirm('¿Eliminar este comprobante? Esta acción no se puede deshacer.')) return;
    const r = await this.request('receipt&id=' + id, { method: 'DELETE' });
    if (r.success) {
      this.toast('Comprobante eliminado', 'ok');
      await this.loadAll();
      this.renderReceipts();
    } else {
      this.toast('No se pudo eliminar', 'err');
    }
  },

  /* ---------------- modales ---------------- */
  openClientModal() {
    document.getElementById('modal-client').classList.remove('hidden');
    setTimeout(() => document.getElementById('client-name-input').focus(), 120);
  },
  openUploadModal() {
    if (!this.clients.length) {
      this.toast('Primero crea un cliente', 'err');
      this.nav('clients');
      return;
    }
    this.editingId = null;
    this.pendingFile = null;
    document.getElementById('form-upload').reset();
    document.getElementById('upload-title').textContent = 'Nuevo comprobante';
    document.getElementById('btn-save-receipt').textContent = 'Guardar comprobante';
    document.getElementById('up-date').value = this.today();
    document.getElementById('file-preview').classList.add('hidden');
    document.getElementById('file-preview').src = '';
    document.getElementById('file-text').textContent = 'Adjunta la foto del baucher';
    if (this.currentClient) document.getElementById('up-client').value = this.currentClient.id;
    document.getElementById('modal-upload').classList.remove('hidden');
  },

  editReceipt(id) {
    const r = this.receipts.find(x => Number(x.id) === Number(id));
    if (!r) return;
    this.editingId = r.id;
    this.pendingFile = null;
    document.getElementById('form-upload').reset();
    document.getElementById('upload-title').textContent = 'Editar comprobante';
    document.getElementById('btn-save-receipt').textContent = 'Guardar cambios';
    document.getElementById('up-client').value = r.client_id;
    document.getElementById('up-amount').value = Number(r.amount);
    document.getElementById('up-date').value = r.receipt_date;
    document.getElementById('up-method').value = r.method;
    document.getElementById('up-note').value = r.note || '';
    const prev = document.getElementById('file-preview');
    if (r.file_url) {
      prev.src = r.file_url;
      prev.classList.remove('hidden');
      document.getElementById('file-text').textContent = 'Foto actual. Toca Cámara o Galería solo si quieres reemplazarla.';
    } else {
      prev.src = '';
      prev.classList.add('hidden');
      document.getElementById('file-text').textContent = 'Sin foto. Puedes adjuntar una.';
    }
    document.getElementById('modal-upload').classList.remove('hidden');
  },
  closeModal(id) {
    document.getElementById(id).classList.add('hidden');
  },

  previewFile(e) {
    const f = e.target.files[0];
    if (!f) return;
    this.pendingFile = f;
    const other = e.target.id === 'up-file-camera' ? 'up-file-gallery' : 'up-file-camera';
    const o = document.getElementById(other);
    if (o) o.value = '';
    const img = document.getElementById('file-preview');
    img.src = URL.createObjectURL(f);
    img.classList.remove('hidden');
    document.getElementById('file-text').textContent = f.name;
  },

  async saveClient() {
    const name = document.getElementById('client-name-input').value.trim();
    const phone = document.getElementById('client-phone-input').value.trim();
    if (!name) return this.toast('Escribe el nombre', 'err');
    const r = await this.request('clients', { method: 'POST', body: { name, phone } });
    if (r.success) {
      this.closeModal('modal-client');
      document.getElementById('form-client').reset();
      this.toast('Cliente creado', 'ok');
      await this.loadAll();
      this.renderClients();
    } else {
      this.toast(r.message || 'No se pudo crear', 'err');
    }
  },

  async saveReceipt() {
    const form = document.getElementById('form-upload');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const isEdit = !!this.editingId;
    if (!isEdit && !this.pendingFile) {
      this.toast('Adjunta la foto del baucher', 'err');
      return;
    }

    const btn = document.getElementById('btn-save-receipt');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    const fd = new FormData();
    fd.append('client_id', document.getElementById('up-client').value);
    fd.append('amount', document.getElementById('up-amount').value);
    fd.append('date', document.getElementById('up-date').value);
    fd.append('method', document.getElementById('up-method').value);
    fd.append('note', document.getElementById('up-note').value);
    if (this.pendingFile) fd.append('file', this.pendingFile);

    const action = isEdit ? ('update_receipt&id=' + this.editingId) : 'receipts';
    const r = await this.request(action, { method: 'POST', form: true, body: fd });

    btn.disabled = false;
    btn.innerHTML = original;

    if (r.success) {
      this.closeModal('modal-upload');
      this.toast(isEdit ? 'Comprobante actualizado' : 'Comprobante guardado', 'ok');
      this.editingId = null;
      this.pendingFile = null;
      await this.loadAll();
      if (this.currentClient) this.renderReceipts();
    } else {
      this.toast(r.message || 'No se pudo guardar', 'err');
    }
  },

  /* ---------------- reportes ---------------- */
  renderReportSummary() {
    const sel = document.getElementById('report-client');
    if (!sel) return;
    const id = Number(sel.value || 0);
    const start = document.getElementById('report-start').value;
    const end = document.getElementById('report-end').value;
    let list = this.receipts;
    if (id) list = list.filter(r => Number(r.client_id) === id);
    if (start) list = list.filter(r => r.receipt_date >= start);
    if (end) list = list.filter(r => r.receipt_date <= end);
    const total = list.reduce((s, x) => s + Number(x.amount || 0), 0);
    const name = id ? (this.clients.find(c => Number(c.id) === id) || {}).name : 'Todos los clientes';
    document.getElementById('report-summary').innerHTML =
      `${this.esc(name || '')}<strong>${this.money(total)}</strong>${list.length} comprobantes en el periodo`;
  },

  downloadReport(type) {
    const id = document.getElementById('report-client').value || '0';
    const start = document.getElementById('report-start').value;
    const end = document.getElementById('report-end').value;
    let url = API + '?action=report_' + type + '&client_id=' + id + '&token=' + encodeURIComponent(this.token);
    if (start) url += '&start=' + start;
    if (end) url += '&end=' + end;
    window.location.href = url;
    this.toast('Generando ' + (type === 'pdf' ? 'PDF' : 'Excel') + '...', 'ok');
  }
};

window.App = App;
document.addEventListener('DOMContentLoaded', () => App.init());