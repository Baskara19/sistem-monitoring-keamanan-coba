
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
      <div v-else-if="!hasSchedule" class="state-card">
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

        <!-- Incoming Handover Requests Banner -->
        <section v-if="pendingHandovers.length > 0" class="pending-handover-section">
          <div class="pending-handover-banner" v-for="item in pendingHandovers" :key="item.id">
            <div class="pending-banner-header">
              <div class="pending-banner-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17 1l4 4-4 4"/>
                  <path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                  <path d="M7 23l-4-4 4-4"/>
                  <path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                </svg>
              </div>
              <div class="pending-banner-title">
                <span class="pending-banner-tag">Permintaan Handover Masuk</span>
                <h4>Dari: {{ item.from_satpam_name }}</h4>
              </div>
            </div>

            <div class="pending-banner-body">
              <div class="pending-info-row">
                <span>Titik Patroli:</span>
                <strong>{{ item.patrol_point_name }} (Putaran {{ item.patrol_round }})</strong>
              </div>
              <div class="pending-info-row" v-if="item.reason">
                <span>Alasan:</span>
                <em>"{{ item.reason }}"</em>
              </div>
            </div>

            <div class="pending-banner-actions">
              <button
                type="button"
                class="btn-respond-accept"
                :disabled="respondingId === item.id"
                @click="respondHandover(item.id, 'accept')"
              >
                {{ respondingId === item.id ? 'Memproses...' : 'Terima Handover' }}
              </button>
              <button
                type="button"
                class="btn-respond-reject"
                :disabled="respondingId === item.id"
                @click="respondHandover(item.id, 'reject')"
              >
                Tolak
              </button>
            </div>
          </div>
        </section>

        <!-- Round Tabs Selector (4 Putaran) -->
        <section v-if="isGroupedRound" class="round-tabs-section">
          <div class="round-tabs">
            <button
              v-for="(r, idx) in rounds"
              :key="r.round"
              type="button"
              class="round-tab-btn"
              :class="{ active: activeRoundIndex === idx }"
              @click="activeRoundIndex = idx"
            >
              <span class="tab-round-title">Putaran {{ r.round }}</span>
              <span class="tab-round-time">{{ r.target_time }}</span>
            </button>
          </div>
        </section>

        <!-- Patrol Points -->
        <section class="section">
          <div class="section-heading">
            <div>
              <h2>{{ isGroupedRound ? `Putaran ${currentRoundData?.round} — Mulai Scan ${currentRoundData?.target_time}` : "Rute Patroli" }}</h2>
              <p>{{ displayedPoints.length }} titik patroli</p>
            </div>
          </div>

          <div class="timeline">
            <div
              v-for="(point, index) in displayedPoints"
              :key="point.schedule_detail_id || point.id || index"
              class="timeline-item"
            >
              <!-- Timeline -->
              <div class="timeline-left">
                <div
                  class="sequence"
                  :class="{ last: index === displayedPoints.length - 1 }"
                >
                  {{ point.sequence_order ?? index + 1 }}
                </div>

                <div
                  v-if="index !== displayedPoints.length - 1"
                  class="timeline-line"
                ></div>
              </div>

              <!-- Card -->
              <div class="patrol-card" :class="{ 'card-handover-accepted': point.handover?.status === 'accepted' }">
                <div class="patrol-card-top">
                  <div class="patrol-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                      <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
                      <circle cx="12" cy="10" r="2.5" />
                    </svg>
                  </div>

                  <div class="patrol-info">
                    <span class="point-label">
                      Titik {{ point.sequence_order ?? index + 1 }}
                    </span>

                    <h3>
                      {{ point.patrol_point_name || point.patrol_point?.name || "Titik Patroli" }}
                    </h3>
                  </div>

                  <span
                    v-if="point.scan_status_label"
                    class="status-badge"
                    :class="getStatusClass(point.scan_status_label || point.scan_status)"
                  >
                    {{ point.scan_status_label }}
                  </span>
                  <span
                    v-else
                    class="status-badge default"
                  >
                    Belum Scan
                  </span>
                </div>

                <div class="patrol-address" v-if="point.location_address || point.patrol_point?.location_address">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M12 21s7-6.1 7-12a7 7 0 1 0-14 0c0 5.9 7 12 7 12Z" />
                    <circle cx="12" cy="9" r="2.5" />
                  </svg>

                  <span>{{ point.location_address || point.patrol_point?.location_address }}</span>
                </div>

                <div class="time-row">
                  <div class="time-item">
                    <span>Mulai Scan</span>
                    <strong>{{ isGroupedRound ? (currentRoundData?.target_time || "-") : formatTime(point.shift_start) }}</strong>
                  </div>

                  <div class="time-divider"></div>

                  <div class="time-item">
                    <span>Waktu Scan</span>
                    <strong v-if="point.scan_time">{{ formatScanTime(point.scan_time) }}</strong>
                    <span v-else-if="point.handover?.status === 'accepted' && point.handover?.is_sender" class="text-handover-waiting">Menunggu Rekan</span>
                    <span v-else class="text-not-scanned">Belum</span>
                  </div>
                </div>

                <!-- Handover Status Banner inside Card -->
                <div v-if="point.handover" class="handover-status-box" :class="point.handover.status">
                  <div class="handover-meta">
                    <span class="handover-pill" :class="point.handover.is_sender ? (point.handover.status === 'accepted' ? 'accepted' : 'pending') : 'received'">
                      {{ point.handover.is_sender ? (point.handover.status === 'accepted' ? 'Handover Diserahkan' : 'Handover Diajukan') : 'Titik Handover Masuk' }}
                    </span>
                    <p class="handover-desc" v-if="point.handover.is_sender">
                      {{ point.handover.status === 'accepted' ? 'Dialihkan ke' : 'Menunggu respon' }}: <strong>{{ point.handover.partner_name }}</strong>
                    </p>
                    <p class="handover-desc" v-else>
                      Mewakili rekan: <strong>{{ point.handover.partner_name }}</strong>
                    </p>
                    <small class="handover-reason" v-if="point.handover.reason">
                      Alasan: "{{ point.handover.reason }}"
                    </small>
                    <small class="handover-scanned-info" v-if="point.scanned_by_partner">
                      ✓ Telah discan oleh {{ point.scanned_by_partner }}
                    </small>
                  </div>

                  <!-- Tombol Batal jika pengaju & masih pending -->
                  <button
                    v-if="point.handover.is_sender && point.handover.status === 'pending'"
                    type="button"
                    class="btn-cancel-handover"
                    @click="cancelHandover(point.handover.id)"
                    :disabled="cancellingHandoverId === point.handover.id"
                  >
                    {{ cancellingHandoverId === point.handover.id ? '...' : 'Batal' }}
                  </button>
                </div>

                <!-- Tombol Ajukan Handover jika titik belum di-scan & belum ada handover -->
                <div
                  v-else-if="isToday && isGroupedRound && (!point.scan_status || point.scan_status === 'belum')"
                  class="handover-action-row"
                >
                  <button
                    type="button"
                    class="btn-trigger-handover"
                    @click="openHandoverModal(point)"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M17 1l4 4-4 4"/>
                      <path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                      <path d="M7 23l-4-4 4-4"/>
                      <path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                    </svg>
                    Handover Titik
                  </button>
                </div>
              </div>
            </div>
          </div>
        </section>
      </template>

      <!-- Modal Dialog Handover -->
      <div v-if="showHandoverModal" class="modal-overlay" @click.self="closeHandoverModal">
        <div class="modal-box">
          <div class="modal-header">
            <div>
              <h3>Handover Titik Patroli</h3>
              <p>Alihkan tugas scan titik ini kepada rekan satpam pada shift yang sama.</p>
            </div>
            <button type="button" class="modal-close-btn" @click="closeHandoverModal">✕</button>
          </div>

          <div class="modal-body">
            <div class="modal-point-summary">
              <span class="summary-round">Putaran {{ currentRoundData?.round }}</span>
              <h4>{{ selectedPointForHandover?.patrol_point_name || selectedPointForHandover?.patrol_point?.name }}</h4>
              <span class="summary-seq">Titik Urutan #{{ selectedPointForHandover?.sequence_order }}</span>
            </div>

            <div v-if="loadingColleagues" class="modal-colleague-loading">
              Memuat daftar rekan satpam...
            </div>
            <div v-else-if="colleagues.length === 0" class="modal-colleague-empty">
              <p>Tidak ada rekan satpam lain yang sedang bertugas pada shift dan lokasi yang sama hari ini.</p>
            </div>
            <div v-else class="form-group">
              <label for="handover-colleague">Pilih Rekan Satpam Penerima</label>
              <select id="handover-colleague" v-model="handoverForm.to_satpam_id" class="form-select">
                <option value="" disabled>-- Pilih Rekan Satpam --</option>
                <option v-for="c in colleagues" :key="c.id" :value="c.id">
                  {{ c.name }} (NIPKWT: {{ c.nipkwt || '-' }})
                </option>
              </select>
            </div>

            <div class="form-group">
              <label for="handover-reason">Alasan Handover</label>
              <textarea
                id="handover-reason"
                v-model="handoverForm.reason"
                class="form-textarea"
                rows="3"
                placeholder="Contoh: Mengamankan gerbang / kendala darurat / izin mendesak"
              ></textarea>
            </div>

            <div v-if="handoverError" class="modal-error-alert">
              {{ handoverError }}
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" @click="closeHandoverModal">
              Batal
            </button>
            <button
              type="button"
              class="btn-modal-submit"
              :disabled="submittingHandover || !handoverForm.to_satpam_id || !handoverForm.reason.trim()"
              @click="submitHandover"
            >
              {{ submittingHandover ? 'Mengirim...' : 'Kirim Permintaan' }}
            </button>
          </div>
        </div>
      </div>
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
const rounds = ref([]);
const activeRoundIndex = ref(0);
const shiftInfo = ref(null);

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
      "https://sistem-monitoring-keamanan-be.onrender.com/api/satpam/schedule",
      {
        ...headers,
        params: { date: selectedDate.value },
      },
    );

    rounds.value = response.data.rounds ?? [];
    shiftInfo.value = {
      label: response.data.shift_label,
      start: response.data.shift_start,
      end: response.data.shift_end,
    };
    scheduleDetails.value = response.data.schedule ?? [];

    // Reset active round index jika di luar jangkauan
    if (activeRoundIndex.value >= rounds.value.length) {
      activeRoundIndex.value = 0;
    }
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

const isGroupedRound = computed(() => rounds.value.length > 0);

const hasSchedule = computed(() => {
  return rounds.value.length > 0 || scheduleDetails.value.length > 0;
});

const currentRoundData = computed(() => {
  if (rounds.value.length === 0) return null;
  return rounds.value[activeRoundIndex.value] || rounds.value[0];
});

const displayedPoints = computed(() => {
  if (currentRoundData.value?.points?.length) {
    return currentRoundData.value.points;
  }
  return scheduleDetails.value;
});

const scheduleTitle = computed(() => {
  if (shiftInfo.value?.label) {
    return `Shift ${shiftInfo.value.label} (${shiftInfo.value.start || ""} - ${shiftInfo.value.end || ""})`;
  }
  const first = scheduleDetails.value[0];
  return first?.schedule?.title ?? "";
});

/*
|--------------------------------------------------------------------------
| Format Time
|--------------------------------------------------------------------------
*/

const getStatusClass = (status) => {
  if (!status) return "default";
  const s = String(status).toLowerCase();
  if (s.includes("berhasil")) return "success";
  if (s.includes("terlambat")) return "late";
  if (s.includes("skip")) return "skip";
  if (s.includes("anomali")) return "anomaly";
  if (s.includes("terlewat")) return "missed";
  if (s.includes("handover") || s.includes("dialihkan")) return "handover";
  return "default";
};

const formatTime = (time) => {
  if (!time) {
    return "--:--";
  }

  return String(time).slice(0, 5);
};

const formatScanTime = (dateTime) => {
  if (!dateTime) return "--:--";
  try {
    const d = new Date(dateTime);
    if (!isNaN(d.getTime())) {
      return d.toLocaleTimeString("id-ID", {
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
      });
    }
  } catch (e) {}

  if (typeof dateTime === "string" && dateTime.includes(" ")) {
    return dateTime.split(" ")[1]?.slice(0, 5) || "--:--";
  }
  return String(dateTime).slice(0, 5);
};

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

const API_BASE = import.meta.env.VITE_API_URL || "https://sistem-monitoring-keamanan-be.onrender.com/api";

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
| Handover Feature
|--------------------------------------------------------------------------
*/

const pendingHandovers = ref([]);
const colleagues = ref([]);
const loadingColleagues = ref(false);
const showHandoverModal = ref(false);
const selectedPointForHandover = ref(null);
const handoverForm = ref({ to_satpam_id: "", reason: "" });
const submittingHandover = ref(false);
const handoverError = ref("");
const respondingId = ref(null);
const cancellingHandoverId = ref(null);

const fetchPendingHandovers = async () => {
  try {
    const response = await axios.get(`${API_BASE}/satpam/handovers/pending`, getAuthHeaders());
    pendingHandovers.value = Array.isArray(response.data) ? response.data : (response.data?.pending_handovers ?? []);
  } catch (err) {
    console.error("Gagal mengambil pending handovers:", err);
  }
};

const fetchColleagues = async () => {
  loadingColleagues.value = true;
  try {
    const response = await axios.get(`${API_BASE}/satpam/handovers/colleagues`, getAuthHeaders());
    colleagues.value = response.data.colleagues ?? [];
  } catch (err) {
    console.error("Gagal mengambil rekan satpam:", err);
  } finally {
    loadingColleagues.value = false;
  }
};

const openHandoverModal = (point) => {
  selectedPointForHandover.value = point;
  handoverForm.value = { to_satpam_id: "", reason: "" };
  handoverError.value = "";
  showHandoverModal.value = true;
  if (colleagues.value.length === 0) {
    fetchColleagues();
  }
};

const closeHandoverModal = () => {
  showHandoverModal.value = false;
  selectedPointForHandover.value = null;
  handoverError.value = "";
};

const submitHandover = async () => {
  if (!selectedPointForHandover.value) return;
  if (!handoverForm.value.to_satpam_id) {
    handoverError.value = "Silakan pilih rekan satpam penerima.";
    return;
  }
  if (!handoverForm.value.reason.trim()) {
    handoverError.value = "Silakan isi alasan handover.";
    return;
  }

  submittingHandover.value = true;
  handoverError.value = "";
  try {
    const round = currentRoundData.value?.round || 1;
    await axios.post(
      `${API_BASE}/satpam/handovers/request`,
      {
        schedule_detail_id: selectedPointForHandover.value.schedule_detail_id,
        patrol_point_id: selectedPointForHandover.value.patrol_point_id,
        patrol_round: round,
        to_satpam_id: handoverForm.value.to_satpam_id,
        reason: handoverForm.value.reason.trim(),
      },
      getAuthHeaders()
    );

    closeHandoverModal();
    await fetchSchedule();
  } catch (err) {
    console.error("Gagal mengajukan handover:", err);
    handoverError.value = err.response?.data?.message || "Gagal mengajukan handover. Silakan coba lagi.";
  } finally {
    submittingHandover.value = false;
  }
};

const respondHandover = async (id, action) => {
  respondingId.value = id;
  try {
    await axios.post(
      `${API_BASE}/satpam/handovers/${id}/respond`,
      { action },
      getAuthHeaders()
    );
    await Promise.all([fetchPendingHandovers(), fetchSchedule()]);
  } catch (err) {
    console.error("Gagal merespon handover:", err);
    alert(err.response?.data?.message || "Gagal memproses respon handover.");
  } finally {
    respondingId.value = null;
  }
};

const cancelHandover = async (id) => {
  if (!confirm("Apakah Anda yakin ingin membatalkan pengajuan handover titik ini?")) {
    return;
  }
  cancellingHandoverId.value = id;
  try {
    await axios.post(
      `${API_BASE}/satpam/handovers/${id}/cancel`,
      {},
      getAuthHeaders()
    );
    await fetchSchedule();
  } catch (err) {
    console.error("Gagal membatalkan handover:", err);
    alert(err.response?.data?.message || "Gagal membatalkan handover.");
  } finally {
    cancellingHandoverId.value = null;
  }
};

/*
|--------------------------------------------------------------------------
| Mounted
|--------------------------------------------------------------------------
*/

onMounted(() => {
  fetchSchedule();
  fetchPendingHandovers();
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
   ROUND TABS (4 PUTARAN)
========================================= */

.round-tabs-section {
  margin-top: 18px;
}

.round-tabs {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.round-tab-btn {
  background: #ffffff;
  border: 1.5px solid #d9dce8;
  border-radius: 12px;
  padding: 8px 4px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.round-tab-btn:hover {
  border-color: #1f2454;
}

.round-tab-btn.active {
  background: #1f2454;
  border-color: #1f2454;
  box-shadow: 0 4px 12px rgba(31, 36, 84, 0.25);
}

.tab-round-title {
  font-size: 11px;
  font-weight: 700;
  color: #1f2454;
}

.round-tab-btn.active .tab-round-title {
  color: #ffffff;
}

.tab-round-time {
  font-size: 10px;
  font-weight: 600;
  color: #e87500;
}

.round-tab-btn.active .tab-round-time {
  color: #ffaa5b;
}

.text-not-scanned {
  color: #94a3b8;
  font-size: 11px;
}

/* =========================================
   SECTION
========================================= */

.section {
  margin-top: 22px;
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

.status-badge.handover {
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
}

.text-handover-waiting {
  color: #2563eb;
  font-size: 11px;
  font-weight: 600;
  font-style: italic;
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
   HANDOVER STYLING
========================================= */

.pending-handover-section {
  margin-top: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pending-handover-banner {
  background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
  border: 1.5px solid #fed7aa;
  border-radius: 14px;
  padding: 14px;
  box-shadow: 0 4px 14px rgba(232, 117, 0, 0.08);
}

.pending-banner-header {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pending-banner-icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: #ea580c;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.pending-banner-icon svg {
  width: 18px;
  height: 18px;
}

.pending-banner-title h4 {
  margin: 2px 0 0;
  font-size: 13px;
  font-weight: 700;
  color: #9a3412;
}

.pending-banner-tag {
  display: inline-block;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  color: #ea580c;
  letter-spacing: 0.5px;
}

.pending-banner-body {
  margin-top: 10px;
  padding: 8px 10px;
  background: rgba(255, 255, 255, 0.7);
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.pending-info-row {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: #4b5563;
}

.pending-info-row strong {
  color: #1f2937;
}

.pending-info-row em {
  font-style: italic;
  color: #d97706;
}

.pending-banner-actions {
  margin-top: 10px;
  display: flex;
  gap: 8px;
}

.btn-respond-accept {
  flex: 1;
  background: #ea580c;
  color: #ffffff;
  border: none;
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-respond-accept:hover:not(:disabled) {
  background: #c2410c;
}

.btn-respond-reject {
  background: #ffffff;
  color: #6b7280;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 8px 14px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-respond-reject:hover:not(:disabled) {
  background: #f3f4f6;
  color: #1f2937;
}

/* Card Handover States */
.card-handover-accepted {
  border-left: 4px solid #2563eb;
}

.handover-status-box {
  margin-top: 11px;
  padding: 9px 11px;
  border-radius: 10px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.handover-status-box.pending {
  background: #fffbeb;
  border: 1px dashed #fde68a;
}

.handover-status-box.accepted {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
}

.handover-meta {
  flex: 1;
}

.handover-pill {
  display: inline-block;
  font-size: 9px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 12px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin-bottom: 4px;
}

.handover-pill.pending {
  background: #fef3c7;
  color: #b45309;
}

.handover-pill.accepted {
  background: #dbeafe;
  color: #1d4ed8;
}

.handover-pill.received {
  background: #dcfce7;
  color: #15803d;
}

.handover-desc {
  margin: 0;
  font-size: 11px;
  color: #1f2937;
  line-height: 1.4;
}

.handover-reason {
  display: block;
  font-size: 10px;
  color: #6b7280;
  font-style: italic;
  margin-top: 2px;
}

.handover-scanned-info {
  display: block;
  font-size: 10px;
  font-weight: 600;
  color: #16a34a;
  margin-top: 4px;
}

.btn-cancel-handover {
  align-self: center;
  background: #ffffff;
  color: #dc2626;
  border: 1px solid #fecaca;
  border-radius: 6px;
  padding: 5px 10px;
  font-size: 10px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.btn-cancel-handover:hover:not(:disabled) {
  background: #fee2e2;
}

.handover-action-row {
  margin-top: 10px;
  padding-top: 9px;
  border-top: 1px dashed #e2e8f0;
  display: flex;
  justify-content: flex-end;
}

.btn-trigger-handover {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  border-radius: 8px;
  padding: 6px 11px;
  font-size: 10px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-trigger-handover svg {
  width: 13px;
  height: 13px;
  color: #e87500;
}

.btn-trigger-handover:hover {
  border-color: #e87500;
  color: #e87500;
  background: #fff8f0;
}

/* Modal Styling */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-box {
  background: #ffffff;
  width: 100%;
  max-width: 420px;
  border-radius: 18px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: modalIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalIn {
  from {
    opacity: 0;
    transform: translateY(16px) scale(0.96);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.modal-header {
  padding: 18px 20px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.modal-header h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
}

.modal-header p {
  margin: 3px 0 0;
  font-size: 11px;
  color: #64748b;
  line-height: 1.4;
}

.modal-close-btn {
  background: #f1f5f9;
  border: none;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  font-size: 12px;
  color: #475569;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-body {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.modal-point-summary {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 14px;
}

.summary-round {
  display: inline-block;
  font-size: 9px;
  font-weight: 700;
  color: #ea580c;
  text-transform: uppercase;
  margin-bottom: 2px;
}

.modal-point-summary h4 {
  margin: 0;
  font-size: 14px;
  color: #1e293b;
  font-weight: 700;
}

.summary-seq {
  display: block;
  font-size: 10px;
  color: #64748b;
  margin-top: 2px;
}

.modal-colleague-loading,
.modal-colleague-empty {
  font-size: 11px;
  color: #64748b;
  padding: 10px;
  background: #f8fafc;
  border-radius: 8px;
  text-align: center;
}

.modal-colleague-empty {
  color: #dc2626;
  background: #fef2f2;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 11px;
  font-weight: 600;
  color: #334155;
}

.form-select,
.form-textarea {
  width: 100%;
  border: 1.5px solid #cbd5e1;
  border-radius: 10px;
  padding: 9px 12px;
  font-size: 12px;
  font-family: inherit;
  color: #0f172a;
  background: #ffffff;
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}

.form-select:focus,
.form-textarea:focus {
  border-color: #ea580c;
}

.form-textarea {
  resize: vertical;
}

.modal-error-alert {
  padding: 8px 12px;
  background: #fee2e2;
  border-radius: 8px;
  color: #b91c1c;
  font-size: 11px;
}

.modal-footer {
  padding: 14px 20px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn-modal-cancel {
  background: #f1f5f9;
  border: none;
  border-radius: 10px;
  padding: 9px 16px;
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
}

.btn-modal-submit {
  background: #ea580c;
  border: none;
  border-radius: 10px;
  padding: 9px 18px;
  font-size: 12px;
  font-weight: 700;
  color: #ffffff;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-modal-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-modal-submit:hover:not(:disabled) {
  background: #c2410c;
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
