<template>
  <div class="satpam-page">
    <!-- Header -->
    <header class="top-header">
      <h1>Home</h1>

     
    </header>

    <main class="page-content">
      <!-- Welcome Card -->
      <section class="welcome-card">
        <div class="profile-avatar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8" />
          </svg>
        </div>

        <div class="welcome-info">
          <span class="welcome-label">Selamat Datang,</span>

          <h2>{{ user.name }}</h2>

          <p>
            Satpam ID:
            <strong>{{ satpamId }}</strong>
          </p>

          <div v-if="currentShift" class="shift-badge">
            <span class="status-dot"></span>
            Shift: {{ currentShift.label }} ({{ currentShift.shift_start }}-{{ currentShift.shift_end }})
          </div>
        </div>
      </section>

      <!-- Quick Actions -->
      <section class="section">
        <div class="section-heading">
          <h2>Aksi Cepat</h2>
        </div>

        <div class="quick-actions">
          <!-- Scan QR -->
          <button class="quick-card primary" type="button" @click="goToScan">
            <div class="quick-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="3" width="7" height="7" />
                <rect x="14" y="3" width="7" height="7" />
                <rect x="3" y="14" width="7" height="7" />
                <path d="M14 14h3v3h-3z" />
                <path d="M20 14v7h-3" />
              </svg>
            </div>

            <span>Scan QR</span>
          </button>

          <!-- Jadwal -->
          <button class="quick-card navy" type="button" @click="goToSchedule">
            <div class="quick-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="5" width="18" height="16" rx="2" />
                <path d="M16 3v4" />
                <path d="M8 3v4" />
                <path d="M3 10h18" />
              </svg>
            </div>

            <span>Jadwal Saya</span>
          </button>

          <!-- Riwayat -->
          <button class="quick-card outline" type="button" @click="goToHistory">
            <div class="quick-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 2" />
              </svg>
            </div>

            <span>Riwayat</span>
          </button>
        </div>
      </section>

      <!-- Ringkasan Hari Ini -->
      <section class="section">
        <div class="section-heading">
          <div>
            <h2>Ringkasan Hari Ini</h2>
            <p>{{ currentDate }}</p>
          </div>
        </div>

        <div class="summary-card">
          <!-- Terjadwal -->
          <div class="summary-item">
            <div class="summary-info">
              <div class="summary-icon schedule">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="5" width="18" height="16" rx="2" />
                  <path d="M16 3v4" />
                  <path d="M8 3v4" />
                  <path d="M3 10h18" />
                </svg>
              </div>

              <span>Patroli Terjadwal</span>
            </div>

            <strong>{{ summary.scheduled }}</strong>
          </div>

          <div class="summary-divider"></div>

          <!-- Selesai -->
          <div class="summary-item">
            <div class="summary-info">
              <div class="summary-icon completed">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
              </div>

              <span>Selesai</span>
            </div>

            <strong>{{ summary.completed }}</strong>
          </div>

          <div class="summary-divider"></div>

          <!-- Sisa -->
          <div class="summary-item">
            <div class="summary-info">
              <div class="summary-icon remaining">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="9" />
                  <path d="M12 7v5l3 2" />
                </svg>
              </div>

              <span>Sisa</span>
            </div>

            <strong>{{ summary.remaining }}</strong>
          </div>

          <div class="summary-divider"></div>

          <!-- Skip Scan -->
          <div class="summary-item">
            <div class="summary-info">
              <div class="summary-icon skip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M5 4l14 8-14 8V4z" />
                  <path d="M19 5v14" />
                </svg>
              </div>

              <span>Skip Scan</span>
            </div>

            <strong>{{ summary.skip }}</strong>
          </div>

          <div class="summary-divider"></div>

          <!-- Anomali -->
          <div class="summary-item">
            <div class="summary-info">
              <div class="summary-icon anomaly">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 9v4" />
                  <path d="M12 17h.01" />
                  <path
                    d="M10.3 3.9 2.7 17a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"
                  />
                </svg>
              </div>

              <span>Anomali</span>
            </div>

            <strong>{{ summary.anomaly }}</strong>
          </div>
        </div>
      </section>

      <!-- Informasi -->
      <section class="info-card">
        <div class="info-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 11v5" />
            <path d="M12 7h.01" />
          </svg>
        </div>

        <div>
          <h3>Informasi</h3>

          <p>
            Pastikan melakukan scan QR pada setiap titik patroli sesuai dengan jadwal yang telah
            ditentukan.
          </p>
        </div>
      </section>
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-navigation">
      <!-- Home -->
      <button class="nav-item active" type="button">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M3 10.5 12 3l9 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19.5v-9Z" />
        </svg>

        <span>Home</span>
      </button>

      <!-- Jadwal -->
      <button class="nav-item" type="button" @click="goToSchedule">
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
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";

const router = useRouter();

/*
|--------------------------------------------------------------------------
| Data User dari Login
|--------------------------------------------------------------------------
*/

const storedUser = localStorage.getItem("user");

const user = ref(
  storedUser
    ? JSON.parse(storedUser)
    : {
        name: "Satpam",
      },
);

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");

  router.push("/login");
};

const goToSchedule = () => {
  router.push({ name: "satpam-schedule" });
};

const goToHistory = () => {
  router.push({ name: "satpam-history" });
};
const goToScan = () => {
  router.push({ name: "satpam-scan" });
};

/*
|--------------------------------------------------------------------------
| Data sementara
|--------------------------------------------------------------------------
*/

const satpamId = ref("-");
const currentShift = ref(null);

const summary = ref({
  scheduled: 0,
  completed: 0,
  remaining: 0,
  skip: 0,
  anomaly: 0,
});

const getAuthHeaders = () => {
  const token = localStorage.getItem("token");
  return { headers: { Authorization: `Bearer ${token}` } };
};

const fetchSummary = async () => {
  try {
    const response = await axios.get("http://127.0.0.1:8000/api/satpam/summary", getAuthHeaders());

    summary.value = {
      scheduled: response.data.scheduled ?? 0,
      completed: response.data.completed ?? 0,
      remaining: response.data.remaining ?? 0,
      skip: response.data.skip ?? 0,
      anomaly: response.data.anomaly ?? 0,
    };

    satpamId.value = response.data.satpam_id || "-";
    currentShift.value = response.data.current_shift || null;
  } catch (err) {
    console.error(err);
  }
};

let summaryInterval = null;

onMounted(() => {
  fetchSummary();

  // Refresh berkala biar badge shift otomatis muncul/hilang
  // begitu jamnya masuk/lewat dari jendela shift saat ini.
  summaryInterval = setInterval(fetchSummary, 30000);
});

onBeforeUnmount(() => {
  if (summaryInterval) clearInterval(summaryInterval);
});

/*
|--------------------------------------------------------------------------
| Tanggal Hari Ini
|--------------------------------------------------------------------------
*/

const currentDate = computed(() => {
  return new Intl.DateTimeFormat("id-ID", {
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(new Date());
});
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");

/* =========================================
   COLOR PALETTE
========================================= */

.satpam-page {
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
  padding: 0 20px;
  border-bottom: 1px solid #e2e4ea;
}

.top-header h1 {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: #1f2454;
}

.notification-btn {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  border: none;
  background: #f4f5f7;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #1f2454;
  cursor: pointer;
}

.notification-btn svg {
  width: 23px;
  height: 23px;
}

.notification-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #e87500;
  border: 2px solid #f4f5f7;
  position: absolute;
  top: 7px;
  right: 6px;
}

/* =========================================
   MAIN CONTENT
========================================= */

.page-content {
  width: 100%;
  max-width: 460px;
  margin: 0 auto;
  padding: 20px 16px 30px;
  box-sizing: border-box;
}

/* =========================================
   WELCOME CARD
========================================= */

.welcome-card {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #1f2454 0%, #262c62 55%, #313880 100%);
  border-radius: 20px;
  padding: 22px 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  border: none;
  box-shadow: 0 16px 34px rgba(31, 36, 84, 0.28);
}

.welcome-card::before {
  content: "";
  position: absolute;
  top: -50px;
  right: -40px;
  width: 160px;
  height: 160px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(232, 117, 0, 0.35), transparent 70%);
  pointer-events: none;
}

.welcome-card::after {
  content: "";
  position: absolute;
  bottom: -60px;
  left: -30px;
  width: 130px;
  height: 130px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.06), transparent 70%);
  pointer-events: none;
}

.profile-avatar {
  width: 68px;
  height: 68px;
  flex-shrink: 0;
  border-radius: 50%;
  background: linear-gradient(135deg, #e87500, #f08b1a);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 18px rgba(232, 117, 0, 0.35);
  position: relative;
  z-index: 1;
}

.profile-avatar svg {
  width: 34px;
  height: 34px;
}

.welcome-info {
  min-width: 0;
  position: relative;
  z-index: 1;
}

.welcome-label {
  color: rgba(255, 255, 255, 0.65);
  font-size: 12px;
}

.welcome-info h2 {
  margin: 2px 0 5px;
  font-size: 18px;
  font-weight: 700;
  color: #ffffff;
}

.welcome-info p {
  margin: 0;
  font-size: 11px;
  color: rgba(255, 255, 255, 0.65);
}

.welcome-info strong {
  color: #ffffff;
}

.shift-badge {
  margin-top: 9px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 10px;
  font-weight: 700;
  color: #1f2454;
  background: #ffffff;
  padding: 5px 10px;
  border-radius: 20px;
}

.status-dot {
  width: 6px;
  height: 6px;
  background: #e87500;
  border-radius: 50%;
}

/* =========================================
   SECTION
========================================= */

.section {
  margin-top: 26px;
}

.section-heading {
  margin-bottom: 13px;
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
   QUICK ACTION
========================================= */

.quick-actions {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.quick-card {
  min-height: 112px;
  background: #ffffff;
  border: 1px solid #e2e4ea;
  border-radius: 15px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 9px;
  color: #1f2454;
  font-family: inherit;
  cursor: pointer;
  transition: 0.2s ease;
}

.quick-card:hover {
  transform: translateY(-2px);
}

.quick-card.outline:hover {
  border-color: #e87500;
}

.quick-card.primary {
  border: none;
  background: linear-gradient(135deg, #e87500, #f08b1a);
  color: #ffffff;
  box-shadow: 0 10px 22px rgba(232, 117, 0, 0.28);
}

.quick-card.primary:hover {
  background: linear-gradient(135deg, #f08b1a, #e87500);
}

.quick-card.navy {
  border: none;
  background: linear-gradient(135deg, #1f2454, #2f3675);
  color: #ffffff;
  box-shadow: 0 10px 22px rgba(31, 36, 84, 0.28);
}

.quick-card.navy:hover {
  background: linear-gradient(135deg, #262c62, #1f2454);
}

.quick-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #f4f5f7;
  color: #1f2454;
  display: flex;
  align-items: center;
  justify-content: center;
}

.primary .quick-icon {
  background: rgba(255, 255, 255, 0.18);
  color: #ffffff;
}

.navy .quick-icon {
  background: rgba(255, 255, 255, 0.12);
  color: #ffffff;
}

.quick-icon svg {
  width: 22px;
  height: 22px;
}

.quick-card span {
  font-size: 11px;
  font-weight: 600;
  text-align: center;
}

/* =========================================
   SUMMARY
========================================= */

.summary-card {
  background: #ffffff;
  border: 1px solid #e2e4ea;
  border-radius: 16px;
  overflow: hidden;
}

.summary-item {
  min-height: 61px;
  padding: 0 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.summary-info {
  display: flex;
  align-items: center;
  gap: 11px;
}

.summary-info span {
  font-size: 12px;
  color: #8a8d9c;
}

.summary-item strong {
  font-size: 18px;
  font-weight: 700;
  color: #1f2454;
}

.summary-icon {
  width: 31px;
  height: 31px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.summary-icon svg {
  width: 17px;
  height: 17px;
}

.schedule {
  background: #eef0f6;
  color: #1f2454;
}

.completed {
  background: #eaf7ef;
  color: #2d9b61;
}

.remaining {
  background: #fff3e8;
  color: #e87500;
}

.skip {
  background: #fff8e1;
  color: #b7791f;
}

.anomaly {
  background: #fff1f1;
  color: #d63031;
}

.summary-divider {
  height: 1px;
  margin: 0 16px;
  background: #e2e4ea;
}

/* =========================================
   INFORMATION
========================================= */

.info-card {
  margin-top: 26px;
  padding: 16px;
  border-radius: 15px;
  background: #eef0f6;
  border: 1px solid #d9dce8;
  border-left: 4px solid #e87500;
  display: flex;
  gap: 12px;
}

.info-icon {
  width: 34px;
  height: 34px;
  flex-shrink: 0;
  border-radius: 10px;
  background: linear-gradient(135deg, #1f2454, #292f6b);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.info-icon svg {
  width: 19px;
  height: 19px;
}

.info-card h3 {
  margin: 0 0 4px;
  font-size: 13px;
  font-weight: 700;
  color: #1f2454;
}

.info-card p {
  margin: 0;
  font-size: 11px;
  line-height: 1.6;
  color: #8a8d9c;
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
  .satpam-page {
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

  .quick-actions {
    gap: 7px;
  }

  .quick-card {
    min-height: 100px;
  }

  .welcome-card {
    padding: 16px;
  }
}
</style>
