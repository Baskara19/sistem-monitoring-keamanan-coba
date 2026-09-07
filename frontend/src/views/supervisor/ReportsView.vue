<template>
  <div class="supervisor-layout">
    <!-- =====================================================
         SIDEBAR
    ====================================================== -->
    <aside class="sidebar">
      <div class="sidebar-brand">
        <div class="brand-mark">KAI</div>

        <div class="brand-info">
          <h2>KAI SECURITY</h2>
          <span>MONITORING SYSTEM</span>
        </div>
      </div>

      <nav class="sidebar-nav">
        <router-link to="/supervisor/dashboard" class="nav-item">
          <span class="nav-icon">⌂</span>
          Dashboard
        </router-link>

        <router-link to="/supervisor/schedules" class="nav-item">
          <span class="nav-icon">🗓</span>
          Kelola Jadwal
        </router-link>

        <router-link to="/supervisor/monitoring" class="nav-item">
          <span class="nav-icon">◉</span>
          Monitoring
        </router-link>

        <router-link to="/supervisor/reports" class="nav-item">
          <span class="nav-icon">▤</span>
          Laporan
        </router-link>
      </nav>

      <div class="sidebar-footer">
        <div class="user-sidebar">
          <div class="sidebar-avatar">
            {{ displayName.charAt(0).toUpperCase() }}
          </div>

          <div class="sidebar-user-info">
            <strong>{{ displayName }}</strong>
            <span>Supervisor</span>
          </div>
        </div>

        <button class="logout-button" @click="logout">
          <span>↪</span>
          Keluar
        </button>
      </div>
    </aside>

    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->
    <main class="main-content">
      <!-- TOPBAR -->
      <header class="topbar">
        <div class="topbar-left">
          <div>
            <h1>Laporan Patroli</h1>
            <p>Monitoring laporan dan aktivitas patroli petugas keamanan</p>
          </div>
        </div>

        <div class="topbar-right">
          <div class="date-info">
            <span class="date-icon">▣</span>

            <div>
              <span class="date-label">Hari ini</span>
              <strong>{{ currentDate }}</strong>
            </div>
          </div>

          <div class="topbar-divider"></div>

          <div class="profile-info">
            <div class="profile-avatar">
              {{ getInitial(displayName) }}
            </div>

            <div class="profile-text">
              <strong>{{ displayName }}</strong>
              <span>Supervisor</span>
            </div>
          </div>
        </div>
      </header>

      <!-- PAGE CONTENT -->
      <div class="page-content">
        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->
        <div class="page-header">
          <div>
            <span class="section-label">SUPERVISOR</span>
            <h2>Ringkasan Laporan</h2>
            <p>Pantau laporan patroli yang masuk dari petugas keamanan.</p>
          </div>

          <button class="refresh-btn" @click="fetchReports" :disabled="loading">
            <span class="refresh-icon">↻</span>
            {{ loading ? "Memuat..." : "Refresh Data" }}
          </button>
        </div>

        <!-- =====================================================
             STATISTICS
        ====================================================== -->
        <div class="statistics-grid">
          <!-- TOTAL -->
          <div class="stat-card">
            <div class="stat-icon stat-blue">▤</div>

            <div class="stat-content">
              <span>Total Laporan Hari Ini</span>
              <strong>{{ statistics.total_reports }}</strong>
              <small>Seluruh laporan patroli</small>
            </div>
          </div>

          <!-- PENDING -->
          <div class="stat-card">
            <div class="stat-icon stat-orange">!</div>

            <div class="stat-content">
              <span>Belum Ditinjau</span>
              <strong>{{ statistics.pending_reports }}</strong>
              <small>Perlu diperiksa supervisor</small>
            </div>
          </div>

          <!-- SKIP -->
          <div class="stat-card">
            <div class="stat-icon stat-purple">↪</div>

            <div class="stat-content">
              <span>Skip Scan</span>
              <strong>{{ statistics.skip_reports }}</strong>
              <small>Patroli yang dilewati</small>
            </div>
          </div>

          <!-- ANOMALY -->
          <div class="stat-card">
            <div class="stat-icon stat-red">⚠</div>

            <div class="stat-content">
              <span>Anomali</span>
              <strong>{{ statistics.anomaly_reports }}</strong>
              <small>Aktivitas tidak normal</small>
            </div>
          </div>
        </div>

        <!-- =====================================================
             FILTER
        ====================================================== -->
        <section class="panel filter-panel">
          <div class="panel-header">
            <div>
              <span class="panel-kicker">FILTER</span>
              <h3>Filter Laporan</h3>
              <p>Gunakan filter untuk menemukan laporan tertentu.</p>
            </div>

            <button class="reset-btn" @click="resetFilter">Reset Filter</button>
          </div>

          <div class="filter-grid">
            <div class="form-group">
              <label>Tanggal</label>

              <div class="input-wrapper">
                <span class="input-icon">▣</span>

                <input type="date" v-model="filters.date" @change="fetchReports" />
              </div>
            </div>

            <div class="form-group">
              <label>Status</label>

              <div class="input-wrapper">
                <span class="input-icon">◉</span>

                <select v-model="filters.status" @change="fetchReports">
                  <option value="">Semua Status</option>
                  <option value="berhasil">Berhasil</option>
                  <option value="terlambat">Terlambat</option>
                  <option value="skip">Skip</option>
                  <option value="anomali">Anomali</option>
                  <option value="terlewat">Terlewat</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>Nama Satpam</label>

              <div class="input-wrapper">
                <span class="input-icon">⌕</span>

                <input
                  type="text"
                  v-model="filters.satpam"
                  placeholder="Cari nama satpam..."
                  @keyup.enter="fetchReports"
                />
              </div>
            </div>

            <div class="filter-action">
              <button class="search-btn" @click="fetchReports" :disabled="loading">
                <span>⌕</span>
                Cari Laporan
              </button>
            </div>
          </div>
        </section>

        <!-- =====================================================
             TABLE
        ====================================================== -->
        <section class="panel table-panel">
          <div class="panel-header table-panel-header">
            <div>
              <span class="panel-kicker">DATA LAPORAN</span>
              <h3>Daftar Laporan</h3>
              <p>Menampilkan {{ reports.length }} data laporan.</p>
            </div>

            <div class="total-badge">{{ reports.length }} Data</div>
          </div>

          <!-- LOADING -->
          <div v-if="loading" class="state-box">
            <div class="loader"></div>

            <h3>Memuat data laporan</h3>

            <p>Sistem sedang mengambil data laporan patroli.</p>
          </div>

          <!-- ERROR -->
          <div v-else-if="error" class="state-box error-state">
            <div class="state-icon error-icon">⚠</div>

            <h3>Gagal memuat laporan</h3>

            <p>{{ error }}</p>

            <button class="retry-btn" @click="fetchReports">Coba Lagi</button>
          </div>

          <!-- EMPTY -->
          <div v-else-if="reports.length === 0" class="state-box">
            <div class="empty-icon">▤</div>

            <h3>Belum ada laporan</h3>

            <p>Tidak ada data laporan yang sesuai dengan filter.</p>
          </div>

          <!-- TABLE -->
          <div v-else class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Satpam</th>
                  <th>Titik Patroli</th>
                  <th>Waktu</th>
                  <th>Status</th>
                  <th>Laporan</th>
                  <th>Aksi</th>
                </tr>
              </thead>

              <tbody>
                <tr v-for="report in reports" :key="report.id">
                  <!-- SATPAM -->
                  <td>
                    <div class="satpam-cell">
                      <div class="avatar">
                        {{ getInitial(report.satpam_name) }}
                      </div>

                      <div class="satpam-info">
                        <strong>
                          {{ report.satpam_name || "-" }}
                        </strong>

                        <small> Badge: {{ report.badge_number || "-" }} </small>
                      </div>
                    </div>
                  </td>

                  <!-- PATROL POINT -->
                  <td>
                    <div class="point-cell">
                      <span class="point-icon">◉</span>

                      <span class="point-name">
                        {{ report.patrol_point || "-" }}
                      </span>
                    </div>
                  </td>

                  <!-- TIME -->
                  <td>
                    <div class="time-cell">
                      <strong>
                        {{ formatDate(report.scan_time) }}
                      </strong>

                      <small>
                        {{ formatTime(report.scan_time) }}
                      </small>
                    </div>
                  </td>

                  <!-- STATUS -->
                  <td>
                    <span class="status-badge" :class="getStatusClass(report.scan_status)">
                      <span class="status-dot"></span>
                      {{ formatStatus(report.scan_status) }}
                    </span>
                  </td>

                  <!-- REPORT -->
                  <td>
                    <!-- ADA LAPORAN -->
                    <div v-if="report.report_id" class="report-cell">
                      <strong>
                        {{ report.report_title || "Laporan" }}
                      </strong>

                      <small>
                        {{ truncate(report.report_description, 55) }}
                      </small>

                      <!-- STATUS REVIEW LAPORAN -->
                      <span
                        v-if="report.review_status === 'pending'"
                        class="review-badge review-pending"
                      >
                        <span class="review-dot"></span>
                        Belum Ditinjau
                      </span>

                      <span
                        v-else-if="report.review_status === 'reviewed'"
                        class="review-badge review-reviewed"
                      >
                        <span class="review-dot"></span>
                        Sudah Ditinjau
                      </span>
                    </div>

                    <!-- SKIP SCAN -->
                    <div v-else-if="report.scan_status === 'skip'" class="report-cell skip-cell">
                      <strong>Skip Scan</strong>

                      <small>
                        {{ report.skip_reason || "Tidak ada alasan skip." }}
                      </small>

                      <!-- STATUS REVIEW SKIP -->
                      <span
                        v-if="report.skip_review_status === 'reviewed'"
                        class="review-badge review-reviewed"
                      >
                        <span class="review-dot"></span>
                        Sudah Ditinjau
                      </span>

                      <span v-else class="review-badge review-pending">
                        <span class="review-dot"></span>
                        Belum Ditinjau
                      </span>
                    </div>

                    <!-- TIDAK ADA LAPORAN / SKIP -->
                    <span v-else class="no-report"> Belum ada laporan </span>
                  </td>

                  <!-- ACTION -->
                  <td>
                    <button class="detail-btn" @click="goToDetail(report.id)">Lihat Detail</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";

import axios from "axios";
import { useRouter } from "vue-router";

// =====================================================
// ROUTER
// =====================================================

const router = useRouter();

// =====================================================
// DETAIL LAPORAN
// =====================================================
const goToDetail = (id) => {
  router.push(`/supervisor/reports/${id}`);
};

// =====================================================
// USER
// =====================================================

const user = ref({
  name: "Supervisor",
  role: "supervisor",
});

const loadUser = () => {
  try {
    const storedUser = localStorage.getItem("user");

    if (storedUser) {
      const parsedUser = JSON.parse(storedUser);

      user.value = {
        ...user.value,
        ...parsedUser,
      };
    }
  } catch (err) {
    console.error("Data user tidak valid:", err);

    localStorage.removeItem("user");
  }
};

const displayName = computed(() => {
  return user.value?.name || "Supervisor";
});

// =====================================================
// CURRENT DATE
// =====================================================

const currentDate = computed(() => {
  return new Intl.DateTimeFormat("id-ID", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(new Date());
});

// =====================================================
// LOGOUT
// =====================================================

const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");

  router.push("/login");
};

// =====================================================
// DATA
// =====================================================

const reports = ref([]);

const loading = ref(false);

const error = ref("");

// =====================================================
// STATISTICS
// =====================================================

const statistics = ref({
  total_reports: 0,

  pending_reports: 0,

  skip_reports: 0,

  anomaly_reports: 0,
});

// =====================================================
// FILTERS
// =====================================================

const filters = ref({
  date: "",

  status: "",

  satpam: "",
});

// =====================================================
// FETCH REPORTS
// =====================================================

const fetchReports = async () => {
  loading.value = true;

  error.value = "";

  try {
    const token = localStorage.getItem("token");

    const params = {};

    if (filters.value.date) {
      params.date = filters.value.date;
    }

    if (filters.value.status) {
      params.status = filters.value.status;
    }

    if (filters.value.satpam.trim()) {
      params.satpam = filters.value.satpam.trim();
    }

    const response = await axios.get("http://127.0.0.1:8000/api/supervisor/reports", {
      params,

      headers: {
        Authorization: `Bearer ${token}`,

        Accept: "application/json",
      },
    });

    reports.value = response.data.reports || [];

    statistics.value = response.data.statistics || {
      total_reports: 0,

      pending_reports: 0,

      skip_reports: 0,

      anomaly_reports: 0,
    };
  } catch (err) {
    console.error("ERROR FETCH REPORTS:", err);

    if (err.response?.status === 401) {
      error.value = "Sesi login sudah berakhir. Silakan login kembali.";
    } else {
      error.value = err.response?.data?.message || "Gagal mengambil data laporan.";
    }
  } finally {
    loading.value = false;
  }
};

// =====================================================
// FILTER
// =====================================================

const resetFilter = () => {
  filters.value = {
    date: "",

    status: "",

    satpam: "",
  };

  fetchReports();
};

// =====================================================
// FORMAT
// =====================================================

const getInitial = (name) => {
  if (!name || name === "-") {
    return "?";
  }

  return name.charAt(0).toUpperCase();
};

const formatDate = (date) => {
  if (!date) return "-";

  const d = new Date(date);

  if (Number.isNaN(d.getTime())) {
    return date;
  }

  return d.toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
};

const formatTime = (date) => {
  if (!date) return "-";

  const d = new Date(date);

  if (Number.isNaN(d.getTime())) {
    return "-";
  }

  return d.toLocaleTimeString("id-ID", {
    hour: "2-digit",
    minute: "2-digit",
  });
};

const formatStatus = (status) => {
  if (!status) return "-";

  const labels = {
    berhasil: "Berhasil",

    terlambat: "Terlambat",

    skip: "Skip",

    anomali: "Anomali",

    terlewat: "Terlewat",
  };

  return labels[status] || status;
};

const getStatusClass = (status) => {
  return (
    {
      berhasil: "status-success",

      terlambat: "status-warning",

      skip: "status-skip",

      anomali: "status-danger",

      terlewat: "status-danger",
    }[status] || "status-default"
  );
};

const truncate = (text, length = 55) => {
  if (!text) return "-";

  if (text.length <= length) {
    return text;
  }

  return text.substring(0, length) + "...";
};

// =====================================================
// INITIAL LOAD
// =====================================================

onMounted(() => {
  loadUser();

  fetchReports();
});
</script>

<style scoped>
/* =====================================================
   GLOBAL
===================================================== */

.supervisor-layout {
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
  --info: #3578e5;

  min-height: 100vh;

  background: linear-gradient(135deg, #f4f5f7 0%, #eef0f4 100%);

  color: var(--text-primary);

  font-family: "Segoe UI", Arial, sans-serif;
}

/* =====================================================
   SIDEBAR
===================================================== */

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
  z-index: 100;
}

/* BRAND */

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

/* NAVIGATION */

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
  padding-top: 20px;
}

.user-sidebar {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 15px 8px;
  margin-bottom: 12px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-avatar {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 11px;
  background: linear-gradient(135deg, var(--accent), var(--accent-light));
  color: white;
  font-size: 14px;
  font-weight: 700;
}

.sidebar-user-info {
  display: flex;
  flex-direction: column;
}

.sidebar-user-info strong {
  font-size: 12px;
}

.sidebar-user-info span {
  margin-top: 2px;
  color: #aeb2c5;
  font-size: 10px;
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
/* =====================================================
   MAIN
===================================================== */

.main-content {
  min-height: 100vh;

  margin-left: 260px;
}

/* =====================================================
   TOPBAR
===================================================== */

.topbar {
  min-height: 86px;

  box-sizing: border-box;

  padding: 0 42px;

  display: flex;

  align-items: center;

  justify-content: space-between;

  background: rgba(255, 255, 255, 0.92);

  border-bottom: 1px solid var(--border);

  backdrop-filter: blur(12px);
}

.topbar-left h1 {
  margin: 0 0 4px;

  font-size: 22px;

  font-weight: 800;

  color: var(--primary);
}

.topbar-left p {
  margin: 0;

  font-size: 12px;

  color: var(--text-secondary);
}

.topbar-right {
  display: flex;

  align-items: center;

  gap: 22px;
}

.date-info {
  display: flex;

  align-items: center;

  gap: 10px;
}

.date-icon {
  width: 36px;
  height: 36px;

  display: flex;

  align-items: center;
  justify-content: center;

  border-radius: 10px;

  background: #fff3e5;

  color: var(--accent);

  font-size: 15px;
}

.date-info div {
  display: flex;

  flex-direction: column;
}

.date-label {
  font-size: 10px;

  color: var(--text-secondary);
}

.date-info strong {
  margin-top: 2px;

  font-size: 12px;

  color: var(--primary);
}

.topbar-divider {
  width: 1px;

  height: 38px;

  background: var(--border);
}

.profile-info {
  display: flex;

  align-items: center;

  gap: 9px;
}

.profile-avatar {
  width: 38px;
  height: 38px;

  display: flex;

  align-items: center;
  justify-content: center;

  border-radius: 50%;

  background: var(--primary);

  color: white;

  font-size: 13px;

  font-weight: 800;
}

.profile-text {
  display: flex;

  flex-direction: column;
}

.profile-text strong {
  font-size: 12px;
}

.profile-text span {
  margin-top: 2px;

  color: var(--text-secondary);

  font-size: 10px;
}

/* =====================================================
   PAGE CONTENT
===================================================== */

.page-content {
  max-width: 1400px;

  margin: 0 auto;

  padding: 38px 42px 50px;
}

/* PAGE HEADER */

.page-header {
  display: flex;

  align-items: flex-end;

  justify-content: space-between;

  gap: 20px;

  margin-bottom: 26px;
}

.section-label {
  display: inline-block;

  margin-bottom: 7px;

  color: var(--accent);

  font-size: 10px;

  font-weight: 800;

  letter-spacing: 0.13em;
}

.page-header h2 {
  margin: 0 0 6px;

  color: var(--primary);

  font-size: 25px;

  font-weight: 800;
}

.page-header p {
  margin: 0;

  color: var(--text-secondary);

  font-size: 13px;
}

.refresh-btn {
  min-height: 43px;

  display: flex;

  align-items: center;

  gap: 8px;

  padding: 0 17px;

  border: none;

  border-radius: 10px;

  background: var(--primary);

  color: white;

  font-size: 12px;

  font-weight: 700;

  cursor: pointer;

  box-shadow: 0 7px 16px rgba(31, 36, 84, 0.14);

  transition: 0.2s ease;
}

.refresh-btn:hover {
  background: var(--primary-light);

  transform: translateY(-1px);
}

.refresh-btn:disabled {
  opacity: 0.6;

  cursor: not-allowed;

  transform: none;
}

.refresh-icon {
  font-size: 18px;
}

/* =====================================================
   STATISTICS
===================================================== */

.statistics-grid {
  display: grid;

  grid-template-columns: repeat(4, 1fr);

  gap: 18px;

  margin-bottom: 24px;
}

.stat-card {
  min-height: 150px;

  box-sizing: border-box;

  display: flex;

  align-items: center;

  gap: 17px;

  padding: 24px;

  background: rgba(255, 255, 255, 0.92);

  border: 1px solid var(--border);

  border-radius: 18px;

  box-shadow: 0 8px 24px rgba(31, 36, 84, 0.045);
}

.stat-icon {
  width: 50px;
  height: 50px;

  flex-shrink: 0;

  display: flex;

  align-items: center;
  justify-content: center;

  border-radius: 13px;

  font-size: 20px;

  font-weight: 800;
}

.stat-blue {
  background: rgba(53, 120, 229, 0.1);

  color: var(--info);
}

.stat-orange {
  background: rgba(232, 117, 0, 0.11);

  color: var(--accent);
}

.stat-purple {
  background: rgba(124, 58, 237, 0.1);

  color: #7c3aed;
}

.stat-red {
  background: rgba(214, 48, 49, 0.1);

  color: var(--danger);
}

.stat-content {
  min-width: 0;
}

.stat-content span {
  display: block;

  margin-bottom: 5px;

  color: var(--text-secondary);

  font-size: 12px;

  font-weight: 600;
}

.stat-content strong {
  display: block;

  color: var(--primary);

  font-size: 28px;

  line-height: 1;

  font-weight: 800;
}

.stat-content small {
  display: block;

  margin-top: 7px;

  color: #a0a3ae;

  font-size: 10px;
}

/* =====================================================
   PANEL
===================================================== */

.panel {
  margin-bottom: 24px;

  overflow: hidden;

  background: rgba(255, 255, 255, 0.94);

  border: 1px solid var(--border);

  border-radius: 18px;

  box-shadow: 0 8px 24px rgba(31, 36, 84, 0.045);
}

.panel-header {
  display: flex;

  align-items: center;

  justify-content: space-between;

  gap: 20px;

  padding: 23px 24px;

  border-bottom: 1px solid var(--border);
}

.panel-kicker {
  display: block;

  margin-bottom: 5px;

  color: var(--accent);

  font-size: 9px;

  font-weight: 800;

  letter-spacing: 0.12em;
}

.panel-header h3 {
  margin: 0 0 5px;

  color: var(--primary);

  font-size: 17px;

  font-weight: 800;
}

.panel-header p {
  margin: 0;

  color: var(--text-secondary);

  font-size: 12px;
}

/* =====================================================
   FILTER
===================================================== */

.filter-panel {
  overflow: visible;
}

.reset-btn {
  padding: 9px 14px;

  border: 1px solid var(--border);

  border-radius: 9px;

  background: white;

  color: var(--text-secondary);

  font-size: 11px;

  font-weight: 700;

  cursor: pointer;

  transition: 0.2s ease;
}

.reset-btn:hover {
  border-color: var(--accent);

  color: var(--accent);

  background: #fffaf5;
}

.filter-grid {
  display: grid;

  grid-template-columns: 1fr 1fr 1.35fr auto;

  gap: 16px;

  align-items: end;

  padding: 22px 24px 24px;
}

.form-group label {
  display: block;

  margin-bottom: 7px;

  color: var(--primary);

  font-size: 11px;

  font-weight: 700;
}

.input-wrapper {
  position: relative;
}

.input-icon {
  position: absolute;

  left: 12px;

  top: 50%;

  transform: translateY(-50%);

  color: var(--text-secondary);

  font-size: 13px;

  pointer-events: none;
}

.form-group input,
.form-group select {
  width: 100%;

  height: 43px;

  box-sizing: border-box;

  padding: 0 12px 0 34px;

  border: 1px solid var(--border);

  border-radius: 9px;

  outline: none;

  background: #fafbfc;

  color: var(--primary);

  font-family: inherit;

  font-size: 12px;

  transition: 0.2s ease;
}

.form-group input:focus,
.form-group select:focus {
  border-color: var(--accent);

  background: white;

  box-shadow: 0 0 0 3px rgba(232, 117, 0, 0.08);
}

.search-btn {
  height: 43px;

  display: flex;

  align-items: center;

  justify-content: center;

  gap: 7px;

  padding: 0 18px;

  border: none;

  border-radius: 9px;

  background: var(--accent);

  color: white;

  font-size: 12px;

  font-weight: 700;

  cursor: pointer;

  white-space: nowrap;

  transition: 0.2s ease;
}

.search-btn:hover {
  background: var(--accent-light);
}

.search-btn:disabled {
  opacity: 0.6;

  cursor: not-allowed;
}

/* =====================================================
   TABLE
===================================================== */

.table-panel {
  overflow: hidden;
}

.table-panel-header {
  min-height: 70px;
}

.total-badge {
  padding: 7px 11px;

  border-radius: 20px;

  background: #f0f1f6;

  color: var(--primary);

  font-size: 10px;

  font-weight: 800;
}

.table-wrapper {
  width: 100%;

  overflow-x: auto;
}

table {
  width: 100%;

  min-width: 900px;

  border-collapse: collapse;
}

thead {
  background: #f8f9fb;
}

th {
  padding: 13px 18px;

  text-align: left;

  color: var(--text-secondary);

  border-bottom: 1px solid var(--border);

  font-size: 9px;

  font-weight: 800;

  text-transform: uppercase;

  letter-spacing: 0.07em;

  white-space: nowrap;
}

td {
  padding: 16px 18px;

  border-bottom: 1px solid #eef0f4;

  vertical-align: middle;
}

tbody tr {
  transition: background 0.15s ease;
}

tbody tr:hover {
  background: #fafbfc;
}

tbody tr:last-child td {
  border-bottom: none;
}

/* =====================================================
   SATPAM CELL
===================================================== */

.satpam-cell {
  display: flex;

  align-items: center;

  gap: 10px;

  min-width: 175px;
}

.avatar {
  width: 38px;
  height: 38px;

  flex-shrink: 0;

  display: flex;

  align-items: center;
  justify-content: center;

  border-radius: 50%;

  background: rgba(31, 36, 84, 0.08);

  color: var(--primary);

  font-size: 12px;

  font-weight: 800;
}

.satpam-info strong {
  display: block;

  max-width: 145px;

  overflow: hidden;

  text-overflow: ellipsis;

  white-space: nowrap;

  color: var(--primary);

  font-size: 12px;
}

.satpam-info small {
  display: block;

  margin-top: 3px;

  color: var(--text-secondary);

  font-size: 10px;
}

/* =====================================================
   POINT
===================================================== */

.point-cell {
  display: flex;

  align-items: center;

  gap: 8px;
}

.point-icon {
  color: var(--accent);

  font-size: 13px;
}

.point-name {
  color: var(--primary);

  font-size: 12px;

  font-weight: 700;

  white-space: nowrap;
}

/* =====================================================
   TIME
===================================================== */

.time-cell strong {
  display: block;

  color: var(--primary);

  font-size: 11px;

  white-space: nowrap;
}

.time-cell small {
  display: block;

  margin-top: 3px;

  color: var(--text-secondary);

  font-size: 10px;
}

/* =====================================================
   STATUS
===================================================== */

.status-badge {
  display: inline-flex;

  align-items: center;

  gap: 6px;

  padding: 6px 10px;

  border-radius: 20px;

  font-size: 10px;

  font-weight: 800;

  white-space: nowrap;
}

.status-dot {
  width: 6px;
  height: 6px;

  border-radius: 50%;

  background: currentColor;
}

.status-success {
  background: rgba(47, 158, 99, 0.1);

  color: var(--success);
}

.status-warning {
  background: rgba(232, 117, 0, 0.1);

  color: var(--warning);
}

.status-skip {
  background: rgba(124, 58, 237, 0.1);

  color: #7c3aed;
}

.status-danger {
  background: rgba(214, 48, 49, 0.1);

  color: var(--danger);
}

.status-default {
  background: #eef0f3;

  color: #6f7380;
}

/* =====================================================
   REPORT CELL
===================================================== */

.report-cell {
  max-width: 220px;
}

.report-cell strong {
  display: block;

  overflow: hidden;

  text-overflow: ellipsis;

  white-space: nowrap;

  color: var(--primary);

  font-size: 11px;
}

.report-cell small {
  display: block;

  margin-top: 4px;

  overflow: hidden;

  text-overflow: ellipsis;

  white-space: nowrap;

  color: var(--text-secondary);

  font-size: 10px;
}

.no-report {
  color: #a5a8b1;

  font-size: 11px;

  font-style: italic;
}
/* =====================================================
   REVIEW STATUS
===================================================== */

.review-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-top: 7px;
  padding: 4px 8px;
  border-radius: 20px;
  font-size: 9px;
  font-weight: 800;
  white-space: nowrap;
}

.review-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: currentColor;
}

.review-pending {
  background: rgba(232, 117, 0, 0.1);
  color: var(--accent);
}

.review-reviewed {
  background: rgba(47, 158, 99, 0.1);
  color: var(--success);
}

/* =====================================================
   DETAIL BUTTON
===================================================== */

.detail-btn {
  padding: 8px 12px;

  border: 1px solid rgba(232, 117, 0, 0.22);

  border-radius: 8px;

  background: #fff9f3;

  color: var(--accent);

  font-size: 10px;

  font-weight: 800;

  cursor: pointer;

  white-space: nowrap;

  transition: 0.2s ease;
}

.detail-btn:hover {
  background: var(--accent);

  color: white;
}

/* =====================================================
   STATE
===================================================== */

.state-box {
  min-height: 300px;

  display: flex;

  flex-direction: column;

  align-items: center;

  justify-content: center;

  padding: 30px;

  text-align: center;
}

.state-box h3 {
  margin: 13px 0 5px;

  color: var(--primary);

  font-size: 15px;
}

.state-box p {
  margin: 0;

  color: var(--text-secondary);

  font-size: 12px;
}

.empty-icon,
.state-icon {
  width: 58px;
  height: 58px;

  display: flex;

  align-items: center;
  justify-content: center;

  border-radius: 16px;

  font-size: 24px;
}

.empty-icon {
  background: rgba(31, 36, 84, 0.07);

  color: var(--primary);
}

.error-icon {
  background: rgba(214, 48, 49, 0.09);

  color: var(--danger);
}

.retry-btn {
  margin-top: 16px;

  padding: 9px 16px;

  border: none;

  border-radius: 8px;

  background: var(--danger);

  color: white;

  font-size: 11px;

  font-weight: 700;

  cursor: pointer;
}

/* LOADER */

.loader {
  width: 31px;
  height: 31px;

  border: 3px solid #e5e7ed;

  border-top-color: var(--accent);

  border-radius: 50%;

  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* =====================================================
   MODAL
===================================================== */

.modal-overlay {
  position: fixed;

  inset: 0;

  z-index: 1000;

  display: flex;

  align-items: center;
  justify-content: center;

  padding: 20px;

  background: rgba(16, 20, 48, 0.65);

  backdrop-filter: blur(3px);
}

.modal {
  width: min(720px, 100%);

  max-height: 90vh;

  overflow-y: auto;

  border-radius: 18px;

  background: white;

  box-shadow: 0 25px 70px rgba(15, 23, 42, 0.28);
}

.modal-header {
  display: flex;

  align-items: flex-start;

  justify-content: space-between;

  gap: 20px;

  padding: 24px;

  border-bottom: 1px solid var(--border);
}

.modal-label {
  color: var(--accent);

  font-size: 9px;

  font-weight: 900;

  letter-spacing: 0.13em;
}

.modal-header h2 {
  margin: 7px 0 0;

  color: var(--primary);

  font-size: 20px;

  font-weight: 800;
}

.close-btn {
  width: 34px;
  height: 34px;

  flex-shrink: 0;

  display: flex;

  align-items: center;
  justify-content: center;

  border: none;

  border-radius: 50%;

  background: #f1f2f5;

  color: #646875;

  font-size: 22px;

  cursor: pointer;
}

.close-btn:hover {
  background: #e8e9ee;
}

/* MODAL BODY */

.modal-body {
  padding: 24px;
}

.detail-section {
  margin-bottom: 25px;
}

.detail-section:last-child {
  margin-bottom: 0;
}

.detail-section-title {
  display: flex;

  align-items: center;

  gap: 8px;

  margin-bottom: 12px;
}

.detail-section-title h3 {
  margin: 0;

  color: var(--primary);

  font-size: 13px;

  font-weight: 800;
}

.detail-title-icon {
  color: var(--accent);

  font-size: 13px;
}

.orange-icon {
  color: var(--accent);
}

.detail-grid {
  display: grid;

  grid-template-columns: repeat(2, 1fr);

  gap: 12px;
}

.detail-item {
  padding: 13px;

  border: 1px solid #edf0f4;

  border-radius: 10px;

  background: #fafbfc;
}

.detail-item span:first-child {
  display: block;

  margin-bottom: 5px;

  color: var(--text-secondary);

  font-size: 10px;
}

.detail-item strong {
  color: var(--primary);

  font-size: 12px;
}

/* NOTE */

.note-box,
.skip-box,
.description-box {
  padding: 14px;

  border-radius: 10px;

  color: #535765;

  background: #f8f9fb;

  font-size: 12px;

  line-height: 1.7;
}

.skip-box {
  border: 1px solid rgba(232, 117, 0, 0.12);

  background: #fff8ef;
}

/* PHOTO */

.photo-container {
  overflow: hidden;

  border-radius: 12px;

  border: 1px solid var(--border);

  background: #f4f5f7;
}

.photo-container img {
  display: block;

  width: 100%;

  max-height: 400px;

  object-fit: contain;
}

/* MODAL FOOTER */

.modal-footer {
  display: flex;

  justify-content: flex-end;

  padding: 18px 24px;

  border-top: 1px solid var(--border);
}

.close-modal-btn {
  min-width: 90px;

  padding: 10px 18px;

  border: none;

  border-radius: 9px;

  background: var(--primary);

  color: white;

  font-size: 11px;

  font-weight: 700;

  cursor: pointer;
}

.close-modal-btn:hover {
  background: var(--primary-light);
}

/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 1150px) {
  .statistics-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .filter-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .filter-action {
    grid-column: span 2;
  }
}

@media (max-width: 850px) {
  .sidebar {
    width: 76px;

    padding: 28px 10px;
  }

  .main-content {
    margin-left: 76px;
  }

  .sidebar-brand {
    justify-content: center;

    padding: 0 0 28px;
  }

  .brand-text {
    display: none;
  }

  .nav-item {
    justify-content: center;

    padding: 0;
  }

  .nav-text {
    display: none;
  }

  .sidebar-user {
    justify-content: center;

    padding: 5px 0 15px;
  }

  .sidebar-user-info {
    display: none;
  }

  .logout-btn {
    padding: 0;
  }

  .logout-text {
    display: none;
  }

  .topbar {
    padding: 0 28px;
  }

  .page-content {
    padding: 32px 28px 45px;
  }
}

@media (max-width: 700px) {
  .topbar {
    min-height: 75px;

    padding: 0 18px;
  }

  .topbar-left h1 {
    font-size: 18px;
  }

  .topbar-left p {
    display: none;
  }

  .date-info {
    display: none;
  }

  .topbar-divider {
    display: none;
  }

  .profile-text {
    display: none;
  }

  .page-content {
    padding: 25px 18px 40px;
  }

  .page-header {
    align-items: flex-start;

    flex-direction: column;
  }

  .refresh-btn {
    width: 100%;

    justify-content: center;
  }

  .statistics-grid {
    grid-template-columns: 1fr;
  }

  .filter-grid {
    grid-template-columns: 1fr;
  }

  .filter-action {
    grid-column: auto;
  }

  .search-btn {
    width: 100%;
  }

  .panel-header {
    align-items: flex-start;

    flex-direction: column;
  }

  .table-panel-header {
    flex-direction: row;

    align-items: center;
  }

  .detail-grid {
    grid-template-columns: 1fr;
  }

  .modal {
    max-height: 94vh;
  }

  .modal-body {
    padding: 18px;
  }
}

@media (max-width: 550px) {
  .sidebar {
    display: none;
  }

  .main-content {
    margin-left: 0;
  }

  .page-content {
    padding: 22px 14px 35px;
  }

  .topbar {
    padding: 0 14px;
  }

  .page-header h2 {
    font-size: 22px;
  }

  .stat-card {
    min-height: 125px;

    padding: 20px;
  }

  .stat-content strong {
    font-size: 25px;
  }

  .panel-header {
    padding: 19px;
  }

  .filter-grid {
    padding: 19px;
  }

  .modal-overlay {
    padding: 10px;
  }

  .modal-header {
    padding: 19px;
  }

  .modal-footer {
    padding: 15px 19px;
  }
}
</style>
