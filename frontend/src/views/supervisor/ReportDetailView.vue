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
         MAIN
    ====================================================== -->
    <main class="main-content">
      <!-- TOPBAR -->
      <header class="topbar">
        <div class="topbar-left">
          <div>
            <h1>Laporan</h1>
            <p>Detail laporan patroli petugas keamanan</p>
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

      <!-- =====================================================
           CONTENT
      ====================================================== -->
      <div class="page-content">
        <!-- BACK -->
        <button class="back-button" @click="goBack">
          <span>←</span>
          Kembali ke Daftar Laporan
        </button>

        <!-- LOADING -->
        <div v-if="loading" class="state-card">
          <div class="loader"></div>

          <h3>Memuat detail laporan</h3>

          <p>Sistem sedang mengambil data laporan.</p>
        </div>

        <!-- ERROR -->
        <div v-else-if="error" class="state-card error-card">
          <div class="error-icon">⚠</div>

          <h3>Gagal memuat laporan</h3>

          <p>{{ error }}</p>

          <button class="retry-btn" @click="fetchDetail">Coba Lagi</button>
        </div>

        <!-- DETAIL -->
        <template v-else-if="report">
          <!-- PAGE TITLE -->
          <div class="detail-heading">
            <div>
              <span class="section-label"> DETAIL LAPORAN </span>

              <h2>
                {{ report.report_title || "Detail Patroli" }}
              </h2>

              <p>Informasi lengkap aktivitas patroli petugas keamanan.</p>
            </div>

            <span class="status-badge large-status" :class="getStatusClass(report.scan_status)">
              <span class="status-dot"></span>
              {{ formatStatus(report.scan_status) }}
            </span>
          </div>

          <!-- =================================================
               THREE COLUMN
          ================================================== -->
          <div class="detail-layout">
            <!-- ===============================================
                 LEFT COLUMN
            ================================================ -->
            <div class="left-column">
              <!-- INFORMASI LAPORAN -->
              <section class="detail-card">
                <div class="card-header">
                  <div>
                    <span class="card-kicker"> INFORMASI </span>

                    <h3>Informasi Laporan</h3>
                  </div>

                  <div class="card-icon">▤</div>
                </div>

                <div class="info-list">
                  <div class="info-row">
                    <span>ID Laporan</span>

                    <strong> LAP-{{ String(report.id).padStart(6, "0") }} </strong>
                  </div>

                  <div class="info-row">
                    <span>Satpam</span>

                    <strong>
                      {{ report.satpam_name || "-" }}
                    </strong>
                  </div>

                  <div class="info-row">
                    <span>Badge Number</span>

                    <strong>
                      {{ report.badge_number || "-" }}
                    </strong>
                  </div>

                  <div class="info-row">
                    <span>Titik Patroli</span>

                    <strong>
                      {{ report.patrol_point || "-" }}
                    </strong>
                  </div>

                  <div class="info-row">
                    <span>Waktu</span>

                    <strong>
                      {{ formatDateTime(report.scan_time) }}
                    </strong>
                  </div>

                  <div class="info-row">
                    <span>Status</span>

                    <span class="status-badge" :class="getStatusClass(report.scan_status)">
                      <span class="status-dot"></span>

                      {{ formatStatus(report.scan_status) }}
                    </span>
                  </div>
                </div>
              </section>

              <!-- FOTO -->
              <section class="detail-card photo-card">
                <div class="card-header">
                  <div>
                    <span class="card-kicker"> BUKTI </span>

                    <h3>Foto Bukti</h3>
                  </div>

                  <div class="card-icon">▣</div>
                </div>

                <div v-if="report.report_photo" class="photo-container">
                  <img :src="getPhotoUrl(report.report_photo)" alt="Foto laporan" />
                </div>

                <div v-else class="no-photo">
                  <div class="no-photo-icon">▧</div>

                  <strong> Tidak ada foto </strong>

                  <span> Tidak ada foto bukti pada laporan ini. </span>
                </div>
              </section>
            </div>

            <!-- ===============================================
                 MIDDLE COLUMN
            ================================================ -->
            <div class="middle-column">
              <!-- CATATAN -->
              <section class="detail-card note-card">
                <div class="card-header">
                  <div>
                    <span class="card-kicker"> CATATAN </span>

                    <h3>Catatan</h3>
                  </div>

                  <div class="card-icon">▤</div>
                </div>

                <div v-if="report.note" class="note-content">
                  {{ report.note }}
                </div>

                <div v-else class="empty-content">
                  Tidak ada catatan tambahan untuk laporan ini.
                </div>
              </section>


              <!-- ISI LAPORAN / SKIP SCAN -->
              <section
                v-if="report.report_id || report.scan_status === 'skip'"
                class="detail-card report-card"
              >
                <div class="card-header">
                  <div>
                    <span class="card-kicker">
                      {{ report.scan_status === "skip" ? "SKIP SCAN" : "LAPORAN" }}
                    </span>

                    <h3>
                      {{ report.scan_status === "skip" ? "Skip Scan" : "Isi Laporan" }}
                    </h3>
                  </div>

                  <div class="card-icon">▤</div>
                </div>

                <div class="report-content">
                  <!-- ==============================
         LAPORAN BIASA
    =============================== -->
                  <template v-if="report.report_id">
                    <div class="report-title-box">
                      <span>Judul Laporan</span>

                      <strong>
                        {{ report.report_title || "-" }}
                      </strong>
                    </div>

                    <div class="description-box">
                      <span>Deskripsi</span>

                      <p>
                        {{ report.report_description || "Tidak ada deskripsi laporan." }}
                      </p>
                    </div>

                    <!-- REVIEW LAPORAN -->
                    <div class="review-section">
                      <div class="review-header">
                        <span>Status Review</span>

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

                        <span v-else class="review-badge review-pending">
                          <span class="review-dot"></span>
                          Belum Ditinjau
                        </span>
                      </div>

                      <button
                        v-if="report.review_status !== 'reviewed'"
                        class="review-button"
                        :disabled="reviewing"
                        @click="reviewReport"
                      >
                        <span v-if="reviewing" class="button-loader"></span>

                        <span v-else>✓</span>

                        {{ reviewing ? "Memproses..." : "Tandai Sudah Ditinjau" }}
                      </button>
                    </div>
                  </template>

                  <!-- ==============================
         SKIP SCAN
    =============================== -->
                  <template v-else-if="report.scan_status === 'skip'">
                    <div class="report-title-box">
                      <span>Status Patrol</span>

                      <strong> Skip Scan </strong>
                    </div>

                    <div class="description-box">
                      <span>Alasan Skip</span>

                      <p>
                        {{ report.skip_reason || "Tidak ada alasan skip." }}
                      </p>
                    </div>

                    <!-- REVIEW SKIP -->
                    <div class="review-section">
                      <div class="review-header">
                        <span>Status Review</span>

                        <span
                          v-if="report.skip_review_status === 'pending'"
                          class="review-badge review-pending"
                        >
                          <span class="review-dot"></span>
                          Belum Ditinjau
                        </span>

                        <span
                          v-else-if="report.skip_review_status === 'reviewed'"
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

                      <button
                        v-if="report.skip_review_status !== 'reviewed'"
                        class="review-button"
                        :disabled="reviewing"
                        @click="reviewSkip"
                      >
                        <span v-if="reviewing" class="button-loader"></span>

                        <span v-else>✓</span>

                        {{ reviewing ? "Memproses..." : "Tandai Sudah Ditinjau" }}
                      </button>
                    </div>
                  </template>
                </div>
              </section>
            </div>

            <!-- ===============================================
                 RIGHT COLUMN - TIMELINE
            ================================================ -->
            <section class="detail-card timeline-card">
              <div class="card-header">
                <div>
                  <span class="card-kicker"> AKTIVITAS </span>

                  <h3>Timeline Patroli</h3>
                </div>

                <div class="card-icon">◉</div>
              </div>

              <!-- TIMELINE PATROLI -->
              <div class="timeline-section">
                <div class="section-title">
                  <h3>Timeline Patroli</h3>
                  <p>Urutan perjalanan patroli berdasarkan rute yang dijadwalkan</p>
                </div>

                <div v-if="report?.patrol_timeline?.length" class="patrol-timeline">
                  <div
                    v-for="(item, index) in report.patrol_timeline"
                    :key="item.schedule_detail_id"
                    class="timeline-item"
                  >
                    <!-- Nomor titik -->
                    <div class="timeline-marker">
                      <span>{{ index + 1 }}</span>
                    </div>

                    <!-- Garis penghubung -->
                    <div
                      v-if="index < report.patrol_timeline.length - 1"
                      class="timeline-line"
                    ></div>

                    <!-- Isi -->
                    <div class="timeline-content">
                      <div class="timeline-point-header">
                        <div>
                          <h4>{{ item.patrol_point_name }}</h4>

                          <span class="timeline-sequence"> Titik {{ index + 1 }} </span>
                        </div>

                        <span class="timeline-status" :class="getStatusClass(item.status)">
                          {{ formatStatus(item.status) }}
                        </span>
                      </div>

                      <div class="timeline-time">
                        <span v-if="item.scan_time">
                          {{ formatTimelineTime(item.scan_time) }}
                        </span>

                        <span v-else class="not-done"> Belum dilakukan </span>
                      </div>

                      <p v-if="item.note" class="timeline-note">
                        {{ item.note }}
                      </p>
                    </div>
                  </div>
                </div>

                <div v-else class="empty-timeline">
                  <p>Timeline patroli belum tersedia.</p>
                </div>
              </div>
            </section>
          </div>
        </template>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";

import axios from "axios";

import { useRouter, useRoute } from "vue-router";

// =====================================================
// ROUTER
// =====================================================

const router = useRouter();

const route = useRoute();

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
// DATE
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

const report = ref(null);

const loading = ref(false);

const error = ref("");
const reviewing = ref(false);

// =====================================================
// FETCH DETAIL
// =====================================================

const fetchDetail = async () => {
  loading.value = true;

  error.value = "";

  try {
    const token = localStorage.getItem("token");

    const response = await axios.get("https://sistem-monitoring-keamanan-be.onrender.com/api/supervisor/reports", {
      headers: {
        Authorization: `Bearer ${token}`,

        Accept: "application/json",
      },
    });

    const reports = response.data.reports || [];

    const id = Number(route.params.id);

    const found = reports.find((item) => Number(item.id) === id);

    if (!found) {
      error.value = "Data laporan tidak ditemukan.";

      return;
    }

    report.value = found;
  } catch (err) {
    console.error("ERROR FETCH REPORT DETAIL:", err);

    if (err.response?.status === 401) {
      error.value = "Sesi login sudah berakhir. Silakan login kembali.";
    } else {
      error.value = err.response?.data?.message || "Gagal mengambil detail laporan.";
    }
  } finally {
    loading.value = false;
  }
};

// =====================================================
// REVIEW REPORT
// =====================================================

const reviewReport = async () => {
  if (!report.value?.report_id) {
    return;
  }

  if (report.value.review_status === "reviewed") {
    return;
  }

  reviewing.value = true;

  try {
    const token = localStorage.getItem("token");

    const response = await axios.put(
      `https://sistem-monitoring-keamanan-be.onrender.com/api/supervisor/reports/${report.value.report_id}/review`,
      {},
      {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/json",
        },
      },
    );

    report.value.review_status = response.data.report.review_status;
  } catch (err) {
    console.error("ERROR REVIEW REPORT:", err);

    if (err.response?.status === 401) {
      error.value = "Sesi login sudah berakhir. Silakan login kembali.";
    } else {
      alert(err.response?.data?.message || "Gagal menandai laporan sebagai sudah ditinjau.");
    }
  } finally {
    reviewing.value = false;
  }
};

const reviewSkip = async () => {
  if (!report.value?.skip_reason_id) {
    return;
  }

  if (report.value.skip_review_status === "reviewed") {
    return;
  }

  reviewing.value = true;

  try {
    const token = localStorage.getItem("token");

    const response = await axios.put(
      `https://sistem-monitoring-keamanan-be.onrender.com/api/supervisor/skips/${report.value.skip_reason_id}/review`,
      {},
      {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/json",
        },
      },
    );

    report.value.skip_review_status = response.data.skip.review_status;
  } catch (err) {
    console.error("ERROR REVIEW SKIP:", err);

    if (err.response?.status === 401) {
      error.value = "Sesi login sudah berakhir. Silakan login kembali.";
    } else {
      alert(err.response?.data?.message || "Gagal menandai Skip sebagai sudah ditinjau.");
    }
  } finally {
    reviewing.value = false;
  }
};

// =====================================================
// BACK
// =====================================================

const goBack = () => {
  router.push("/supervisor/reports");
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

const formatDateTime = (date) => {
  if (!date) return "-";

  return `${formatDate(date)} ${formatTime(date)}`;
};

const formatStatus = (status) => {
  if (!status) return "-";

  const labels = {
    berhasil: "Berhasil",

    terlambat: "Terlambat",

    skip: "Skip",

    anomali: "Anomali",

    terlewat: "Terlewat",

    belum: "Belum Dilakukan",
  };

  return labels[status] || status;
};

const formatTimelineTime = (time) => {
  if (!time) return "Belum dilakukan";

  const date = new Date(time);

  return (
    date.toLocaleTimeString("id-ID", {
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
    }) + " WIB"
  );
};

const getStatusClass = (status) => {
  return (
    {
      berhasil: "status-success",

      terlambat: "status-warning",

      skip: "status-skip",

      anomali: "status-danger",

      terlewat: "status-danger",

      belum: "status-default",
    }[status] || "status-default"
  );
};

const getPhotoUrl = (photo) => {
  if (!photo) return "";

  if (photo.startsWith("http")) {
    return photo;
  }

  return `https://sistem-monitoring-keamanan-be.onrender.com/storage/${photo}`;
};

// =====================================================
// INITIAL LOAD
// =====================================================

onMounted(() => {
  loadUser();

  fetchDetail();
});
</script>

<style scoped>
/* =====================================================
   VARIABLES
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

/* =========================================================
   BRAND
========================================================= */

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

  color: var(--primary);

  font-size: 22px;

  font-weight: 800;
}

.topbar-left p {
  margin: 0;

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
}

.date-info div {
  display: flex;

  flex-direction: column;
}

.date-label {
  color: var(--text-secondary);

  font-size: 10px;
}

.date-info strong {
  margin-top: 2px;

  color: var(--primary);

  font-size: 12px;
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
   PAGE
===================================================== */

.page-content {
  max-width: 1400px;

  margin: 0 auto;

  padding: 32px 42px 50px;
}

/* BACK */

.back-button {
  display: flex;

  align-items: center;

  gap: 7px;

  margin-bottom: 20px;

  padding: 0;

  border: none;

  background: none;

  color: var(--accent);

  font-family: inherit;

  font-size: 12px;

  font-weight: 700;

  cursor: pointer;
}

.back-button:hover {
  color: var(--accent-light);
}

.back-button span {
  font-size: 17px;
}

/* HEADING */

.detail-heading {
  display: flex;

  align-items: flex-end;

  justify-content: space-between;

  gap: 20px;

  margin-bottom: 25px;
}

.section-label {
  display: inline-block;

  margin-bottom: 6px;

  color: var(--accent);

  font-size: 9px;

  font-weight: 900;

  letter-spacing: 0.13em;
}

.detail-heading h2 {
  margin: 0 0 5px;

  color: var(--primary);

  font-size: 25px;

  font-weight: 800;
}

.detail-heading p {
  margin: 0;

  color: var(--text-secondary);

  font-size: 12px;
}

/* =====================================================
   DETAIL GRID
===================================================== */

.detail-layout {
  display: grid;

  grid-template-columns:
    minmax(270px, 1fr)
    minmax(300px, 1.2fr)
    minmax(280px, 1fr);

  gap: 20px;

  align-items: stretch;
}

.left-column,
.middle-column {
  display: flex;

  flex-direction: column;

  gap: 20px;
}

/* =====================================================
   CARD
===================================================== */

.detail-card {
  box-sizing: border-box;

  overflow: hidden;

  background: rgba(255, 255, 255, 0.94);

  border: 1px solid var(--border);

  border-radius: 18px;

  box-shadow: 0 8px 24px rgba(31, 36, 84, 0.045);
}

.card-header {
  min-height: 66px;

  box-sizing: border-box;

  display: flex;

  align-items: center;

  justify-content: space-between;

  gap: 15px;

  padding: 18px 20px;

  border-bottom: 1px solid var(--border);
}

.card-kicker {
  display: block;

  margin-bottom: 4px;

  color: var(--accent);

  font-size: 8px;

  font-weight: 900;

  letter-spacing: 0.12em;
}

.card-header h3 {
  margin: 0;

  color: var(--primary);

  font-size: 15px;

  font-weight: 800;
}

.card-icon {
  width: 34px;
  height: 34px;

  flex-shrink: 0;

  display: flex;

  align-items: center;
  justify-content: center;

  border-radius: 9px;

  background: rgba(31, 36, 84, 0.07);

  color: var(--primary);

  font-size: 14px;

  font-weight: 800;
}

.card-icon.orange {
  background: rgba(232, 117, 0, 0.1);

  color: var(--accent);
}

/* =====================================================
   INFORMATION
===================================================== */

.info-list {
  padding: 8px 20px 17px;
}

.info-row {
  min-height: 48px;

  display: grid;

  grid-template-columns: 105px 1fr;

  align-items: center;

  gap: 10px;

  border-bottom: 1px solid #f0f1f4;
}

.info-row:last-child {
  border-bottom: none;
}

.info-row > span:first-child {
  color: var(--text-secondary);

  font-size: 10px;
}

.info-row strong {
  color: var(--primary);

  font-size: 11px;
}

/* =====================================================
   PHOTO
===================================================== */

.photo-card {
  flex: 1;
}

.photo-container {
  margin: 18px;

  overflow: hidden;

  border-radius: 12px;

  border: 1px solid var(--border);

  background: #f5f6f8;
}

.photo-container img {
  display: block;

  width: 100%;

  max-height: 310px;

  object-fit: contain;
}

.no-photo {
  min-height: 230px;

  display: flex;

  flex-direction: column;

  align-items: center;

  justify-content: center;

  gap: 7px;

  padding: 20px;

  text-align: center;
}

.no-photo-icon {
  width: 58px;
  height: 58px;

  display: flex;

  align-items: center;
  justify-content: center;

  margin-bottom: 6px;

  border-radius: 15px;

  background: rgba(31, 36, 84, 0.07);

  color: var(--primary);

  font-size: 25px;
}

.no-photo strong {
  color: var(--primary);

  font-size: 12px;
}

.no-photo span {
  color: var(--text-secondary);

  font-size: 10px;
}

/* =====================================================
   NOTE
===================================================== */

.note-card {
  min-height: 300px;
}

.note-content {
  min-height: 230px;

  box-sizing: border-box;

  padding: 22px;

  color: #454957;

  font-size: 13px;

  line-height: 1.7;
}

.empty-content {
  min-height: 230px;

  display: flex;

  align-items: center;

  justify-content: center;

  padding: 25px;

  text-align: center;

  color: var(--text-secondary);

  font-size: 12px;
}

/* =====================================================
   REPORT
===================================================== */

.report-content {
  padding: 18px 20px 20px;
}

.report-title-box {
  display: flex;

  flex-direction: column;

  gap: 5px;

  margin-bottom: 15px;

  padding: 13px;

  border-radius: 10px;

  background: #f8f9fb;
}

.report-title-box span,
.description-box > span {
  color: var(--text-secondary);

  font-size: 9px;

  font-weight: 700;
}

.report-title-box strong {
  color: var(--primary);

  font-size: 12px;
}

.description-box {
  padding: 14px;

  border-radius: 10px;

  background: #f8f9fb;
}

.description-box p {
  margin: 7px 0 0;

  color: #515563;

  font-size: 12px;

  line-height: 1.7;
}

/* =====================================================
   SKIP
===================================================== */

.skip-content {
  margin: 18px 20px 20px;

  padding: 14px;

  border: 1px solid rgba(232, 117, 0, 0.13);

  border-radius: 10px;

  background: #fff8ef;

  color: #8b5700;

  font-size: 12px;

  line-height: 1.6;
}

/* =====================================================
   STATUS
===================================================== */

.status-badge {
  display: inline-flex;

  align-items: center;

  gap: 6px;

  width: fit-content;

  padding: 6px 10px;

  border-radius: 20px;

  font-size: 10px;

  font-weight: 800;

  white-space: nowrap;
}

.large-status {
  padding: 8px 13px;

  font-size: 11px;
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
   REVIEW STATUS
===================================================== */

.review-section {
  margin-top: 18px;

  padding-top: 16px;

  border-top: 1px solid var(--border);
}

.review-header {
  display: flex;

  align-items: center;

  justify-content: space-between;

  gap: 10px;

  margin-bottom: 12px;
}

.review-header > span:first-child {
  color: var(--text-secondary);

  font-size: 10px;

  font-weight: 700;
}

.review-badge {
  display: inline-flex;

  align-items: center;

  gap: 5px;

  padding: 5px 9px;

  border-radius: 20px;

  font-size: 9px;

  font-weight: 800;

  white-space: nowrap;
}

.review-dot {
  width: 5px;
  height: 5px;

  flex-shrink: 0;

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

.review-button {
  width: 100%;

  min-height: 42px;

  display: flex;

  align-items: center;

  justify-content: center;

  gap: 8px;

  padding: 0 14px;

  border: none;

  border-radius: 10px;

  background: linear-gradient(135deg, var(--primary), var(--primary-light));

  color: white;

  font-family: inherit;

  font-size: 11px;

  font-weight: 700;

  cursor: pointer;

  transition: 0.25s;
}

.review-button:hover:not(:disabled) {
  transform: translateY(-1px);

  box-shadow: 0 7px 18px rgba(31, 36, 84, 0.18);
}

.review-button:disabled {
  opacity: 0.65;

  cursor: not-allowed;
}

.button-loader {
  width: 13px;
  height: 13px;

  border: 2px solid rgba(255, 255, 255, 0.35);

  border-top-color: white;

  border-radius: 50%;

  animation: spin 0.7s linear infinite;
}

/* =====================================================
   TIMELINE
===================================================== */

.timeline-card {
  min-height: 100%;
}

.timeline-section {
  padding: 20px;
}

.section-title {
  margin-bottom: 22px;
}

.section-title h3 {
  margin: 0 0 4px;
  color: var(--primary);
  font-size: 13px;
  font-weight: 800;
}

.section-title p {
  margin: 0;
  color: var(--text-secondary);
  font-size: 10px;
  line-height: 1.5;
}

/* TIMELINE CONTAINER */

.patrol-timeline {
  position: relative;
}

/* ITEM */

.timeline-item {
  position: relative;
  display: flex;
  gap: 13px;
  min-height: 92px;
}

/* MARKER */

.timeline-marker {
  position: relative;
  z-index: 2;

  width: 34px;
  height: 34px;

  flex-shrink: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  margin-top: 1px;

  border-radius: 50%;

  background: #eef0f4;
  border: 2px solid #dfe2e8;

  color: #7b7f8c;

  font-size: 12px;
  font-weight: 800;
}

/* TITIK YANG SUDAH DILAKUKAN */

.timeline-item:has(.status-success) .timeline-marker {
  background: rgba(47, 158, 99, 0.1);
  border-color: rgba(47, 158, 99, 0.25);
  color: var(--success);
}

/* TITIK TERLAMBAT */

.timeline-item:has(.status-warning) .timeline-marker {
  background: rgba(232, 117, 0, 0.1);
  border-color: rgba(232, 117, 0, 0.25);
  color: var(--warning);
}

/* TITIK ERROR / ANOMALI */

.timeline-item:has(.status-danger) .timeline-marker {
  background: rgba(214, 48, 49, 0.1);
  border-color: rgba(214, 48, 49, 0.25);
  color: var(--danger);
}

/* GARIS */

.timeline-line {
  position: absolute;

  left: 16px;
  top: 36px;
  bottom: 0;

  width: 2px;

  background: #e5e7ec;

  z-index: 1;
}

/* CONTENT */

.timeline-content {
  flex: 1;

  min-width: 0;

  padding: 0 0 22px;
}

/* HEADER TITIK */

.timeline-point-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;

  gap: 10px;
}

/* NAMA TITIK */

.timeline-point-header h4 {
  margin: 0 0 3px;

  color: var(--primary);

  font-size: 12px;
  font-weight: 800;

  line-height: 1.4;
}

/* NOMOR TITIK */

.timeline-sequence {
  display: block;

  margin: 0 !important;

  color: var(--text-secondary) !important;

  font-size: 9px !important;
}

/* STATUS */

.timeline-status {
  display: inline-flex !important;

  align-items: center;

  flex-shrink: 0;

  width: fit-content;

  margin: 0 !important;

  padding: 5px 8px;

  border-radius: 20px;

  font-size: 9px !important;
  font-weight: 800;

  white-space: nowrap;
}

/* WAKTU */

.timeline-time {
  margin-top: 7px;
}

.timeline-time span {
  display: inline-block;

  margin: 0 !important;

  color: #555a68;

  font-size: 10px;

  font-weight: 600;
}

.timeline-time .not-done {
  color: #a0a3ad;

  font-style: italic;
}

/* CATATAN */

.timeline-note {
  margin: 7px 0 0;

  padding: 7px 9px;

  border-radius: 7px;

  background: #f8f9fb;

  color: #686c78;

  font-size: 9px;

  line-height: 1.5;
}

/* EMPTY */

.empty-timeline {
  min-height: 180px;

  display: flex;

  align-items: center;
  justify-content: center;

  padding: 20px;

  text-align: center;
}

.empty-timeline p {
  margin: 0;

  color: var(--text-secondary);

  font-size: 11px;
}
/* =====================================================
   STATE
===================================================== */

.state-card {
  min-height: 350px;

  display: flex;

  flex-direction: column;

  align-items: center;
  justify-content: center;

  padding: 30px;

  background: rgba(255, 255, 255, 0.94);

  border: 1px solid var(--border);

  border-radius: 18px;

  text-align: center;
}

.state-card h3 {
  margin: 14px 0 5px;

  color: var(--primary);

  font-size: 15px;
}

.state-card p {
  margin: 0;

  color: var(--text-secondary);

  font-size: 12px;
}

.loader {
  width: 32px;
  height: 32px;

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

.error-icon {
  width: 55px;
  height: 55px;

  display: flex;

  align-items: center;
  justify-content: center;

  border-radius: 15px;

  background: rgba(214, 48, 49, 0.09);

  color: var(--danger);

  font-size: 22px;
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

/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 1150px) {
  .detail-layout {
    grid-template-columns: 1fr 1fr;
  }

  .timeline-card {
    grid-column: span 2;

    min-height: auto;
  }

  .timeline {
    display: grid;

    grid-template-columns: 1fr 1fr;

    column-gap: 25px;
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
    padding: 30px 28px 45px;
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
    padding: 24px 18px 40px;
  }

  .detail-heading {
    align-items: flex-start;

    flex-direction: column;
  }

  .detail-layout {
    grid-template-columns: 1fr;
  }

  .timeline-card {
    grid-column: auto;
  }

  .timeline {
    display: block;
  }

  .large-status {
    align-self: flex-start;
  }
}

@media (max-width: 550px) {
  .sidebar {
    display: none;
  }

  .main-content {
    margin-left: 0;
  }

  .topbar {
    padding: 0 14px;
  }

  .page-content {
    padding: 22px 14px 35px;
  }

  .detail-heading h2 {
    font-size: 21px;
  }

  .detail-card {
    border-radius: 15px;
  }

  .info-row {
    grid-template-columns: 90px 1fr;
  }

  .timeline {
    padding: 20px 17px;
  }
}
</style>
