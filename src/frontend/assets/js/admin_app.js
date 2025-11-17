// Lightweight admin app script
(function(){
  const API_BASE = (typeof API_BASE_URL !== 'undefined') ? API_BASE_URL : '../../backend/api/';

  const AUTH_ME = [API_BASE + 'auth.php?action=me', API_BASE + 'users.php?action=me'];
  const AUTH_LOGIN = [API_BASE + 'login.php', API_BASE + 'auth.php'];

  const state = { user: null };

  // API helper
  async function apiFetch(path, opts = {}){
    const url = path.startsWith('http') ? path : (path.startsWith('/') ? path : API_BASE + path);
    const options = Object.assign({credentials:'include'}, opts);
    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      options.headers = Object.assign({ 'Content-Type':'application/json' }, options.headers || {});
      options.body = JSON.stringify(options.body);
    }
    const res = await fetch(url, options);
    let data;
    const ct = res.headers.get('content-type') || '';
    if (ct.includes('application/json')) data = await res.json(); else data = await res.text();
    return { ok: res.ok, status: res.status, data };
  }

  // Auth
  async function checkAuth(){
    for(const url of AUTH_ME){
      try{
        const r = await apiFetch(url);
        if (!r.ok) continue;
        const d = r.data;
        if (d && (d.success || d.user || d.data || d.id)){
          state.user = d.user || d.data || d;
          renderUserBox();
          return true;
        }
      }catch(e){/*ignore*/}
    }
    state.user = null; renderUserBox(); return false;
  }

  function renderUserBox(){
    const el = document.getElementById('userBox'); if(!el) return;
    if(state.user && (state.user.full_name || state.user.name || state.user.email)){
      el.innerHTML = `<span class="name">${escapeHtml(state.user.full_name || state.user.name || state.user.email)}</span> <button id="btnLogout" class="btn small">Sign out</button>`;
      document.getElementById('btnLogout').addEventListener('click', performLogout);
    } else {
      el.innerHTML = `Not signed in <button id="btnLogin" class="btn small">Sign in</button>`;
      document.getElementById('btnLogin').addEventListener('click', showLoginModal);
    }
  }

  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  // Login modal
  function showLoginModal(){
    if(document.getElementById('adminLoginModal')) return;
    const root = document.getElementById('authModalRoot') || document.body;
    const html = `
      <div class="modal-backdrop" id="adminLoginModal">
        <div class="modal-card">
          <h3>Sign in</h3>
          <div>
            <form id="adminLoginForm">
              <div style="display:flex;flex-direction:column;gap:8px">
                <input name="username" placeholder="Username or email" required />
                <input name="password" type="password" placeholder="Password" required />
                <div class="modal-actions"><button type="submit" class="btn">Sign in</button><button type="button" id="cancelLogin" class="btn">Cancel</button></div>
                <div id="adminLoginError" style="color:#c0392b"></div>
              </div>
            </form>
          </div>
        </div>
      </div>`;
    root.insertAdjacentHTML('beforeend', html);
    document.getElementById('cancelLogin').addEventListener('click', ()=>document.getElementById('adminLoginModal')?.remove());
    document.getElementById('adminLoginForm').addEventListener('submit', async function(e){
      e.preventDefault();
      const fd = new FormData(this);
      await performLogin(fd);
    });
  }

  async function performLogin(formData){
    const errEl = document.getElementById('adminLoginError'); if(errEl) errEl.textContent='';
    for(const ep of AUTH_LOGIN){
      try{
        const res = await fetch(ep + (ep.includes('?')? '&action=login' : '?action=login'), { method:'POST', credentials:'include', body: formData });
        if(!res.ok) continue;
        const d = await res.json();
        if (d && (d.success || d.logged_in)){
          state.user = d.user || d.data || d;
          document.getElementById('adminLoginModal')?.remove();
          renderUserBox();
          loadOverviewCounts();
          return true;
        }
        if (d && d.message) { if(errEl) errEl.textContent = d.message; }
      }catch(e){ if(errEl) errEl.textContent = 'Login error: '+e.message }
    }
    if(errEl) errEl.textContent = errEl.textContent || 'Login failed';
    return false;
  }

  async function performLogout(){
    try{ await apiFetch(API_BASE + 'logout.php', { method:'POST' }); }catch(e){}
    state.user = null; renderUserBox();
    // Redirect to main login page after logout
    window.location.replace('/Phamarcy/src/frontend/index.html');
  }

  // Overview
  async function loadOverviewCounts(){
    const med = await count('medicines.php');
    const cust = await count('customers.php');
    const users = await count('users.php');
    const medEl = document.getElementById('medCount'); if (medEl) medEl.textContent = med;
    const custEl = document.getElementById('custCount'); if (custEl) custEl.textContent = cust;
    const userEl = document.getElementById('userCount'); if (userEl) userEl.textContent = users;
  }

  async function count(path){
    try{
      const r = await apiFetch(path);
      if (!r.ok) return '—';
      const d = r.data; if(Array.isArray(d)) return d.length; if(d && d.data && Array.isArray(d.data)) return d.data.length; return d.total || d.count || '—';
    }catch(e){ return '—'; }
  }

  // Ensure Chart.js is available by loading a local loader script that prefers a local bundle
  async function ensureChartAvailable(){
    if (typeof window.Chart !== 'undefined') return true;
    // Try to load a local loader script (which will attempt local chart.min.js first)
    try{
      await new Promise((resolve, reject)=>{
        const s = document.createElement('script');
        s.src = (typeof API_BASE_URL !== 'undefined') ? (API_BASE_URL.replace(/\/src\/backend\/api\/$/, '') + 'src/frontend/assets/vendor/chart.local.js') : '../assets/vendor/chart.local.js';
        s.onload = resolve; s.onerror = reject;
        document.head.appendChild(s);
      });
      // wait a short while for Chart to be available
      for(let i=0;i<40;i++){ if(window.Chart) return true; await new Promise(r=>setTimeout(r,50)); }
      return !!window.Chart;
    }catch(e){
      // final fallback: try CDN directly
      try{
        await new Promise((resolve, reject)=>{
          const s = document.createElement('script');
          s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
          s.onload = resolve; s.onerror = reject;
          document.head.appendChild(s);
        });
        return !!window.Chart;
      }catch(_){ return false; }
    }
  }

  // Simple list renderer for medicines/customers/users
  async function renderList(entity){
    const overview = document.getElementById('overview');
    const lv = document.getElementById('listView');
    if(overview) overview.classList.add('hidden');
    if(lv) {
      lv.classList.remove('hidden');
      lv.style.display = 'block'; // Ensure display override
    }
    const titleEl = document.getElementById('listTitle');
    if(titleEl) titleEl.textContent = entity.charAt(0).toUpperCase()+entity.slice(1);
    // If viewing customers in admin, add a shortcut to the existing customers page (reuse frontend page)
    try{
      const header = document.querySelector('.list-header');
      if (header && entity === 'customers'){
        if (!document.getElementById('btnAddCustomerAdmin')){
          const btn = document.createElement('button');
          btn.id = 'btnAddCustomerAdmin'; btn.className = 'btn btn-primary'; btn.style.marginLeft = '8px'; btn.textContent = 'Add Customer';
          btn.addEventListener('click', ()=>{ window.location.href = '../customers_modern.html'; });
          // append to header right side
          header.appendChild(btn);
        }
      } else {
        const existing = document.getElementById('btnAddCustomerAdmin'); if(existing) existing.remove();
      }
    }catch(e){/* ignore */}
    const dataRes = await apiFetch(entity + '.php');
    const data = (dataRes.ok && Array.isArray(dataRes.data)) ? dataRes.data : (dataRes.ok && dataRes.data && Array.isArray(dataRes.data.data) ? dataRes.data.data : []);
    const container = document.getElementById('listContainer');
    if(!data.length) { container.innerHTML = '<div class="responsive"><table class="table"><tr><td>No records</td></tr></table></div>'; return }
    const keys = Object.keys(data[0]).slice(0,6);
    const head = '<tr>' + keys.map(k=>`<th>${escapeHtml(k)}</th>`).join('') + '<th>Actions</th></tr>';
    const rows = data.map(row=>'<tr>' + keys.map(k=>`<td>${escapeHtml(row[k]||'')}</td>`).join('') + `<td><button class="btn small">Edit</button></td></tr>`).join('');
    container.innerHTML = `<div class="responsive"><table class="table"><thead>${head}</thead><tbody>${rows}</tbody></table></div>`;
    // simple search
    const search = document.getElementById('listSearch'); search.value = '';
    search.oninput = ()=>{
      const q = search.value.trim().toLowerCase();
      const filtered = data.filter(r=> JSON.stringify(r).toLowerCase().includes(q));
      const rows2 = filtered.map(row=>'<tr>' + keys.map(k=>`<td>${escapeHtml(row[k]||'')}</td>`).join('') + `<td><button class="btn small">Edit</button></td></tr>`).join('');
      container.querySelector('tbody').innerHTML = rows2 || '<tr><td>No records</td></tr>';
    };
  }

  // Routing

  // Reports renderer - inject Chart.js if needed and draw charts from available report endpoints
  async function renderReports(){
    const overview = document.getElementById('overview');
    const lv = document.getElementById('listView');
    if(overview) overview.classList.add('hidden');
    if(lv) {
      lv.classList.remove('hidden');
      lv.style.display = 'block';
    }
    const titleEl = document.getElementById('listTitle');
    if(titleEl) titleEl.textContent = 'Reports';
    const container = document.getElementById('listContainer');
    container.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px">
        <div style="display:flex;gap:8px;align-items:center">
          <label>From <input id="reportFrom" type="date" style="padding:6px;border-radius:6px;border:1px solid #e6e9ef"/></label>
          <label>To <input id="reportTo" type="date" style="padding:6px;border-radius:6px;border:1px solid #e6e9ef"/></label>
          <button id="reportRefresh" class="btn small">Refresh</button>
        </div>
        <div>
          <button id="reportExport" class="btn small">Export CSV</button>
        </div>
      </div>
      <div class="report-charts">
        <div class="card chart-card"><h4>Sales (last 30)</h4><canvas id="chartSales" class="chart-canvas"></canvas></div>
        <div class="card chart-card"><h4>Inventory Status</h4><canvas id="chartInventory" class="chart-canvas"></canvas></div>
      </div>`;

    const ok = await ensureChartAvailable();
    if (!ok){ container.insertAdjacentHTML('beforeend','<div style="color:#c0392b;margin-top:12px">Unable to load Chart library. Check assets/vendor/chart.min.js or network.</div>'); return; }

    // Helper to safely parse API shapes
    async function fetchJsonSafe(path){
      try{ const r = await apiFetch(path); if (!r.ok) return null; return r.data; }catch(e){ return null; }
    }

    // Sales: try common endpoints
    let salesData = await fetchJsonSafe('reports/sales.php') || await fetchJsonSafe('sales.php') || await fetchJsonSafe('orders.php');
    // Inventory: try common endpoints
    let invData = await fetchJsonSafe('reports/inventory.php') || await fetchJsonSafe('medicines.php');

    // Normalizer helpers
    function toArray(d){ if(!d) return []; if(Array.isArray(d)) return d; if(Array.isArray(d.data)) return d.data; return []; }
    function filterByDate(arr, startMs, endMs){ if(!startMs && !endMs) return arr; return arr.filter(item=>{ const raw = item.date || item.d || item.day || item.created_at || item.date_time || item.timestamp || item.dt || item.label; if(!raw) return true; const t = Date.parse(raw); if(isNaN(t)) return true; if(startMs && t < startMs) return false; if(endMs && t > endMs) return false; return true; }); }

    // Build & draw charts (common function)
    function drawSalesChart(filteredSales){
      const labels = []; const values = [];
      filteredSales.slice(-30).forEach(item=>{ labels.push(item.label || item.date || item.day || ''); values.push(Number(item.total || item.value || item.amount || item.t || 0)); });
      const ctx = document.getElementById('chartSales').getContext('2d');
      if (window._salesChart) window._salesChart.destroy();
      window._salesChart = new Chart(ctx, { type: 'line', data: { labels, datasets: [{ label: 'Sales', data: values, borderColor:'#4f46e5', backgroundColor: 'rgba(79,70,229,0.08)', tension:0.2, fill:true }] }, options:{ responsive:true, plugins:{ legend:{display:false} }, scales:{ x:{ grid:{display:false} }, y:{ beginAtZero:true } } } });
    }

    function drawInvChart(filteredInv){
      const labels = []; const values = [];
      const arr = filteredInv.slice().sort((a,b)=>(Number(a.stock||a.qty||a.quantity||0) - Number(b.stock||b.qty||b.quantity||0))).slice(0,8);
      arr.forEach(i=>{ labels.push(i.name || i.medicine || i.title || ''); values.push(Number(i.stock || i.qty || i.quantity || 0)); });
      const ctx2 = document.getElementById('chartInventory').getContext('2d');
      if (window._invChart) window._invChart.destroy();
      window._invChart = new Chart(ctx2, { type: 'bar', data: { labels, datasets: [{ label: 'Count', data: values, backgroundColor:'#06b6d4' }] }, options:{ responsive:true, plugins:{ legend:{display:false} }, scales:{ x:{ ticks:{maxRotation:30,minRotation:10} }, y:{ beginAtZero:true } } } });
    }

    function exportCSV(filename, rows){
      if(!rows || !rows.length) return;
      const keys = Object.keys(rows[0]);
      const lines = [keys.join(',')];
      rows.forEach(r=>{ lines.push(keys.map(k=>`"${String(r[k]||'').replace(/"/g,'""')}"`).join(',')); });
      const blob = new Blob([lines.join('\n')], { type:'text/csv' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a'); a.href = url; a.download = filename; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    }

    // initial draw
    let salesArr = toArray(salesData); let invArr = toArray(invData);
    const fromEl = document.getElementById('reportFrom'); const toEl = document.getElementById('reportTo');
    function refreshCharts(){
      const startMs = fromEl.value ? Date.parse(fromEl.value) : null;
      const endMs = toEl.value ? (Date.parse(toEl.value) + 24*60*60*1000 - 1) : null;
      const fs = filterByDate(salesArr, startMs, endMs);
      const fi = filterByDate(invArr, startMs, endMs);
      drawSalesChart(fs);
      drawInvChart(fi);
    }
    refreshCharts();

    document.getElementById('reportRefresh').addEventListener('click', ()=>{
      // attempt to reload remote data and redraw
      (async ()=>{ salesData = await fetchJsonSafe('reports/sales.php') || await fetchJsonSafe('sales.php') || await fetchJsonSafe('orders.php'); invData = await fetchJsonSafe('reports/inventory.php') || await fetchJsonSafe('medicines.php'); salesArr = toArray(salesData); invArr = toArray(invData); refreshCharts(); })();
    });

    document.getElementById('reportExport').addEventListener('click', ()=>{
      // export both datasets as two CSVs in sequence
      if(salesArr && salesArr.length) exportCSV('sales_export.csv', salesArr);
      if(invArr && invArr.length) exportCSV('inventory_export.csv', invArr);
    });
  }

  // Medicines CRUD renderer - COPIED FROM PHARMACIST DASHBOARD
  async function renderMedicines(){
    const overview = document.getElementById('overview');
    const lv = document.getElementById('listView');
    if(overview) overview.classList.add('hidden');
    if(lv) {
      lv.classList.remove('hidden');
      lv.style.display = 'block';
    }
    const titleEl = document.getElementById('listTitle');
    if(titleEl) titleEl.textContent = 'Medicine Management';
    const container = document.getElementById('listContainer');

    // Render full pharmacist-style medicine management UI
    container.innerHTML = `
      <div style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center">
        <button class="btn btn-primary" onclick="adminAddNewMedicine()">
          <i class="fas fa-plus"></i> Add Medicine
        </button>
        <button class="btn btn-secondary" onclick="adminViewInventory()">
          <i class="fas fa-boxes"></i> View Full Inventory
        </button>
      </div>

      <!-- Inventory Table -->
      <div class="inventory-table-container" style="overflow-x:auto;border:1px solid #e9ecef;border-radius:8px">
        <table class="inventory-table" style="width:100%;border-collapse:collapse;font-size:14px">
          <thead>
            <tr style="background:#f8f9fa">
              <th style="padding:12px;text-align:left">Medicine Name</th>
              <th style="padding:12px;text-align:left">Category</th>
              <th style="padding:12px;text-align:left">Manufacturer</th>
              <th style="padding:12px;text-align:left">Quantity</th>
              <th style="padding:12px;text-align:left">Unit Price</th>
              <th style="padding:12px;text-align:left">Expiry Date</th>
              <th style="padding:12px;text-align:left">Status</th>
              <th style="padding:12px;text-align:left">Actions</th>
            </tr>
          </thead>
          <tbody id="adminInventoryTableBody">
            <tr><td colspan="8" style="padding:20px;text-align:center">Loading medicines...</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Add/Edit Medicine Modal -->
      <div id="adminMedicineModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center">
        <div style="background:white;border-radius:15px;width:90%;max-width:600px;max-height:90vh;overflow-y:auto">
          <div style="background:linear-gradient(135deg,#27ae60,#2ecc71);color:white;padding:20px;border-radius:15px 15px 0 0;display:flex;justify-content:space-between;align-items:center">
            <h2 id="adminModalTitle" style="margin:0;font-size:1.4rem"><i class="fas fa-pills"></i> Add New Medicine</h2>
            <span onclick="adminCloseModal()" style="cursor:pointer;font-size:28px;font-weight:bold">&times;</span>
          </div>
          <div style="padding:25px">
            <form id="adminMedicineForm">
              <input type="hidden" id="adminEditId" name="edit_id" />
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
                <div style="display:flex;flex-direction:column">
                  <label style="margin-bottom:8px;font-weight:500">Medicine Name *</label>
                  <input type="text" name="name" required placeholder="Enter medicine name" style="padding:12px;border:2px solid #e9ecef;border-radius:8px" />
                </div>
                <div style="display:flex;flex-direction:column">
                  <label style="margin-bottom:8px;font-weight:500">Generic Name</label>
                  <input type="text" name="generic_name" placeholder="Enter generic name" style="padding:12px;border:2px solid #e9ecef;border-radius:8px" />
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
                <div style="display:flex;flex-direction:column">
                  <label style="margin-bottom:8px;font-weight:500">Category *</label>
                  <select name="category" required style="padding:12px;border:2px solid #e9ecef;border-radius:8px">
                    <option value="">Select Category</option>
                    <option value="tablet">Tablet</option>
                    <option value="capsule">Capsule</option>
                    <option value="syrup">Syrup</option>
                    <option value="injection">Injection</option>
                    <option value="cream">Cream/Ointment</option>
                    <option value="drops">Drops</option>
                  </select>
                </div>
                <div style="display:flex;flex-direction:column">
                  <label style="margin-bottom:8px;font-weight:500">Manufacturer</label>
                  <input type="text" name="manufacturer" placeholder="Enter manufacturer" style="padding:12px;border:2px solid #e9ecef;border-radius:8px" />
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
                <div style="display:flex;flex-direction:column">
                  <label style="margin-bottom:8px;font-weight:500">Initial Quantity *</label>
                  <input type="number" name="stock_quantity" required min="1" placeholder="Enter quantity" style="padding:12px;border:2px solid #e9ecef;border-radius:8px" />
                </div>
                <div style="display:flex;flex-direction:column">
                  <label style="margin-bottom:8px;font-weight:500">Unit Price *</label>
                  <input type="number" name="unit_price" required step="0.01" min="0" placeholder="Enter price" style="padding:12px;border:2px solid #e9ecef;border-radius:8px" />
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
                <div style="display:flex;flex-direction:column">
                  <label style="margin-bottom:8px;font-weight:500">Reorder Level</label>
                  <input type="number" name="reorder_level" min="1" placeholder="Minimum stock level" style="padding:12px;border:2px solid #e9ecef;border-radius:8px" />
                </div>
                <div style="display:flex;flex-direction:column">
                  <label style="margin-bottom:8px;font-weight:500">Expiry Date</label>
                  <input type="date" name="expiry_date" style="padding:12px;border:2px solid #e9ecef;border-radius:8px" />
                </div>
              </div>
              <div style="display:flex;flex-direction:column;margin-bottom:20px">
                <label style="margin-bottom:8px;font-weight:500">Description/Notes</label>
                <textarea name="description" placeholder="Additional notes" style="padding:12px;border:2px solid #e9ecef;border-radius:8px;min-height:80px;resize:vertical"></textarea>
              </div>
              <div style="display:flex;gap:15px;justify-content:flex-end;margin-top:25px;padding-top:20px;border-top:1px solid #e9ecef">
                <button type="button" class="btn btn-secondary" onclick="adminCloseModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Medicine</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;

    // Load and display medicine data
    await adminLoadInventoryData();
  }

  // Admin medicine management functions (copied from pharmacist)
  async function adminLoadInventoryData() {
    const tableBody = document.getElementById('adminInventoryTableBody');
    if (!tableBody) return;
    
    tableBody.innerHTML = '<tr><td colspan="8" style="padding:20px;text-align:center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
    
    try {
      const apiPaths = [
        API_BASE + 'medicines_working.php',
        API_BASE + 'medicines.php'
      ];
      
      let response = null;
      for (const apiPath of apiPaths) {
        try {
          response = await fetch(apiPath, { credentials: 'include' });
          if (response.ok) {
            console.log('Medicines loaded from:', apiPath);
            break;
          }
        } catch (error) {
          console.log('Failed path:', apiPath, error.message);
          continue;
        }
      }
      
      if (!response || !response.ok) {
        throw new Error('All medicine API endpoints failed');
      }
      
      const data = await response.json();
      console.log('Medicine API response:', data);
      
      if (data.success && data.data) {
        adminDisplayInventoryData(data.data);
      } else if (Array.isArray(data)) {
        adminDisplayInventoryData(data);
      } else {
        throw new Error('Invalid API response format');
      }
    } catch (error) {
      console.error('Error loading medicines:', error);
      tableBody.innerHTML = '<tr><td colspan="8" style="padding:20px;text-align:center;color:#e74c3c">Error loading medicines. Please refresh.</td></tr>';
    }
  }

  function adminDisplayInventoryData(medicines) {
    const tableBody = document.getElementById('adminInventoryTableBody');
    if (!tableBody) return;
    
    if (!medicines || !medicines.length) {
      tableBody.innerHTML = '<tr><td colspan="8" style="padding:20px;text-align:center">No medicines found. Click "Add Medicine" to get started.</td></tr>';
      return;
    }
    
    tableBody.innerHTML = '';
    medicines.forEach(medicine => {
      const row = adminCreateInventoryRow(medicine);
      tableBody.appendChild(row);
    });
  }

  function adminCreateInventoryRow(medicine) {
    const row = document.createElement('tr');
    row.style.borderBottom = '1px solid #e9ecef';
    
    const quantity = medicine.stock_quantity || medicine.quantity || 0;
    const price = medicine.unit_price || medicine.price || 0;
    const reorderLevel = medicine.reorder_level || 10;
    
    let status = 'normal';
    let statusText = 'Normal';
    let statusColor = '#d4edda';
    let statusTextColor = '#155724';
    
    if (quantity <= reorderLevel) {
      status = quantity <= 5 ? 'critical' : 'low';
      statusText = quantity <= 5 ? 'Critical' : 'Low Stock';
      statusColor = quantity <= 5 ? '#f8d7da' : '#fff3cd';
      statusTextColor = quantity <= 5 ? '#721c24' : '#856404';
    }
    
    if (medicine.expiry_date) {
      const expiryDate = new Date(medicine.expiry_date);
      const today = new Date();
      const daysDiff = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
      
      if (daysDiff <= 30 && daysDiff >= 0) {
        status = 'expiring';
        statusText = `Expires in ${daysDiff} days`;
        statusColor = '#ffeaa7';
        statusTextColor = '#d63031';
      }
    }
    
    row.innerHTML = `
      <td style="padding:12px"><strong>${escapeHtml(medicine.name)}</strong></td>
      <td style="padding:12px">${escapeHtml(medicine.category || 'N/A')}</td>
      <td style="padding:12px">${escapeHtml(medicine.manufacturer || 'N/A')}</td>
      <td style="padding:12px">${quantity} pcs</td>
      <td style="padding:12px">KSh ${parseFloat(price).toFixed(2)}</td>
      <td style="padding:12px">${medicine.expiry_date || 'N/A'}</td>
      <td style="padding:12px"><span style="padding:4px 8px;border-radius:12px;font-size:12px;font-weight:500;background:${statusColor};color:${statusTextColor}">${statusText}</span></td>
      <td style="padding:12px">
        <button class="btn btn-sm" onclick="adminEditMedicine(${medicine.id})" style="background:#3498db;color:white;margin-right:5px" title="Edit">
          <i class="fas fa-edit"></i>
        </button>
        <button class="btn btn-sm" onclick="adminDeleteMedicine(${medicine.id})" style="background:#e74c3c;color:white" title="Delete">
          <i class="fas fa-trash"></i>
        </button>
      </td>
    `;
    
    return row;
  }

  window.adminAddNewMedicine = function() {
    const modal = document.getElementById('adminMedicineModal');
    const form = document.getElementById('adminMedicineForm');
    const titleEl = document.getElementById('adminModalTitle');
    
    if (form) form.reset();
    document.getElementById('adminEditId').value = '';
    if (titleEl) titleEl.innerHTML = '<i class="fas fa-pills"></i> Add New Medicine';
    if (modal) modal.style.display = 'flex';
  };

  window.adminViewInventory = function() {
    renderMedicines();
  };

  window.adminCloseModal = function() {
    const modal = document.getElementById('adminMedicineModal');
    if (modal) modal.style.display = 'none';
  };

  window.adminEditMedicine = async function(id) {
    try {
      const response = await apiFetch('medicines.php');
      const data = response.data;
      const medicines = Array.isArray(data) ? data : (data && data.data ? data.data : []);
      const medicine = medicines.find(m => String(m.id) === String(id));
      
      if (!medicine) {
        alert('Medicine not found');
        return;
      }
      
      const form = document.getElementById('adminMedicineForm');
      const titleEl = document.getElementById('adminModalTitle');
      
      document.getElementById('adminEditId').value = medicine.id;
      form.elements['name'].value = medicine.name || '';
      form.elements['generic_name'].value = medicine.generic_name || '';
      form.elements['category'].value = medicine.category || '';
      form.elements['manufacturer'].value = medicine.manufacturer || '';
      form.elements['stock_quantity'].value = medicine.stock_quantity || medicine.quantity || 0;
      form.elements['unit_price'].value = medicine.unit_price || medicine.price || 0;
      form.elements['reorder_level'].value = medicine.reorder_level || 10;
      form.elements['expiry_date'].value = medicine.expiry_date || '';
      form.elements['description'].value = medicine.description || '';
      
      if (titleEl) titleEl.innerHTML = '<i class="fas fa-edit"></i> Edit Medicine';
      document.getElementById('adminMedicineModal').style.display = 'flex';
    } catch (error) {
      console.error('Error loading medicine for edit:', error);
      alert('Error loading medicine details');
    }
  };

  window.adminDeleteMedicine = async function(id) {
    if (!confirm('Are you sure you want to delete this medicine?')) return;
    
    try {
      const response = await apiFetch('medicines.php', {
        method: 'DELETE',
        body: { id: id }
      });
      
      if (response.ok && response.data && response.data.success) {
        if (window.showToast) showToast('Medicine deleted successfully', 'success');
        await adminLoadInventoryData();
        loadOverviewCounts();
      } else {
        alert('Error deleting medicine: ' + (response.data && response.data.message || 'Unknown error'));
      }
    } catch (error) {
      console.error('Error deleting medicine:', error);
      alert('Network error deleting medicine');
    }
  };

  // Attach form handler immediately when modal is rendered (not in DOMContentLoaded)
  function attachFormHandler() {
    const form = document.getElementById('adminMedicineForm');
    if (!form) {
      console.log('Form not found yet, will try again when modal opens');
      return;
    }
    
    // Remove existing listener if any
    const newForm = form.cloneNode(true);
    form.parentNode.replaceChild(newForm, form);
    
    newForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      const editId = document.getElementById('adminEditId').value;
      const isEditing = !!editId;
      
      const medicineData = {
        name: formData.get('name'),
        generic_name: formData.get('generic_name'),
        category: formData.get('category'),
        manufacturer: formData.get('manufacturer'),
        stock_quantity: parseInt(formData.get('stock_quantity')) || 0,
        unit_price: parseFloat(formData.get('unit_price')) || 0,
        reorder_level: parseInt(formData.get('reorder_level')) || 10,
        expiry_date: formData.get('expiry_date'),
        description: formData.get('description')
      };
      
      if (isEditing) {
        medicineData.id = editId;
      }
      
      console.log('💊 Saving medicine:', medicineData);
      
      try {
        const apiPaths = [
          API_BASE + 'medicines_working.php',
          API_BASE + 'medicines.php'
        ];
        
        let response = null;
        let lastError = null;
        
        for (const apiPath of apiPaths) {
          try {
            console.log(`🔄 Trying: ${apiPath}`);
            const fetchUrl = isEditing ? `${apiPath}?id=${editId}` : apiPath;
            const method = isEditing ? 'PUT' : 'POST';
            
            response = await fetch(fetchUrl, {
              method: method,
              credentials: 'include',
              headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: JSON.stringify(medicineData)
            });
            
            console.log(`📡 Response status: ${response.status} ${response.statusText}`);
            
            if (response.ok || response.status < 500) {
              console.log('✅ Success with:', apiPath);
              break;
            }
            
            lastError = `${response.status} ${response.statusText}`;
          } catch (pathError) {
            console.error('❌ Failed:', apiPath, pathError.message);
            lastError = pathError.message;
            continue;
          }
        }
        
        if (!response) {
          throw new Error('All API paths failed. Last error: ' + lastError);
        }
        
        const responseText = await response.text();
        console.log('📄 Raw API response:', responseText);
        
        let result;
        try {
          result = JSON.parse(responseText);
        } catch (parseError) {
          console.error('⚠️ Response is not JSON:', responseText.substring(0, 500));
          throw new Error(`API returned non-JSON response. Check backend error logs.`);
        }
        
        console.log('📦 Parsed result:', result);
        
        if (result.success) {
          const successMessage = isEditing ? '✅ Medicine updated successfully!' : '✅ Medicine added successfully!';
          console.log(successMessage);
          if (window.showToast) showToast(successMessage, 'success');
          else alert(successMessage);
          
          adminCloseModal();
          this.reset();
          document.getElementById('adminEditId').value = '';
          
          await adminLoadInventoryData();
          loadOverviewCounts();
        } else {
          const errorMsg = (isEditing ? 'Error updating: ' : 'Error adding: ') + (result.message || result.error || 'Unknown error');
          console.error('❌', errorMsg);
          alert(errorMsg);
        }
      } catch (error) {
        console.error('💥 Fatal error saving medicine:', error);
        alert(`Error: ${error.message}\n\n📋 Checklist:\n✓ XAMPP running?\n✓ Database accessible?\n✓ API files exist?\n\nCheck browser console for details.`);
      }
    });
    
    console.log('✅ Form submit handler attached');
  }
  
  // Override adminAddNewMedicine to attach handler when modal opens
  const originalAddMedicine = window.adminAddNewMedicine;
  window.adminAddNewMedicine = function() {
    if (originalAddMedicine) originalAddMedicine();
    
    // Wait for modal to render, then attach handler
    setTimeout(() => {
      attachFormHandler();
    }, 100);
  };
  
  // Override adminEditMedicine to attach handler when modal opens
  const originalEditMedicine = window.adminEditMedicine;
  window.adminEditMedicine = async function(id) {
    if (originalEditMedicine) await originalEditMedicine.call(this, id);
    
    // Wait for modal to render, then attach handler
    setTimeout(() => {
      attachFormHandler();
    }, 100);
  };
  function setActive(route){
  document.querySelectorAll('.sidebar nav li, .admin-sidebar nav li').forEach(li=>li.classList.remove('active'));
  const el = document.querySelector(`.sidebar nav li[data-route="${route}"], .admin-sidebar nav li[data-route="${route}"]`);
    if(el) el.classList.add('active');
    document.getElementById('pageTitle').textContent = route.charAt(0).toUpperCase()+route.slice(1);
  }

  // Wire nav
  document.addEventListener('DOMContentLoaded', async ()=>{
  document.querySelectorAll('.sidebar nav li, .admin-sidebar nav li').forEach(li=>{
      li.addEventListener('click', async ()=>{
        const route = li.dataset.route;
        // Require authentication for admin routes
        if (!state.user) { showLoginModal(); return; }
        setActive(route);
        
        // Hide all views first
        const overview = document.getElementById('overview');
        const listView = document.getElementById('listView');
        if(overview) overview.classList.add('hidden');
        if(listView) listView.classList.add('hidden');
        
        // Show the appropriate view
        if (route === 'overview'){
          if(overview) overview.classList.remove('hidden');
        } else if (route === 'logout'){
          renderLogoutPage();
        } else if (route === 'reports') {
          await renderReports();
        } else if (route === 'medicines') {
          await renderMedicines();
        } else {
          await renderList(route);
        }
      });
    });

    document.getElementById('toggleSidebar').addEventListener('click', ()=>{
      const sb = document.querySelector('.sidebar') || document.querySelector('.admin-sidebar');
      if(sb) sb.classList.toggle('collapsed');
    });

    // initial auth check and counts; if not authenticated, force login modal
    const authed = await checkAuth();
    if (!authed) {
      // hide content until login
      document.getElementById('overview').classList.add('hidden');
      document.getElementById('listView').classList.add('hidden');
      showLoginModal();
    } else {
      await loadOverviewCounts();
    }

    // Try to instantiate pharmacist dashboard helper so we get the exact medicine helpers
    try{
      if (typeof PharmacistDashboard !== 'undefined' && !window.pharmacistDashboard) {
        // Instantiate pharmacist helper for medicine modal support
        window.pharmacistDashboard = new PharmacistDashboard();
      }
    }catch(e){ console.warn('PharmacistDashboard not available', e); }
    
    // Define admin-safe quick actions AFTER pharmacist instantiation
    // These paths are correct for admin/index.html location
    window.quickAddMedicine = function(){ 
      if (window.pharmacistDashboard && typeof window.pharmacistDashboard.quickAddMedicine === 'function') { 
        window.pharmacistDashboard.quickAddMedicine(); 
      } else if (typeof showPharmacistAddMedicineModal === 'function') { 
        showPharmacistAddMedicineModal(); 
      } else { 
        alert('Add Medicine feature not available'); 
      } 
    };
    window.viewStockLevels = function(){ window.location.href = '../stock_management.html'; };
    window.openPointOfSale = function(){ window.location.href = '../sales_modern.html'; };
  });

  // Render a simple logout confirmation page in the main area
  function renderLogoutPage(){
    document.getElementById('overview').classList.add('hidden');
    const lv = document.getElementById('listView'); lv.classList.remove('hidden');
    document.getElementById('listTitle').textContent = 'Logout';
    const container = document.getElementById('listContainer');
    container.innerHTML = `<div style="padding:18px;background:var(--card);border-radius:8px;">`+
      `<p>You're signed in as <strong>${escapeHtml(state.user?.full_name || state.user?.name || state.user?.email || '')}</strong>.</p>`+
      `<p>Click the button below to sign out of the admin interface.</p>`+
      `<div style="margin-top:12px"><button id="confirmLogout" class="btn">Sign out</button></div>`+
      `</div>`;
    document.getElementById('confirmLogout').addEventListener('click', async ()=>{
      await performLogout();
      // after logout, force login modal so user must re-authenticate
      showLoginModal();
    });
  }

  // Expose small API for tests
  window.AdminApp = { renderList, checkAuth, loadOverviewCounts };

})();

// Toast helper (global)
(function(){
  if (window.showToast) return;
  const container = document.createElement('div'); container.id='toastContainer'; container.style.position='fixed'; container.style.right='20px'; container.style.bottom='20px'; container.style.zIndex='2000'; document.body.appendChild(container);
  window.showToast = function(msg, type='info', timeout=3500){
    const t = document.createElement('div'); t.className = 'toast toast-'+type; t.style.marginTop='8px'; t.style.padding='10px 14px'; t.style.borderRadius='8px'; t.style.color='#fff'; t.style.minWidth='160px'; t.style.boxShadow='0 6px 20px rgba(0,0,0,0.12)';
    if(type==='success') t.style.background='#10b981'; else if(type==='error') t.style.background='#ef4444'; else t.style.background='#4f46e5';
    t.textContent = msg; container.appendChild(t);
    setTimeout(()=>{ t.style.opacity='0'; t.style.transform='translateY(8px)'; setTimeout(()=>t.remove(),400); }, timeout);
  };
})();

// Pharmacist-style Add Medicine modal (UI copied from pharmacist dashboard but saved via API)
(function(){
  function createPharmacistModal(title, content, buttons){
    const modal = document.createElement('div'); modal.className='modal-overlay';
    modal.innerHTML = `
      <div class="modal-container">
        <div class="modal-header"><h3>${title}</h3><button class="modal-close" onclick="closePharmacistModal()">&times;</button></div>
        <div class="modal-body">${content}</div>
        <div class="modal-footer">${buttons.map(b=>`<button class="btn ${b.class}" id="phbtn_${b.text.replace(/\s+/g,'_')}">${b.text}</button>`).join('')}</div>
      </div>`;
    document.body.appendChild(modal);
    addPharmacistModalStyles();
    buttons.forEach(b=>{ const el = document.getElementById('phbtn_'+b.text.replace(/\s+/g,'_')); if(el) el.addEventListener('click', b.action); });
    return modal;
  }

  function closePharmacistModal(){ const m = document.querySelector('.modal-overlay'); if(m) m.remove(); }

  function addPharmacistModalStyles(){ if (document.getElementById('pharmacistModalStyles')) return; const styles = document.createElement('style'); styles.id='pharmacistModalStyles'; styles.textContent=`.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000}.modal-container{background:#fff;border-radius:12px;width:90%;max-width:600px;max-height:90vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3)}.modal-header{padding:20px 25px;border-bottom:2px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center}.modal-close{background:none;border:none;font-size:1.5rem;cursor:pointer;color:#666;padding:5px}.modal-body{padding:25px;max-height:60vh;overflow:auto}.modal-footer{padding:20px 25px;border-top:2px solid #f0f0f0;display:flex;gap:10px;justify-content:flex-end}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}`; document.head.appendChild(styles); }

  async function saveNewMedicineFromPharmacist(){
    const form = document.getElementById('quickAddMedicineForm'); if(!form) return;
    const fd = new FormData(form);
    const obj = { name: fd.get('name'), category: fd.get('category'), stock_quantity: Number(fd.get('stock')||0), unit_price: Number(fd.get('price')||0), reorder_level: Number(fd.get('reorderLevel')||0), expiry_date: fd.get('expiry') };
    try{
      const res = await apiFetch('medicines.php', { method: 'POST', body: obj });
      if (res.ok && res.data && res.data.success){ closePharmacistModal(); if(window.showToast) showToast((obj.name||'Medicine') + ' added', 'success'); if(typeof renderMedicines === 'function') renderMedicines(); if(typeof loadOverviewCounts === 'function') loadOverviewCounts(); } else { if(window.showToast) showToast('Save failed: '+(res.data && res.data.message||res.status),'error'); }
    }catch(e){ if(window.showToast) showToast('Save error: '+e.message,'error'); }
  }

  function showAddMedicinePharmacistModal(){
    const content = `
      <form id="quickAddMedicineForm" class="form-grid">
        <div class="form-group"><label>Medicine Name *</label><input type="text" name="name" required></div>
        <div class="form-group"><label>Category *</label><select name="category" required><option value="">Select Category</option><option value="Pain Relief">Pain Relief</option><option value="Antibiotics">Antibiotics</option><option value="Cardiovascular">Cardiovascular</option><option value="Gastro">Gastro</option><option value="Respiratory">Respiratory</option></select></div>
        <div class="form-group"><label>Stock Quantity *</label><input type="number" name="stock" required min="0"></div>
        <div class="form-group"><label>Price</label><input type="number" name="price" required min="0" step="0.01"></div>
        <div class="form-group"><label>Reorder Level</label><input type="number" name="reorderLevel" min="0"></div>
        <div class="form-group"><label>Expiry Date</label><input type="date" name="expiry"></div>
      </form>`;
    createPharmacistModal('Add New Medicine', content, [ { text:'Add Medicine', action: saveNewMedicineFromPharmacist, class: 'btn-primary' }, { text:'Cancel', action: closePharmacistModal, class: 'btn-secondary' } ]);
  }

  // expose to global scope so wrapper uses it
  window.showPharmacistAddMedicineModal = showAddMedicinePharmacistModal;
  window.closePharmacistModal = closePharmacistModal;
})();
