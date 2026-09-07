<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'

const router = useRouter()

const search = ref('')
const filterStatus = ref('')
const filterDate = ref('')

const user = ref({ name: 'Admin', role: 'admin' })

const activities = ref([])
const loading = ref(true)
const error = ref('')

const getAuthHeaders = () => {
  const token = localStorage.getItem('token')
  return { headers: { Authorization: `Bearer ${token}` } }
}

const loadUser = () => {
  const storedUser = localStorage.getItem('user')
  if (storedUser) user.value = JSON.parse(storedUser)
}

const fetchActivities = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await axios.get(
      'http://127.0.0.1:8000/api/admin/activities',
      getAuthHeaders()
    )
    activities.value = response.data.activities ?? []
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal memuat data aktivitas.'
  } finally {
    loading.value = false
  }
}

const filteredActivities = computed(() => {
  return activities.value
    .filter((a) => {
      const keyword = search.value.toLowerCase()
      const matchSearch =
        a.satpam_name.toLowerCase().includes(keyword) ||
        a.patrol_point_name.toLowerCase().includes(keyword)
      const matchStatus = filterStatus.value === '' || a.scan_status === filterStatus.value
      const matchDate = filterDate.value === '' || a.scan_time.startsWith(filterDate.value)
      return matchSearch && matchStatus && matchDate
    })
    .sort((a, b) => new Date(b.scan_time) - new Date(a.scan_time))
})

const summary = computed(() => ({
  total: activities.value.length,
  berhasil: activities.value.filter((a) => a.scan_status === 'berhasil').length,
  terlambat: activities.value.filter((a) => a.scan_status === 'terlambat').length,
  skip: activities.value.filter((a) => a.scan_status === 'skip').length,
  anomali: activities.value.filter((a) => a.scan_status === 'anomali').length,
  terlewat: activities.value.filter((a) => a.scan_status === 'terlewat').length,
}))

const formatDateTime = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const formatStatus = (status) => {
  const statuses = {
    berhasil: 'Berhasil',
    terlambat: 'Terlambat',
    skip: 'Skip Scan',
    anomali: 'Anomali',
    terlewat: 'Terlewat',
  }
  return statuses[status] || status || '-'
}

const statusClass = (status) => {
  if (status === 'berhasil') return 'status-success'
  if (status === 'anomali') return 'status-danger'
  if (status === 'terlewat') return 'status-missed'
  if (status === 'skip') return 'status-warning'
  if (status === 'terlambat') return 'status-late'
  return 'status-neutral'
}

const displayName = computed(() => user.value?.name || 'Admin')

const logout = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  router.push('/')
}

onMounted(() => {
  loadUser()
  fetchActivities()
})
</script>

<template>
  <div class="admin-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="sidebar-brand">
        <div class="brand-mark">KAI</div>
        <div class="brand-info">
          <h2>KAI SECURITY</h2>
          <span>MONITORING SYSTEM</span>
        </div>
      </div>

      <nav class="sidebar-nav">
        <router-link to="/admin/dashboard" class="nav-item">
          <span class="nav-icon">⌂</span>
          Dashboard
        </router-link>
        <router-link to="/admin/users" class="nav-item">
          <span class="nav-icon">♟</span>
          Kelola User
        </router-link>
        <router-link to="/admin/patrol-points" class="nav-item">
          <span class="nav-icon">⌖</span>
          Titik Patroli
        </router-link>
      </nav>

      <div class="sidebar-footer">
        <button class="logout-button" @click="logout">
          <span>↪</span>
          Keluar
        </button>
      </div>
    </aside>

    <!-- MAIN -->
    <div class="main-content">

      <!-- TOPBAR -->
      <div class="topbar">
        <div class="page-heading">
          <h1>Aktivitas Patroli</h1>
          <p>Riwayat scan checkpoint oleh petugas keamanan</p>
        </div>
        <div class="user-profile">
          <div class="profile-avatar">{{ displayName.charAt(0) }}</div>
          <div class="profile-info">
            <strong>{{ displayName }}</strong>
            <span>Administrator</span>
          </div>
        </div>
      </div>

      <!-- CONTENT -->
      <div class="dashboard-content">

        <!-- ERROR -->
        <div v-if="error" class="error-alert">
          <span>⚠</span>
          <div>
            <strong>Terjadi kesalahan</strong>
            <p>{{ error }}</p>
          </div>
          <button @click="fetchActivities">Coba Lagi</button>
        </div>

        <!-- STATS -->
        <section class="stats-grid">
          <div class="stat-card">
            <p>Total Aktivitas</p>
            <h3>{{ summary.total }}</h3>
          </div>
          <div class="stat-card">
            <p>Berhasil</p>
            <h3 class="text-success">{{ summary.berhasil }}</h3>
          </div>
          <div class="stat-card">
            <p>Terlambat</p>
            <h3 class="text-warning">{{ summary.terlambat }}</h3>
          </div>
          <div class="stat-card">
            <p>Skip Scan</p>
            <h3 class="text-warning">{{ summary.skip }}</h3>
          </div>
          <div class="stat-card">
            <p>Anomali</p>
            <h3 class="text-danger">{{ summary.anomali }}</h3>
          </div>
          <div class="stat-card">
            <p>Terlewat</p>
            <h3 class="text-missed">{{ summary.terlewat }}</h3>
          </div>
        </section>

        <!-- HEADER ROW -->
        <div class="content-header">
          <div>
            <div class="section-label">MONITORING</div>
            <h2>Riwayat Aktivitas</h2>
            <p>Total {{ filteredActivities.length }} aktivitas ditemukan</p>
          </div>
        </div>

        <!-- FILTER ROW -->
        <div class="filter-row">
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama petugas atau titik patroli..."
            class="search-input"
          />
          <input v-model="filterDate" type="date" class="filter-select" />
          <select v-model="filterStatus" class="filter-select">
            <option value="">Semua Status</option>
            <option value="berhasil">Berhasil</option>
            <option value="terlambat">Terlambat</option>
            <option value="skip">Skip Scan</option>
            <option value="anomali">Anomali</option>
            <option value="terlewat">Terlewat</option>
          </select>
        </div>

        <!-- LOADING -->
        <div v-if="loading" class="loading-box">
          <div class="spinner"></div>
          <p>Memuat data...</p>
        </div>

        <!-- TABLE -->
        <div v-else class="table-card">
          <table>
            <thead>
              <tr>
                <th>Waktu</th>
                <th>Petugas</th>
                <th>Titik Patroli</th>
                <th>Status</th>
                <th>Catatan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredActivities.length === 0">
                <td colspan="5" class="empty-state">
                  Tidak ada aktivitas yang ditemukan.
                </td>
              </tr>
              <tr v-for="activity in filteredActivities" :key="activity.id">
                <td class="text-secondary">{{ formatDateTime(activity.scan_time) }}</td>
                <td>
                  <div class="user-cell">
                    <div class="user-avatar">{{ activity.satpam_name.charAt(0) }}</div>
                    <span>{{ activity.satpam_name }}</span>
                  </div>
                </td>
                <td>{{ activity.patrol_point_name }}</td>
                <td>
                  <span class="badge" :class="statusClass(activity.scan_status)">
                    {{ formatStatus(activity.scan_status) }}
                  </span>
                </td>
                <td class="text-secondary">{{ activity.note || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-layout {
  --primary: #1f2454;
  --primary-light: #292f6b;
  --accent: #e87500;
  --accent-light: #f08b1a;
  --background: #f4f5f7;
  --background-soft: #e9ebef;
  --white: #ffffff;
  --text-primary: #1f2454;
  --text-secondary: #8a8d9c;
  --border: #e2e4ea;
  --success: #2f9e63;
  --danger: #d63031;
  --warning: #e87500;

  min-height: 100vh;
  display: flex;
  background: linear-gradient(135deg, var(--background) 0%, var(--background-soft) 100%);
  font-family: "Segoe UI", Arial, sans-serif;
  color: var(--text-primary);
}

/* SIDEBAR */
.sidebar {
  width: 260px;
  min-height: 100vh;
  position: fixed;
  left: 0; top: 0;
  display: flex;
  flex-direction: column;
  background: linear-gradient(180deg, var(--primary) 0%, #181c43 100%);
  color: white;
  padding: 28px 18px;
  box-sizing: border-box;
  box-shadow: 8px 0 30px rgba(31,36,84,0.08);
  z-index: 10;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 10px 32px;
}

.brand-mark { font-size: 27px; font-weight: 900; font-style: italic; letter-spacing: -2px; }
.brand-info { display: flex; flex-direction: column; }
.brand-info h2 { margin: 0; font-size: 13px; letter-spacing: 1px; }
.brand-info span { margin-top: 3px; color: #bfc2d5; font-size: 8px; letter-spacing: 1.5px; }

.sidebar-nav { display: flex; flex-direction: column; gap: 8px; }

.nav-item {
  display: flex;
  align-items: center;
  gap: 13px;
  min-height: 50px;
  padding: 0 16px;
  border-radius: 12px;
  color: #bfc2d5;
  text-decoration: none;
  font-size: 13px;
  font-weight: 600;
  transition: 0.25s;
}

.nav-item:hover { background: rgba(255,255,255,0.08); color: white; }

.nav-item.router-link-active {
  background: linear-gradient(135deg, var(--accent), var(--accent-light));
  color: white;
  box-shadow: 0 8px 20px rgba(232,117,0,0.2);
}

.nav-icon { width: 20px; text-align: center; font-size: 18px; }

.sidebar-footer { margin-top: auto; padding: 20px 0 0; }

.logout-button {
  width: 100%;
  min-height: 48px;
  border: none;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 16px;
  background: rgba(255,255,255,0.06);
  color: #c8cad9;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: 0.25s;
}

.logout-button:hover { background: rgba(214,48,49,0.16); color: #ffb7b7; }

/* MAIN */
.main-content { width: 100%; min-height: 100vh; margin-left: 260px; }

/* TOPBAR */
.topbar {
  min-height: 86px;
  padding: 0 42px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(255,255,255,0.88);
  border-bottom: 1px solid var(--border);
  backdrop-filter: blur(10px);
}

.page-heading h1 { margin: 0; font-size: 23px; font-weight: 700; color: var(--primary); }
.page-heading p { margin: 4px 0 0; color: var(--text-secondary); font-size: 12px; }

.user-profile { display: flex; align-items: center; gap: 11px; }

.profile-avatar {
  width: 42px; height: 42px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 13px;
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  color: white;
  font-size: 15px; font-weight: 700;
  box-shadow: 0 7px 16px rgba(31,36,84,0.18);
}

.profile-info { display: flex; flex-direction: column; }
.profile-info strong { font-size: 13px; }
.profile-info span { margin-top: 2px; color: var(--text-secondary); font-size: 11px; }

/* CONTENT */
.dashboard-content {
  max-width: 1400px;
  margin: 0 auto;
  padding: 38px 42px 50px;
  box-sizing: border-box;
}

/* ERROR */
.error-alert {
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 24px; padding: 15px 18px;
  background: #fff1f1;
  border-left: 4px solid var(--danger);
  border-radius: 10px; color: var(--danger);
}
.error-alert strong { display: block; font-size: 13px; }
.error-alert p { margin: 3px 0 0; font-size: 12px; }
.error-alert button {
  margin-left: auto; border: none; border-radius: 8px;
  padding: 9px 14px; background: var(--danger);
  color: white; font-size: 11px; font-weight: 700; cursor: pointer;
}

/* LOADING */
.loading-box {
  display: flex; flex-direction: column;
  align-items: center; padding: 60px; color: var(--text-secondary);
}

.spinner {
  width: 36px; height: 36px;
  border-radius: 50%;
  border: 3px solid var(--border);
  border-top-color: var(--accent);
  animation: spin 0.7s linear infinite;
  margin-bottom: 14px;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* STATS */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 18px;
  margin-bottom: 26px;
}

.stat-card {
  padding: 20px 22px;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--white);
  box-shadow: 0 6px 20px rgba(31,36,84,0.04);
}

.stat-card p { margin: 0; color: var(--text-secondary); font-size: 12px; font-weight: 600; }
.stat-card h3 { margin: 8px 0 0; font-size: 28px; color: var(--primary); }
.text-success { color: var(--success) !important; }
.text-warning { color: var(--warning) !important; }
.text-danger { color: var(--danger) !important; }
.text-missed { color: #7a5cf0 !important; }

.content-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 20px;
}

.section-label { color: var(--accent); font-size: 10px; font-weight: 800; letter-spacing: 1.4px; }
.content-header h2 { margin: 6px 0 4px; font-size: 24px; color: var(--primary); }
.content-header p { margin: 0; color: var(--text-secondary); font-size: 13px; }

/* FILTER ROW */
.filter-row {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.search-input {
  flex: 1;
  height: 44px;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0 16px;
  font-size: 13px;
  outline: none;
  transition: 0.2s;
  background: var(--white);
}

.search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(232,117,0,0.08); }

.filter-select {
  height: 44px;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0 14px;
  font-size: 13px;
  outline: none;
  background: var(--white);
  cursor: pointer;
  min-width: 150px;
}

/* TABLE */
.table-card {
  background: var(--white);
  border-radius: 16px;
  border: 1px solid var(--border);
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(31,36,84,0.06);
}

table { width: 100%; border-collapse: collapse; }

thead { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
thead th {
  padding: 16px 20px;
  text-align: left;
  color: white;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.8px;
  text-transform: uppercase;
}

tbody tr { border-bottom: 1px solid var(--border); transition: 0.2s; }
tbody tr:hover { background: #f8f9fc; }
tbody tr:last-child { border-bottom: none; }

td { padding: 16px 20px; font-size: 13px; color: var(--text-primary); }
.text-secondary { color: var(--text-secondary); }
.empty-state { text-align: center; color: var(--text-secondary); padding: 40px; }

.user-cell { display: flex; align-items: center; gap: 10px; }

.user-avatar {
  width: 34px; height: 34px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  color: white;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700;
  flex-shrink: 0;
}

/* BADGE */
.badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
}

.status-success { background: rgba(47,158,99,0.12); color: var(--success); }
.status-danger { background: rgba(214,48,49,0.1); color: var(--danger); }
.status-warning { background: rgba(232,117,0,0.12); color: var(--warning); }
.status-late { background: rgba(232,117,0,0.12); color: var(--warning); }
.status-missed { background: rgba(122,92,240,0.12); color: #7a5cf0; }
.status-neutral { background: #f1f2f6; color: var(--text-secondary); }

@media (max-width: 1250px) {
  .stats-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 700px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 480px) {
  .stats-grid { grid-template-columns: 1fr; }
}
</style>
