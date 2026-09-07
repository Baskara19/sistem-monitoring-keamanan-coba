
<template>
  <div class="schedule-page">
    <!-- Header -->
    <header class="top-header">
      <button class="back-button" type="button" @click="goBack">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M15 18l-6-6 6-6" />
        </svg>
      </button>

      <h1>Jadwal Saya</h1>

      <div class="header-spacer"></div>
    </header>

    <main class="page-content">
      <!-- Date Picker -->
      <section class="date-picker-card">
        <label for="schedule-date">Pilih Tanggal</label>
        <input
          id="schedule-date"
          type="date"
          v-model="selectedDate"
          @change="fetchSchedule"
        />
      </section>

      <!-- Loading -->
      <div v-if="loading" class="state-card">
        <div class="loading-spinner"></div>
        <p>Memuat jadwal...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="state-card error-card">
        <div class="state-icon error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 9v4" />
            <path d="M12 17h.01" />
            <path
              d="M10.3 3.9 2.7 17a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"
            />
          </svg>
        </div>

        <h3>Gagal Memuat Jadwal</h3>
        <p>{{ error }}</p>

        <button class="retry-button" type="button" @click="fetchSchedule">
          Coba Lagi
        </button>
      </div>

      <!-- Empty -->
      <div v-else-if="scheduleDetails.length === 0" class="state-card">
        <div class="state-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M16 3v4" />
            <path d="M8 3v4" />
            <path d="M3 10h18" />
          </svg>
        </div>

        <h3>Belum Ada Jadwal</h3>
        <p>
          Belum ada jadwal patroli yang ditentukan untuk Anda
          {{ isToday ? "hari ini" : "pada tanggal ini" }}.
        </p>
      </div>

      <!-- Schedule -->
      <template v-else>
        <!-- Schedule Info -->
        <section class="schedule-header-card">
          <div class="calendar-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <rect x="3" y="5" width="18" height="16" rx="2" />
              <path d="M16 3v4" />
              <path d="M8 3v4" />
              <path d="M3 10h18" />
            </svg>
          </div>

          <div>
            <span class="label">{{ isToday ? "Jadwal Hari Ini" : "Jadwal Tanggal Dipilih" }}</span>
            <h2>{{ currentDate }}</h2>
            <p v-if="scheduleTitle">{{ scheduleTitle }}</p>
          </div>
        </section>

        <!-- Patrol Points -->
        <section class="section">
          <div class="section-heading">
            <div>
              <h2>Rute Patroli</h2>
              <p>{{ scheduleDetails.length }} titik patroli</p>
            </div>
          </div>

          <div class="timeline">
            <div
              v-for="(detail, index) in scheduleDetails"
              :key="detail.id"
              class="timeline-item"
            >
              <!-- Timeline -->
              <div class="timeline-left">
                <div
                  class="sequence"
                  :class="{ last: index === scheduleDetails.length - 1 }"
                >
                  {{ detail.sequence_order ?? index + 1 }}
                </div>

                <div
                  v-if="index !== scheduleDetails.length - 1"
                  class="timeline-line"
                ></div>
              </div>

              <!-- Card -->
              <div class="patrol-card">
                <div class="patrol-card-top">
                  <div class="patrol-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                      <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
                      <circle cx="12" cy="10" r="2.5" />
                    </svg>
                  </div>

                  <div class="patrol-info">
                    <span class="point-label">
                      Titik {{ detail.sequence_order ?? index + 1 }}
                    </span>

                    <h3>
                      {{ detail.patrol_point?.name ?? "Titik Patroli" }}
                    </h3>
                  </div>

                  <span
                    v-if="detail.scan_status_label"
                    class="status-badge"
                    :class="getStatusClass(detail.scan_status)"
                  >
                    {{ detail.scan_status_label }}
                  </span>
                </div>

                <div class="patrol-address" v-if="detail.patrol_point?.location_address">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M12 21s7-6.1 7-12a7 7 0 1 0-14 0c0 5.9 7 12 7 12Z" />
                    <circle cx="12" cy="9" r="2.5" />
                  </svg>

                  <span>{{ detail.patrol_point.location_address }}</span>
                </div>

                <div class="time-row">
                  <div class="time-item">
                    <span>Mulai</span>
                    <strong>{{ formatTime(detail.shift_start) }}</strong>
                  </div>

                  <div class="time-divider"></div>

                  <div class="time-item">
                    <span>Selesai</span>
                    <strong>{{ formatTime(detail.shift_end) }}</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </template>
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-navigation">
      <!-- Home -->
      <button class="nav-item" type="button" @click="goToDashboard">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M3 10.5 12 3l9 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19.5v-9Z" />
        </svg>

        <span>Home</span>
      </button>

      <!-- Jadwal -->
      <button class="nav-item active" type="button">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <rect x="3" y="5" width="18" height="16" rx="2" />
          <path d="M16 3v4" />
          <path d="M8 3v4" />
          <path d="M3 10h18" />
        </svg>

        <span>Jadwal</span>
      </button>

      <!-- Scan -->
      <button class="scan-navigation-button" type="button" @click="goToScan">
        <div>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
      <button class="nav-item" type="button" @click="goToHistory">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <circle cx="12" cy="12" r="9" />
          <path d="M12 7v5l3 2" />
        </svg>

        <span>Riwayat</span>
      </button>

      <!-- Logout -->
      <button class="nav-item logout" type="button" @click="logout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M10 17l5-5-5-5" />
          <path d="M15 12H3" />
          <path d="M13 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5" />
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

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const loading = ref(true);
const error = ref("");
const scheduleDetails = ref([]);

const toDateInputValue = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
};

const todayValue = toDateInputValue(new Date());
const selectedDate = ref(todayValue);

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

const getAuthHeaders = () => {
  const token = localStorage.getItem("token");

  return {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  };
};

/*
|--------------------------------------------------------------------------
| Fetch Schedule
|--------------------------------------------------------------------------
*/

const fetchSchedule = async () => {
  loading.value = true;
  error.value = "";

  try {
    const headers = getAuthHeaders();

    const response = await axios.get(
      "http://127.0.0.1:8000/api/satpam/schedule",
      {
        ...headers,
        params: { date: selectedDate.value },
      },
    );

    scheduleDetails.value = response.data.schedule ?? [];
  } catch (err) {
    console.error("Gagal mengambil jadwal:", err);

    if (err.response?.status === 401) {
      error.value = "Sesi login Anda sudah berakhir. Silakan login kembali.";
    } else if (err.response?.status === 404) {
      error.value = "Data satpam tidak ditemukan.";
    } else {
      error.value =
        err.response?.data?.message ??
        "Terjadi kesalahan saat mengambil data jadwal.";
    }
  } finally {
    loading.value = false;
  }
};

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const currentDate = computed(() => {
  const [year, month, day] = selectedDate.value.split("-").map(Number);

  return new Intl.DateTimeFormat("id-ID", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(new Date(year, month - 1, day));
});

const isToday = computed(() => selectedDate.value === todayValue);

const scheduleTitle = computed(() => {
  const first = scheduleDetails.value[0];

  return first?.schedule?.title ?? "";
});

/*
|--------------------------------------------------------------------------
| Format Time
|--------------------------------------------------------------------------
*/

const getStatusClass = (status) => {
  return (
    {
      berhasil: "success",
      terlambat: "late",
      skip: "skip",
      anomali: "anomaly",
      terlewat: "missed",
    }[status] || "default"
  );
};

const formatTime = (time) => {
  if (!time) {
    return "--:--";
  }

  return String(time).slice(0, 5);
};

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

const goBack = () => {
  router.push({ name: "satpam-dashboard" });
};

const goToDashboard = () => {
  router.push({ name: "satpam-dashboard" });
};

const goToScan = () => {
  router.push({ name: "satpam-scan" });
};

const goToHistory = () => {
  router.push({ name: "satpam-history" });
};

const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");

  router.push({ name: "login" });
};

/*
|--------------------------------------------------------------------------
| Mounted
|--------------------------------------------------------------------------
*/

onMounted(() => {
  fetchSchedule();
});
</script>

<style scoped>
* {
  box-sizing: border-box;
}

.schedule-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #f4f5f7 0%, #e9ebef 100%);
  color: #1f2454;
  font-family: "Segoe UI", Arial, sans-serif;
  padding-bottom: 110px;
}

/* =========================================
   HEADER
========================================= */

.top-header {
  height: 68px;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  border-bottom: 1px solid #e2e4ea;
}

.top-header h1 {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: #1f2454;
}

.back-button {
  width: 40px;
  height: 40px;
  border: none;
  border-radius: 50%;
  background: #f4f5f7;
  color: #1f2454;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.back-button svg {
  width: 21px;
  height: 21px;
}

.header-spacer {
  width: 40px;
}

/* =========================================
   MAIN
========================================= */

.page-content {
  width: 100%;
  max-width: 460px;
  margin: 0 auto;
  padding: 20px 16px 30px;
}

/* =========================================
   DATE PICKER
========================================= */

.date-picker-card {
  background: #ffffff;
  border: 1px solid #e2e4ea;
  border-radius: 14px;
  padding: 12px 14px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.date-picker-card label {
  font-size: 12px;
  font-weight: 600;
  color: #1f2454;
}

.date-picker-card input[type="date"] {
  border: 1px solid #d9dce8;
  border-radius: 9px;
  padding: 7px 10px;
  font-size: 12px;
  font-family: inherit;
  color: #1f2454;
  background: #f9fafc;
}

/* =========================================
   STATE
========================================= */

.state-card {
  background: #ffffff;
  border: 1px solid #e2e4ea;
  border-radius: 16px;
  padding: 35px 20px;
  text-align: center;
}

.state-card h3 {
  margin: 12px 0 6px;
  font-size: 15px;
  color: #1f2454;
}

.state-card p {
  margin: 0;
  color: #8a8d9c;
  font-size: 12px;
  line-height: 1.6;
}

.state-icon {
  width: 52px;
  height: 52px;
  margin: 0 auto;
  border-radius: 14px;
  background: #eef0f6;
  color: #1f2454;
  display: flex;
  align-items: center;
  justify-content: center;
}

.state-icon svg {
  width: 27px;
  height: 27px;
}

.state-icon.error {
  background: #fff1f1;
  color: #d63031;
}

.error-card {
  border-color: #f0d2d2;
}

.retry-button {
  margin-top: 18px;
  border: none;
  border-radius: 10px;
  padding: 10px 18px;
  background: #e87500;
  color: #ffffff;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.loading-spinner {
  width: 32px;
  height: 32px;
  margin: 0 auto 12px;
  border: 3px solid #e2e4ea;
  border-top-color: #e87500;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* =========================================
   SCHEDULE HEADER
========================================= */

.schedule-header-card {
  background: linear-gradient(135deg, #1f2454 0%, #2f3675 100%);
  border-radius: 18px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 14px 28px rgba(31, 36, 84, 0.2);
}

.calendar-icon {
  width: 48px;
  height: 48px;
  flex-shrink: 0;
  border-radius: 13px;
  background: rgba(255, 255, 255, 0.12);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.calendar-icon svg {
  width: 25px;
  height: 25px;
}

.schedule-header-card .label {
  display: block;
  color: rgba(255, 255, 255, 0.6);
  font-size: 10px;
  margin-bottom: 3px;
}

.schedule-header-card h2 {
  margin: 0;
  color: #ffffff;
  font-size: 16px;
  font-weight: 700;
}

.schedule-header-card p {
  margin: 3px 0 0;
  color: rgba(255, 255, 255, 0.7);
  font-size: 10px;
}

/* =========================================
   SECTION
========================================= */

.section {
  margin-top: 26px;
}

.section-heading {
  margin-bottom: 14px;
}

.section-heading h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: #1f2454;
}

.section-heading p {
  margin: 3px 0 0;
  color: #8a8d9c;
  font-size: 11px;
}

/* =========================================
   TIMELINE
========================================= */

.timeline-item {
  display: flex;
  gap: 10px;
}

.timeline-left {
  width: 32px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.sequence {
  width: 30px;
  height: 30px;
  flex-shrink: 0;
  border-radius: 50%;
  background: #e87500;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  box-shadow: 0 5px 12px rgba(232, 117, 0, 0.25);
  z-index: 2;
}

.timeline-line {
  width: 2px;
  flex: 1;
  min-height: 20px;
  background: #d9dce8;
}

/* =========================================
   PATROL CARD
========================================= */

.patrol-card {
  flex: 1;
  margin-bottom: 12px;
  background: #ffffff;
  border: 1px solid #e2e4ea;
  border-radius: 15px;
  padding: 14px;
}

.patrol-card-top {
  display: flex;
  align-items: center;
  gap: 11px;
}

.status-badge {
  flex-shrink: 0;
  margin-left: auto;
  padding: 4px 8px;
  border-radius: 20px;
  font-size: 9px;
  font-weight: 700;
  white-space: nowrap;
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

.status-badge.missed {
  background: #f1edff;
  color: #7a5cf0;
}

.status-badge.default {
  background: #eef0f6;
  color: #1f2454;
}

.patrol-icon {
  width: 38px;
  height: 38px;
  flex-shrink: 0;
  border-radius: 11px;
  background: #fff3e8;
  color: #e87500;
  display: flex;
  align-items: center;
  justify-content: center;
}

.patrol-icon svg {
  width: 20px;
  height: 20px;
}

.patrol-info {
  min-width: 0;
}

.point-label {
  display: block;
  color: #8a8d9c;
  font-size: 9px;
  margin-bottom: 2px;
}

.patrol-info h3 {
  margin: 0;
  color: #1f2454;
  font-size: 13px;
  font-weight: 700;
}

.patrol-address {
  margin-top: 11px;
  padding-top: 10px;
  border-top: 1px solid #eef0f4;
  display: flex;
  align-items: flex-start;
  gap: 7px;
  color: #8a8d9c;
  font-size: 10px;
  line-height: 1.5;
}

.patrol-address svg {
  width: 15px;
  height: 15px;
  flex-shrink: 0;
  color: #1f2454;
}

.time-row {
  margin-top: 11px;
  padding-top: 10px;
  border-top: 1px solid #eef0f4;
  display: flex;
  align-items: center;
}

.time-item {
  flex: 1;
}

.time-item span {
  display: block;
  color: #9a9dab;
  font-size: 9px;
  margin-bottom: 2px;
}

.time-item strong {
  color: #1f2454;
  font-size: 12px;
}

.time-divider {
  width: 1px;
  height: 25px;
  background: #e2e4ea;
}

/* =========================================
   BOTTOM NAVIGATION
========================================= */

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

/* =========================================
   SCAN BUTTON
========================================= */

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

/* =========================================
   DESKTOP PREVIEW
========================================= */

@media (min-width: 700px) {
  .schedule-page {
    max-width: 460px;
    margin: 0 auto;
    box-shadow: 0 0 40px rgba(31, 36, 84, 0.1);
  }

  .bottom-navigation {
    width: 460px;
  }
}

/* =========================================
   SMALL MOBILE
========================================= */

@media (max-width: 360px) {
  .page-content {
    padding-left: 12px;
    padding-right: 12px;
  }

  .schedule-header-card {
    padding: 16px;
  }

  .patrol-card {
    padding: 12px;
  }
}
</style>
