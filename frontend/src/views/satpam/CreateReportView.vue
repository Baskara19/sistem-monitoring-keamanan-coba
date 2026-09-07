<template>
  <div class="report-page">
    <!-- Header -->
    <header class="top-header">
      <button class="back-btn" type="button" aria-label="Kembali" @click="goBack">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M15 18l-6-6 6-6" />
        </svg>
      </button>

      <h1>Buat Laporan</h1>

      <div class="header-spacer"></div>
    </header>

    <main class="report-body">
      <!-- Info titik patroli -->
      <div class="info-card">
        <div class="info-row">
          <div class="info-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M12 21s-7-6.1-7-11a7 7 0 0 1 14 0c0 4.9-7 11-7 11Z" />
              <circle cx="12" cy="10" r="2.5" />
            </svg>
          </div>
          <span>{{ pointInfo.name || "-" }}</span>
        </div>

        <div class="info-row">
          <div class="info-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M12 2l2.9 6 6.6.6-5 4.5 1.5 6.4-6-3.6-6 3.6 1.5-6.4-5-4.5 6.6-.6z" />
            </svg>
          </div>
          <span>Titik ID : {{ pointInfo.code || "-" }}</span>
        </div>

        <div class="info-row">
          <div class="info-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <circle cx="12" cy="12" r="9" />
              <path d="M12 7v5l3 2" />
            </svg>
          </div>
          <span>Jadwal : {{ pointInfo.jadwal || "-" }}</span>
        </div>
      </div>

      <div v-if="error" class="form-error">{{ error }}</div>

      <!-- Jenis Patroli -->
      <div class="form-group">
        <label>Jenis Patroli</label>
        <div class="select-wrapper">
          <select v-model="form.report_type">
            <option value="rutin">Patroli Rutin</option>
            <option value="insiden">Insiden</option>
            <option value="temuan">Temuan</option>
          </select>
        </div>
      </div>

      <!-- Kondisi -->
      <div class="form-group">
        <label>Kondisi</label>
        <div class="select-wrapper">
          <select v-model="form.kondisi">
            <option value="" disabled>Pilih Kondisi</option>
            <option value="aman">Aman</option>
            <option value="mencurigakan">Mencurigakan</option>
            <option value="kerusakan">Ada Kerusakan</option>
            <option value="darurat">Darurat</option>
          </select>
        </div>
      </div>

      <!-- Catatan -->
      <div class="form-group">
        <label>Catatan</label>
        <textarea
          v-model="form.description"
          maxlength="300"
          rows="5"
          placeholder="Tuliskan catatan tambahan tentang kondisi di titik ini..."
        ></textarea>
        <span class="char-counter">{{ form.description.length }}/300 Karakter</span>
      </div>

      <!-- Foto -->
      <div class="form-group">
        <label>Foto (Opsional)</label>

        <input
          ref="fileInputRef"
          type="file"
          accept="image/*"
          class="hidden-file-input"
          @change="onPhotoSelected"
        />

        <button type="button" class="upload-box" @click="fileInputRef.click()">
          <template v-if="photoPreview">
            <img :src="photoPreview" alt="Preview foto" class="upload-preview" />
            <span class="upload-replace">Tap untuk ganti foto</span>
          </template>

          <template v-else>
            <div class="upload-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="3" width="18" height="18" rx="2" />
                <circle cx="8.5" cy="9.5" r="1.5" />
                <path d="M21 15l-5-5-9 9" />
              </svg>
            </div>
            <span class="upload-title">Tap untuk unggah foto</span>
            <span class="upload-hint">Maks. 5MB / foto</span>
          </template>
        </button>
      </div>

      <button class="btn-save" type="button" :disabled="submitting" @click="submitReport">
        {{ submitting ? "Menyimpan..." : "Simpan Laporan" }}
      </button>
    </main>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import axios from "axios";
import { useRoute, useRouter } from "vue-router";
import Swal from "sweetalert2";

const router = useRouter();
const route = useRoute();

const pointInfo = computed(() => ({
  name: route.query.name || "",
  code: route.query.code || "",
  jadwal: route.query.jadwal || "",
}));

const patrolLogId = route.query.patrol_log_id;

const form = ref({
  report_type: "rutin",
  kondisi: "",
  description: "",
});

const fileInputRef = ref(null);
const photoFile = ref(null);
const photoPreview = ref("");

const error = ref("");
const submitting = ref(false);

const getAuthHeaders = () => {
  const token = localStorage.getItem("token");
  return { headers: { Authorization: `Bearer ${token}` } };
};

const onPhotoSelected = (event) => {
  const file = event.target.files?.[0];
  if (!file) return;

  if (file.size > 5 * 1024 * 1024) {
    error.value = "Ukuran foto maksimal 5MB.";
    event.target.value = "";
    return;
  }

  photoFile.value = file;
  photoPreview.value = URL.createObjectURL(file);
};

const submitReport = async () => {
  error.value = "";

  if (!patrolLogId) {
    await Swal.fire({
      icon: "error",
      title: "Data Scan Tidak Ditemukan",
      text: "Silakan scan ulang titik patroli.",
      confirmButtonText: "OK",
      confirmButtonColor: "#1f2454",
    });

    return;
  }

  if (!form.value.kondisi) {
    await Swal.fire({
      icon: "warning",
      title: "Kondisi Belum Dipilih",
      text: "Silakan pilih kondisi patroli terlebih dahulu.",
      confirmButtonText: "OK",
      confirmButtonColor: "#e87500",
    });

    return;
  }

  submitting.value = true;

  try {
    const payload = new FormData();

    payload.append("patrol_log_id", patrolLogId);
    payload.append("report_type", form.value.report_type);
    payload.append("kondisi", form.value.kondisi);

    if (form.value.description) {
      payload.append("description", form.value.description);
    }

    if (photoFile.value) {
      payload.append("photo", photoFile.value);
    }

    await axios.post(
      "http://127.0.0.1:8000/api/satpam/reports",
      payload,
      {
        headers: {
          ...getAuthHeaders().headers,
          "Content-Type": "multipart/form-data",
        },
      }
    );

    await Swal.fire({
      icon: "success",
      title: "Laporan Berhasil Disimpan",
      text: "Laporan patroli berhasil disimpan.",
      confirmButtonText: "Kembali ke Dashboard",
      confirmButtonColor: "#1f2454",
      allowOutsideClick: false,
    });

    router.push("/satpam/dashboard");

  } catch (err) {
    console.error("Gagal menyimpan laporan:", err);

    const message =
      err.response?.data?.message ||
      "Terjadi kesalahan saat menyimpan laporan.";

    await Swal.fire({
      icon: "error",
      title: "Gagal Menyimpan",
      text: message,
      confirmButtonText: "Coba Lagi",
      confirmButtonColor: "#d63031",
    });

  } finally {
    submitting.value = false;
  }
};

const goBack = () => {
  router.back();
};
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");

.report-page {
  min-height: 100vh;
  background: #f4f5f7;
  color: #1f2454;
  font-family: "Segoe UI", Arial, sans-serif;
}

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

.report-body {
  max-width: 460px;
  margin: 0 auto;
  padding: 20px 16px 50px;
  box-sizing: border-box;
}

.info-card {
  padding: 18px;
  border: 1px solid #e2e4ea;
  border-radius: 16px;
  background: #ffffff;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-bottom: 22px;
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
  background: linear-gradient(135deg, #1f2454, #292f6b);
  color: #ffffff;
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

.form-error {
  margin-bottom: 16px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #fff1f1;
  border-left: 3px solid #d63031;
  color: #d63031;
  font-size: 12px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group > label {
  display: block;
  margin-bottom: 8px;
  font-size: 13px;
  font-weight: 700;
  color: #1f2454;
}

.select-wrapper {
  position: relative;
}

.select-wrapper::after {
  content: "";
  position: absolute;
  right: 16px;
  top: 50%;
  width: 8px;
  height: 8px;
  border-right: 2px solid #8a8d9c;
  border-bottom: 2px solid #8a8d9c;
  transform: translateY(-70%) rotate(45deg);
  pointer-events: none;
}

.form-group select {
  width: 100%;
  height: 50px;
  box-sizing: border-box;
  border: 1px solid #e2e4ea;
  border-radius: 12px;
  padding: 0 40px 0 14px;
  font-size: 13px;
  font-family: inherit;
  outline: none;
  background: #ffffff;
  color: #1f2454;
  appearance: none;
  cursor: pointer;
}

.form-group textarea {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid #e2e4ea;
  border-radius: 12px;
  padding: 12px 14px;
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

.char-counter {
  display: block;
  margin-top: 6px;
  text-align: right;
  font-size: 11px;
  color: #8a8d9c;
}

.hidden-file-input {
  display: none;
}

.upload-box {
  width: 100%;
  min-height: 130px;
  border: 1.5px dashed #d9dce8;
  border-radius: 14px;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 16px;
  box-sizing: border-box;
  cursor: pointer;
  overflow: hidden;
}

.upload-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: #eef0f6;
  color: #1f2454;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 4px;
}

.upload-icon svg {
  width: 22px;
  height: 22px;
}

.upload-title {
  font-size: 13px;
  font-weight: 600;
  color: #1f2454;
}

.upload-hint {
  font-size: 11px;
  color: #8a8d9c;
}

.upload-preview {
  max-width: 100%;
  max-height: 160px;
  border-radius: 10px;
  object-fit: cover;
}

.upload-replace {
  margin-top: 8px;
  font-size: 11px;
  font-weight: 600;
  color: #e87500;
}

.btn-save {
  width: 100%;
  height: 54px;
  border: none;
  border-radius: 14px;
  background: linear-gradient(135deg, #1f2454, #292f6b);
  color: #ffffff;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  margin-top: 6px;
}

.btn-save:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (min-width: 700px) {
  .report-page {
    max-width: 460px;
    margin: 0 auto;
    box-shadow: 0 0 40px rgba(31, 36, 84, 0.1);
  }
}
</style>
