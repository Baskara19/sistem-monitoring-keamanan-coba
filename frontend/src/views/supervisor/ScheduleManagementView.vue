<script setup>
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";

const router = useRouter();

const loading = ref(true);
const error = ref("");

const schedules = ref([]);
const satpamOptions = ref([]);
const patrolPointOptions = ref([]);
const routeOptions = ref([]);

const search = ref("");
const dateFrom = ref("");
const dateTo = ref("");

const currentPage = ref(1);
const perPage = 10;

const showModal = ref(false);
const modalMode = ref("create"); // create | edit
const selectedId = ref(null);
const saving = ref(false);
const formError = ref("");

const form = ref({
  satpam_id: "",
  route_id: "",
  patrol_point_id: "",
  start_date: "",
  end_date: "",
  shift_start: "",
  shift_end: "",
  status: "aktif",
});

const user = ref({ name: "Supervisor", role: "supervisor" });

/*
|--------------------------------------------------------------------------
| AUTH / USER
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

const loadUser = () => {
  try {
    const storedUser = localStorage.getItem("user");

    if (storedUser) {
      user.value = { ...user.value, ...JSON.parse(storedUser) };
    }
  } catch (err) {
    console.error(err);
  }
};

const displayName = computed(() => user.value?.name || "Supervisor");

const currentDate = computed(() => {
  return new Intl.DateTimeFormat("id-ID", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(new Date());
});

const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");
  router.push("/login");
};

/*
|--------------------------------------------------------------------------
| FETCH DATA
|--------------------------------------------------------------------------
*/

const fetchSchedules = async () => {
  loading.value = true;
  error.value = "";

  try {
    const response = await axios.get("https://sistem-monitoring-keamanan-be.onrender.com/api/supervisor/schedules", {
      ...getAuthHeaders(),
      params: {
        search: search.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
      },
    });

    schedules.value = response.data.schedules ?? [];
  } catch (err) {
    error.value = err.response?.data?.message || "Gagal memuat data jadwal.";
  } finally {
    loading.value = false;
  }
};

const fetchOptions = async () => {
  try {
    const [satpamRes, pointRes, routeRes] = await Promise.all([
      axios.get("https://sistem-monitoring-keamanan-be.onrender.com/api/supervisor/satpam", getAuthHeaders()),
      axios.get("https://sistem-monitoring-keamanan-be.onrender.com/api/supervisor/patrol-points", getAuthHeaders()),
      axios.get("https://sistem-monitoring-keamanan-be.onrender.com/api/supervisor/routes", getAuthHeaders()),
    ]);

    satpamOptions.value = satpamRes.data.satpam ?? [];
    patrolPointOptions.value = pointRes.data.patrol_points ?? [];
    routeOptions.value = routeRes.data.routes ?? [];
  } catch (err) {
    console.error(err);
  }
};

const selectedRoutePoints = computed(() => {
  const route = routeOptions.value.find((r) => r.id === form.value.route_id);
  return route?.points ?? [];
});

/*
|--------------------------------------------------------------------------
| FILTER + PAGINATION (client-side, karena search/date sudah difilter di server
| tiap kali fetchSchedules dipanggil ulang)
|--------------------------------------------------------------------------
*/

const totalPages = computed(() => Math.max(1, Math.ceil(schedules.value.length / perPage)));

const paginatedSchedules = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  return schedules.value.slice(start, start + perPage);
});

const goToPage = (page) => {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
};

const applyFilter = () => {
  currentPage.value = 1;
  fetchSchedules();
};

/*
|--------------------------------------------------------------------------
| FORMAT
|--------------------------------------------------------------------------
*/

const formatDate = (value) => {
  if (!value) return "-";
  return new Date(value).toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
};

const formatDateRange = (row) => {
  if (!row.start_date) return "-";
  if (row.start_date === row.end_date) return formatDate(row.start_date);
  return `${formatDate(row.start_date)} - ${formatDate(row.end_date)}`;
};

const formatTime = (value) => {
  if (!value) return "-";
  return value.slice(0, 5);
};

const formatShift = (row) => {
  return `${row.shift_label || "-"} (${formatTime(row.shift_start)} - ${formatTime(row.shift_end)})`;
};

const statusLabel = (status) => (status === "aktif" ? "Terjadwal" : "Nonaktif");

/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/

const resetForm = () => {
  form.value = {
    satpam_id: "",
    route_id: "",
    patrol_point_id: "",
    start_date: "",
    end_date: "",
    shift_start: "",
    shift_end: "",
    status: "aktif",
  };
};

const openCreateModal = () => {
  modalMode.value = "create";
  formError.value = "";
  resetForm();
  showModal.value = true;
};

const openEditModal = (row) => {
  modalMode.value = "edit";
  formError.value = "";
  selectedId.value = row.id;

  form.value = {
    satpam_id: row.satpam_id,
    route_id: "",
    patrol_point_id: row.patrol_point_id,
    start_date: row.start_date,
    end_date: row.end_date,
    shift_start: formatTime(row.shift_start),
    shift_end: formatTime(row.shift_end),
    status: row.status,
  };

  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const submitForm = async () => {
  formError.value = "";

  const missingRoute = modalMode.value === "create" && !form.value.route_id;
  const missingPoint = modalMode.value === "edit" && !form.value.patrol_point_id;

  if (
    !form.value.satpam_id ||
    missingRoute ||
    missingPoint ||
    !form.value.start_date ||
    !form.value.end_date ||
    !form.value.shift_start ||
    !form.value.shift_end
  ) {
    formError.value = "Semua field wajib diisi.";
    return;
  }

  saving.value = true;

  try {
    if (modalMode.value === "create") {
      await axios.post(
        "https://sistem-monitoring-keamanan-be.onrender.com/api/supervisor/schedules",
        {
          satpam_id: form.value.satpam_id,
          route_id: form.value.route_id,
          start_date: form.value.start_date,
          end_date: form.value.end_date,
          shift_start: form.value.shift_start,
          shift_end: form.value.shift_end,
          status: form.value.status,
        },
        getAuthHeaders()
      );
    } else {
      await axios.put(
        `https://sistem-monitoring-keamanan-be.onrender.com/api/supervisor/schedules/${selectedId.value}`,
        {
          satpam_id: form.value.satpam_id,
          patrol_point_id: form.value.patrol_point_id,
          start_date: form.value.start_date,
          end_date: form.value.end_date,
          shift_start: form.value.shift_start,
          shift_end: form.value.shift_end,
          status: form.value.status,
        },
        getAuthHeaders()
      );
    }

    closeModal();
    await fetchSchedules();
  } catch (err) {
    const validationErrors = err.response?.data?.errors;

    formError.value = validationErrors
      ? Object.values(validationErrors).flat().join(" ")
      : err.response?.data?.message || "Gagal menyimpan jadwal.";
  } finally {
    saving.value = false;
  }
};

const deleteSchedule = async (row) => {
  if (!confirm(`Hapus jadwal ${row.satpam_name} di ${row.area}?`)) return;

  try {
    await axios.delete(
      `https://sistem-monitoring-keamanan-be.onrender.com/api/supervisor/schedules/${row.id}`,
      getAuthHeaders()
    );

    await fetchSchedules();
  } catch (err) {
    alert(err.response?.data?.message || "Gagal menghapus jadwal.");
  }
};

const showImportModal = ref(false);
const importFile = ref(null);
const importFileInputRef = ref(null);
const importing = ref(false);
const downloadingTemplate = ref(false);
const importError = ref("");
const importResult = ref(null);

const importJadwal = () => {
  importFile.value = null;
  importError.value = "";
  importResult.value = null;
  showImportModal.value = true;
};

const closeImportModal = () => {
  showImportModal.value = false;

  // Kalau ada yang berhasil diimpor, refresh daftar jadwal di belakang modal.
  if (importResult.value?.imported > 0) {
    fetchSchedules();
  }
};

const onImportFileSelected = (event) => {
  importFile.value = event.target.files?.[0] || null;
  importResult.value = null;
  importError.value = "";
};

const downloadTemplate = async () => {
  downloadingTemplate.value = true;

  try {
    const response = await axios.get(
      "https://sistem-monitoring-keamanan-be.onrender.com/api/supervisor/schedules-import-template",
      { ...getAuthHeaders(), responseType: "blob" }
    );

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.download = "template_import_jadwal.xlsx";
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (err) {
    importError.value = "Gagal mengunduh template.";
  } finally {
    downloadingTemplate.value = false;
  }
};

const submitImport = async () => {
  importError.value = "";
  importResult.value = null;

  if (!importFile.value) {
    importError.value = "Pilih file Excel atau CSV terlebih dahulu.";
    return;
  }

  importing.value = true;

  try {
    const payload = new FormData();
    payload.append("file", importFile.value);

    const response = await axios.post(
      "https://sistem-monitoring-keamanan-be.onrender.com/api/supervisor/schedules-import",
      payload,
      {
        headers: {
          ...getAuthHeaders().headers,
          "Content-Type": "multipart/form-data",
        },
      }
    );

    importResult.value = response.data;
  } catch (err) {
    const validationErrors = err.response?.data?.errors;

    importError.value = validationErrors
      ? Object.values(validationErrors).flat().join(" ")
      : err.response?.data?.message || "Gagal mengimpor file.";
  } finally {
    importing.value = false;
  }
};

onMounted(() => {
  loadUser();
  fetchSchedules();
  fetchOptions();
});
</script>

<template>
  <div class="supervisor-layout">
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
        <router-link to="/supervisor/dashboard" class="nav-item">
          <span class="nav-icon">⌂</span>
          <span class="nav-label">Dashboard</span>
        </router-link>

        <router-link to="/supervisor/schedules" class="nav-item">
          <span class="nav-icon">🗓</span>
          <span class="nav-label">Kelola Jadwal</span>
        </router-link>

        <router-link to="/supervisor/monitoring" class="nav-item">
          <span class="nav-icon">◉</span>
          <span class="nav-label">Monitoring</span>
        </router-link>

        <router-link to="/supervisor/reports" class="nav-item">
          <span class="nav-icon">▤</span>
          <span class="nav-label">Laporan</span>
        </router-link>
      </nav>

      <div class="sidebar-footer">
        <div class="user-sidebar">
          <div class="sidebar-avatar">{{ displayName.charAt(0).toUpperCase() }}</div>
          <div class="sidebar-user-info">
            <strong>{{ displayName }}</strong>
            <span>Supervisor</span>
          </div>
        </div>

        <button class="logout-button" @click="logout">
          <span>↪</span>
          <span class="nav-label">Keluar</span>
        </button>
      </div>
    </aside>

    <!-- MAIN -->
    <main class="main-content">
      <header class="topbar">
        <div class="page-heading">
          <h1>Kelola Jadwal</h1>
          <p>Atur jadwal patroli untuk setiap petugas keamanan.</p>
        </div>

        <div class="topbar-right">
          <div class="date-info">
            <span class="date-icon">▣</span>
            <div>
              <small>Hari ini</small>
              <strong>{{ currentDate }}</strong>
            </div>
          </div>

          <div class="user-profile">
            <div class="profile-avatar">{{ displayName.charAt(0).toUpperCase() }}</div>
            <div class="profile-info">
              <strong>{{ displayName }}</strong>
              <span>Supervisor</span>
            </div>
          </div>
        </div>
      </header>

      <section class="content">
        <!-- ERROR -->
        <div v-if="error" class="error-alert">
          <span>⚠</span>
          <div>
            <strong>Terjadi kesalahan</strong>
            <p>{{ error }}</p>
          </div>
          <button @click="fetchSchedules">Coba Lagi</button>
        </div>

        <!-- FILTER ROW -->
        <div class="filter-row">
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama atau area..."
            class="search-input"
            @keyup.enter="applyFilter"
          />

          <div class="date-range-input">
            <input v-model="dateFrom" type="date" @change="applyFilter" />
            <span>-</span>
            <input v-model="dateTo" type="date" @change="applyFilter" />
          </div>

          <button class="btn-outline" @click="importJadwal">+ Import jadwal</button>
          <button class="btn-primary" @click="openCreateModal">+ Tambah jadwal</button>
        </div>

        <!-- LOADING -->
        <div v-if="loading" class="loading-box">
          <div class="spinner"></div>
          <p>Memuat data...</p>
        </div>

        <!-- TABLE -->
        <div v-else class="table-card">
          <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Satpam</th>
                <th>Tanggal</th>
                <th>Shift</th>
                <th>Area</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="paginatedSchedules.length === 0">
                <td colspan="6" class="empty-state">Belum ada jadwal yang dibuat.</td>
              </tr>

              <tr v-for="row in paginatedSchedules" :key="row.id">
                <td class="cell-strong">{{ row.satpam_name }}</td>
                <td class="text-secondary">{{ formatDateRange(row) }}</td>
                <td class="text-secondary">{{ formatShift(row) }}</td>
                <td class="text-secondary">{{ row.area }}</td>
                <td>
                  <span class="badge" :class="row.status === 'aktif' ? 'badge-aktif' : 'badge-nonaktif'">
                    {{ statusLabel(row.status) }}
                  </span>
                </td>
                <td>
                  <div class="action-buttons">
                    <button class="btn-icon" title="Edit" @click="openEditModal(row)">✏️</button>
                    <button class="btn-icon btn-icon-danger" title="Hapus" @click="deleteSchedule(row)">🗑️</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          </div>

          <!-- PAGINATION -->
          <div v-if="schedules.length > perPage" class="pagination">
            <button :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">‹</button>

            <button
              v-for="page in totalPages"
              :key="page"
              :class="{ active: page === currentPage }"
              @click="goToPage(page)"
            >
              {{ page }}
            </button>

            <button :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">›</button>
          </div>
        </div>
      </section>
    </main>

    <!-- MODAL TAMBAH / EDIT -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ modalMode === "create" ? "Tambah Jadwal" : "Edit Jadwal" }}</h3>
          <button class="modal-close" @click="closeModal">✕</button>
        </div>

        <div class="modal-body">
          <div v-if="formError" class="modal-error">{{ formError }}</div>

          <div class="form-group">
            <label>Satpam</label>
            <select v-model="form.satpam_id">
              <option value="" disabled>Pilih satpam</option>
              <option v-for="s in satpamOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>

          <!-- TAMBAH: pilih rute -->
          <div v-if="modalMode === 'create'" class="form-group">
            <label>Rute Patroli</label>
            <select v-model="form.route_id">
              <option value="" disabled>Pilih rute</option>
              <option v-for="r in routeOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>

            <div v-if="routeOptions.length === 0" class="field-hint warning">
              Belum ada rute patroli. Buat dulu di halaman "Rute Patroli" (Admin → Titik Patroli).
            </div>

            <div v-else-if="selectedRoutePoints.length" class="route-preview">
              <span
                v-for="(point, index) in selectedRoutePoints"
                :key="point.patrol_point_id"
                class="route-preview-item"
              >
                <b>{{ index + 1 }}</b> {{ point.name }}
                <template v-if="index !== selectedRoutePoints.length - 1"> → </template>
              </span>
            </div>
          </div>

          <!-- EDIT: satu titik saja, karena tiap baris di tabel mewakili 1 titik -->
          <div v-else class="form-group">
            <label>Area / Titik Patroli</label>
            <select v-model="form.patrol_point_id">
              <option value="" disabled>Pilih area</option>
              <option v-for="p in patrolPointOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Tanggal Mulai</label>
              <input v-model="form.start_date" type="date" />
            </div>
            <div class="form-group">
              <label>Tanggal Selesai</label>
              <input v-model="form.end_date" type="date" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Shift Mulai</label>
              <input v-model="form.shift_start" type="time" />
            </div>
            <div class="form-group">
              <label>Shift Selesai</label>
              <input v-model="form.shift_end" type="time" />
            </div>
          </div>

          <p v-if="modalMode === 'create' && selectedRoutePoints.length" class="field-hint">
            Jam ini berlaku untuk semua {{ selectedRoutePoints.length }} titik di rute ini.
          </p>

          <div class="form-group" v-if="modalMode === 'edit'">
            <label>Status</label>
            <select v-model="form.status">
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Nonaktif</option>
            </select>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-cancel" @click="closeModal">Batal</button>
          <button class="btn-primary" :disabled="saving" @click="submitForm">
            {{ saving ? "Menyimpan..." : "Simpan" }}
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL IMPORT -->
    <div v-if="showImportModal" class="modal-overlay" @click.self="closeImportModal">
      <div class="modal">
        <div class="modal-header">
          <h3>Import Jadwal</h3>
          <button class="modal-close" @click="closeImportModal">✕</button>
        </div>

        <div class="modal-body">
          <p class="field-hint" style="margin-bottom: 16px">
            Tiap baris di file = 1 penugasan (satpam + rute). Titik-titik patroli otomatis
            diambil dari rute yang dipilih. Pastikan satpam dan rute-nya sudah terdaftar dulu.
          </p>

          <button class="btn-outline" style="width: 100%; margin-bottom: 18px" :disabled="downloadingTemplate" @click="downloadTemplate">
            {{ downloadingTemplate ? "Mengunduh..." : "⬇ Download Template Excel" }}
          </button>

          <div v-if="importError" class="modal-error">{{ importError }}</div>

          <div class="form-group">
            <label>File Excel / CSV</label>
            <input
              ref="importFileInputRef"
              type="file"
              accept=".xlsx,.xls,.csv"
              @change="onImportFileSelected"
            />
          </div>

          <!-- HASIL IMPORT -->
          <div v-if="importResult" class="import-result">
            <div class="import-summary">
              <span class="import-ok">{{ importResult.imported }} berhasil</span>
              <span v-if="importResult.failed" class="import-fail">{{ importResult.failed }} gagal</span>
            </div>

            <div v-if="importResult.errors?.length" class="import-errors">
              <div v-for="err in importResult.errors" :key="err.row" class="import-error-row">
                Baris {{ err.row }}: {{ err.message }}
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-cancel" @click="closeImportModal">
            {{ importResult ? "Selesai" : "Batal" }}
          </button>
          <button class="btn-primary" :disabled="importing || !importFile" @click="submitImport">
            {{ importing ? "Memproses..." : "Upload & Proses" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
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

/* MAIN */
.main-content {
  width: 100%;
  min-height: 100vh;
  margin-left: 260px;
}

/* TOPBAR */
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

/* CONTENT */
.content {
  max-width: 1400px;
  margin: 0 auto;
  padding: 34px 42px 50px;
  box-sizing: border-box;
}

/* ERROR */
.error-alert {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 22px;
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

/* FILTER ROW */
.filter-row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 22px;
}

.search-input {
  flex: 1;
  min-width: 220px;
  height: 46px;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0 16px;
  font-size: 13px;
  outline: none;
  background: var(--white);
  box-sizing: border-box;
}

.search-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(232, 117, 0, 0.08);
}

.date-range-input {
  display: flex;
  align-items: center;
  gap: 6px;
  height: 46px;
  padding: 0 10px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--white);
  color: var(--text-secondary);
  font-size: 12px;
}

.date-range-input input {
  border: none;
  outline: none;
  font-size: 12px;
  font-family: inherit;
  color: var(--text-primary);
  background: transparent;
}

.btn-outline {
  height: 46px;
  padding: 0 20px;
  border: 1px solid var(--primary);
  border-radius: 10px;
  background: var(--white);
  color: var(--primary);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.btn-outline:hover {
  background: var(--background);
}

.btn-primary {
  height: 46px;
  padding: 0 22px;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--accent), var(--accent-light));
  color: white;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  box-shadow: 0 6px 16px rgba(232, 117, 0, 0.25);
}

.btn-primary:hover {
  transform: translateY(-1px);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* LOADING */
.loading-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60px;
  color: var(--text-secondary);
}

.spinner {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 3px solid var(--border);
  border-top-color: var(--accent);
  animation: spin 0.7s linear infinite;
  margin-bottom: 14px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* TABLE */
.table-card {
  background: var(--white);
  border-radius: 16px;
  border: 1px solid var(--border);
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(31, 36, 84, 0.06);
}

.table-scroll {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

table {
  width: 100%;
  min-width: 640px;
  border-collapse: collapse;
}

thead {
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

thead th {
  padding: 16px 20px;
  text-align: left;
  color: white;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.8px;
  text-transform: uppercase;
}

tbody tr {
  border-bottom: 1px solid var(--border);
  transition: 0.2s;
}

tbody tr:hover {
  background: #f8f9fc;
}

tbody tr:last-child {
  border-bottom: none;
}

td {
  padding: 16px 20px;
  font-size: 13px;
  color: var(--text-primary);
}

.cell-strong {
  font-weight: 600;
  color: var(--primary);
}

.text-secondary {
  color: var(--text-secondary);
}

.empty-state {
  text-align: center;
  color: var(--text-secondary);
  padding: 40px;
}

/* BADGE */
.badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
}

.badge-aktif {
  background: rgba(53, 120, 229, 0.12);
  color: #3578e5;
}

.badge-nonaktif {
  background: #f1f2f6;
  color: var(--text-secondary);
}

/* ACTIONS */
.action-buttons {
  display: flex;
  gap: 8px;
}

.btn-icon {
  width: 34px;
  height: 34px;
  border: none;
  border-radius: 8px;
  background: var(--background);
  color: var(--text-primary);
  font-size: 14px;
  cursor: pointer;
  transition: 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-icon:hover {
  background: var(--border);
}

.btn-icon-danger:hover {
  background: rgba(214, 48, 49, 0.1);
}

/* PAGINATION */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 18px;
  border-top: 1px solid var(--border);
}

.pagination button {
  min-width: 34px;
  height: 34px;
  padding: 0 8px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--white);
  color: var(--text-primary);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.pagination button.active {
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  color: white;
  border-color: transparent;
}

.pagination button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
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
  z-index: 100;
  padding: 20px;
  box-sizing: border-box;
}

.modal {
  background: var(--white);
  border-radius: 20px;
  width: 480px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 30px 80px rgba(31, 36, 84, 0.2);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 22px 26px;
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  background: var(--white);
}

.modal-header h3 {
  margin: 0;
  font-size: 17px;
  color: var(--primary);
}

.modal-close {
  border: none;
  background: none;
  font-size: 17px;
  color: var(--text-secondary);
  cursor: pointer;
}

.modal-body {
  padding: 24px 26px;
}

.modal-error {
  margin-bottom: 16px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #fff1f1;
  border-left: 3px solid var(--danger);
  color: var(--danger);
  font-size: 12px;
}

.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  margin-bottom: 7px;
  font-size: 13px;
  font-weight: 600;
  color: var(--primary);
}

.form-group input,
.form-group select {
  width: 100%;
  height: 44px;
  box-sizing: border-box;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0 13px;
  font-size: 13px;
  font-family: inherit;
  outline: none;
  background: var(--white);
  color: var(--text-primary);
}

.form-group input:focus,
.form-group select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(232, 117, 0, 0.08);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.field-hint {
  margin: -8px 0 16px;
  font-size: 11px;
  color: var(--text-secondary);
  line-height: 1.6;
}

.field-hint.warning {
  margin-top: 8px;
  padding: 10px 12px;
  border-radius: 8px;
  background: #fff3e8;
  color: var(--accent);
}

.route-preview {
  margin-top: 10px;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--background);
  font-size: 12px;
  line-height: 2;
}

.route-preview-item {
  color: var(--text-primary);
}

.route-preview-item b {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: rgba(232, 117, 0, 0.15);
  color: var(--accent);
  font-size: 10px;
  margin-right: 4px;
}

/* IMPORT RESULT */
.import-result {
  margin-top: 18px;
}

.import-summary {
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
}

.import-ok,
.import-fail {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
}

.import-ok {
  background: rgba(47, 158, 99, 0.12);
  color: var(--success);
}

.import-fail {
  background: rgba(214, 48, 49, 0.1);
  color: var(--danger);
}

.import-errors {
  max-height: 220px;
  overflow-y: auto;
  border: 1px solid var(--border);
  border-radius: 10px;
}

.import-error-row {
  padding: 10px 12px;
  font-size: 12px;
  color: var(--danger);
  border-bottom: 1px solid var(--border);
}

.import-error-row:last-child {
  border-bottom: none;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 18px 26px;
  border-top: 1px solid var(--border);
  position: sticky;
  bottom: 0;
  background: var(--white);
}

.btn-cancel {
  padding: 11px 20px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: transparent;
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-cancel:hover {
  background: var(--background);
}

@media (max-width: 1100px) {
  .content {
    padding: 28px 24px 40px;
  }

  .topbar {
    padding: 0 24px;
  }
}

@media (max-width: 900px) {
  .sidebar {
    width: 76px;
    padding: 20px 10px;
  }

  .sidebar-brand {
    justify-content: center;
    padding: 0 0 28px;
  }

  .brand-info,
  .nav-label,
  .sidebar-user-info {
    display: none;
  }

  .nav-item {
    justify-content: center;
    padding: 0;
  }

  .logout-button {
    justify-content: center;
    padding: 0;
  }

  .main-content {
    margin-left: 76px;
  }

  .date-info {
    display: none;
  }
}

@media (max-width: 700px) {
  .sidebar {
    display: none;
  }

  .main-content {
    margin-left: 0;
  }

  .topbar {
    min-height: auto;
    padding: 16px 20px;
  }

  .page-heading p {
    display: none;
  }

  .profile-info {
    display: none;
  }

  .content {
    padding: 20px 16px 40px;
  }

  .filter-row {
    flex-direction: column;
    align-items: stretch;
  }

  .date-range-input {
    width: 100%;
    box-sizing: border-box;
    justify-content: space-between;
  }

  .btn-outline,
  .btn-primary {
    width: 100%;
    text-align: center;
  }

  .modal {
    width: 100%;
    border-radius: 18px 18px 0 0;
    max-height: 85vh;
    margin-top: auto;
  }

  .modal-overlay {
    align-items: flex-end;
    padding: 0;
  }

  .form-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 420px) {
  .date-range-input {
    flex-direction: column;
    align-items: stretch;
    height: auto;
    padding: 10px;
    gap: 8px;
  }

  .date-range-input span {
    display: none;
  }

  .date-range-input input {
    width: 100%;
  }
}
</style>
