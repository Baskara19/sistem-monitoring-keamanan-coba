<script setup>
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";
import Swal from "sweetalert2";

const router = useRouter();

const routes = ref([]);
const patrolPoints = ref([]);
const loading = ref(true);
const error = ref("");
const search = ref("");

const showModal = ref(false);
const modalMode = ref("create");
const selectedRouteId = ref(null);
const saving = ref(false);
const formError = ref("");

const form = ref({
  name: "",
  description: "",
  status: "aktif",
  point_ids: [],
});

const pointToAdd = ref("");

const user = ref({ name: "Admin", role: "admin" });

const getAuthHeaders = () => {
  const token = localStorage.getItem("token");
  return { headers: { Authorization: `Bearer ${token}` } };
};

const loadUser = () => {
  const storedUser = localStorage.getItem("user");
  if (storedUser) {
    try {
      user.value = JSON.parse(storedUser);
    } catch {
      console.error("Data user tidak valid");
    }
  }
};

const fetchRoutes = async () => {
  loading.value = true;
  error.value = "";

  try {
    const response = await axios.get("http://127.0.0.1:8000/api/admin/routes", getAuthHeaders());
    routes.value = response.data.routes ?? [];
  } catch (err) {
    error.value = err.response?.data?.message || "Gagal memuat data rute patroli.";
  } finally {
    loading.value = false;
  }
};

const fetchPatrolPoints = async () => {
  try {
    const response = await axios.get("http://127.0.0.1:8000/api/admin/patrol-points", getAuthHeaders());
    patrolPoints.value = response.data.patrol_points ?? [];
  } catch (err) {
    console.error(err);
  }
};

const filteredRoutes = computed(() => {
  const keyword = search.value.toLowerCase();
  return routes.value.filter((r) => r.name.toLowerCase().includes(keyword));
});

// Titik yang belum dipilih di form, buat opsi dropdown "+ Tambah Titik"
const availablePoints = computed(() => {
  return patrolPoints.value.filter((p) => !form.value.point_ids.includes(p.id));
});

const selectedPointObjects = computed(() => {
  return form.value.point_ids
    .map((id) => patrolPoints.value.find((p) => p.id === id))
    .filter(Boolean);
});

const addPoint = () => {
  if (!pointToAdd.value) return;
  form.value.point_ids.push(Number(pointToAdd.value));
  pointToAdd.value = "";
};

const removePoint = (index) => {
  form.value.point_ids.splice(index, 1);
};

const movePoint = (index, direction) => {
  const target = index + direction;
  if (target < 0 || target >= form.value.point_ids.length) return;

  const items = form.value.point_ids;
  [items[index], items[target]] = [items[target], items[index]];
};

const resetForm = () => {
  form.value = { name: "", description: "", status: "aktif", point_ids: [] };
  pointToAdd.value = "";
};

const openCreateModal = () => {
  modalMode.value = "create";
  formError.value = "";
  resetForm();
  showModal.value = true;
};

const openEditModal = (route) => {
  modalMode.value = "edit";
  formError.value = "";
  selectedRouteId.value = route.id;

  form.value = {
    name: route.name,
    description: route.description || "",
    status: route.status,
    point_ids: route.points
      .slice()
      .sort((a, b) => a.sequence_order - b.sequence_order)
      .map((p) => p.patrol_point_id),
  };

  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const submitForm = async () => {
  formError.value = "";

  if (!form.value.name.trim()) {
    formError.value = "Nama rute wajib diisi.";
    return;
  }

  if (form.value.point_ids.length === 0) {
    formError.value = "Pilih minimal 1 titik patroli untuk rute ini.";
    return;
  }

  saving.value = true;

  try {
    const payload = {
      name: form.value.name,
      description: form.value.description || null,
      status: form.value.status,
      patrol_point_ids: form.value.point_ids,
    };

    if (modalMode.value === "create") {
      await axios.post("http://127.0.0.1:8000/api/admin/routes", payload, getAuthHeaders());
    } else {
      await axios.put(
        `http://127.0.0.1:8000/api/admin/routes/${selectedRouteId.value}`,
        payload,
        getAuthHeaders()
      );
    }

    closeModal();
    await fetchRoutes();
  } catch (err) {
    const validationErrors = err.response?.data?.errors;
    formError.value = validationErrors
      ? Object.values(validationErrors).flat().join(" ")
      : err.response?.data?.message || "Gagal menyimpan rute patroli.";
  } finally {
    saving.value = false;
  }
};

const deleteRoute = async (route) => {
  const result = await Swal.fire({
    icon: "warning",
    title: "Hapus Rute Patroli?",
    text: `Rute "${route.name}" akan dihapus permanen.`,
    showCancelButton: true,
    confirmButtonText: "Ya, Hapus",
    cancelButtonText: "Batal",
    confirmButtonColor: "#d63031",
  });

  if (!result.isConfirmed) return;

  try {
    await axios.delete(`http://127.0.0.1:8000/api/admin/routes/${route.id}`, getAuthHeaders());
    await fetchRoutes();
  } catch (err) {
    Swal.fire({
      icon: "error",
      title: "Gagal Menghapus",
      text: err.response?.data?.message || "Terjadi kesalahan.",
    });
  }
};

const displayName = computed(() => user.value?.name || "Admin");

const goBack = () => {
  router.push("/admin/patrol-points");
};

const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");
  router.push("/");
};

onMounted(() => {
  loadUser();
  fetchRoutes();
  fetchPatrolPoints();
});
</script>

<template>
  <div class="admin-layout">
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
        <router-link to="/admin/dashboard" class="nav-item">
          <span class="nav-icon">⌂</span>
          Dashboard
        </router-link>
        <router-link to="/admin/users" class="nav-item">
          <span class="nav-icon">♟</span>
          Kelola User
        </router-link>
        <router-link to="/admin/patrol-points" class="nav-item">
          <span class="nav-icon">⌖</span>
          Titik Patroli
        </router-link>
      </nav>

      <div class="sidebar-footer">
        <button class="logout-button" @click="logout">
          <span>↪</span>
          Keluar
        </button>
      </div>
    </aside>

    <!-- MAIN -->
    <main class="main-content">
      <header class="topbar">
        <div class="page-heading">
          <button class="back-btn" @click="goBack">← Titik Patroli</button>
          <h1>Rute Patroli</h1>
          <p>Susun titik patroli jadi satu rute yang bisa dipakai berulang saat bikin jadwal.</p>
        </div>

        <div class="user-profile">
          <div class="profile-avatar">{{ displayName.charAt(0).toUpperCase() }}</div>
          <div class="profile-info">
            <strong>{{ displayName }}</strong>
            <span>Administrator</span>
          </div>
        </div>
      </header>

      <section class="content">
        <div v-if="error" class="error-alert">
          <span>⚠</span>
          <div>
            <strong>Terjadi kesalahan</strong>
            <p>{{ error }}</p>
          </div>
          <button @click="fetchRoutes">Coba Lagi</button>
        </div>

        <div class="content-header">
          <div>
            <div class="section-label">TITIK PATROLI</div>
            <h2>Daftar Rute</h2>
            <p>Total {{ filteredRoutes.length }} rute patroli</p>
          </div>

          <button class="btn-primary" @click="openCreateModal">+ Tambah Rute</button>
        </div>

        <input
          v-model="search"
          type="text"
          placeholder="Cari nama rute..."
          class="search-input"
        />

        <div v-if="loading" class="loading-box">
          <div class="spinner"></div>
          <p>Memuat data...</p>
        </div>

        <div v-else-if="filteredRoutes.length === 0" class="empty-state">
          Belum ada rute patroli. Klik "+ Tambah Rute" buat bikin yang pertama.
        </div>

        <div v-else class="route-grid">
          <article v-for="route in filteredRoutes" :key="route.id" class="route-card">
            <div class="route-card-top">
              <div>
                <h3>{{ route.name }}</h3>
                <span class="badge" :class="route.status === 'aktif' ? 'badge-aktif' : 'badge-nonaktif'">
                  {{ route.status === "aktif" ? "Aktif" : "Nonaktif" }}
                </span>
              </div>

              <div class="route-actions">
                <button class="btn-icon" title="Edit" @click="openEditModal(route)">✏️</button>
                <button class="btn-icon btn-icon-danger" title="Hapus" @click="deleteRoute(route)">🗑️</button>
              </div>
            </div>

            <p v-if="route.description" class="route-description">{{ route.description }}</p>

            <div class="route-points">
              <div v-for="(point, index) in route.points" :key="point.id" class="route-point">
                <span class="point-badge">{{ index + 1 }}</span>
                <span>{{ point.name }}</span>
                <span v-if="index !== route.points.length - 1" class="point-arrow">→</span>
              </div>
            </div>
          </article>
        </div>
      </section>
    </main>

    <!-- MODAL -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ modalMode === "create" ? "Tambah Rute" : "Edit Rute" }}</h3>
          <button class="modal-close" @click="closeModal">✕</button>
        </div>

        <div class="modal-body">
          <div v-if="formError" class="modal-error">{{ formError }}</div>

          <div class="form-group">
            <label>Nama Rute</label>
            <input v-model="form.name" type="text" placeholder="Contoh: Rute Malam A" />
          </div>

          <div class="form-group">
            <label>Deskripsi (Opsional)</label>
            <textarea v-model="form.description" rows="2" placeholder="Catatan tentang rute ini..."></textarea>
          </div>

          <div class="form-group">
            <label>Status</label>
            <select v-model="form.status">
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Nonaktif</option>
            </select>
          </div>

          <div class="form-group">
            <label>Titik Patroli (urutan sesuai list di bawah)</label>

            <div class="point-list">
              <div v-if="selectedPointObjects.length === 0" class="point-list-empty">
                Belum ada titik dipilih.
              </div>

              <div v-for="(point, index) in selectedPointObjects" :key="point.id" class="point-list-item">
                <span class="point-index">{{ index + 1 }}</span>
                <span class="point-name">{{ point.name }}</span>

                <div class="point-item-actions">
                  <button type="button" :disabled="index === 0" @click="movePoint(index, -1)">↑</button>
                  <button
                    type="button"
                    :disabled="index === selectedPointObjects.length - 1"
                    @click="movePoint(index, 1)"
                  >
                    ↓
                  </button>
                  <button type="button" class="remove-btn" @click="removePoint(index)">✕</button>
                </div>
              </div>
            </div>

            <div class="add-point-row">
              <select v-model="pointToAdd">
                <option value="" disabled>Pilih titik untuk ditambahkan</option>
                <option v-for="p in availablePoints" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
              <button type="button" class="btn-add" :disabled="!pointToAdd" @click="addPoint">+ Tambah</button>
            </div>
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
  </div>
</template>

<style scoped>
.admin-layout {
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

.brand-mark { font-size: 27px; font-weight: 900; font-style: italic; letter-spacing: -2px; }
.brand-info { display: flex; flex-direction: column; }
.brand-info h2 { margin: 0; font-size: 13px; letter-spacing: 1px; }
.brand-info span { margin-top: 3px; color: #bfc2d5; font-size: 8px; letter-spacing: 1.5px; }

.sidebar-nav { display: flex; flex-direction: column; gap: 8px; }

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

.nav-item:hover { background: rgba(255, 255, 255, 0.08); color: white; }

.nav-item.router-link-active {
  background: linear-gradient(135deg, var(--accent), var(--accent-light));
  color: white;
  box-shadow: 0 8px 20px rgba(232, 117, 0, 0.2);
}

.nav-icon { width: 20px; text-align: center; font-size: 18px; }

.sidebar-footer { margin-top: auto; padding-top: 20px; }

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

.logout-button:hover { background: rgba(214, 48, 49, 0.16); color: #ffb7b7; }

/* MAIN */
.main-content { width: 100%; min-height: 100vh; margin-left: 260px; }

/* TOPBAR */
.topbar {
  min-height: 100px;
  padding: 20px 42px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(255, 255, 255, 0.88);
  border-bottom: 1px solid var(--border);
  backdrop-filter: blur(10px);
  gap: 20px;
}

.back-btn {
  border: none;
  background: none;
  color: var(--accent);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  padding: 0 0 6px;
}

.page-heading h1 { margin: 0; font-size: 23px; font-weight: 700; color: var(--primary); }
.page-heading p { margin: 4px 0 0; color: var(--text-secondary); font-size: 12px; }

.user-profile { display: flex; align-items: center; gap: 11px; flex-shrink: 0; }

.profile-avatar {
  width: 42px; height: 42px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 13px;
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  color: white; font-size: 15px; font-weight: 700;
}

.profile-info { display: flex; flex-direction: column; }
.profile-info strong { font-size: 13px; }
.profile-info span { margin-top: 2px; color: var(--text-secondary); font-size: 11px; }

/* CONTENT */
.content {
  max-width: 1400px;
  margin: 0 auto;
  padding: 34px 42px 50px;
  box-sizing: border-box;
}

.error-alert {
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 22px; padding: 15px 18px;
  background: #fff1f1; border-left: 4px solid var(--danger);
  border-radius: 10px; color: var(--danger);
}
.error-alert strong { display: block; font-size: 13px; }
.error-alert p { margin: 3px 0 0; font-size: 12px; }
.error-alert button {
  margin-left: auto; border: none; border-radius: 8px;
  padding: 9px 14px; background: var(--danger); color: white;
  font-size: 11px; font-weight: 700; cursor: pointer;
}

.content-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 20px;
}

.section-label { color: var(--accent); font-size: 10px; font-weight: 800; letter-spacing: 1.4px; }
.content-header h2 { margin: 6px 0 4px; font-size: 24px; color: var(--primary); }
.content-header p { margin: 0; color: var(--text-secondary); font-size: 13px; }

.search-input {
  width: 100%;
  max-width: 360px;
  height: 46px;
  box-sizing: border-box;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0 16px;
  font-size: 13px;
  outline: none;
  background: var(--white);
  margin-bottom: 20px;
}

.search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(232, 117, 0, 0.08); }

/* LOADING / EMPTY */
.loading-box {
  display: flex; flex-direction: column; align-items: center;
  padding: 60px; color: var(--text-secondary);
}

.spinner {
  width: 36px; height: 36px; border-radius: 50%;
  border: 3px solid var(--border); border-top-color: var(--accent);
  animation: spin 0.7s linear infinite; margin-bottom: 14px;
}

@keyframes spin { to { transform: rotate(360deg); } }

.empty-state {
  padding: 50px; text-align: center; color: var(--text-secondary);
  background: var(--white); border: 1px solid var(--border); border-radius: 16px;
}

/* ROUTE GRID */
.route-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 18px;
}

.route-card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 4px 20px rgba(31, 36, 84, 0.06);
}

.route-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 10px;
}

.route-card-top h3 {
  margin: 0 0 6px;
  font-size: 15px;
  color: var(--primary);
}

.route-actions { display: flex; gap: 6px; flex-shrink: 0; }

.badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 700;
}

.badge-aktif { background: rgba(53, 120, 229, 0.12); color: #3578e5; }
.badge-nonaktif { background: #f1f2f6; color: var(--text-secondary); }

.route-description {
  margin: 0 0 14px;
  font-size: 12px;
  color: var(--text-secondary);
  line-height: 1.6;
}

.route-points {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.route-point {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--text-primary);
}

.point-badge {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: rgba(232, 117, 0, 0.12);
  color: var(--accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 700;
  flex-shrink: 0;
}

.point-arrow { color: var(--border); font-size: 12px; }

.btn-icon {
  width: 32px; height: 32px; border: none; border-radius: 8px;
  background: var(--background); color: var(--text-primary);
  font-size: 13px; cursor: pointer; display: flex;
  align-items: center; justify-content: center;
}
.btn-icon:hover { background: var(--border); }
.btn-icon-danger:hover { background: rgba(214, 48, 49, 0.1); }

.btn-primary {
  padding: 11px 22px;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--accent), var(--accent-light));
  color: white;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 6px 16px rgba(232, 117, 0, 0.25);
}

.btn-primary:hover { transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

/* MODAL */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(31, 36, 84, 0.4);
  backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  z-index: 100; padding: 20px; box-sizing: border-box;
}

.modal {
  background: var(--white);
  border-radius: 20px;
  width: 520px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 30px 80px rgba(31, 36, 84, 0.2);
}

.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 22px 26px; border-bottom: 1px solid var(--border);
  position: sticky; top: 0; background: var(--white);
}

.modal-header h3 { margin: 0; font-size: 17px; color: var(--primary); }
.modal-close { border: none; background: none; font-size: 17px; color: var(--text-secondary); cursor: pointer; }

.modal-body { padding: 24px 26px; }

.modal-error {
  margin-bottom: 16px; padding: 10px 12px; border-radius: 10px;
  background: #fff1f1; border-left: 3px solid var(--danger);
  color: var(--danger); font-size: 12px;
}

.form-group { margin-bottom: 18px; }

.form-group label {
  display: block; margin-bottom: 7px; font-size: 13px;
  font-weight: 600; color: var(--primary);
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0 13px;
  height: 44px;
  font-size: 13px;
  font-family: inherit;
  outline: none;
  background: var(--white);
  color: var(--text-primary);
}

.form-group textarea {
  height: auto;
  padding: 10px 13px;
  resize: vertical;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(232, 117, 0, 0.08);
}

/* POINT LIST */
.point-list {
  border: 1px solid var(--border);
  border-radius: 12px;
  margin-bottom: 12px;
  overflow: hidden;
}

.point-list-empty {
  padding: 18px;
  text-align: center;
  color: var(--text-secondary);
  font-size: 12px;
}

.point-list-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border-bottom: 1px solid var(--border);
}

.point-list-item:last-child { border-bottom: none; }

.point-index {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: rgba(31, 36, 84, 0.08);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  flex-shrink: 0;
}

.point-name {
  flex: 1;
  font-size: 13px;
  color: var(--text-primary);
}

.point-item-actions {
  display: flex;
  gap: 4px;
}

.point-item-actions button {
  width: 26px;
  height: 26px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--white);
  color: var(--text-primary);
  font-size: 11px;
  cursor: pointer;
}

.point-item-actions button:disabled { opacity: 0.35; cursor: not-allowed; }

.point-item-actions .remove-btn {
  border-color: rgba(214, 48, 49, 0.3);
  color: var(--danger);
}

.add-point-row {
  display: flex;
  gap: 8px;
}

.add-point-row select {
  flex: 1;
  height: 42px;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0 12px;
  font-size: 13px;
  font-family: inherit;
  background: var(--white);
  color: var(--text-primary);
}

.btn-add {
  height: 42px;
  padding: 0 16px;
  border: none;
  border-radius: 10px;
  background: var(--primary);
  color: white;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.btn-add:disabled { opacity: 0.5; cursor: not-allowed; }

.modal-footer {
  display: flex; justify-content: flex-end; gap: 12px;
  padding: 18px 26px; border-top: 1px solid var(--border);
  position: sticky; bottom: 0; background: var(--white);
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

.btn-cancel:hover { background: var(--background); }

@media (max-width: 900px) {
  .sidebar { width: 76px; padding: 20px 10px; }
  .brand-info { display: none; }
  .nav-item { justify-content: center; padding: 0; font-size: 0; }
  .nav-icon { font-size: 20px; }
  .logout-button { justify-content: center; padding: 0; font-size: 0; }
  .logout-button span { font-size: 18px; }
  .main-content { margin-left: 76px; }
}

@media (max-width: 700px) {
  .sidebar { display: none; }
  .main-content { margin-left: 0; }
  .topbar { padding: 16px 20px; flex-direction: column; align-items: flex-start; }
  .content { padding: 20px 16px 40px; }
  .content-header { flex-direction: column; align-items: flex-start; gap: 12px; }
  .btn-primary { width: 100%; }
  .search-input { max-width: 100%; }
  .route-grid { grid-template-columns: 1fr; }
  .modal { width: 100%; }
}
</style>
