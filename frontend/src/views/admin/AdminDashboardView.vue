<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";

const router = useRouter();

const loading = ref(true);
const error = ref("");

let refreshInterval = null;

onBeforeUnmount(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval);
  }
});

const dashboard = ref({
  total_users: 0,
  total_patrol_points: 0,
  today_activities: 0,
  recent_activities: [],
});

const user = ref({
  name: "Admin",
  role: "admin",
});

const getAuthHeaders = () => {
  const token = localStorage.getItem("token");

  return {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  };
};

const fetchDashboard = async (initialLoad = false) => {
  // Loading hanya untuk pertama kali halaman dibuka
  if (initialLoad) {
    loading.value = true;
  }

  error.value = "";

  try {
    const response = await axios.get("https://sistem-monitoring-keamanan-be.onrender.com/api/admin/dashboard", getAuthHeaders());

    dashboard.value = {
      total_users: response.data.total_users ?? 0,
      total_patrol_points: response.data.total_patrol_points ?? 0,
      today_activities: response.data.today_activities ?? 0,
      recent_activities: response.data.recent_activities ?? [],
    };
  } catch (err) {
    console.error(err);

    error.value = err.response?.data?.message || "Gagal memuat data dashboard.";
  } finally {
    // Hanya mematikan loading pada initial load
    if (initialLoad) {
      loading.value = false;
    }
  }
};

const loadUser = () => {
  const storedUser = localStorage.getItem("user");

  if (storedUser) {
    user.value = JSON.parse(storedUser);
  }
};

const displayName = computed(() => {
  return user.value?.name || "Admin";
});

const formatTime = (date) => {
  if (!date) return "-";

  return new Date(date).toLocaleTimeString("id-ID", {
    hour: "2-digit",
    minute: "2-digit",
  });
};

const formatStatus = (status) => {
  const statuses = {
    berhasil: "Berhasil",
    terlambat: "Terlambat",
    skip: "Skip Scan",
    anomali: "Anomali",
    terlewat: "Terlewat",
  };

  return statuses[status] || status || "-";
};

const statusClass = (status) => {
  if (status === "berhasil") {
    return "status-success";
  }

  if (status === "anomali") {
    return "status-danger";
  }

  if (status === "terlewat") {
    return "status-missed";
  }

  if (status === "skip" || status === "terlambat") {
    return "status-warning";
  }

  return "status-neutral";
};

const goToUsers = () => {
  router.push("/admin/users");
};

const goToPatrolPoints = () => {
  router.push("/admin/patrol-points");
};

const goToActivities = () => {
  router.push("/admin/activities");
};

const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");

  router.push("/");
};

onMounted(() => {
  loadUser();

  // Load pertama kali
  fetchDashboard(true);

  // Refresh data setiap 5 detik
  refreshInterval = setInterval(() => {
    fetchDashboard();
  }, 5000);
});
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

      <!-- NAVIGATION -->
      <nav class="sidebar-nav">
        <router-link to="/admin/dashboard" class="nav-item active">
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

      <!-- SIDEBAR FOOTER -->
      <div class="sidebar-footer">
        <button class="logout-button" @click="logout">
          <span>↪</span>
          Keluar
        </button>
      </div>
    </aside>

    <!-- MAIN -->
    <main class="main-content">
      <!-- TOPBAR -->
      <header class="topbar">
        <div class="page-heading">
          <h1>Dashboard Admin</h1>

          <p>Pusat pengelolaan sistem monitoring keamanan.</p>
        </div>

        <div class="user-profile">
          <div class="profile-avatar">
            {{ displayName.charAt(0).toUpperCase() }}
          </div>

          <div class="profile-info">
            <strong>{{ displayName }}</strong>
            <span>Administrator</span>
          </div>
        </div>
      </header>

      <!-- DASHBOARD BODY -->
      <section class="dashboard-content">
        <!-- WELCOME -->
        <div class="welcome-section">
          <div>
            <span class="section-label"> OVERVIEW SISTEM </span>

            <h2>Ringkasan Sistem</h2>

            <p>Pantau pengguna, titik patroli, dan aktivitas keamanan dari satu tempat.</p>
          </div>

          <div class="system-status">
            <span class="status-dot"></span>

            <span>Sistem Aktif</span>
          </div>
        </div>

        <!-- ERROR -->
        <div v-if="error" class="error-alert">
          <span>⚠</span>

          <div>
            <strong>Terjadi kesalahan</strong>
            <p>{{ error }}</p>
          </div>

          <button @click="fetchDashboard">Coba Lagi</button>
        </div>

        <!-- STATS -->
        <section class="stats-grid">
          <!-- TOTAL USER -->
          <div class="stat-card">
            <div class="stat-top">
              <div class="stat-icon users-icon">♟</div>

              <span class="stat-badge"> Sistem </span>
            </div>

            <div class="stat-content">
              <p>Total User</p>

              <h3 v-if="!loading">
                {{ dashboard.total_users }}
              </h3>

              <div v-else class="number-skeleton"></div>

              <span> Admin & petugas terdaftar </span>
            </div>
          </div>

          <!-- PATROL POINT -->
          <div class="stat-card">
            <div class="stat-top">
              <div class="stat-icon location-icon">⌖</div>

              <span class="stat-badge"> QR Point </span>
            </div>

            <div class="stat-content">
              <p>Titik Patroli</p>

              <h3 v-if="!loading">
                {{ dashboard.total_patrol_points }}
              </h3>

              <div v-else class="number-skeleton"></div>

              <span> Titik keamanan terdaftar </span>
            </div>
          </div>

          <!-- ACTIVITIES -->
          <div class="stat-card accent-card">
            <div class="stat-top">
              <div class="stat-icon activity-icon">✓</div>

              <span class="stat-badge accent-badge"> Hari Ini </span>
            </div>

            <div class="stat-content">
              <p>Aktivitas Hari Ini</p>

              <h3 v-if="!loading">
                {{ dashboard.today_activities }}
              </h3>

              <div v-else class="number-skeleton"></div>

              <span> Scan patroli tercatat hari ini </span>
            </div>
          </div>
        </section>

        <!-- LOWER GRID -->
        <section class="dashboard-grid">
          <!-- ACTIVITY -->
          <div class="activity-card">
            <div class="card-header">
              <div>
                <span class="card-label"> MONITORING </span>

                <h3>Aktivitas Terbaru</h3>
              </div>

              <button class="text-button" @click="goToActivities">Lihat Semua →</button>
            </div>

            <!-- LOADING -->
            <div v-if="loading" class="activity-loading">
              <div class="row-skeleton"></div>
              <div class="row-skeleton"></div>
              <div class="row-skeleton"></div>
              <div class="row-skeleton"></div>
            </div>

            <!-- EMPTY -->
            <div v-else-if="dashboard.recent_activities.length === 0" class="empty-state">
              <div class="empty-icon">◌</div>

              <h4>Belum Ada Aktivitas</h4>

              <p>Aktivitas patroli yang tercatat akan muncul di bagian ini.</p>
            </div>

            <!-- TABLE -->
            <div v-else class="activity-table-wrapper">
              <table class="activity-table">
                <thead>
                  <tr>
                    <th>Waktu</th>
                    <th>Petugas</th>
                    <th>Titik Patroli</th>
                    <th>Status</th>
                  </tr>
                </thead>

                <tbody>
                  <tr v-for="activity in dashboard.recent_activities" :key="activity.id">
                    <td>
                      {{ formatTime(activity.scan_time) }}
                    </td>

                    <td>
                      <div class="officer-name">
                        <div class="mini-avatar">
                          {{ activity.satpam_name?.charAt(0).toUpperCase() }}
                        </div>

                        {{ activity.satpam_name || "-" }}
                      </div>
                    </td>

                    <td>
                      {{ activity.patrol_point_name || "-" }}
                    </td>

                    <td>
                      <span class="status-pill" :class="statusClass(activity.scan_status)">
                        {{ formatStatus(activity.scan_status) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- QUICK ACTION -->
          <div class="quick-action-card">
            <div class="quick-header">
              <div class="quick-icon">⚡</div>

              <div>
                <span>AKSES CEPAT</span>
                <h3>Aksi Cepat</h3>
              </div>
            </div>

            <div class="quick-actions">
              <button class="quick-button primary" @click="goToUsers">
                <span class="quick-symbol"> + </span>

                <div>
                  <strong>Tambah User</strong>
                  <small> Daftarkan pengguna baru </small>
                </div>
              </button>

              <button class="quick-button secondary" @click="goToPatrolPoints">
                <span class="quick-symbol"> + </span>

                <div>
                  <strong>Tambah Titik Patroli</strong>
                  <small> Buat checkpoint baru </small>
                </div>
              </button>

              <button class="quick-button outline" @click="goToActivities">
                <span class="quick-symbol"> ↗ </span>

                <div>
                  <strong>Lihat Aktivitas</strong>
                  <small> Monitoring seluruh patroli </small>
                </div>
              </button>
            </div>

            <div class="quick-footer">
              <span class="footer-dot"></span>

              Data terhubung ke sistem database
            </div>
          </div>
        </section>
      </section>
    </main>
  </div>
</template>

<style scoped>
/* =========================================
   COLOR SYSTEM
   Mengikuti LoginView.vue
========================================= */

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

/* =========================================
   SIDEBAR
========================================= */

.sidebar {
  width: 260px;

  min-height: 100vh;

  position: fixed;

  left: 0;
  top: 0;

  display: flex;
  flex-direction: column;

  background: linear-gradient(180deg, var(--primary) 0%, #181c43 100%);

  color: white;

  padding: 28px 18px;

  box-sizing: border-box;

  box-shadow: 8px 0 30px rgba(31, 36, 84, 0.08);

  z-index: 10;
}

.sidebar-brand {
  display: flex;
  align-items: center;

  gap: 12px;

  padding: 0 10px 32px;
}

.brand-mark {
  font-size: 27px;

  font-weight: 900;

  font-style: italic;

  letter-spacing: -2px;
}

.brand-info {
  display: flex;
  flex-direction: column;
}

.brand-info h2 {
  margin: 0;

  font-size: 13px;

  letter-spacing: 1px;
}

.brand-info span {
  margin-top: 3px;

  color: #bfc2d5;

  font-size: 8px;

  letter-spacing: 1.5px;
}

/* =========================================
   NAVIGATION
========================================= */

.sidebar-nav {
  display: flex;
  flex-direction: column;

  gap: 8px;
}

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

.nav-item:hover {
  background: rgba(255, 255, 255, 0.08);

  color: white;
}

.nav-item.active,
.nav-item.router-link-active {
  background: linear-gradient(135deg, var(--accent), var(--accent-light));

  color: white;

  box-shadow: 0 8px 20px rgba(232, 117, 0, 0.2);
}

.nav-icon {
  width: 20px;

  text-align: center;

  font-size: 18px;
}

/* =========================================
   SIDEBAR FOOTER
========================================= */

.sidebar-footer {
  margin-top: auto;

  padding: 20px 0 0;
}

.logout-button {
  width: 100%;

  min-height: 48px;

  border: none;

  border-radius: 12px;

  display: flex;
  align-items: center;

  gap: 12px;

  padding: 0 16px;

  background: rgba(255, 255, 255, 0.06);

  color: #c8cad9;

  font-size: 13px;

  font-weight: 600;

  cursor: pointer;

  transition: 0.25s;
}

.logout-button:hover {
  background: rgba(214, 48, 49, 0.16);

  color: #ffb7b7;
}

/* =========================================
   MAIN CONTENT
========================================= */

.main-content {
  width: 100%;

  min-height: 100vh;

  margin-left: 260px;
}

/* =========================================
   TOPBAR
========================================= */

.topbar {
  min-height: 86px;

  padding: 0 42px;

  box-sizing: border-box;

  display: flex;
  align-items: center;
  justify-content: space-between;

  background: rgba(255, 255, 255, 0.88);

  border-bottom: 1px solid var(--border);

  backdrop-filter: blur(10px);
}

.page-heading h1 {
  margin: 0;

  font-size: 23px;

  font-weight: 700;

  color: var(--primary);
}

.page-heading p {
  margin: 4px 0 0;

  color: var(--text-secondary);

  font-size: 12px;
}

.user-profile {
  display: flex;
  align-items: center;

  gap: 11px;
}

.profile-avatar {
  width: 42px;
  height: 42px;

  display: flex;
  align-items: center;
  justify-content: center;

  border-radius: 13px;

  background: linear-gradient(135deg, var(--primary), var(--primary-light));

  color: white;

  font-size: 15px;

  font-weight: 700;

  box-shadow: 0 7px 16px rgba(31, 36, 84, 0.18);
}

.profile-info {
  display: flex;
  flex-direction: column;
}

.profile-info strong {
  font-size: 13px;
}

.profile-info span {
  margin-top: 2px;

  color: var(--text-secondary);

  font-size: 11px;
}

/* =========================================
   DASHBOARD CONTENT
========================================= */

.dashboard-content {
  max-width: 1400px;

  margin: 0 auto;

  padding: 38px 42px 50px;

  box-sizing: border-box;
}

/* =========================================
   WELCOME
========================================= */

.welcome-section {
  display: flex;
  align-items: center;
  justify-content: space-between;

  margin-bottom: 28px;
}

.section-label,
.card-label {
  color: var(--accent);

  font-size: 10px;

  font-weight: 800;

  letter-spacing: 1.4px;
}

.welcome-section h2 {
  margin: 6px 0 8px;

  color: var(--primary);

  font-size: 28px;
}

.welcome-section p {
  margin: 0;

  color: var(--text-secondary);

  font-size: 14px;
}

.system-status {
  display: flex;
  align-items: center;

  gap: 8px;

  padding: 10px 16px;

  background: rgba(255, 255, 255, 0.8);

  border: 1px solid var(--border);

  border-radius: 30px;

  color: var(--primary);

  font-size: 12px;

  font-weight: 700;
}

.status-dot,
.footer-dot {
  width: 8px;
  height: 8px;

  border-radius: 50%;

  background: #2f9e63;

  box-shadow: 0 0 10px rgba(47, 158, 99, 0.6);
}

/* =========================================
   ERROR
========================================= */

.error-alert {
  display: flex;
  align-items: center;

  gap: 12px;

  margin-bottom: 24px;

  padding: 15px 18px;

  background: #fff1f1;

  border-left: 4px solid var(--danger);

  border-radius: 10px;

  color: var(--danger);
}

.error-alert strong {
  display: block;

  font-size: 13px;
}

.error-alert p {
  margin: 3px 0 0;

  font-size: 12px;
}

.error-alert button {
  margin-left: auto;

  border: none;

  border-radius: 8px;

  padding: 9px 14px;

  background: var(--danger);

  color: white;

  font-size: 11px;

  font-weight: 700;

  cursor: pointer;
}

/* =========================================
   STATISTICS
========================================= */

.stats-grid {
  display: grid;

  grid-template-columns: repeat(3, minmax(0, 1fr));

  gap: 22px;

  margin-bottom: 25px;
}

.stat-card {
  min-height: 190px;

  padding: 24px;

  box-sizing: border-box;

  border: 1px solid rgba(226, 228, 234, 0.9);

  border-radius: 18px;

  background: rgba(255, 255, 255, 0.88);

  box-shadow: 0 10px 30px rgba(31, 36, 84, 0.04);

  transition: 0.25s;
}

.stat-card:hover {
  transform: translateY(-4px);

  box-shadow: 0 16px 35px rgba(31, 36, 84, 0.1);
}

.stat-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.stat-icon {
  width: 46px;
  height: 46px;

  display: flex;
  align-items: center;
  justify-content: center;

  border-radius: 14px;

  font-size: 20px;
}

.users-icon {
  background: rgba(31, 36, 84, 0.1);

  color: var(--primary);
}

.location-icon {
  background: rgba(41, 47, 107, 0.1);

  color: var(--primary-light);
}

.activity-icon {
  background: rgba(232, 117, 0, 0.1);

  color: var(--accent);
}

.stat-badge {
  padding: 6px 10px;

  border-radius: 20px;

  background: #f1f2f6;

  color: var(--text-secondary);

  font-size: 10px;

  font-weight: 700;
}

.accent-badge {
  background: rgba(232, 117, 0, 0.1);

  color: var(--accent);
}

.stat-content {
  margin-top: 22px;
}

.stat-content p {
  margin: 0;

  color: var(--text-secondary);

  font-size: 13px;

  font-weight: 600;
}

.stat-content h3 {
  margin: 7px 0 5px;

  color: var(--primary);

  font-size: 38px;

  line-height: 1;
}

.stat-content span {
  color: #9a9dab;

  font-size: 11px;
}

/* =========================================
   DASHBOARD LOWER
========================================= */

.dashboard-grid {
  display: grid;

  grid-template-columns:
    minmax(0, 1.8fr)
    minmax(310px, 0.9fr);

  gap: 25px;
}

.activity-card,
.quick-action-card {
  min-height: 410px;

  padding: 26px;

  box-sizing: border-box;

  background: rgba(255, 255, 255, 0.88);

  border: 1px solid rgba(226, 228, 234, 0.9);

  border-radius: 18px;

  box-shadow: 0 10px 30px rgba(31, 36, 84, 0.04);
}

.card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;

  margin-bottom: 24px;
}

.card-header h3,
.quick-header h3 {
  margin: 5px 0 0;

  color: var(--primary);

  font-size: 19px;
}

.text-button {
  border: none;

  background: transparent;

  color: var(--accent);

  font-size: 12px;

  font-weight: 700;

  cursor: pointer;
}

/* =========================================
   ACTIVITY TABLE
========================================= */

.activity-table-wrapper {
  overflow-x: auto;
}

.activity-table {
  width: 100%;

  border-collapse: collapse;

  font-size: 12px;
}

.activity-table th {
  padding: 0 12px 14px;

  text-align: left;

  color: var(--text-secondary);

  font-size: 10px;

  text-transform: uppercase;

  letter-spacing: 0.7px;
}

.activity-table td {
  padding: 14px 12px;

  border-top: 1px solid #eceef2;

  color: #5d6071;
}

.activity-table tbody tr {
  transition: 0.2s;
}

.activity-table tbody tr:hover {
  background: #fafafb;
}

.officer-name {
  display: flex;
  align-items: center;

  gap: 8px;

  color: var(--primary);

  font-weight: 600;
}

.mini-avatar {
  width: 27px;
  height: 27px;

  display: flex;
  align-items: center;
  justify-content: center;

  border-radius: 9px;

  background: rgba(31, 36, 84, 0.1);

  color: var(--primary);

  font-size: 10px;

  font-weight: 800;
}

.status-pill {
  display: inline-flex;

  padding: 5px 9px;

  border-radius: 20px;

  font-size: 10px;

  font-weight: 700;
}

.status-success {
  background: rgba(47, 158, 99, 0.12);

  color: var(--success);
}

.status-danger {
  background: rgba(214, 48, 49, 0.1);

  color: var(--danger);
}

.status-warning {
  background: rgba(232, 117, 0, 0.12);

  color: var(--warning);
}

.status-neutral {
  background: #f1f2f6;

  color: var(--text-secondary);
}

.status-missed {
  background: rgba(122, 92, 240, 0.12);

  color: #7a5cf0;
}

/* =========================================
   EMPTY STATE
========================================= */

.empty-state {
  min-height: 270px;

  display: flex;
  flex-direction: column;

  align-items: center;
  justify-content: center;

  text-align: center;
}

.empty-icon {
  width: 62px;
  height: 62px;

  display: flex;
  align-items: center;
  justify-content: center;

  margin-bottom: 14px;

  border-radius: 20px;

  background: rgba(31, 36, 84, 0.07);

  color: var(--primary);

  font-size: 30px;
}

.empty-state h4 {
  margin: 0;

  color: var(--primary);

  font-size: 15px;
}

.empty-state p {
  max-width: 260px;

  margin: 8px 0 0;

  color: var(--text-secondary);

  font-size: 12px;

  line-height: 1.7;
}

/* =========================================
   QUICK ACTION
========================================= */

.quick-header {
  display: flex;
  align-items: center;

  gap: 12px;

  margin-bottom: 25px;
}

.quick-header span {
  color: var(--accent);

  font-size: 10px;

  font-weight: 800;

  letter-spacing: 1.2px;
}

.quick-icon {
  width: 46px;
  height: 46px;

  display: flex;
  align-items: center;
  justify-content: center;

  border-radius: 14px;

  background: linear-gradient(135deg, var(--accent), var(--accent-light));

  color: white;

  box-shadow: 0 8px 20px rgba(232, 117, 0, 0.2);
}

.quick-actions {
  display: flex;
  flex-direction: column;

  gap: 12px;
}

.quick-button {
  width: 100%;

  min-height: 70px;

  display: flex;
  align-items: center;

  gap: 13px;

  padding: 13px 15px;

  border-radius: 14px;

  text-align: left;

  cursor: pointer;

  transition: 0.25s;
}

.quick-button:hover {
  transform: translateY(-2px);
}

.quick-symbol {
  width: 34px;
  height: 34px;

  display: flex;
  align-items: center;
  justify-content: center;

  border-radius: 10px;

  font-size: 20px;
}

.quick-button div {
  display: flex;
  flex-direction: column;

  gap: 3px;
}

.quick-button strong {
  font-size: 12px;
}

.quick-button small {
  font-size: 10px;

  opacity: 0.75;
}

.quick-button.primary {
  border: none;

  background: linear-gradient(135deg, var(--primary), var(--primary-light));

  color: white;
}

.quick-button.primary .quick-symbol {
  background: rgba(255, 255, 255, 0.12);
}

.quick-button.secondary {
  border: 1px solid rgba(232, 117, 0, 0.22);

  background: rgba(232, 117, 0, 0.06);

  color: var(--primary);
}

.quick-button.secondary .quick-symbol {
  background: rgba(232, 117, 0, 0.12);

  color: var(--accent);
}

.quick-button.outline {
  border: 1px solid var(--border);

  background: white;

  color: var(--primary);
}

.quick-button.outline .quick-symbol {
  background: #f1f2f6;

  color: var(--primary);
}

.quick-footer {
  display: flex;
  align-items: center;

  gap: 7px;

  margin-top: 20px;

  padding-top: 18px;

  border-top: 1px solid #eceef2;

  color: #9a9dab;

  font-size: 10px;
}

/* =========================================
   LOADING SKELETON
========================================= */

.number-skeleton,
.row-skeleton {
  position: relative;

  overflow: hidden;

  background: #eceef2;

  border-radius: 6px;
}

.number-skeleton {
  width: 80px;
  height: 38px;

  margin: 8px 0;
}

.row-skeleton {
  width: 100%;
  height: 56px;

  margin-bottom: 12px;
}

.number-skeleton::after,
.row-skeleton::after {
  content: "";

  position: absolute;

  top: 0;
  left: -100%;

  width: 100%;
  height: 100%;

  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);

  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  100% {
    left: 100%;
  }
}

/* =========================================
   RESPONSIVE
========================================= */

@media (max-width: 1100px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 850px) {
  .sidebar {
    width: 76px;

    padding: 20px 10px;
  }

  .brand-info,
  .nav-item:not(.active)::after,
  .nav-item,
  .logout-button {
    font-size: 0;
  }

  .sidebar-brand {
    justify-content: center;

    padding-bottom: 28px;
  }

  .brand-mark {
    font-size: 22px;
  }

  .nav-item {
    justify-content: center;

    padding: 0;
  }

  .nav-icon {
    font-size: 20px;
  }

  .logout-button {
    justify-content: center;

    padding: 0;
  }

  .logout-button span {
    font-size: 20px;
  }

  .main-content {
    margin-left: 76px;
  }
}

@media (max-width: 650px) {
  .main-content {
    margin-left: 0;
  }

  .sidebar {
    display: none;
  }

  .topbar {
    padding: 16px 20px;

    min-height: auto;
  }

  .page-heading p,
  .profile-info {
    display: none;
  }

  .dashboard-content {
    padding: 25px 20px 40px;
  }

  .welcome-section {
    align-items: flex-start;

    gap: 20px;

    flex-direction: column;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .welcome-section h2 {
    font-size: 24px;
  }
}
</style>
