const API = 'https://lonmar.lat/api'; // <-- CAMBIA esto por tu URL real de Hostinger

let monthlyChart = null;

const app = {
  token: localStorage.getItem('token') || '',
  user: JSON.parse(localStorage.getItem('user') || 'null'),

  init() {
    if (this.token && this.user) {
      this.showView('view-app');
      this.loadDashboard();
    } else {
      this.showView('view-login');
    }

    document.getElementById('form-login').addEventListener('submit', (e) => {
      e.preventDefault();
      this.login(document.getElementById('login-email').value, document.getElementById('login-password').value);
    });

    document.getElementById('form-register').addEventListener('submit', (e) => {
      e.preventDefault();
      this.register(
        document.getElementById('reg-name').value,
        document.getElementById('reg-email').value,
        document.getElementById('reg-phone').value,
        document.getElementById('reg-password').value
      );
    });

    document.getElementById('form-receipt').addEventListener('submit', (e) => {
      e.preventDefault();
      this.saveReceipt();
    });

    document.getElementById('form-client').addEventListener('submit', (e) => {
      e.preventDefault();
      this.saveClient();
    });
  },

  async api(action, method = 'GET', body = null, isForm = false) {
    const headers = {};
    if (!isForm && body) headers['Content-Type'] = 'application/json';
    if (this.token) headers['Authorization'] = 'Bearer ' + this.token;
    const url = API + '?action=' + action;
    try {
      const res = await fetch(url, { method, headers, body: isForm ? body : (body ? JSON.stringify(body) : null) });
      return await res.json();
    } catch (e) {
      alert('Error de conexión: ' + e.message);
      return { success: false };
    }
  },

  async login(email, password) {
    const r = await this.api('login', 'POST', { email, password });
    if (r.success) {
      this.token = r.token;
      this.user = r.user;
      localStorage.setItem('token', r.token);
      localStorage.setItem('user', JSON.stringify(r.user));
      this.showView('view-app');
      this.loadDashboard();
    } else {
      alert(r.message || 'Error al iniciar sesión');
    }
  },

  async register(name, email, phone, password) {
    const r = await this.api('register', 'POST', { name, email, phone, password });
    alert(r.message || 'Cuenta creada');
    if (r.success) this.showView('view-login');
  },

  logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    location.reload();
  },

  showView(id) {
    document.querySelectorAll('[id^="view-"]').forEach(el => el.classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
  },

  showTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('[id$="-content"]').forEach(el => el.classList.add('hidden'));
    document.getElementById('tab-' + tab).classList.add('active');
    const content = document.getElementById(tab + '-content');
    if (content) content.classList.remove('hidden');
    if (tab === 'dashboard') this.loadDashboard();
    if (tab === 'clients') { this.loadClients(); this.loadClientSelect('report-client'); }
    if (tab === 'upload') this.loadClientSelect('receipt-client');
    if (tab === 'receipts-list') this.loadReceiptsList();
  },

  money(n) {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(n);
  },

  async loadDashboard() {
    const r = await this.api('receipts');
    const list = r.receipts || [];
    const total = list.reduce((s, x) => s + parseFloat(x.amount || 0), 0);
    document.getElementById('stat-total').textContent = this.money(total);
    document.getElementById('stat-count').textContent = list.length;
    document.getElementById('chip-total').innerHTML = '<i class="fas fa-coins mr-1"></i> ' + this.money(total);
    document.getElementById('user-greeting').textContent = 'Bienvenido, ' + (this.user.name || '');
    document.getElementById('app-title').textContent = 'AppContable';

    // Por mes
    const byMonth = {};
    list.forEach(x => {
      const m = (x.receipt_date || '').slice(0, 7);
      byMonth[m] = (byMonth[m] || 0) + parseFloat(x.amount || 0);
    });
    const labels = Object.keys(byMonth).sort();
    const data = labels.map(l => byMonth[l]);

    const ctx = document.getElementById('chartMonthly');
    if (monthlyChart) monthlyChart.destroy();
    monthlyChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels.map(l => l.replace('2026-', '')),
        datasets: [{
          label: 'Recibidos por mes (COP)',
          data: data,
          backgroundColor: 'rgba(79, 70, 229, 0.7)',
          borderColor: '#4f46e5',
          borderWidth: 1,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false }, title: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => '$' + v.toLocaleString('es-CO') } } }
      }
    });

    const recent = list.slice(0, 6);
    const html = recent.map(x => `
      <div class="receipt-row">
        <div class="receipt-info">
          <div class="client">${x.client_name || 'Sin cliente'}</div>
          <div class="meta">${x.receipt_date} • ${x.method}</div>
        </div>
        <div class="receipt-amount">${parseFloat(x.amount).toLocaleString('es-CO')} <small>COP</small></div>
      </div>
    `).join('') || '<div class="empty-state"><i class="fas fa-inbox"></i><h3>Sin comprobantes</h3><p>Empieza subiendo tu primer comprobante.</p></div>';
    document.getElementById('list-recent').innerHTML = html;
  },

  async loadClients() {
    const r = await this.api('clients');
    const list = r.clients || [];
    const html = list.map(c => `
      <div class="receipt-row" style="padding:1rem 0;">
        <div class="receipt-info">
          <div class="client">${c.name}</div>
          <div class="meta">${c.phone || 'Sin teléfono'} • ID ${c.id}</div>
        </div>
      </div>
    `).join('') || '<div class="empty-state"><i class="fas fa-users"></i><h3>Sin clientes</h3><p>Agrega tu primer cliente para organizar los recibos.</p></div>';
    document.getElementById('list-clients').innerHTML = html;
  },

  async loadClientSelect(selectId) {
    const r = await this.api('clients');
    const list = r.clients || [];
    const sel = document.getElementById(selectId);
    sel.innerHTML = '<option value="">Seleccionar cliente</option>' + list.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
  },

  async loadReceiptsList() {
    const r = await this.api('receipts');
    const list = r.receipts || [];
    const html = list.map(x => `
      <div class="receipt-row" style="padding:1rem 0;">
        <div class="receipt-info">
          <div class="client">${x.client_name || 'Sin cliente'}</div>
          <div class="meta">${x.receipt_date} • ${x.method}</div>
        </div>
        <div class="receipt-amount">${parseFloat(x.amount).toLocaleString('es-CO')} <small>COP</small></div>
      </div>
    `).join('') || '<div class="empty-state"><i class="fas fa-inbox"></i><h3>Sin comprobantes</h3></div>';
    document.getElementById('list-receipts').innerHTML = html;
  },

  openClientModal() {
    document.getElementById('modal-client').classList.remove('hidden');
  },
  closeClientModal() {
    document.getElementById('modal-client').classList.add('hidden');
  },

  async saveClient() {
    const name = document.getElementById('client-name').value.trim();
    const phone = document.getElementById('client-phone').value.trim();
    if (!name) return alert('Ingresa el nombre');
    const r = await this.api('clients', 'POST', { name, phone });
    if (r.success) {
      this.closeClientModal();
      document.getElementById('client-name').value = '';
      document.getElementById('client-phone').value = '';
      this.loadClients();
      this.loadClientSelect('receipt-client');
      this.loadClientSelect('report-client');
    } else alert(r.message || 'Error al guardar cliente');
  },

  async saveReceipt() {
    const form = document.getElementById('form-receipt');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const fd = new FormData(form);
    const r = await this.api('receipts', 'POST', fd, true);
    if (r.success) {
      form.reset();
      document.getElementById('receipt-date').value = new Date().toISOString().split('T')[0];
      this.loadDashboard();
      this.showTab('dashboard');
    } else alert(r.message || 'Error al guardar comprobante');
  },

  downloadReport(type) {
    const sel = document.getElementById('report-client');
    const clientId = sel.value || '0';
    window.location.href = API + '?action=report_' + type + '&client_id=' + clientId;
  }
};

function showView(id) { app.showView(id); }

app.init();
