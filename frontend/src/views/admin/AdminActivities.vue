<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";

const router = useRouter();

const loading = ref(true);
const error = ref("");

const search = ref("");
const statusFilter = ref("all");
const dateFilter = ref("today");

const activities = ref([]);

// ================================
// AUTH HEADER
// ================================
const getAuthHeaders = () => {
  const token = localStorage.getItem("token");

  return {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  };
};

// ================================
// FETCH ACTIVITIES
// ================================
const fetchActivities = async () => {
  loading.value = true;
  error.value = "";

  try {
    const response = await axios.get(
      "https://sistem-monitoring-keamanan-be.onrender.com/api/admin/activities",
      getAuthHeaders(),
    );

    console.log("Data aktivitas:", response.data);

    activities.value = response.data.activities ?? [];
  } catch (err) {
    console.error("Gagal mengambil aktivitas:", err);

    error.value = err.response?.data?.message || "Gagal memuat aktivitas patroli.";
  } finally {
    loading.value = false;
  }
};

// ================================
// FILTER
// ================================
const filteredActivities = computed(() => {
  const now = new Date();

  return activities.value.filter((activity) => {
    const keyword = search.value.toLowerCase();

    // ================================
    // SEARCH
    // ================================
    const matchesSearch =
      activity.satpam_name?.toLowerCase().includes(keyword) ||
      activity.patrol_point_name?.toLowerCase().includes(keyword);

    // ================================
    // STATUS
    // ================================
    const matchesStatus =
      statusFilter.value === "all" || activity.scan_status === statusFilter.value;

    // ================================
    // DATE
    // ================================
    let matchesDate = true;

    if (activity.scan_time) {
      const activityDate = new Date(activity.scan_time);

      if (dateFilter.value === "today") {
        matchesDate = activityDate.toDateString() === now.toDateString();
      }

      if (dateFilter.value === "week") {
        const sevenDaysAgo = new Date();

        sevenDaysAgo.setDate(now.getDate() - 7);

        matchesDate = activityDate >= sevenDaysAgo && activityDate <= now;
      }

      if (dateFilter.value === "month") {
        const thirtyDaysAgo = new Date();

        thirtyDaysAgo.setDate(now.getDate() - 30);

        matchesDate = activityDate >= thirtyDaysAgo && activityDate <= now;
      }
    }

    return matchesSearch && matchesStatus && matchesDate;
  });
});

// ================================
// FORMAT TIME
// ================================
const formatTime = (date) => {
  if (!date) return "-";

  return new Date(date).toLocaleTimeString("id-ID", {
    hour: "2-digit",
    minute: "2-digit",
  });
};

// ================================
// FORMAT STATUS
// ================================
const formatStatus = (status) => {
  const statuses = {
    valid: "Berhasil",
    invalid: "Gagal",
    skip: "Dilewati",
    terlambat: "Terlambat",
  };

  return statuses[status] || status || "-";
};

// ================================
// STATUS CLASS
// ================================
const statusClass = (status) => {
  if (status === "valid") return "status-success";
  if (status === "invalid") return "status-danger";
  if (status === "skip") return "status-warning";
  if (status === "terlambat") return "status-late";

  return "status-neutral";
};

// ================================
// BACK
// ================================
const goBack = () => {
  router.push("/admin/dashboard");
};

// ================================
// LOGOUT
// ================================
const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");

  router.push("/");
};

// ================================
// ON MOUNT
// ================================
onMounted(() => {
  fetchActivities();
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
    <main class="main-content">
      <!-- TOPBAR -->
      <header class="topbar">
        <div class="page-heading">
          <button class="back-button" @click="goBack">← Kembali</button>

          <h1>Aktivitas Patroli</h1>

          <p>Monitoring seluruh aktivitas patroli petugas keamanan.</p>
        </div>
      </header>

      <!-- CONTENT -->
      <section class="dashboard-content">
        <!-- PAGE INTRO -->
        <div class="page-intro">
          <div>
            <span class="section-label">MONITORING PATROLI</span>

            <h2>Semua Aktivitas</h2>

            <p>Pantau riwayat scan patroli yang dilakukan oleh seluruh petugas.</p>
          </div>
        </div>

        <!-- FILTER -->
        <div class="filter-card">
          <div class="filter-group search-group">
            <label>Cari Aktivitas</label>

            <input v-model="search" type="text" placeholder="Cari petugas atau titik patroli..." />
          </div>

          <div class="filter-group">
            <label>Status</label>

            <select v-model="statusFilter">
              <option value="all">Semua Status</option>
              <option value="valid">Berhasil</option>
              <option value="invalid">Gagal</option>
              <option value="terlambat">Terlambat</option>
              <option value="skip">Dilewati</option>
            </select>
          </div>

          <div class="filter-group">
            <label>Periode</label>

            <select v-model="dateFilter">
              <option value="today">Hari Ini</option>
              <option value="week">7 Hari Terakhir</option>
              <option value="month">30 Hari Terakhir</option>
            </select>
          </div>
        </div>

        <!-- TABLE -->
        <div class="activity-card">
          <div class="card-header">
            <div>
              <span class="card-label">RIWAYAT SCAN</span>

              <h3>Aktivitas Patroli</h3>
            </div>

            <span class="result-count"> {{ filteredActivities.length }} aktivitas </span>
          </div>

          <div class="activity-table-wrapper">
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
                <tr v-for="activity in filteredActivities" :key="activity.id">
                  <td>
                    {{ formatTime(activity.scan_time) }}
                  </td>

                  <td>
                    <div class="officer-name">
                      <div class="mini-avatar">
                        {{ activity.satpam_name.charAt(0).toUpperCase() }}
                      </div>

                      {{ activity.satpam_name }}
                    </div>
                  </td>

                  <td>
                    {{ activity.patrol_point_name }}
                  </td>

                  <td>
                    <span class="status-pill" :class="statusClass(activity.scan_status)">
                      {{ formatStatus(activity.scan_status) }}
                    </span>
                  </td>
                </tr>

                <tr v-if="filteredActivities.length === 0">
                  <td colspan="4">
                    <div class="empty-state">
                      <div class="empty-icon">◌</div>

                      <h4>Tidak Ada Aktivitas</h4>

                      <p>Tidak ditemukan aktivitas yang sesuai dengan filter.</p>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </main>
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

/* NAV */

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

/* FOOTER */

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
}

/* MAIN */

.main-content {
  width: 100%;
  min-height: 100vh;

  margin-left: 260px;
}

/* TOPBAR */

.topbar {
  min-height: 100px;

  padding: 20px 42px;

  box-sizing: border-box;

  display: flex;
  align-items: center;

  background: rgba(255, 255, 255, 0.88);

  border-bottom: 1px solid var(--border);

  backdrop-filter: blur(10px);
}

.page-heading h1 {
  margin: 7px 0 0;

  font-size: 23px;

  font-weight: 700;

  color: var(--primary);
}

.page-heading p {
  margin: 4px 0 0;

  color: var(--text-secondary);

  font-size: 12px;
}

.back-button {
  padding: 0;

  border: none;

  background: transparent;

  color: var(--accent);

  font-size: 12px;

  font-weight: 700;

  cursor: pointer;
}

/* CONTENT */

.dashboard-content {
  max-width: 1400px;

  margin: 0 auto;

  padding: 38px 42px 50px;

  box-sizing: border-box;
}

.page-intro {
  margin-bottom: 25px;
}

.section-label {
  color: var(--accent);

  font-size: 10px;

  font-weight: 800;

  letter-spacing: 1.4px;
}

.page-intro h2 {
  margin: 6px 0 8px;

  color: var(--primary);

  font-size: 28px;
}

.page-intro p {
  margin: 0;

  color: var(--text-secondary);

  font-size: 14px;
}

/* FILTER */

.filter-card {
  display: grid;

  grid-template-columns: 1.6fr 1fr 1fr;

  gap: 15px;

  margin-bottom: 22px;

  padding: 20px;

  background: rgba(255, 255, 255, 0.88);

  border: 1px solid var(--border);

  border-radius: 16px;

  box-shadow: 0 10px 30px rgba(31, 36, 84, 0.04);
}

.filter-group {
  display: flex;
  flex-direction: column;

  gap: 7px;
}

.filter-group label {
  color: var(--text-secondary);

  font-size: 10px;

  font-weight: 700;

  text-transform: uppercase;

  letter-spacing: 0.6px;
}

.filter-group input,
.filter-group select {
  height: 42px;

  padding: 0 13px;

  box-sizing: border-box;

  border: 1px solid var(--border);

  border-radius: 10px;

  background: white;

  color: var(--primary);

  font-family: inherit;

  font-size: 12px;

  outline: none;
}

.filter-group input:focus,
.filter-group select:focus {
  border-color: var(--accent);

  box-shadow: 0 0 0 3px rgba(232, 117, 0, 0.08);
}

/* ACTIVITY */

.activity-card {
  padding: 26px;

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

.card-label {
  color: var(--accent);

  font-size: 10px;

  font-weight: 800;

  letter-spacing: 1.4px;
}

.card-header h3 {
  margin: 5px 0 0;

  color: var(--primary);

  font-size: 19px;
}

.result-count {
  padding: 7px 11px;

  border-radius: 20px;

  background: #f1f2f6;

  color: var(--text-secondary);

  font-size: 10px;

  font-weight: 700;
}

/* TABLE */

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
  padding: 15px 12px;

  border-top: 1px solid #eceef2;

  color: #5d6071;
}

.activity-table tbody tr {
  transition: 0.2s;
}

.activity-table tbody tr:hover {
  background: #fafafb;
}

/* OFFICER */

.officer-name {
  display: flex;

  align-items: center;

  gap: 8px;

  color: var(--primary);

  font-weight: 600;
}

.mini-avatar {
  width: 30px;
  height: 30px;

  display: flex;
  align-items: center;
  justify-content: center;

  border-radius: 9px;

  background: rgba(31, 36, 84, 0.1);

  color: var(--primary);

  font-size: 10px;

  font-weight: 800;
}

/* STATUS */

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

.status-late {
  background: rgba(232, 117, 0, 0.12);

  color: var(--accent);
}

/* EMPTY */

.empty-state {
  min-height: 250px;

  display: flex;

  flex-direction: column;

  align-items: center;

  justify-content: center;

  text-align: center;
}

.empty-icon {
  width: 60px;
  height: 60px;

  display: flex;

  align-items: center;
  justify-content: center;

  margin-bottom: 12px;

  border-radius: 18px;

  background: rgba(31, 36, 84, 0.07);

  color: var(--primary);

  font-size: 28px;
}

.empty-state h4 {
  margin: 0;

  color: var(--primary);

  font-size: 15px;
}

.empty-state p {
  margin: 7px 0 0;

  color: var(--text-secondary);

  font-size: 12px;
}

/* RESPONSIVE */

@media (max-width: 900px) {
  .filter-card {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 650px) {
  .sidebar {
    display: none;
  }

  .main-content {
    margin-left: 0;
  }

  .topbar {
    padding: 18px 20px;
  }

  .dashboard-content {
    padding: 25px 20px 40px;
  }

  .page-intro h2 {
    font-size: 24px;
  }

  .activity-card {
    padding: 18px;
  }
}
</style>
