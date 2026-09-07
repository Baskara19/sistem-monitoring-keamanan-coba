<script setup>
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";

const router = useRouter();

const users = ref([]);
const loading = ref(true);
const error = ref("");
const search = ref("");
const filterRole = ref("");

const showModal = ref(false);
const modalMode = ref("create");
const selectedUser = ref(null);

const form = ref({
  name: "",
  username: "",
  email: "",
  password: "",
  role: "satpam",
  status: "aktif",
  phone: "",
  location: "",
});

const user = ref({ name: "Admin", role: "admin" });

const getAuthHeaders = () => {
  const token = localStorage.getItem("token");
  return { headers: { Authorization: `Bearer ${token}` } };
};

const loadUser = () => {
  const storedUser = localStorage.getItem("user");
  if (storedUser) user.value = JSON.parse(storedUser);
};

const fetchUsers = async () => {
  loading.value = true;
  error.value = "";
  try {
    const response = await axios.get("https://sistem-monitoring-keamanan-be.onrender.com/api/admin/users", getAuthHeaders());
    users.value = response.data.users;
  } catch (err) {
    error.value = err.response?.data?.message || "Gagal memuat data user.";
  } finally {
    loading.value = false;
  }
};

const filteredUsers = computed(() => {
  return users.value.filter((u) => {
    const matchSearch =
      u.name.toLowerCase().includes(search.value.toLowerCase()) ||
      (u.username && u.username.toLowerCase().includes(search.value.toLowerCase()));
    const matchRole = filterRole.value === "" || u.role === filterRole.value;
    return matchSearch && matchRole;
  });
});

const openCreateModal = () => {
  modalMode.value = "create";
  form.value = {
    name: "",
    username: "",
    email: "",
    password: "",
    role: "satpam",
    status: "aktif",
    phone: "",
    location: "",
  };
  showModal.value = true;
};

const openEditModal = (u) => {
  modalMode.value = "edit";
  selectedUser.value = u;
  form.value = {
    name: u.name,
    username: u.username || "",
    email: u.email,
    password: "",
    role: u.role,
    status: u.status,
    phone: u.phone || "",
    location: u.location || "",
  };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  selectedUser.value = null;
  error.value = "";
};

const submitForm = async () => {
  error.value = "";

  // ==============================
  // VALIDASI PASSWORD
  // ==============================

  // Saat tambah user
  if (modalMode.value === "create" && form.value.password.length < 6) {
    error.value = "Password minimal 6 karakter.";
    return;
  }

  // Saat edit user:
  // password boleh kosong karena berarti tidak diubah
  if (
    modalMode.value === "edit" &&
    form.value.password.length > 0 &&
    form.value.password.length < 6
  ) {
    error.value = "Password baru minimal 6 karakter.";
    return;
  }

  try {
    // ==============================
    // TAMBAH USER
    // ==============================
    if (modalMode.value === "create") {
      await axios.post("https://sistem-monitoring-keamanan-be.onrender.com/api/admin/users", form.value, getAuthHeaders());
    }

    // ==============================
    // UPDATE USER
    // ==============================
    else {
      const updateData = {
        name: form.value.name,
        username: form.value.username,
        email: form.value.email,
        role: form.value.role,
        status: form.value.status,
        phone: form.value.phone,
        location: form.value.location,
      };

      // Password hanya dikirim kalau memang diisi
      if (form.value.password.trim() !== "") {
        updateData.password = form.value.password;
      }

      console.log("UPDATE USER:", selectedUser.value.id);
      console.log("DATA UPDATE:", updateData);

      await axios.put(
        `https://sistem-monitoring-keamanan-be.onrender.com/api/admin/users/${selectedUser.value.id}`,
        updateData,
        getAuthHeaders(),
      );
    }

    // ==============================
    // BERHASIL
    // ==============================

    closeModal();
    await fetchUsers();
  } catch (err) {
  console.error("ERROR:", err);

  console.log(
    "RESPONSE DETAIL:",
    JSON.stringify(err.response?.data, null, 2)
  );

  console.log(
    "VALIDATION ERRORS:",
    JSON.stringify(err.response?.data?.errors, null, 2)
  );

    // ==============================
    // ERROR VALIDASI LARAVEL 422
    // ==============================

    if (err.response?.status === 422) {
      const validationErrors = err.response.data?.errors;

      if (validationErrors) {
        error.value = Object.values(validationErrors).flat().join(" ");
      } else {
        error.value = err.response.data?.message || "Data yang dimasukkan tidak valid.";
      }
    }

    // ==============================
    // ERROR LAIN
    // ==============================
    else {
      error.value = err.response?.data?.message || "Gagal menyimpan data.";
    }
  }
};

const deleteUser = async (id) => {
  if (!confirm("Yakin ingin menghapus user ini?")) return;
  try {
    await axios.delete(`https://sistem-monitoring-keamanan-be.onrender.com/api/admin/users/${id}`, getAuthHeaders());
    fetchUsers();
  } catch (err) {
    error.value = err.response?.data?.message || "Gagal menghapus user.";
  }
};

const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");
  router.push("/");
};

const displayName = computed(() => user.value?.name || "Admin");

const roleLabel = (role) => {
  const labels = { satpam: "Satpam", supervisor: "Supervisor", admin: "Admin" };
  return labels[role] || role;
};

onMounted(() => {
  loadUser();
  fetchUsers();
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
    <div class="main-content">
      <!-- TOPBAR -->
      <div class="topbar">
        <div class="page-heading">
          <h1>Kelola User</h1>
          <p>Manajemen data pengguna sistem</p>
        </div>
        <div class="user-profile">
          <div class="profile-avatar">{{ displayName.charAt(0) }}</div>
          <div class="profile-info">
            <strong>{{ displayName }}</strong>
            <span>Administrator</span>
          </div>
        </div>
      </div>

      <!-- CONTENT -->
      <div class="dashboard-content">
        <!-- ERROR -->
        <div v-if="error" class="error-alert">
          <span>⚠</span>
          <div>
            <strong>Terjadi kesalahan</strong>
            <p>{{ error }}</p>
          </div>
          <button @click="error = ''">Tutup</button>
        </div>

        <!-- HEADER ROW -->
        <div class="content-header">
          <div>
            <div class="section-label">MANAJEMEN</div>
            <h2>Daftar Pengguna</h2>
            <p>Total {{ filteredUsers.length }} pengguna ditemukan</p>
          </div>
          <button class="btn-primary" @click="openCreateModal">+ Tambah User</button>
        </div>

        <!-- FILTER ROW -->
        <div class="filter-row">
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama atau username..."
            class="search-input"
          />
          <select v-model="filterRole" class="filter-select">
            <option value="">Semua Role</option>
            <option value="admin">Admin</option>
            <option value="supervisor">Supervisor</option>
            <option value="satpam">Satpam</option>
          </select>
        </div>

        <!-- LOADING -->
        <div v-if="loading" class="loading-box">
          <div class="spinner"></div>
          <p>Memuat data...</p>
        </div>

        <!-- TABLE -->
        <div v-else class="table-card">
          <table>
            <thead>
              <tr>
                <th>Nama</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Lokasi</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredUsers.length === 0">
                <td colspan="6" class="empty-state">Tidak ada data yang ditemukan.</td>
              </tr>
              <tr v-for="u in filteredUsers" :key="u.id">
                <td>
                  <div class="user-cell">
                    <div class="user-avatar">{{ u.name.charAt(0) }}</div>
                    <span>{{ u.name }}</span>
                  </div>
                </td>
                <td class="text-secondary">{{ u.username || "-" }}</td>
                <td>{{ roleLabel(u.role) }}</td>
                <td>
                  <span :class="'badge badge-' + u.status">
                    {{ u.status === "aktif" ? "Aktif" : "Nonaktif" }}
                  </span>
                </td>
                <td class="text-secondary">{{ u.location || "-" }}</td>
                <td>
                  <div class="action-buttons">
                    <button class="btn-icon" @click="openEditModal(u)" title="Edit">✏️</button>
                    <button
                      v-if="u.id !== user.id"
                      class="btn-icon btn-icon-danger"
                      @click="deleteUser(u.id)"
                      title="Hapus"
                    >
                      🗑️
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ modalMode === "create" ? "Tambah User" : "Edit User" }}</h3>
          <button class="modal-close" @click="closeModal">✕</button>
        </div>

        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label>Nama Lengkap</label>
              <input v-model="form.name" type="text" placeholder="Nama lengkap" />
            </div>
            <div class="form-group">
              <label>Username</label>
              <input v-model="form.username" type="text" placeholder="Username" />
            </div>
          </div>

          <div class="form-group">
            <label>Email</label>
            <input v-model="form.email" type="email" placeholder="email@example.com" />
          </div>

          <div class="form-group">
            <label>
              Password
              <span v-if="modalMode === 'edit'" class="label-hint">
                (kosongkan jika tidak diubah)
              </span>
            </label>
            <input
              v-model="form.password"
              type="password"
              placeholder="Minimal 6 karakter"
              minlength="6"
            />

            <small
              v-if="form.password.length > 0 && form.password.length < 6"
              class="password-hint"
            >
              Password minimal 6 karakter.
            </small>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>No. Telepon</label>
              <input v-model="form.phone" type="text" placeholder="08xxxxxxxxxx" />
            </div>
            <div class="form-group">
              <label>Lokasi</label>
              <input v-model="form.location" type="text" placeholder="DAOP 6 Yogyakarta" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Role</label>
              <select v-model="form.role">
                <option value="satpam">Satpam</option>
                <option value="supervisor">Supervisor</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div class="form-group" v-if="modalMode === 'edit'">
              <label>Status</label>
              <select v-model="form.status">
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Nonaktif</option>
              </select>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-cancel" @click="closeModal">Batal</button>
          <button class="btn-primary" @click="submitForm">
            {{ modalMode === "create" ? "Simpan" : "Update" }}
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

.password-hint {
  display: block;
  margin-top: 6px;
  color: var(--danger);
  font-size: 11px;
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
  padding: 20px 0 0;
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
  box-sizing: border-box;
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
  box-shadow: 0 7px 16px rgba(31, 36, 84, 0.18);
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
.dashboard-content {
  max-width: 1400px;
  margin: 0 auto;
  padding: 38px 42px 50px;
  box-sizing: border-box;
}

.content-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 20px;
}

.section-label {
  color: var(--accent);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 1.4px;
}
.content-header h2 {
  margin: 6px 0 4px;
  font-size: 24px;
  color: var(--primary);
}
.content-header p {
  margin: 0;
  color: var(--text-secondary);
  font-size: 13px;
}

/* FILTER ROW */
.filter-row {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.search-input {
  flex: 1;
  height: 44px;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0 16px;
  font-size: 13px;
  outline: none;
  transition: 0.2s;
  background: var(--white);
}

.search-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(232, 117, 0, 0.08);
}

.filter-select {
  height: 44px;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0 14px;
  font-size: 13px;
  outline: none;
  background: var(--white);
  cursor: pointer;
  min-width: 150px;
}

/* ERROR */
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

table {
  width: 100%;
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
.text-secondary {
  color: var(--text-secondary);
}
.empty-state {
  text-align: center;
  color: var(--text-secondary);
  padding: 40px;
}

.user-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--primary), var(--primary-light));
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
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
  background: rgba(47, 158, 99, 0.1);
  color: var(--success);
}
.badge-nonaktif {
  background: rgba(214, 48, 49, 0.1);
  color: var(--danger);
}

/* BUTTONS */
.btn-primary {
  padding: 11px 22px;
  border: none;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--accent), var(--accent-light));
  color: white;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: 0.25s;
  box-shadow: 0 6px 16px rgba(232, 117, 0, 0.25);
}

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 10px 22px rgba(232, 117, 0, 0.35);
}

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
  font-size: 15px;
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
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px 28px;
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  background: var(--white);
  z-index: 1;
}

.modal-header h3 {
  margin: 0;
  font-size: 18px;
  color: var(--primary);
}

.modal-close {
  border: none;
  background: none;
  font-size: 18px;
  color: var(--text-secondary);
  cursor: pointer;
}

.modal-body {
  padding: 28px;
}

.form-group {
  margin-bottom: 18px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--primary);
}

.label-hint {
  font-weight: 400;
  color: var(--text-secondary);
  font-size: 11px;
}

.form-group input,
.form-group select {
  width: 100%;
  height: 46px;
  box-sizing: border-box;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0 14px;
  font-size: 13px;
  outline: none;
  transition: 0.25s;
  background: var(--white);
}

.form-group input:focus,
.form-group select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(232, 117, 0, 0.08);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 20px 28px;
  border-top: 1px solid var(--border);
  position: sticky;
  bottom: 0;
  background: var(--white);
}

.btn-cancel {
  padding: 11px 22px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: transparent;
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: 0.2s;
}

.btn-cancel:hover {
  background: var(--background);
}
</style>
