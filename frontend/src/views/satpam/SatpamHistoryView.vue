<template>
  <div class="history-page">
    <!-- Header -->
    <header class="top-header">
      <h1>Riwayat</h1>
    </header>

    <main class="page-content">
      <!-- Filter -->
      <section class="filter-row">
        <input
          type="date"
          v-model="selectedDate"
          class="filter-select"
          @change="onDateChange"
        />

        <select v-model="filterStatus" class="filter-select">
          <option value="">Semua Status</option>
          <option value="berhasil">Berhasil</option>
          <option value="terlambat">Terlambat</option>
          <option value="skip">Skip Scan</option>
          <option value="anomali">Anomali</option>
          <option value="terlewat">Terlewat</option>
        </select>
      </section>

      <!-- Loading -->
      <div v-if="loading" class="state-card">
        <p>Memuat riwayat patroli...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="state-card error-card">
        <p>{{ error }}</p>

        <button type="button" @click="fetchHistory">
          Coba Lagi
        </button>
      </div>

      <!-- Empty -->
      <div v-else-if="filteredHistory.length === 0" class="state-card">
        <div class="empty-icon">
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
          >
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
          </svg>
        </div>

        <h2>Belum Ada Riwayat</h2>

        <p>
          {{
            !selectedDate
              ? "Riwayat patroli akan muncul setelah Anda melakukan scan QR."
              : "Belum ada riwayat patroli pada tanggal ini."
          }}
        </p>
      </div>

      <!-- History -->
      <section v-else class="history-section">
        <div class="section-heading">
          <div>
            <h2>Riwayat Patroli</h2>
            <p>{{ filteredHistory.length }} aktivitas ditemukan</p>
          </div>
        </div>

        <div class="history-list">
          <div
            v-for="item in filteredHistory"
            :key="item.id"
            class="history-card"
          >
            <!-- Icon -->
            <div
              class="history-icon"
              :class="getStatusClass(item.scan_status)"
            >
              <svg
                v-if="item.scan_status === 'berhasil'"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M20 6 9 17l-5-5" />
              </svg>

              <svg
                v-else-if="item.scan_status === 'skip'"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M5 4l14 8-14 8V4z" />
                <path d="M19 5v14" />
              </svg>

              <svg
                v-else-if="item.scan_status === 'anomali'"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M12 9v4" />
                <path d="M12 17h.01" />
                <path
                  d="M10.3 3.9 2.7 17a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"
                />
              </svg>

              <svg
                v-else-if="item.scan_status === 'terlewat'"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              >
                <path d="M13 5l7 7-7 7" />
                <path d="M4 5l7 7-7 7" />
              </svg>

              <svg
                v-else
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
              >
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 2" />
              </svg>
            </div>

            <!-- Content -->
            <div class="history-content">
              <div class="history-top">
                <h3>
                  {{ item.patrol_point?.name || "Titik Patroli" }}
                </h3>

                <span
                  class="status-badge"
                  :class="getStatusClass(item.scan_status)"
                >
                  {{ getStatusLabel(item.scan_status) }}
                </span>
              </div>

              <p class="location">
                {{
                  item.patrol_point?.location_address ||
                  "Lokasi tidak tersedia"
                }}
              </p>

              <div class="history-meta">
                <span>
                  <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                  >
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 2" />
                  </svg>

                  {{ formatDateTime(item.scan_time) }}
                </span>

                <span v-if="item.schedule_detail">
                  <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                  >
                    <rect x="3" y="5" width="18" height="16" rx="2" />
                    <path d="M16 3v4" />
                    <path d="M8 3v4" />
                    <path d="M3 10h18" />
                  </svg>

                  Shift {{ item.schedule_detail.shift_label }}
                  ({{ formatShiftTime(item.schedule_detail.shift_start) }}-{{ formatShiftTime(item.schedule_detail.shift_end) }})
                </span>
              </div>

              <!-- Skip Reason -->
              <div
                v-if="item.scan_status === 'skip' && item.skip_reason"
                class="note-box"
              >
                <strong>Alasan Skip</strong>

                <p>
                  {{ item.skip_reason.reason }}
                </p>
              </div>

              <!-- Note -->
              <div
                v-if="item.scan_status !== 'skip' && item.note"
                class="note-box"
              >
                <strong>Catatan</strong>

                <p>
                  {{ item.note }}
                </p>
              </div>

              <!-- Report -->
              <div
                v-if="item.report"
                class="report-box"
              >
                <strong>Laporan</strong>

                <p>
                  {{ item.report.title }}
                </p>

                <p
                  v-if="item.report.description"
                  class="report-description"
                >
                  {{ item.report.description }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-navigation">
      <!-- Home -->
      <button
        class="nav-item"
        type="button"
        @click="goToHome"
      >
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
        >
          <path
            d="M3 10.5 12 3l9 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19.5v-9Z"
          />
        </svg>

        <span>Home</span>
      </button>

      <!-- Jadwal -->
      <button
        class="nav-item"
        type="button"
        @click="goToSchedule"
      >
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
        >
          <rect x="3" y="5" width="18" height="16" rx="2" />
          <path d="M16 3v4" />
          <path d="M8 3v4" />
          <path d="M3 10h18" />
        </svg>

        <span>Jadwal</span>
      </button>

      <!-- Scan -->
      <button
        class="scan-navigation-button"
        type="button"
        @click="goToScan"
      >
        <div>
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path d="M4 8V4h4" />
            <path d="M20 8V4h-4" />
            <path d="M4 16v4h4" />
            <path d="M20 16v4h-4" />
            <path d="M7 7h10v10H7z" />
          </svg>
        </div>

        <span>Scan</span>
      </button>

      <!-- Riwayat -->
      <button
        class="nav-item active"
        type="button"
      >
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
        >
          <circle cx="12" cy="12" r="9" />
          <path d="M12 7v5l3 2" />
        </svg>

        <span>Riwayat</span>
      </button>

      <!-- Logout -->
      <button
        class="nav-item logout"
        type="button"
        @click="logout"
      >
        <svg
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
        >
          <path d="M10 17l5-5-5-5" />
          <path d="M15 12H3" />
          <path
            d="M13 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5"
          />
        </svg>

        <span>Log Out</span>
      </button>
    </nav>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";

const router = useRouter();

const history = ref([]);
const loading = ref(false);
const error = ref("");

const toDateInputValue = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
};

const selectedDate = ref(toDateInputValue(new Date()));
const filterStatus = ref("");

const filteredHistory = computed(() => {
  if (! filterStatus.value) {
    return history.value;
  }

  return history.value.filter((item) => item.scan_status === filterStatus.value);
});

const getAuthHeaders = () => {
  const token = localStorage.getItem("token");

  return {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  };
};

const fetchHistory = async () => {
  loading.value = true;
  error.value = "";

  try {
    const headers = getAuthHeaders();

    const response = await axios.get(
      "https://sistem-monitoring-keamanan-be.onrender.com/api/satpam/history",
      {
        ...headers,
        params: { date: selectedDate.value || "all" },
      },
    );

    history.value = response.data.history || [];
  } catch (err) {
    console.error("Gagal mengambil riwayat:", err);

    if (err.response?.status === 401) {
      error.value = "Sesi login Anda sudah berakhir.";
    } else {
      error.value = "Gagal mengambil data riwayat patroli.";
    }
  } finally {
    loading.value = false;
  }
};

const onDateChange = () => {
  fetchHistory();
};

const getStatusLabel = (status) => {
  const labels = {
    berhasil: "Berhasil",
    terlambat: "Terlambat",
    skip: "Skip",
    anomali: "Anomali",
    terlewat: "Terlewat",
  };

  return labels[status] || status || "Tidak diketahui";
};

const getStatusClass = (status) => {
  return {
    berhasil: "success",
    terlambat: "late",
    skip: "skip",
    anomali: "anomaly",
    terlewat: "missed",
  }[status] || "default";
};

const formatDateTime = (date) => {
  if (!date) {
    return "-";
  }

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(date));
};

const formatShiftTime = (value) => {
  if (!value) return "-";
  return value.slice(0, 5);
};

const goToHome = () => {
  router.push({
    name: "satpam-dashboard",
  });
};

const goToSchedule = () => {
  router.push({
    name: "satpam-schedule",
  });
};

const goToScan = () => {
  router.push({
    name: "satpam-scan",
  });
};

const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");

  router.push({
    name: "login",
  });
};

onMounted(() => {
  fetchHistory();
});
</script>

<style scoped>
.history-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #f4f5f7 0%, #e9ebef 100%);
  color: #1f2454;
  font-family: "Segoe UI", Arial, sans-serif;
  padding-bottom: 110px;
}

/* HEADER */

.top-header {
  height: 68px;
  background: #ffffff;
  display: flex;
  align-items: center;
  padding: 0 20px;
  border-bottom: 1px solid #e2e4ea;
}

.top-header h1 {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
}

/* CONTENT */

.page-content {
  width: 100%;
  max-width: 460px;
  margin: 0 auto;
  padding: 20px 16px 30px;
  box-sizing: border-box;
}

/* FILTER */

.filter-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 16px;
}

.filter-select {
  flex: 1;
  min-width: 130px;
  border: 1px solid #d9dce8;
  border-radius: 10px;
  padding: 9px 10px;
  font-size: 12px;
  font-family: inherit;
  color: #1f2454;
  background: #ffffff;
}

/* SECTION */

.section-heading {
  margin-bottom: 13px;
}

.section-heading h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.section-heading p {
  margin: 3px 0 0;
  color: #8a8d9c;
  font-size: 11px;
}

/* STATE */

.state-card {
  background: #ffffff;
  border: 1px solid #e2e4ea;
  border-radius: 16px;
  padding: 32px 20px;
  text-align: center;
}

.state-card p {
  margin: 0;
  color: #8a8d9c;
  font-size: 13px;
}

.error-card p {
  margin-bottom: 15px;
  color: #d63031;
}

.error-card button {
  border: none;
  border-radius: 9px;
  padding: 9px 16px;
  background: #1f2454;
  color: #ffffff;
  font-family: inherit;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.empty-icon {
  width: 50px;
  height: 50px;
  margin: 0 auto 14px;
  border-radius: 14px;
  background: #eef0f6;
  color: #1f2454;
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-icon svg {
  width: 25px;
  height: 25px;
}

.state-card h2 {
  margin: 0 0 6px;
  font-size: 16px;
}

.state-card p {
  line-height: 1.6;
}

/* HISTORY LIST */

.history-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.history-card {
  background: #ffffff;
  border: 1px solid #e2e4ea;
  border-radius: 16px;
  padding: 15px;
  display: flex;
  gap: 12px;
}

/* ICON */

.history-icon {
  width: 38px;
  height: 38px;
  flex-shrink: 0;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.history-icon svg {
  width: 19px;
  height: 19px;
}

.history-icon.success {
  background: #eaf7ef;
  color: #2d9b61;
}

.history-icon.late {
  background: #fff3e8;
  color: #e87500;
}

.history-icon.skip {
  background: #fff8e1;
  color: #b7791f;
}

.history-icon.anomaly {
  background: #fff1f1;
  color: #d63031;
}

.history-icon.default {
  background: #eef0f6;
  color: #1f2454;
}

.history-icon.missed {
  background: #f1edff;
  color: #7a5cf0;
}

/* CONTENT */

.history-content {
  min-width: 0;
  flex: 1;
}

.history-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.history-top h3 {
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  color: #1f2454;
}

.status-badge {
  flex-shrink: 0;
  padding: 4px 8px;
  border-radius: 20px;
  font-size: 9px;
  font-weight: 700;
}

.status-badge.success {
  background: #eaf7ef;
  color: #2d9b61;
}

.status-badge.late {
  background: #fff3e8;
  color: #e87500;
}

.status-badge.skip {
  background: #fff8e1;
  color: #b7791f;
}

.status-badge.anomaly {
  background: #fff1f1;
  color: #d63031;
}

.status-badge.default {
  background: #eef0f6;
  color: #1f2454;
}

.status-badge.missed {
  background: #f1edff;
  color: #7a5cf0;
}

.location {
  margin: 4px 0 8px;
  color: #8a8d9c;
  font-size: 10px;
  line-height: 1.5;
}

.history-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  color: #8a8d9c;
  font-size: 10px;
}

.history-meta span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.history-meta svg {
  width: 13px;
  height: 13px;
}

/* NOTES */

.note-box,
.report-box {
  margin-top: 10px;
  padding: 9px 10px;
  border-radius: 9px;
  background: #f4f5f7;
}

.note-box strong,
.report-box strong {
  display: block;
  margin-bottom: 3px;
  font-size: 10px;
}

.note-box p,
.report-box p {
  margin: 0;
  color: #8a8d9c;
  font-size: 10px;
  line-height: 1.5;
}

.report-box {
  background: #eef0f6;
}

.report-description {
  margin-top: 3px !important;
}

/* BOTTOM NAVIGATION */

.bottom-navigation {
  position: fixed;
  z-index: 10;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  max-width: 460px;
  height: 78px;
  background: #ffffff;
  border-top: 1px solid #e2e4ea;
  display: flex;
  align-items: center;
  justify-content: space-around;
  padding: 0 5px;
  box-sizing: border-box;
  box-shadow: 0 -5px 20px rgba(31, 36, 84, 0.05);
}

.nav-item,
.scan-navigation-button {
  border: none;
  background: transparent;
  font-family: inherit;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #9a9dab;
  gap: 4px;
  min-width: 54px;
}

.nav-item svg {
  width: 21px;
  height: 21px;
}

.nav-item span,
.scan-navigation-button span {
  font-size: 9px;
  font-weight: 600;
}

.nav-item.active {
  color: #e87500;
}

.logout {
  color: #d63031;
}

/* SCAN BUTTON */

.scan-navigation-button {
  color: #1f2454;
  transform: translateY(-16px);
}

.scan-navigation-button div {
  width: 54px;
  height: 54px;
  border-radius: 50%;
  background: linear-gradient(135deg, #e87500, #f08b1a);
  border: 5px solid #f4f5f7;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 7px 18px rgba(232, 117, 0, 0.3);
}

.scan-navigation-button svg {
  width: 25px;
  height: 25px;
}

.scan-navigation-button span {
  margin-top: 1px;
  color: #e87500;
}

/* DESKTOP */

@media (min-width: 700px) {
  .history-page {
    max-width: 460px;
    margin: 0 auto;
    box-shadow: 0 0 40px rgba(31, 36, 84, 0.1);
  }

  .bottom-navigation {
    width: 460px;
  }
}
</style>