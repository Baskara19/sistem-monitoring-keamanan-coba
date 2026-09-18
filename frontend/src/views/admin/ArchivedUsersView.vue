// ArchivedUsersView.vue
```vue
<script setup>
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";
import Swal from "sweetalert2";

const router = useRouter();

const users = ref([]);
const loading = ref(true);
const error = ref("");
const search = ref("");

const user = ref({ name: "Admin", role: "admin" });

const getAuthHeaders = () => {
  const token = localStorage.getItem("token");

  return {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  };
};

const loadUser = () => {
  const storedUser = localStorage.getItem("user");

  if (storedUser) {
    user.value = JSON.parse(storedUser);
  }
};

const fetchArchivedUsers = async () => {
  loading.value = true;
  error.value = "";

  try {
    const response = await axios.get(
      "https://sistem-monitoring-keamanan-be.onrender.com/api/admin/users/archived",
      getAuthHeaders(),
    );

    users.value = response.data.users;
  } catch (err) {
    error.value = err.response?.data?.message || "Gagal memuat data arsip.";
  } finally {
    loading.value = false;
  }
};

const filteredUsers = computed(() => {
  return users.value.filter((u) => {
    const keyword = search.value.toLowerCase();

    return (
      u.name?.toLowerCase().includes(keyword) ||
      u.username?.toLowerCase().includes(keyword) ||
      u.email?.toLowerCase().includes(keyword)
    );
  });
});

const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");

  router.push("/");
};

const displayName = computed(() => user.value?.name || "Admin");

const roleLabel = (role) => {
  const labels = {
    satpam: "Satpam",
    supervisor: "Supervisor",
    admin: "Admin",
    katim: "Katim",
  };

  return labels[role] || role;
};
const restoreUser = async (id) => {
  const result = await Swal.fire({
    icon: "warning",
    title: "Aktifkan Kembali User?",
    text: "User ini akan diaktifkan kembali dan bisa login seperti biasa.",
    showCancelButton: true,
    confirmButtonText: "Ya, Aktifkan",
    cancelButtonText: "Batal",
    reverseButtons: true,
    focusCancel: true,
  });

  if (!result.isConfirmed) return;

  try {
    await axios.put(`https://sistem-monitoring-keamanan-be.onrender.com/api/admin/users/${id}/restore`, {}, getAuthHeaders());

    await Swal.fire({
      icon: "success",
      title: "Berhasil!",
      text: "User berhasil diaktifkan kembali.",
      confirmButtonText: "OK",
      confirmButtonColor: "#1f2454",
    });

    fetchArchivedUsers();
  } catch (err) {
    console.error(err);

    const message = err.response?.data?.message || "Gagal mengaktifkan kembali user.";
    error.value = message;

    await Swal.fire({
      icon: "error",
      title: "Gagal",
      text: message,
      confirmButtonText: "OK",
      confirmButtonColor: "#d63031",
    });
  }
};

onMounted(() => {
  loadUser();
  fetchArchivedUsers();
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
          <h1>Arsip Satpam</h1>
          <p>Data satpam yang telah dinonaktifkan</p>
        </div>

        <div class="user-profile">
          <div class="profile-avatar">
            {{ displayName.charAt(0) }}
          </div>

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

        <!-- HEADER -->
        <div class="content-header">
          <div>
            <div class="section-label">ARSIP USER</div>

            <h2>Daftar Satpam Nonaktif</h2>

            <p>Total {{ filteredUsers.length }} satpam di dalam arsip</p>
          </div>

          <div class="header-actions">
            <button class="btn-secondary" @click="router.push('/admin/users')">← Kembali</button>
          </div>
        </div>

        <!-- SEARCH -->
        <div class="filter-row">
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama, username, atau email..."
            class="search-input"
          />
        </div>

        <!-- LOADING -->
        <div v-if="loading" class="loading-box">
          <div class="spinner"></div>
          <p>Memuat data arsip...</p>
        </div>

        <!-- TABLE -->
        <div v-else class="table-card">
          <table>
            <thead>
              <tr>
                <th>Nama</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>No. Telepon</th>
                <th>Lokasi</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>

            <tbody>
              <!-- EMPTY -->
              <tr v-if="filteredUsers.length === 0">
                <td colspan="8" class="empty-state">
                  <div class="empty-icon">📁</div>

                  <strong>Belum Ada Arsip Satpam</strong>

                  <p>Satpam yang dinonaktifkan akan muncul di halaman ini.</p>
                </td>
              </tr>

              <!-- DATA -->
              <tr v-for="u in filteredUsers" :key="u.id">
                <td>
                  <div class="user-cell">
                    <div class="user-avatar">
                      {{ u.name?.charAt(0) }}
                    </div>

                    <span>{{ u.name }}</span>
                  </div>
                </td>

                <td class="text-secondary">
                  {{ u.username || "-" }}
                </td>

                <td class="text-secondary">
                  {{ u.email || "-" }}
                </td>

                <td>
                  {{ roleLabel(u.role) }}
                </td>

                <td class="text-secondary">
                  {{ u.phone || "-" }}
                </td>

                <td class="text-secondary">
                  {{ u.location || "-" }}
                </td>

                <td>
                  <span class="badge badge-nonaktif"> Nonaktif </span>
                </td>
                <td>
                  <button class="btn-restore" @click="restoreUser(u.id)">
                    ✏️ Aktifkan Kembali
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
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

/* ==============================
   SIDEBAR
============================== */

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

/* ==============================
   MAIN
============================== */

.main-content {
  width: 100%;
  min-height: 100vh;
  margin-left: 260px;
}

/* ==============================
   TOPBAR
============================== */

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

/* ==============================
   CONTENT
============================== */

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

.header-actions {
  display: flex;
  gap: 12px;
}

/* ==============================
   BUTTON
============================== */

.btn-secondary {
  padding: 11px 22px;

  border: 1px solid var(--border);
  border-radius: 10px;

  background: white;

  color: var(--primary);

  font-size: 13px;
  font-weight: 700;

  cursor: pointer;

  transition: 0.25s;
}

.btn-secondary:hover {
  background: #f5f5f5;
  transform: translateY(-1px);
}

/* ==============================
   FILTER
============================== */

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

/* ==============================
   ERROR
============================== */

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

/* ==============================
   LOADING
============================== */

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

/* ==============================
   TABLE
============================== */

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

/* ==============================
   STATUS
============================== */

.badge {
  display: inline-block;

  padding: 4px 12px;

  border-radius: 20px;

  font-size: 11px;
  font-weight: 700;
}

.badge-nonaktif {
  background: rgba(214, 48, 49, 0.1);
  color: var(--danger);
}

/* ==============================
   EMPTY STATE
============================== */

.empty-state {
  text-align: center;

  padding: 60px 20px !important;

  color: var(--text-secondary);
}

.empty-state .empty-icon {
  font-size: 40px;

  margin-bottom: 10px;
}

.empty-state strong {
  display: block;

  color: var(--primary);

  font-size: 15px;

  margin-bottom: 5px;
}

.empty-state p {
  margin: 0;

  color: var(--text-secondary);

  font-size: 12px;
}
.btn-restore {
  padding: 8px 14px;
  border: none;
  border-radius: 8px;
  background: #1f2454;
  color: white;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: 0.2s;
}

.btn-restore:hover {
  background: #e87500;
}

/* ==============================
   RESPONSIVE
============================== */

@media (max-width: 1100px) {
  .dashboard-content {
    padding: 30px;
  }

  .topbar {
    padding: 0 30px;
  }

  .table-card {
    overflow-x: auto;
  }

  table {
    min-width: 1000px;
  }
}

@media (max-width: 700px) {
  .sidebar {
    width: 220px;
  }

  .main-content {
    margin-left: 220px;
  }

  .dashboard-content {
    padding: 25px 20px;
  }

  .topbar {
    padding: 0 20px;
  }

  .content-header {
    align-items: flex-start;
    flex-direction: column;
    gap: 15px;
  }

  .header-actions {
    width: 100%;
  }

  .btn-secondary {
    width: 100%;
  }
}
</style>
```
