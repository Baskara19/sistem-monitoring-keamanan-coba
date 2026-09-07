<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";

const router = useRouter();

let refreshInterval = null;

onMounted(() => {
  loadUser();

  fetchDashboard();

  refreshInterval = setInterval(() => {
    fetchDashboard();
  }, 5000);
});

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval);
  }
});

/*
|--------------------------------------------------------------------------
| STATE
|--------------------------------------------------------------------------
*/

const loading = ref(true);
const error = ref("");

const selectedShift = ref("all");

const dashboard = ref({
  statistics: {
    total_satpam: 0,
    total_patroli: 0,
    selesai: 0,
    terlambat: 0,
    skip: 0,
    anomali: 0,
  },

  activities: [],

  guards: [],

  progress: {
    percentage: 0,
    completed: 0,
    skip: 0,
    late: 0,
    missed: 0,
  },

  stats: {
    total_reports: 0,
    pending_reports: 0,
    completed_patrols: 0,
    ongoing_patrols: 0,
    late_patrols: 0,
    missed_patrols: 0,
  },
});

/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

const user = ref({
  name: "Supervisor",
  role: "supervisor",
});

/*
|--------------------------------------------------------------------------
| AUTH HEADER
|--------------------------------------------------------------------------
*/

const getAuthHeaders = () => {
  const token = localStorage.getItem("token");

  return {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    },
  };
};

/*
|--------------------------------------------------------------------------
| LOAD USER
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| DISPLAY NAME
|--------------------------------------------------------------------------
*/

const displayName = computed(() => {
  return user.value?.name || "Supervisor";
});

/*
|--------------------------------------------------------------------------
| CURRENT DATE
|--------------------------------------------------------------------------
*/

const currentDate = computed(() => {
  return new Intl.DateTimeFormat("id-ID", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(new Date());
});

/*
|--------------------------------------------------------------------------
| PATROL PROGRESS
|--------------------------------------------------------------------------
*/

const patrolProgress = computed(() => {
  const total = Number(dashboard.value.statistics.total_patroli) || 0;
  const selesai = Number(dashboard.value.statistics.selesai) || 0;

  if (total === 0) {
    return 0;
  }

  return Math.round((selesai / total) * 100);
});
/*
|--------------------------------------------------------------------------
| FETCH DASHBOARD
|--------------------------------------------------------------------------
*/

const fetchDashboard = async (initialLoad = false) => {
  if (initialLoad) {
    loading.value = true;
  }

  try {
    const response = await axios.get(
      "http://127.0.0.1:8000/api/supervisor/dashboard",
      getAuthHeaders(),
    );

    const data = response.data;

    dashboard.value = {
      statistics: {
        total_satpam: data.statistics?.total_satpam ?? 0,
        total_patroli: data.statistics?.total_patroli ?? 0,
        selesai: data.statistics?.selesai ?? 0,
        terlambat: data.statistics?.terlambat ?? 0,
        skip: data.statistics?.skip ?? 0,
        anomali: data.statistics?.anomali ?? 0,
      },

      activities: Array.isArray(data.activities) ? data.activities : [],

      guards: Array.isArray(data.guards) ? data.guards : [],

      progress: {
        percentage: data.progress?.percentage ?? 0,
        completed: data.progress?.completed ?? 0,
        skip: data.progress?.skip ?? 0,
        late: data.progress?.late ?? 0,
        missed: data.progress?.missed ?? 0,
      },

      stats: {
        total_reports: data.stats?.total_reports ?? 0,
        pending_reports: data.stats?.pending_reports ?? 0,
        completed_patrols: data.stats?.completed_patrols ?? 0,
        ongoing_patrols: data.stats?.ongoing_patrols ?? 0,
        late_patrols: data.stats?.late_patrols ?? 0,
        missed_patrols: data.stats?.missed_patrols ?? 0,
      },
    };
  } catch (err) {
    console.error("Gagal mengambil dashboard:", err);
  } finally {
    if (initialLoad) {
      loading.value = false;
    }
  }
};

/*
|--------------------------------------------------------------------------
| FILTER SATPAM
|--------------------------------------------------------------------------
*/

const filteredGuards = computed(() => {
  if (selectedShift.value === "all") {
    return dashboard.value.guards;
  }

  return dashboard.value.guards.filter((guard) => {
    const shift = guard.shift?.toLowerCase() || "";

    return shift.includes(selectedShift.value.toLowerCase());
  });
});

/*
|--------------------------------------------------------------------------
| PROGRESS STYLE
|--------------------------------------------------------------------------
*/
const progressChartStyle = computed(() => {
  const completed = Number(dashboard.value.progress.completed) || 0;
  const skip = Number(dashboard.value.progress.skip) || 0;
  const late = Number(dashboard.value.progress.late) || 0;
  const missed = Number(dashboard.value.progress.missed) || 0;

  const total = completed + skip + late + missed;

  if (!total) {
    return {
      background: "#eceef2",
    };
  }

  const c1 = (completed / total) * 100;
  const c2 = c1 + (skip / total) * 100;
  const c3 = c2 + (late / total) * 100;

  return {
    background: `
      conic-gradient(
        #2f9e63 0% ${c1}%,
        #7c3aed ${c1}% ${c2}%,
        #e87500 ${c2}% ${c3}%,
        #d63031 ${c3}% 100%
      )
    `,
  };
});

/*
|--------------------------------------------------------------------------
| FORMAT TIME
|--------------------------------------------------------------------------
*/

const formatTime = (date) => {
  if (!date) {
    return "-";
  }

  try {
    return new Date(date).toLocaleTimeString("id-ID", {
      hour: "2-digit",
      minute: "2-digit",
    });
  } catch {
    return date;
  }
};

/*
|--------------------------------------------------------------------------
| FORMAT STATUS
|--------------------------------------------------------------------------
*/

const formatStatus = (status) => {
  const value = status?.toLowerCase();

  const statuses = {
    selesai: "Selesai",

    berhasil: "Berhasil",

    completed: "Selesai",

    berjalan: "Berjalan",

    ongoing: "Berjalan",

    terlambat: "Terlambat",

    late: "Terlambat",

    terlewat: "Terlewat",

    missed: "Terlewat",

    skip: "Skip",

    anomali: "Anomali",

    belum_mulai: "Belum Mulai",

    not_started: "Belum Mulai",
  };

  return statuses[value] || status || "-";
};

/*
|--------------------------------------------------------------------------
| STATUS CLASS
|--------------------------------------------------------------------------
*/

const statusClass = (status) => {
  const value = status?.toLowerCase();

  if (value === "selesai" || value === "berhasil" || value === "completed") {
    return "status-success";
  }

  if (value === "terlambat" || value === "late") {
    return "status-warning";
  }

  if (value === "terlewat" || value === "missed" || value === "anomali" || value === "skip") {
    return "status-danger";
  }

  if (value === "berjalan" || value === "ongoing") {
    return "status-info";
  }

  return "status-neutral";
};

/*
|--------------------------------------------------------------------------
| ACTIVITY CLASS
|--------------------------------------------------------------------------
*/

const activityClass = (activity) => {
  const status = activity?.scan_status?.toLowerCase();

  if (status === "berhasil" || status === "selesai" || status === "completed") {
    return "success";
  }

  if (status === "terlambat" || status === "late" || status === "skip") {
    return "warning";
  }

  if (status === "anomali" || status === "terlewat" || status === "missed") {
    return "danger";
  }

  return "success";
};

/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

const goToMonitoring = () => {
  router.push("/supervisor/monitoring");
};
const goToReports = () => {
  router.push("/supervisor/reports");
};

/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");

  router.push("/login");
};

/*
|--------------------------------------------------------------------------
| MOUNT
|--------------------------------------------------------------------------
*/

onMounted(() => {
  loadUser();

  // Loading hanya saat pertama kali
  fetchDashboard(true);

  // Selanjutnya ambil data tanpa menampilkan loading
  refreshInterval = setInterval(() => {
    fetchDashboard(false);
  }, 5000);
});
</script>

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

      <!-- NAVIGATION -->

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

      <!-- SIDEBAR FOOTER -->

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
      <!-- ===================================================
           TOPBAR
      ==================================================== -->

      <header class="topbar">
        <div class="page-heading">
          <h1>Dashboard Supervisor</h1>

          <p>Monitoring aktivitas patroli petugas keamanan hari ini.</p>
        </div>

        <div class="topbar-right">
          <div class="date-info">
            <span class="date-icon"> ▣ </span>

            <div>
              <small>Hari ini</small>
              <strong>{{ currentDate }}</strong>
            </div>
          </div>

          <div class="user-profile">
            <div class="profile-avatar">
              {{ displayName.charAt(0).toUpperCase() }}
            </div>

            <div class="profile-info">
              <strong>
                {{ displayName }}
              </strong>

              <span> Supervisor </span>
            </div>
          </div>
        </div>
      </header>

      <!-- ===================================================
           DASHBOARD
      ==================================================== -->

      <section class="dashboard-content">
        <!-- WELCOME -->

        <div class="welcome-section">
          <div>
            <span class="section-label"> OVERVIEW MONITORING </span>

            <h2>Ringkasan Patroli</h2>

            <p>Pantau aktivitas dan progress patroli petugas keamanan hari ini.</p>
          </div>

          <div class="system-status">
            <span class="status-dot"></span>

            <span> Sistem Aktif </span>
          </div>
        </div>

        <!-- ERROR -->

        <div v-if="error" class="error-alert">
          <span>⚠</span>

          <div>
            <strong> Terjadi kesalahan </strong>

            <p>
              {{ error }}
            </p>
          </div>

          <button @click="fetchDashboard">Coba Lagi</button>
        </div>

        <!-- =================================================
             STATISTICS
        ================================================== -->

        <section class="stats-grid">
          <!-- SATPAM AKTIF -->

          <div class="stat-card">
            <div class="stat-top">
              <div class="stat-icon users-icon">◉</div>

              <span class="stat-badge"> Hari Ini </span>
            </div>

            <div class="stat-content">
              <p>Satpam Aktif</p>

              <h3 v-if="!loading">
                {{ dashboard.statistics.total_satpam }}
              </h3>

              <div v-else class="number-skeleton"></div>

              <span> Petugas dengan jadwal hari ini </span>
            </div>
          </div>

          <!-- PATROLI -->

          <div class="stat-card">
            <div class="stat-top">
              <div class="stat-icon location-icon">✓</div>

              <span class="stat-badge"> Hari Ini </span>
            </div>

            <div class="stat-content">
              <p>Patroli Hari Ini</p>

              <h3 v-if="!loading">
                {{ dashboard.statistics.total_patroli }}
              </h3>

              <div v-else class="number-skeleton"></div>

              <span> Total patroli terjadwal </span>
            </div>
          </div>

          <!-- TERLAMBAT -->

          <div class="stat-card">
            <div class="stat-top">
              <div class="stat-icon warning-icon">!</div>

              <span class="stat-badge warning-badge"> Perhatian </span>
            </div>

            <div class="stat-content">
              <p>Terlambat</p>

              <h3 v-if="!loading">
                {{ dashboard.statistics.terlambat }}
              </h3>

              <div v-else class="number-skeleton"></div>

              <span> Patroli yang terlambat </span>
            </div>
          </div>

          <!-- LAPORAN -->

          <div class="stat-card accent-card">
            <div class="stat-top">
              <div class="stat-icon activity-icon">▤</div>

              <span class="stat-badge accent-badge"> Laporan </span>
            </div>

            <div class="stat-content">
              <p>Laporan Masuk</p>

              <h3 v-if="!loading">
                {{ dashboard.stats.total_reports }}
              </h3>

              <div v-else class="number-skeleton"></div>

              <span>
                {{ dashboard.stats.pending_reports }}
                belum ditinjau
              </span>
            </div>
          </div>
        </section>

        <!-- =================================================
             MAIN GRID
        ================================================== -->

        <section class="dashboard-grid">
          <!-- =================================================
               PROGRESS PATROLI
          ================================================== -->

          <div class="panel progress-panel">
            <div class="card-header">
              <div>
                <span class="card-label"> MONITORING </span>

                <h3>Progress Patroli</h3>
              </div>

              <select v-model="selectedShift">
                <option value="all">Semua Shift</option>

                <option value="pagi">Shift Pagi</option>

                <option value="siang">Shift Siang</option>

                <option value="malam">Shift Malam</option>
              </select>
            </div>

            <div class="progress-content">
              <!-- CIRCLE -->

              <div class="progress-circle" :style="progressChartStyle">
                <div class="circle-inner">
                  <strong v-if="!loading"> {{ patrolProgress }}% </strong>

                  <strong v-else> - </strong>

                  <span> Selesai </span>
                </div>
              </div>

              <!-- DETAIL -->

              <div class="progress-details">
                <div class="progress-item">
                  <span class="progress-dot completed"></span>

                  <div>
                    <strong>
                      {{ dashboard.progress.completed }}
                    </strong>

                    <small> Selesai </small>
                  </div>
                </div>

                <div class="progress-item">
                  <span class="progress-dot skip"></span>

                  <div>
                    <strong>
                      {{ dashboard.progress.skip }}
                    </strong>

                    <small> Skip Scan </small>
                  </div>
                </div>

                <div class="progress-item">
                  <span class="progress-dot late"></span>

                  <div>
                    <strong>
                      {{ dashboard.progress.late }}
                    </strong>

                    <small> Terlambat </small>
                  </div>
                </div>

                <div class="progress-item">
                  <span class="progress-dot missed"></span>

                  <div>
                    <strong>
                      {{ dashboard.progress.missed }}
                    </strong>

                    <small> Terlewat </small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- =================================================
               STATUS PATROLI
          ================================================== -->

          <div class="panel status-panel">
            <div class="card-header">
              <div>
                <span class="card-label"> STATUS </span>

                <h3>Status Patroli</h3>
              </div>
            </div>

            <div class="status-list">
              <div class="status-row">
                <div class="status-label">
                  <span class="status-dot green"></span>

                  Selesai
                </div>

                <strong>
                  {{ dashboard.stats.completed_patrols }}
                </strong>
              </div>

              <div class="status-row">
                <div class="status-label">
                  <span class="status-dot purple"></span>

                  Skip Scan
                </div>

                <strong>
                  {{ dashboard.statistics.skip }}
                </strong>
              </div>

              <div class="status-row">
                <div class="status-label">
                  <span class="status-dot orange"></span>

                  Terlambat
                </div>

                <strong>
                  {{ dashboard.stats.late_patrols }}
                </strong>
              </div>

              <div class="status-row">
                <div class="status-label">
                  <span class="status-dot red"></span>

                  Terlewat
                </div>

                <strong>
                  {{ dashboard.stats.missed_patrols }}
                </strong>
              </div>
            </div>
          </div>
        </section>

        <!-- =================================================
             MONITORING SATPAM
        ================================================== -->

        <section class="panel monitoring-panel">
          <div class="card-header">
            <div>
              <span class="card-label"> PETUGAS </span>

              <h3>Monitoring Satpam</h3>
            </div>

            <button class="text-button" @click="goToMonitoring">Lihat Semua →</button>
          </div>

          <!-- LOADING -->

          <div v-if="loading">
            <div class="row-skeleton"></div>
            <div class="row-skeleton"></div>
            <div class="row-skeleton"></div>
          </div>

          <!-- EMPTY -->

          <div v-else-if="filteredGuards.length === 0" class="empty-state">
            <div class="empty-icon">◌</div>

            <h4>Belum Ada Data Patroli</h4>

            <p>Data monitoring petugas akan muncul ketika terdapat jadwal patroli.</p>
          </div>

          <!-- TABLE -->

          <div v-else class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Satpam</th>

                  <th>Shift</th>

                  <th>Rute</th>

                  <th>Progress</th>

                  <th>Scan Terakhir</th>

                  <th>Status</th>
                </tr>
              </thead>

              <tbody>
                <tr v-for="guard in filteredGuards" :key="guard.id">
                  <td>
                    <div class="guard-info">
                      <div class="guard-avatar">
                        {{ guard.name ? guard.name.charAt(0).toUpperCase() : "?" }}
                      </div>

                      <div>
                        <strong>
                          {{ guard.name || "-" }}
                        </strong>

                        <small>
                          {{ guard.badge || guard.badge_number || "-" }}
                        </small>
                      </div>
                    </div>
                  </td>

                  <td>
                    <span class="shift-badge">
                      {{ guard.shift || "-" }}
                    </span>
                  </td>

                  <td>
                    {{ guard.route || guard.route_name || "-" }}
                  </td>

                  <td>
                    <div class="progress-wrapper">
                      <div class="progress-bar">
                        <div
                          class="progress-fill"
                          :style="{
                            width: `${guard.progress ?? 0}%`,
                          }"
                        ></div>
                      </div>

                      <span> {{ guard.progress ?? 0 }}% </span>
                    </div>
                  </td>

                  <td>
                    {{
                      guard.last_scan
                        ? formatTime(guard.last_scan)
                        : guard.lastScan
                          ? guard.lastScan
                          : "-"
                    }}
                  </td>

                  <td>
                    <span class="status-pill" :class="statusClass(guard.status)">
                      {{ formatStatus(guard.status) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- =================================================
             AKTIVITAS TERBARU
        ================================================== -->

        <section class="panel activity-panel">
          <div class="card-header">
            <div>
              <span class="card-label"> AKTIVITAS </span>

              <h3>Aktivitas Terbaru</h3>
            </div>

            <button class="text-button" @click="goToReports">Lihat Laporan →</button>
          </div>

          <!-- EMPTY -->

          <div v-if="!loading && dashboard.activities.length === 0" class="empty-state small-empty">
            <div class="empty-icon">◌</div>

            <h4>Belum Ada Aktivitas</h4>

            <p>Aktivitas scan QR terbaru akan muncul di sini.</p>
          </div>

          <!-- ACTIVITY -->

          <div v-else class="activity-list">
            <div v-for="activity in dashboard.activities" :key="activity.id" class="activity-item">
              <div class="activity-icon" :class="activityClass(activity)">✓</div>

              <div class="activity-content">
                <strong>
                  {{ activity.satpam_name || activity.guard_name || activity.name || "-" }}
                </strong>

                <span>
                  melakukan scan pada

                  <b>
                    {{
                      activity.patrol_point_name ||
                      activity.point_name ||
                      activity.patrol_point ||
                      "-"
                    }}
                  </b>
                </span>
              </div>

              <div class="activity-time">
                {{ formatTime(activity.scan_time || activity.created_at) }}
              </div>
            </div>
          </div>
        </section>
      </section>
    </main>
  </div>
</template>

<style scoped>
/* =========================================================
   COLOR SYSTEM
========================================================= */

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

  display: flex;

  background: linear-gradient(135deg, var(--background) 0%, var(--background-soft) 100%);

  font-family: "Segoe UI", Arial, sans-serif;

  color: var(--text-primary);
}

/* =========================================================
   SIDEBAR
========================================================= */

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

/* =========================================================
   NAVIGATION
========================================================= */

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

/* =========================================================
   SIDEBAR USER
========================================================= */

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

/* =========================================================
   MAIN
========================================================= */

.main-content {
  width: 100%;

  min-height: 100vh;

  margin-left: 260px;
}

/* =========================================================
   TOPBAR
========================================================= */

.topbar {
  min-height: 86px;

  padding: 0 42px;

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

.topbar-right {
  display: flex;
  align-items: center;

  gap: 22px;
}

.date-info {
  display: flex;
  align-items: center;

  gap: 10px;

  padding: 9px 13px;

  background: white;

  border: 1px solid var(--border);

  border-radius: 10px;
}

.date-icon {
  font-size: 18px;

  color: var(--accent);
}

.date-info small {
  display: block;

  color: var(--text-secondary);

  font-size: 10px;
}

.date-info strong {
  display: block;

  margin-top: 2px;

  font-size: 11px;
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

/* =========================================================
   CONTENT
========================================================= */

.dashboard-content {
  max-width: 1400px;

  margin: 0 auto;

  padding: 38px 42px 50px;

  box-sizing: border-box;
}

/* =========================================================
   WELCOME
========================================================= */

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

  background: var(--success);

  box-shadow: 0 0 10px rgba(47, 158, 99, 0.6);
}

/* =========================================================
   ERROR
========================================================= */

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

/* =========================================================
   STATS
========================================================= */

.stats-grid {
  display: grid;

  grid-template-columns: repeat(4, minmax(0, 1fr));

  gap: 22px;

  margin-bottom: 25px;
}

.stat-card {
  min-height: 190px;

  padding: 24px;

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
  background: rgba(47, 158, 99, 0.1);

  color: var(--success);
}

.warning-icon {
  background: rgba(232, 117, 0, 0.1);

  color: var(--warning);
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

.warning-badge {
  background: rgba(232, 117, 0, 0.1);

  color: var(--warning);
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

/* =========================================================
   DASHBOARD GRID
========================================================= */

.dashboard-grid {
  display: grid;

  grid-template-columns:
    minmax(0, 1.8fr)
    minmax(310px, 0.9fr);

  gap: 25px;

  margin-bottom: 25px;
}

.panel {
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

.card-header h3 {
  margin: 5px 0 0;

  color: var(--primary);

  font-size: 19px;
}

.card-header select {
  padding: 8px 10px;

  border: 1px solid var(--border);

  border-radius: 8px;

  background: white;

  color: var(--primary);

  font-size: 11px;

  cursor: pointer;
}

.text-button {
  border: none;

  background: transparent;

  color: var(--accent);

  font-size: 12px;

  font-weight: 700;

  cursor: pointer;
}

/* =========================================================
   PROGRESS
========================================================= */

.progress-content {
  display: flex;
  align-items: center;

  gap: 55px;

  min-height: 220px;
}

.progress-circle {
  width: 165px;
  height: 165px;

  flex-shrink: 0;

  border-radius: 50%;

  background: conic-gradient(var(--accent) calc(var(--progress, 0) * 1%), #eceef2 0);

  display: flex;
  align-items: center;
  justify-content: center;

  position: relative;
}

.progress-circle::before {
  content: "";

  position: absolute;

  inset: 0;

  border-radius: 50%;

  background: conic-gradient(var(--accent) 0deg, var(--accent) 270deg, #eceef2 270deg);

  display: none;
}

.circle-inner {
  width: 122px;
  height: 122px;

  border-radius: 50%;

  background: white;

  display: flex;
  flex-direction: column;

  justify-content: center;
  align-items: center;

  position: relative;
  z-index: 1;
}

.circle-inner strong {
  font-size: 30px;

  color: var(--primary);
}

.circle-inner span {
  margin-top: 4px;

  color: var(--text-secondary);

  font-size: 11px;
}

.progress-details {
  display: flex;
  flex-direction: column;

  gap: 18px;
}

.progress-item {
  display: flex;
  align-items: center;

  gap: 10px;
}

.progress-item strong {
  display: block;

  color: var(--primary);

  font-size: 16px;
}

.progress-item small {
  display: block;

  margin-top: 2px;

  color: var(--text-secondary);

  font-size: 10px;
}

.progress-dot {
  width: 10px;
  height: 10px;

  border-radius: 50%;
}

.progress-dot.completed {
  background: var(--success);
}

.progress-dot.skip {
  background: #7c3aed;
}

.progress-dot.late {
  background: var(--warning);
}

.progress-dot.missed {
  background: var(--danger);
}

/* =========================================================
   STATUS
========================================================= */

.status-list {
  display: flex;
  flex-direction: column;
}

.status-row {
  display: flex;
  align-items: center;

  justify-content: space-between;

  padding: 15px 0;

  border-bottom: 1px solid #eceef2;
}

.status-row:last-child {
  border-bottom: none;
}

.status-label {
  display: flex;
  align-items: center;

  gap: 10px;

  color: #5d6071;

  font-size: 12px;
}

.status-row strong {
  color: var(--primary);

  font-size: 14px;
}

.status-dot {
  width: 9px;
  height: 9px;

  border-radius: 50%;
}

.status-dot.green {
  background: var(--success);
}

.status-dot.blue {
  background: #3578e5;
}

.status-dot.orange {
  background: var(--warning);
}

.status-dot.red {
  background: var(--danger);
}

/* =========================================================
   MONITORING
========================================================= */

.monitoring-panel {
  margin-bottom: 25px;
}

.table-wrapper {
  overflow-x: auto;
}

table {
  width: 100%;

  border-collapse: collapse;

  min-width: 800px;
}

th {
  padding: 0 12px 14px;

  text-align: left;

  color: var(--text-secondary);

  font-size: 10px;

  text-transform: uppercase;

  letter-spacing: 0.7px;
}

td {
  padding: 14px 12px;

  border-top: 1px solid #eceef2;

  color: #5d6071;

  font-size: 12px;
}

.guard-info {
  display: flex;
  align-items: center;

  gap: 9px;
}

.guard-avatar {
  width: 34px;
  height: 34px;

  display: flex;
  align-items: center;
  justify-content: center;

  border-radius: 10px;

  background: rgba(31, 36, 84, 0.1);

  color: var(--primary);

  font-size: 11px;

  font-weight: 800;
}

.guard-info strong {
  display: block;

  color: var(--primary);

  font-size: 12px;
}

.guard-info small {
  display: block;

  margin-top: 2px;

  color: var(--text-secondary);

  font-size: 10px;
}

.shift-badge {
  display: inline-block;

  padding: 5px 9px;

  border-radius: 6px;

  background: #f1f2f6;

  color: #626575;

  font-size: 10px;

  font-weight: 600;
}

.progress-wrapper {
  display: flex;
  align-items: center;

  gap: 8px;
}

.progress-bar {
  width: 80px;
  height: 6px;

  overflow: hidden;

  border-radius: 10px;

  background: #e5e7eb;
}

.progress-fill {
  height: 100%;

  border-radius: 10px;

  background: var(--accent);

  transition: width 0.4s ease;
}

.progress-wrapper span {
  color: var(--text-secondary);

  font-size: 10px;
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

.status-warning {
  background: rgba(232, 117, 0, 0.12);

  color: var(--warning);
}

.status-danger {
  background: rgba(214, 48, 49, 0.1);

  color: var(--danger);
}

.status-info {
  background: rgba(53, 120, 229, 0.1);

  color: var(--info);
}

.status-neutral {
  background: #f1f2f6;

  color: var(--text-secondary);
}

/* =========================================================
   ACTIVITY
========================================================= */

.activity-panel {
  margin-bottom: 20px;
}

.activity-list {
  display: flex;
  flex-direction: column;
}

.activity-item {
  display: flex;
  align-items: center;

  gap: 12px;

  padding: 13px 0;

  border-bottom: 1px solid #eceef2;
}

.activity-item:last-child {
  border-bottom: none;
}

.activity-icon {
  width: 34px;
  height: 34px;

  flex-shrink: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  border-radius: 10px;

  font-size: 12px;

  font-weight: 700;
}

.activity-icon.success {
  background: rgba(47, 158, 99, 0.12);

  color: var(--success);
}

.activity-icon.warning {
  background: rgba(232, 117, 0, 0.12);

  color: var(--warning);
}

.activity-icon.danger {
  background: rgba(214, 48, 49, 0.1);

  color: var(--danger);
}

.activity-content {
  flex: 1;
}

.activity-content strong {
  color: var(--primary);

  font-size: 12px;

  margin-right: 4px;
}

.activity-content span {
  color: var(--text-secondary);

  font-size: 12px;
}

.activity-content b {
  color: #4c4f5f;
}

.activity-time {
  color: var(--text-secondary);

  font-size: 10px;
}

/* =========================================================
   EMPTY
========================================================= */

.empty-state {
  min-height: 220px;

  display: flex;
  flex-direction: column;

  align-items: center;
  justify-content: center;

  text-align: center;
}

.small-empty {
  min-height: 180px;
}

.empty-icon {
  width: 58px;
  height: 58px;

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

  font-size: 14px;
}

.empty-state p {
  max-width: 300px;

  margin: 7px 0 0;

  color: var(--text-secondary);

  font-size: 11px;

  line-height: 1.7;
}

/* =========================================================
   LOADING
========================================================= */

.number-skeleton,
.row-skeleton {
  position: relative;

  overflow: hidden;

  background: #eceef2;

  border-radius: 7px;
}

.number-skeleton {
  width: 80px;
  height: 38px;

  margin: 8px 0;
}

.row-skeleton {
  width: 100%;
  height: 55px;

  margin-bottom: 10px;
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

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1150px) {
  .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
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
  .sidebar-user-info {
    display: none;
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

    font-size: 0;
  }

  .nav-icon {
    font-size: 20px;
  }

  .user-sidebar {
    justify-content: center;
  }

  .logout-button {
    justify-content: center;

    padding: 0;

    font-size: 0;
  }

  .logout-button span {
    font-size: 20px;
  }

  .main-content {
    margin-left: 76px;
  }

  .topbar {
    padding: 0 25px;
  }

  .dashboard-content {
    padding: 30px 25px 40px;
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
    min-height: auto;

    padding: 18px 20px;
  }

  .topbar-right {
    gap: 0;
  }

  .date-info {
    display: none;
  }

  .profile-info {
    display: none;
  }

  .dashboard-content {
    padding: 25px 20px 40px;
  }

  .welcome-section {
    align-items: flex-start;

    flex-direction: column;

    gap: 18px;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .progress-content {
    flex-direction: column;

    gap: 30px;
  }

  .progress-details {
    width: 100%;
  }
}
.status-dot.purple {
  background: #7c3aed;
}
</style>
