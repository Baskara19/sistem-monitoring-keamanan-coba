<template>
  <div class="scan-page">
    <!-- Header -->
    <header class="top-header">
      

      <h1>{{ stage === "success" ? "Scan Berhasil" : "Scan QR" }}</h1>

      
    </header>

    <!-- ============================= -->
    <!-- SCANNING -->
    <!-- ============================= -->
    <main v-if="stage === 'scanning'" class="scan-body">
      <div class="camera-area">
        <video ref="videoRef" class="camera-feed" autoplay playsinline muted></video>
        <canvas ref="canvasRef" class="hidden-canvas"></canvas>

        <div class="scan-frame">
          <span class="corner tl"></span>
          <span class="corner tr"></span>
          <span class="corner bl"></span>
          <span class="corner br"></span>
        </div>

        <div v-if="cameraError" class="camera-error">
          <p>{{ cameraError }}</p>
        </div>
      </div>

      <div class="hint-card">
        <div class="hint-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="3" y="3" width="7" height="7" />
            <rect x="14" y="3" width="7" height="7" />
            <rect x="3" y="14" width="7" height="7" />
            <path d="M14 14h3v3h-3z" />
            <path d="M20 14v7h-3" />
          </svg>
        </div>

        <div>
          <strong>Arahkan kamera ke QR Code</strong>
          <p>Posisikan QR Code titik patroli di dalam bingkai untuk memindai secara otomatis.</p>
        </div>
      </div>

      <div v-if="scanMessage" class="scan-toast" :class="scanMessageType">
        {{ scanMessage }}
      </div>

      <button class="skip-scan-button" type="button" @click="openSkipModal">
        <span>Skip Scan</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <circle cx="12" cy="12" r="9" />
          <path d="M9 9l6 6" />
          <path d="M15 9l-6 6" />
        </svg>
      </button>
    </main>

    <!-- ============================= -->
    <!-- SUCCESS -->
    <!-- ============================= -->
    <main v-else-if="stage === 'success'" class="success-body">
      <div class="result-icon" :class="{ warning: result.scan_status !== 'berhasil' }">
        <svg v-if="result.scan_status === 'berhasil'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M20 6 9 17l-5-5" />
        </svg>
        <svg v-else-if="result.scan_status === 'terlambat'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <circle cx="12" cy="12" r="9" />
          <path d="M12 7v5l3 2" />
        </svg>
        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M12 9v4" />
          <path d="M12 17h.01" />
          <path d="M10.3 3.9 2.7 17a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
        </svg>
      </div>

      <h2>{{ resultTitle }}</h2>

      <p class="result-subtitle">{{ resultSubtitle }}</p>

      <div class="info-card">
        <div class="info-row">
          <div class="info-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M12 21s-7-6.1-7-11a7 7 0 0 1 14 0c0 4.9-7 11-7 11Z" />
              <circle cx="12" cy="10" r="2.5" />
            </svg>
          </div>
          <span>{{ result.patrol_point.name }}</span>
        </div>

        <div class="info-row">
          <div class="info-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M12 2l2.9 6 6.6.6-5 4.5 1.5 6.4-6-3.6-6 3.6 1.5-6.4-5-4.5 6.6-.6z" />
            </svg>
          </div>
          <span>Titik ID : {{ result.patrol_point.code }}</span>
        </div>

        <div class="info-row">
          <div class="info-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <circle cx="12" cy="12" r="9" />
              <path d="M12 7v5l3 2" />
            </svg>
          </div>
          <span>Jadwal : {{ result.jadwal || "-" }}</span>
        </div>
      </div>

      <div v-if="result.skipped_points?.length" class="missed-notice">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M12 9v4" />
          <path d="M12 17h.01" />
          <path d="M10.3 3.9 2.7 17a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
        </svg>
        <span>
          Titik <strong>{{ result.skipped_points.join(", ") }}</strong>
          otomatis tercatat <strong>terlewat</strong> karena belum di-scan sebelum titik ini.
        </span>
      </div>

      <div class="result-actions">
        <button class="btn-report" type="button" @click="buatLaporan">Buat Laporan</button>
        
      </div>
    </main>

    <!-- Skip Scan Modal -->
    <div v-if="showSkipModal" class="modal-overlay" @click.self="closeSkipModal">
      <div class="modal">
        <div class="modal-header">
          <h3>Skip Scan</h3>
          <button class="modal-close" type="button" @click="closeSkipModal">✕</button>
        </div>

        <div class="modal-body">
          <p class="modal-desc">
            Gunakan ini kalau kamu sengaja tidak bisa scan di titik patroli ini. Wajib isi alasannya.
          </p>

          <div v-if="skipError" class="modal-error">{{ skipError }}</div>

          <div class="form-group">
            <label>Titik Patroli</label>

            <select v-if="skipOptionsLoading || patrolPoints.length" v-model="skipForm.patrol_point_id" :disabled="skipOptionsLoading">
              <option value="" disabled>{{ skipOptionsLoading ? "Memuat titik..." : "Pilih titik patroli" }}</option>
              <option v-for="point in patrolPoints" :key="point.patrol_point_id" :value="point.patrol_point_id">
                {{ point.sequence_order }}. {{ point.name }}
              </option>
            </select>

            <p v-else class="field-hint">
              Semua titik jadwal hari ini sudah di-scan/di-skip, atau kamu belum punya jadwal hari ini.
            </p>
          </div>

          <div class="form-group">
            <label>Alasan</label>
            <textarea
              v-model="skipForm.reason"
              rows="3"
              placeholder="Contoh: sedang menangani insiden lain di lokasi berbeda"
            ></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-cancel" type="button" @click="closeSkipModal">Batal</button>
          <button class="btn-submit" type="button" :disabled="skipSubmitting" @click="submitSkipScan">
            {{ skipSubmitting ? "Mengirim..." : "Kirim" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";
import jsQR from "jsqr";
import Swal from "sweetalert2";

const router = useRouter();

const stage = ref("scanning"); // scanning | success
const result = ref(null);

const resultTitle = computed(() => {
  if (!result.value) return "";
  if (result.value.scan_status === "terlambat") return "Anda Terlambat";
  if (result.value.scan_status === "anomali") return "Scan Tercatat";
  return "Scan Berhasil!";
});

const resultSubtitle = computed(() => {
  if (!result.value) return "";
  if (result.value.scan_status === "terlambat") return "Scan dilakukan setelah jadwal shift berakhir";
  if (result.value.scan_status === "anomali") return "Lokasi Anda di luar radius titik patroli";
  return "Anda berada di titik yang benar";
});

const videoRef = ref(null);
const canvasRef = ref(null);
const cameraError = ref("");
const scanMessage = ref("");
const scanMessageType = ref("info");

let mediaStream = null;
let rafId = null;
let decoding = false;

const getAuthHeaders = () => {
  const token = localStorage.getItem("token");
  return { headers: { Authorization: `Bearer ${token}` } };
};

/*
|--------------------------------------------------------------------------
| Kamera + Decode QR
|--------------------------------------------------------------------------
*/

const startCamera = async () => {
  try {
    mediaStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: "environment" },
    });

    videoRef.value.srcObject = mediaStream;
    await videoRef.value.play();

    tick();
  } catch (err) {
    console.error(err);
    cameraError.value = "Tidak bisa mengakses kamera. Pastikan izin kamera sudah diberikan.";
  }
};

const stopCamera = () => {
  if (rafId) cancelAnimationFrame(rafId);
  if (mediaStream) {
    mediaStream.getTracks().forEach((track) => track.stop());
    mediaStream = null;
  }
};

const tick = () => {
  const video = videoRef.value;
  const canvas = canvasRef.value;

  if (!video || !canvas || video.readyState !== video.HAVE_ENOUGH_DATA) {
    rafId = requestAnimationFrame(tick);
    return;
  }

  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;

  const ctx = canvas.getContext("2d");
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

  const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const code = jsQR(imageData.data, imageData.width, imageData.height, {
    inversionAttempts: "dontInvert",
  });

  if (code && code.data && !decoding) {
    handleDecoded(code.data);
    return;
  }

  rafId = requestAnimationFrame(tick);
};

const getLocation = () => {
  return new Promise((resolve) => {
    if (!navigator.geolocation) {
      resolve(null);
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }),
      () => resolve(null),
      { timeout: 4000 }
    );
  });
};

const handleDecoded = async (qrCode) => {
  decoding = true;

  const location = await getLocation();

  try {
    const response = await axios.post(
      "http://127.0.0.1:8000/api/satpam/scan",
      {
        qr_code: qrCode,
        latitude: location?.latitude,
        longitude: location?.longitude,
      },
      getAuthHeaders()
    );

    stopCamera();

    const shift = response.data.shift;

    result.value = {
      scan_status: response.data.scan_status,
      patrol_log_id: response.data.patrol_log.id,
      patrol_point: response.data.patrol_point,
      jadwal: shift ? `${shift.label} (${shift.shift_start}-${shift.shift_end})` : null,
      skipped_points: response.data.skipped_points || [],
    };
    stage.value = "success";
  } catch (err) {
    scanMessageType.value = "error";
    scanMessage.value = err.response?.data?.message || "QR Code tidak dikenali, coba lagi.";

    setTimeout(() => {
      scanMessage.value = "";
      decoding = false;
      rafId = requestAnimationFrame(tick);
    }, 1800);
  }
};

/*
|--------------------------------------------------------------------------
| Skip Scan
|--------------------------------------------------------------------------
*/

const patrolPoints = ref([]);
const skipOptionsLoading = ref(false);
const showSkipModal = ref(false);
const skipSubmitting = ref(false);
const skipError = ref("");

const skipForm = ref({
  patrol_point_id: "",
  reason: "",
});

const fetchSkipOptions = async () => {
  skipOptionsLoading.value = true;

  try {
    const response = await axios.get(
      "http://127.0.0.1:8000/api/satpam/skip-options",
      getAuthHeaders()
    );

    patrolPoints.value = response.data.points ?? [];
  } catch (err) {
    console.error(err);
  } finally {
    skipOptionsLoading.value = false;
  }
};

const openSkipModal = () => {
  skipForm.value = { patrol_point_id: "", reason: "" };
  skipError.value = "";
  showSkipModal.value = true;

  // Selalu ambil ulang, karena titik yang tersedia bisa berubah
  // (misalnya baru saja discan) sejak modal terakhir dibuka.
  fetchSkipOptions();
};

const closeSkipModal = () => {
  showSkipModal.value = false;
};

const submitSkipScan = async () => {
  skipError.value = "";

  if (!skipForm.value.patrol_point_id) {
    await Swal.fire({
      icon: "warning",
      title: "Titik Belum Dipilih",
      text: "Pilih titik patroli terlebih dahulu.",
      confirmButtonText: "OK",
      confirmButtonColor: "#e87500",
    });
    return;
  }

  if (!skipForm.value.reason.trim()) {
    await Swal.fire({
      icon: "warning",
      title: "Alasan Wajib Diisi",
      text: "Silakan masukkan alasan kenapa scan dilewati.",
      confirmButtonText: "OK",
      confirmButtonColor: "#e87500",
    });
    return;
  }

  skipSubmitting.value = true;

  try {
    const response = await axios.post(
      "http://127.0.0.1:8000/api/satpam/skip-scan",
      skipForm.value,
      getAuthHeaders()
    );

    showSkipModal.value = false;

    const shift = response.data.shift;
    const skippedPoints = response.data.skipped_points || [];

    let text = shift
      ? `Skip scan berhasil dicatat pada shift ${shift.label} (${shift.shift_start}-${shift.shift_end}).`
      : "Skip scan berhasil dicatat.";

    if (skippedPoints.length) {
      text += ` Titik ${skippedPoints.join(", ")} otomatis tercatat terlewat.`;
    }

    await Swal.fire({
      icon: "success",
      title: "Skip Scan Berhasil",
      text,
      confirmButtonText: "OK",
      confirmButtonColor: "#1f2454",
    });

    router.push("/satpam/dashboard");
  } catch (err) {
    console.error("Gagal mengirim skip scan:", err);

    const message =
      err.response?.data?.message ||
      "Gagal mengirim skip scan. Silakan coba lagi.";

    await Swal.fire({
      icon: "error",
      title: "Gagal",
      text: message,
      confirmButtonText: "OK",
      confirmButtonColor: "#d63031",
    });
  } finally {
    skipSubmitting.value = false;
  }
};

/*
|--------------------------------------------------------------------------
| Navigasi
|--------------------------------------------------------------------------
*/



const buatLaporan = () => {
  router.replace({
    path: "/satpam/report",
    query: {
      patrol_log_id: result.value.patrol_log_id,
      name: result.value.patrol_point.name,
      code: result.value.patrol_point.code,
      jadwal: result.value.jadwal || "",
    },
  });
};



onMounted(() => {
  startCamera();
});

onBeforeUnmount(() => {
  stopCamera();
});
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");

.scan-page {
  min-height: 100vh;
  background: #f4f5f7;
  color: #1f2454;
  font-family: "Segoe UI", Arial, sans-serif;
}

/* HEADER */
.top-header {
  height: 68px;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  border-bottom: 1px solid #e2e4ea;
}

.back-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: none;
  background: #f4f5f7;
  color: #1f2454;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.back-btn svg {
  width: 20px;
  height: 20px;
}

.top-header h1 {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
  color: #1f2454;
}

.header-spacer {
  width: 40px;
}

/* SCANNING BODY */
.scan-body {
  max-width: 460px;
  margin: 0 auto;
  padding: 20px 16px 100px;
  box-sizing: border-box;
}

.camera-area {
  position: relative;
  width: 100%;
  aspect-ratio: 1 / 1;
  border-radius: 20px;
  overflow: hidden;
  background: #1a1c22;
}

.camera-feed {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.hidden-canvas {
  display: none;
}

.scan-frame {
  position: absolute;
  inset: 18%;
  pointer-events: none;
}

.corner {
  position: absolute;
  width: 34px;
  height: 34px;
  border-color: #e87500;
  border-style: solid;
  border-width: 0;
}

.corner.tl { top: 0; left: 0; border-top-width: 4px; border-left-width: 4px; border-top-left-radius: 8px; }
.corner.tr { top: 0; right: 0; border-top-width: 4px; border-right-width: 4px; border-top-right-radius: 8px; }
.corner.bl { bottom: 0; left: 0; border-bottom-width: 4px; border-left-width: 4px; border-bottom-left-radius: 8px; }
.corner.br { bottom: 0; right: 0; border-bottom-width: 4px; border-right-width: 4px; border-bottom-right-radius: 8px; }

.camera-error {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(31, 36, 84, 0.85);
  color: #ffffff;
  text-align: center;
  font-size: 12px;
}

.hint-card {
  margin-top: 18px;
  padding: 18px;
  background: #ffffff;
  border: 1px solid #e2e4ea;
  border-radius: 16px;
  display: flex;
  gap: 14px;
  align-items: flex-start;
}

.hint-icon {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  border-radius: 12px;
  background: #fff3e8;
  color: #e87500;
  display: flex;
  align-items: center;
  justify-content: center;
}

.hint-icon svg {
  width: 20px;
  height: 20px;
}

.hint-card strong {
  display: block;
  font-size: 13px;
  color: #1f2454;
}

.hint-card p {
  margin: 4px 0 0;
  font-size: 11px;
  line-height: 1.6;
  color: #8a8d9c;
}

.scan-toast {
  margin-top: 14px;
  padding: 12px 14px;
  border-radius: 12px;
  font-size: 12px;
  text-align: center;
}

.scan-toast.error {
  background: #fff1f1;
  color: #d63031;
}

.scan-toast.info {
  background: #eef0f6;
  color: #1f2454;
}

.skip-scan-button {
  position: fixed;
  left: 50%;
  bottom: 24px;
  transform: translateX(-50%);
  width: calc(100% - 32px);
  max-width: 428px;
  height: 54px;
  border-radius: 16px;
  border: 1px solid #e2e4ea;
  background: #ffffff;
  color: #d63031;
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  box-shadow: 0 10px 25px rgba(31, 36, 84, 0.1);
}

.skip-scan-button svg {
  width: 18px;
  height: 18px;
}

/* SUCCESS BODY */
.success-body {
  max-width: 460px;
  margin: 0 auto;
  padding: 40px 24px 40px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.result-icon {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  background: #eaf7ef;
  color: #2d9b61;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
}

.result-icon.warning {
  background: #fff3e8;
  color: #e87500;
}

.result-icon svg {
  width: 46px;
  height: 46px;
}

.success-body h2 {
  margin: 0;
  font-size: 21px;
  font-weight: 700;
  color: #1f2454;
}

.result-subtitle {
  margin: 6px 0 26px;
  font-size: 13px;
  color: #8a8d9c;
}

.info-card {
  width: 100%;
  padding: 18px;
  border: 1px solid #e2e4ea;
  border-radius: 16px;
  background: #ffffff;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 14px;
  text-align: left;
}

.info-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.info-icon {
  width: 34px;
  height: 34px;
  flex-shrink: 0;
  border-radius: 10px;
  background: #eef0f6;
  color: #1f2454;
  display: flex;
  align-items: center;
  justify-content: center;
}

.info-icon svg {
  width: 17px;
  height: 17px;
}

.info-row span {
  font-size: 13px;
  color: #1f2454;
  font-weight: 600;
}

.missed-notice {
  width: 100%;
  margin-top: 16px;
  padding: 14px 16px;
  border-radius: 14px;
  background: #f1edff;
  color: #7a5cf0;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  text-align: left;
  font-size: 12px;
  line-height: 1.6;
  box-sizing: border-box;
}

.missed-notice svg {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  margin-top: 1px;
}

.result-actions {
  width: 100%;
  margin-top: 28px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.btn-report {
  height: 52px;
  border: none;
  border-radius: 14px;
  background: linear-gradient(135deg, #1f2454, #292f6b);
  color: #ffffff;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.btn-history {
  height: 52px;
  border: 1px solid #e2e4ea;
  border-radius: 14px;
  background: #ffffff;
  color: #1f2454;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

/* MODAL */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(31, 36, 84, 0.4);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  box-sizing: border-box;
  z-index: 100;
}

.modal {
  width: 100%;
  max-width: 380px;
  max-height: 90vh;
  overflow-y: auto;
  background: #ffffff;
  border-radius: 18px;
  box-shadow: 0 30px 80px rgba(31, 36, 84, 0.25);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px;
  border-bottom: 1px solid #e2e4ea;
}

.modal-header h3 {
  margin: 0;
  font-size: 15px;
  color: #1f2454;
}

.modal-close {
  border: none;
  background: none;
  font-size: 16px;
  color: #8a8d9c;
  cursor: pointer;
}

.modal-body {
  padding: 20px;
}

.modal-desc {
  margin: 0 0 16px;
  font-size: 11px;
  line-height: 1.6;
  color: #8a8d9c;
}

.modal-error {
  margin-bottom: 14px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #fff1f1;
  border-left: 3px solid #d63031;
  color: #d63031;
  font-size: 11px;
}

.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  margin-bottom: 7px;
  font-size: 12px;
  font-weight: 600;
  color: #1f2454;
}

.field-hint {
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: #eef0f6;
  color: #8a8d9c;
  font-size: 11px;
  line-height: 1.6;
}

.form-group select,
.form-group textarea {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid #e2e4ea;
  border-radius: 10px;
  padding: 10px 12px;
  font-size: 13px;
  font-family: inherit;
  outline: none;
  resize: vertical;
  background: #ffffff;
  color: #1f2454;
}

.form-group select:focus,
.form-group textarea:focus {
  border-color: #e87500;
  box-shadow: 0 0 0 3px rgba(232, 117, 0, 0.08);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 20px;
  border-top: 1px solid #e2e4ea;
}

.btn-cancel {
  padding: 10px 18px;
  border: 1px solid #e2e4ea;
  border-radius: 10px;
  background: transparent;
  color: #8a8d9c;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.btn-submit {
  padding: 10px 18px;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg, #e87500, #f08b1a);
  color: #ffffff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.btn-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (min-width: 700px) {
  .scan-page {
    max-width: 460px;
    margin: 0 auto;
    box-shadow: 0 0 40px rgba(31, 36, 84, 0.1);
  }
}
</style>
