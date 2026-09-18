// UserView.vue
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
const filterRole = ref("");
const locations = ref([]);
const locationLoading = ref(false);
const locationError = ref("");

const showModal = ref(false);
const modalMode = ref("create");
const selectedUser = ref(null);
const showLocationModal = ref(false);

const form = ref({
  name: "",
  username: "",
  nipkwt: "",
  email: "",
  password: "",
  role: "satpam",
  tim: "",
  status: "aktif",
  phone: "",
  location: "",
  location_id: "",
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

const openLocationModal = async () => {
  showLocationModal.value = true;
  await fetchLocations();
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

const fetchLocations = async () => {
  locationLoading.value = true;
  locationError.value = "";

  try {
    const response = await axios.get("https://sistem-monitoring-keamanan-be.onrender.com/api/admin/locations", getAuthHeaders());

    locations.value = response.data;
  } catch (err) {
    console.error("Gagal mengambil data lokasi:", err);

    locationError.value = err.response?.data?.message || "Gagal mengambil data lokasi.";
  } finally {
    locationLoading.value = false;
  }
};

const showLocationForm = ref(false);
const locationForm = ref({
  name: "",
  status: "aktif",
});
const locationSaving = ref(false);
const locationEditMode = ref(false);
const selectedLocation = ref(null);

const openLocationForm = () => {
  locationEditMode.value = false;
  selectedLocation.value = null;

  locationForm.value = {
    name: "",
    status: "aktif",
  };

  showLocationForm.value = true;
};

const openEditLocation = (location) => {
  locationEditMode.value = true;
  selectedLocation.value = location;

  locationForm.value = {
    name: location.name,
    status: location.status,
  };

  showLocationForm.value = true;
};

const saveLocation = async () => {
  if (!locationForm.value.name.trim()) {
    await Swal.fire({
      icon: "warning",
      title: "Nama Belum Diisi",
      text: "Nama lokasi wajib diisi.",
      confirmButtonText: "OK",
      confirmButtonColor: "#e87500",
    });
    return;
  }

  locationSaving.value = true;

  try {
    if (locationEditMode.value && selectedLocation.value) {
      await axios.put(
        `https://sistem-monitoring-keamanan-be.onrender.com/api/admin/locations/${selectedLocation.value.id}`,
        {
          name: locationForm.value.name.trim(),
          status: locationForm.value.status,
        },
        getAuthHeaders(),
      );

      await Swal.fire({
        icon: "success",
        title: "Berhasil!",
        text: "Lokasi berhasil diperbarui.",
        confirmButtonText: "OK",
        confirmButtonColor: "#1f2454",
      });
    } else {
      await axios.post(
        "https://sistem-monitoring-keamanan-be.onrender.com/api/admin/locations",
        {
          name: locationForm.value.name.trim(),
          status: "aktif",
        },
        getAuthHeaders(),
      );

      await Swal.fire({
        icon: "success",
        title: "Berhasil!",
        text: "Lokasi berhasil ditambahkan.",
        confirmButtonText: "OK",
        confirmButtonColor: "#1f2454",
      });
    }

    showLocationForm.value = false;
    locationEditMode.value = false;
    selectedLocation.value = null;

    await fetchLocations();
  } catch (err) {
    console.error("Gagal menyimpan lokasi:", err);

    await Swal.fire({
      icon: "error",
      title: "Gagal",
      text: err.response?.data?.message || "Gagal menyimpan lokasi.",
      confirmButtonText: "OK",
      confirmButtonColor: "#d63031",
    });
  } finally {
    locationSaving.value = false;
  }
};

const toggleLocationStatus = async (location) => {
  const newStatus = location.status === "aktif" ? "nonaktif" : "aktif";

  const result = await Swal.fire({
    icon: "warning",
    title: `Yakin ingin ${newStatus === "aktif" ? "mengaktifkan" : "menonaktifkan"} lokasi ini?`,
    text: `Lokasi "${location.name}" akan di${newStatus === "aktif" ? "aktifkan" : "nonaktifkan"}.`,
    showCancelButton: true,
    confirmButtonText: "Ya, Lanjutkan",
    cancelButtonText: "Batal",
    reverseButtons: true,
    focusCancel: true,
  });

  if (!result.isConfirmed) {
    return;
  }

  try {
    await axios.put(
      `https://sistem-monitoring-keamanan-be.onrender.com/api/admin/locations/${location.id}`,
      {
        name: location.name,
        status: newStatus,
      },
      getAuthHeaders(),
    );

    await fetchLocations();

    await Swal.fire({
      icon: "success",
      title: "Berhasil!",
      text: newStatus === "aktif" ? "Lokasi berhasil diaktifkan." : "Lokasi berhasil dinonaktifkan.",
      confirmButtonText: "OK",
      confirmButtonColor: "#1f2454",
    });
  } catch (err) {
    console.error("Gagal mengubah status lokasi:", err);

    await Swal.fire({
      icon: "error",
      title: "Gagal",
      text: err.response?.data?.message || "Gagal mengubah status lokasi.",
      confirmButtonText: "OK",
      confirmButtonColor: "#d63031",
    });
  }
};

const filteredUsers = computed(() => {
  const keyword = search.value.trim().toLowerCase();

  return users.value.filter((u) => {
    const matchSearch =
      String(u.name || "").toLowerCase().includes(keyword) ||
      String(u.username || "").toLowerCase().includes(keyword) ||
      String(u.location || "").toLowerCase().includes(keyword);

    const matchRole = filterRole.value === "" || u.role === filterRole.value;

    const matchStatus = u.status === "aktif";

    return matchSearch && matchRole && matchStatus;
  });
});
const openCreateModal = () => {
  modalMode.value = "create";
  error.value = "";
  form.value = {
    name: "",
    username: "",
    nipkwt: "",
    email: "",
    password: "",
    role: "satpam",
    tim: "",
    status: "aktif",
    phone: "",
    location: "",
    location_id: "",
  };
  showModal.value = true;
};

const openEditModal = (u) => {
  modalMode.value = "edit";
  error.value = "";
  selectedUser.value = u;
  form.value = {
    name: u.name,
    username: u.username || "",
    nipkwt: u.nipkwt || "",
    email: u.email,
    password: "",
    role: u.role,
    tim: u.tim || "",
    status: u.status,
    phone: u.phone || "",
    location: u.location || "",
    location_id: u.location_id || "",
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

  // NIPKWT wajib angka 6-12 digit
  if (!/^\d{6,12}$/.test(form.value.nipkwt)) {
    error.value = "NIPKWT harus berupa angka, 6-12 digit.";
    return;
  }

  // Role Katim wajib punya nomor tim
  if (form.value.role === "katim" && !form.value.tim) {
    error.value = "Nomor tim wajib diisi untuk role Katim.";
    return;
  }

  try {
    // ==============================
    // TAMBAH USER
    // ==============================
    if (modalMode.value === "create") {
      const createData = {
        name: form.value.name,
        username: form.value.username,
        nipkwt: form.value.nipkwt,
        email: form.value.email,
        password: form.value.password,
        role: form.value.role,
        tim: form.value.role === "katim" ? form.value.tim : null,
        phone: form.value.phone,
        location: form.value.location,
        location_id: form.value.location_id || null,
      };

      await axios.post("https://sistem-monitoring-keamanan-be.onrender.com/api/admin/users", createData, getAuthHeaders());
    }

    // ==============================
    // UPDATE USER
    // ==============================
    else {
      const updateData = {
        name: form.value.name,
        username: form.value.username,
        nipkwt: form.value.nipkwt,
        email: form.value.email,
        role: form.value.role,
        tim: form.value.role === "katim" ? form.value.tim : null,
        status: form.value.status,
        phone: form.value.phone,
        location: form.value.location,
        location_id: form.value.location_id || null,
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

    console.log("RESPONSE DETAIL:", JSON.stringify(err.response?.data, null, 2));

    console.log("VALIDATION ERRORS:", JSON.stringify(err.response?.data?.errors, null, 2));

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
  const result = await Swal.fire({
    icon: "warning",
    title: "Arsipkan User Ini?",
    text: "User akan dipindahkan ke arsip dan dinonaktifkan.",
    showCancelButton: true,
    confirmButtonText: "Ya, Arsipkan",
    cancelButtonText: "Batal",
    reverseButtons: true,
    focusCancel: true,
  });

  if (!result.isConfirmed) return;

  try {
    await axios.put(
      `https://sistem-monitoring-keamanan-be.onrender.com/api/admin/users/${id}/archive`,
      {
        status: "nonaktif",
      },
      getAuthHeaders(),
    );

    await Swal.fire({
      icon: "success",
      title: "Berhasil!",
      text: "User berhasil dipindahkan ke arsip.",
      confirmButtonText: "OK",
      confirmButtonColor: "#1f2454",
    });

    fetchUsers();
  } catch (err) {
    const message = err.response?.data?.message || "Gagal mengarsipkan user.";
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

const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");
  router.push("/");
};

const displayName = computed(() => user.value?.name || "Admin");

const roleLabel = (u) => {
  const labels = { satpam: "Satpam", supervisor: "Supervisor", admin: "Admin", katim: "Katim" };
  const label = labels[u.role] || u.role;
  return u.role === "katim" && u.tim ? `${label} ${u.tim}` : label;
};

onMounted(() => {
  loadUser();
  fetchUsers();
  fetchLocations();
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
          <div class="header-actions">
            <button class="btn-secondary" @click="router.push('/admin/users/archive')">
              📁 Arsip Satpam
            </button>
            <button class="btn-secondary" type="button" @click="openLocationModal">
              📍 Kelola Lokasi
            </button>
            <button class="btn-primary" @click="openCreateModal">+ Tambah User</button>
          </div>
        </div>

        <!-- FILTER ROW -->
        <div class="filter-row">
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama, username, atau lokasi..."
            class="search-input"
          />
          <select v-model="filterRole" class="filter-select">
            <option value="">Semua Role</option>
            <option value="admin">Admin</option>
            <option value="supervisor">Supervisor</option>
            <option value="satpam">Satpam</option>
            <option value="katim">Katim</option>
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
                <th>NIPKWT</th>
                <th>Role</th>
                <th>Status</th>
                <th>Lokasi</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredUsers.length === 0">
                <td colspan="7" class="empty-state">Tidak ada data yang ditemukan.</td>
              </tr>
              <tr v-for="u in filteredUsers" :key="u.id">
                <td>
                  <div class="user-cell">
                    <div class="user-avatar">{{ u.name.charAt(0) }}</div>
                    <span>{{ u.name }}</span>
                  </div>
                </td>
                <td class="text-secondary">{{ u.username || "-" }}</td>
                <td class="text-secondary">{{ u.nipkwt || "-" }}</td>
                <td>{{ roleLabel(u) }}</td>
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
          <div v-if="error" class="modal-error">
            <span>⚠</span>
            <p>{{ error }}</p>
          </div>

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

          <div class="form-row">
            <div class="form-group">
              <label>NIPKWT</label>
              <input
                v-model="form.nipkwt"
                type="text"
                inputmode="numeric"
                maxlength="12"
                placeholder="Angka 6-12 digit"
                @input="form.nipkwt = form.nipkwt.replace(/\D/g, '')"
              />
              <small
                v-if="form.nipkwt.length > 0 && (form.nipkwt.length < 6 || form.nipkwt.length > 12)"
                class="password-hint"
              >
                NIPKWT harus angka 6-12 digit.
              </small>
            </div>
            <div class="form-group">
              <label>Email</label>
              <input v-model="form.email" type="email" placeholder="email@example.com" />
            </div>
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

              <select v-model="form.location_id">
                <option value="">Pilih lokasi</option>

                <option
                  v-for="location in locations.filter((l) => l.status === 'aktif')"
                  :key="location.id"
                  :value="location.id"
                >
                  {{ location.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Role</label>
              <select v-model="form.role">
                <option value="satpam">Satpam</option>
                <option value="katim">Katim</option>
                <option value="supervisor">Supervisor</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div class="form-group" v-if="form.role === 'katim'">
              <label>Nomor Tim</label>
              <input v-model="form.tim" type="number" min="1" placeholder="Contoh: 1, 2, 3, ..." />
            </div>
            <div class="form-group" v-if="modalMode === 'edit' && form.role !== 'katim'">
              <label>Status</label>
              <select v-model="form.status">
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Nonaktif</option>
              </select>
            </div>
          </div>

          <div class="form-row" v-if="modalMode === 'edit' && form.role === 'katim'">
            <div class="form-group">
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
  <!-- Modal Kelola Lokasi -->
  <div v-if="showLocationModal" class="modal-overlay" @click.self="showLocationModal = false">
    <div class="modal location-modal">
      <div class="modal-header">
        <h3>Kelola Lokasi</h3>

        <div class="modal-header-actions">
          <button type="button" class="location-add-btn" @click="openLocationForm">
            + Tambah Lokasi
          </button>

          <button type="button" class="btn-close" @click="showLocationModal = false">×</button>
        </div>
      </div>

      <div class="modal-body">
        <!-- Loading -->
        <div v-if="locationLoading" class="text-center py-4">Memuat lokasi...</div>

        <!-- Error -->
        <div v-else-if="locationError" class="alert alert-danger">
          {{ locationError }}
        </div>

        <!-- Daftar lokasi -->
        <div v-else class="location-list">
          <div v-for="location in locations" :key="location.id" class="location-item">
            <div class="location-info">
              <div class="location-name">
                {{ location.name }}
              </div>

              <div
                class="location-status"
                :class="location.status === 'aktif' ? 'status-active' : 'status-inactive'"
              >
                {{ location.status === "aktif" ? "Aktif" : "Nonaktif" }}
              </div>
            </div>

            <div class="location-actions">
              <button type="button" class="location-edit-btn" @click="openEditLocation(location)">
                ✏️
              </button>

              <button
                type="button"
                class="location-status-btn"
                :class="location.status === 'aktif' ? 'deactivate' : 'activate'"
                @click="toggleLocationStatus(location)"
              >
                {{ location.status === "aktif" ? "Nonaktifkan" : "Aktifkan" }}
              </button>
            </div>
          </div>

          <div v-if="locations.length === 0" class="text-center py-4 text-secondary">
            Belum ada lokasi.
          </div>
        </div>

        <!-- Form Tambah Lokasi -->
        <div v-if="showLocationForm" class="location-form">
          <h4>
            {{ locationEditMode ? "Edit Lokasi" : "Tambah Lokasi" }}
          </h4>

          <div class="form-group">
            <label>Nama Lokasi</label>

            <input
              v-model="locationForm.name"
              type="text"
              placeholder="Contoh: Stasiun Solo Balapan"
              @keyup.enter="saveLocation"
            />
          </div>

          <div class="form-group">
            <label>Status</label>

            <select v-model="locationForm.status" class="location-status-select">
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Nonaktif</option>
            </select>
          </div>

          <div class="form-actions">
            <button type="button" class="btn-secondary" @click="showLocationForm = false">
              Batal
            </button>

            <button
              type="button"
              class="btn-primary"
              :disabled="locationSaving"
              @click="saveLocation"
            >
              {{
                locationSaving ? "Menyimpan..." : locationEditMode ? "Simpan Perubahan" : "Simpan"
              }}
            </button>
          </div>
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
.header-actions {
  display: flex;
  gap: 12px;
}

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

  overflow: hidden;
  overscroll-behavior: contain;
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

.modal-error {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 18px;
  padding: 13px 15px;
  background: #fff1f1;
  border-left: 4px solid var(--danger);
  border-radius: 8px;
  color: var(--danger);
}

.modal-error span {
  font-size: 15px;
  line-height: 1.4;
}

.modal-error p {
  margin: 0;
  font-size: 12px;
  line-height: 1.5;
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
/* =========================
   MODAL KELOLA LOKASI
========================= */

.modal-header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.location-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.location-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  transition: 0.2s ease;
}

.location-item:hover {
  border-color: #cbd5e1;
  background: #f8fafc;
}

.location-name {
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
}

.location-status {
  display: inline-block;
  margin-top: 5px;
  padding: 4px 9px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}

.status-active {
  color: #166534;
  background: #dcfce7;
}

.status-inactive {
  color: #991b1b;
  background: #fee2e2;
}

/* =========================
   FORM TAMBAH LOKASI
========================= */

.location-form {
  margin-top: 18px;
  padding: 18px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f8fafc;
}

.location-form h4 {
  margin: 0 0 16px;
  font-size: 16px;
  font-weight: 600;
  color: #1f2454;
}

.location-form .form-group {
  margin-bottom: 16px;
}

.location-form .form-group label {
  display: block;
  margin-bottom: 7px;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

.location-form .form-group input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  box-sizing: border-box;
  transition: 0.2s ease;
}

.location-form .form-group input:focus {
  border-color: #1f2454;
  box-shadow: 0 0 0 3px rgba(31, 36, 84, 0.08);
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

/* =========================
   BUTTON
========================= */

.location-form .btn-primary {
  border: none;
  border-radius: 8px;
  padding: 9px 16px;
  background: #1f2454;
  color: #fff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: 0.2s ease;
}

.location-form .btn-primary:hover {
  background: #171b42;
}

.location-form .btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.location-form .btn-secondary {
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 9px 16px;
  background: #fff;
  color: #374151;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.location-form .btn-secondary:hover {
  background: #f3f4f6;
}

/* =========================
   RESPONSIVE
========================= */

@media (max-width: 600px) {
  .modal-header-actions {
    gap: 6px;
  }

  .modal-header-actions .btn-primary {
    padding: 8px 10px;
    font-size: 12px;
  }

  .location-item {
    padding: 12px;
  }

  .form-actions {
    flex-direction: column;
  }

  .form-actions button {
    width: 100%;
  }
}
.modal-body {
  background: #ffffff;
} /* =========================
   LOCATION MODAL FIX
========================= */

.location-modal {
  background-color: #ffffff !important;
  opacity: 1 !important;
  width: 520px;
  max-width: calc(100vw - 40px);
  overflow: hidden;
}

.location-modal .modal-header {
  background-color: #ffffff !important;
  opacity: 1 !important;
}

.location-modal .modal-body {
  background-color: #ffffff !important;
  opacity: 1 !important;
}

.location-modal .location-list {
  background-color: #ffffff;
}

.location-modal .location-item {
  background-color: #ffffff !important;
  opacity: 1 !important;
}

.location-modal .location-form {
  margin: 0;
  border-top: 1px solid #e5e7eb;
  border-radius: 0;
  background-color: #f8fafc !important;
}
/* ================================
   MODAL KELOLA LOKASI
================================ */
.location-modal {
  width: 520px;
  max-width: calc(100vw - 40px);
  max-height: 85vh;

  display: flex;
  flex-direction: column;

  background: #ffffff !important;
  border-radius: 16px;

  overflow: hidden;

  box-shadow: 0 20px 50px rgba(31, 36, 84, 0.18);
}

/* Header */

.location-modal .modal-header {
  flex-shrink: 0;

  padding: 20px 24px;
  background: #ffffff !important;
  border-bottom: 1px solid #e5e7eb;
}

.location-modal .modal-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: #1f2454;
}

/* Tombol header */

.location-modal .modal-header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.location-add-btn {
  display: inline-flex !important;
  align-items: center;
  justify-content: center;

  min-height: 38px;
  padding: 9px 14px;

  border: none;
  border-radius: 8px;

  background: #1f2454 !important;
  color: #ffffff !important;

  font-family: inherit;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.2;

  cursor: pointer;
  white-space: nowrap;
}

.location-add-btn:hover {
  background: #171b42 !important;
}

/* Tombol close */

.location-modal .btn-close {
  display: flex;
  align-items: center;
  justify-content: center;

  width: 36px;
  height: 36px;

  padding: 0;
  border: none;
  border-radius: 8px;

  background: #f3f4f6;
  color: #374151;

  font-size: 22px;
  line-height: 1;

  cursor: pointer;
}

.location-modal .btn-close:hover {
  background: #e5e7eb;
}

/* Body */

.location-modal .modal-body {
  flex: 1;
  min-height: 0;

  padding: 24px;

  background: #ffffff !important;
  font-family: inherit;

  overflow-y: auto;
  overflow-x: hidden;

  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
}

/* List lokasi */

.location-modal .location-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.location-modal .location-item {
  display: flex;
  align-items: center;
  justify-content: space-between;

  padding: 14px 16px;

  background: #ffffff !important;
  border: 1px solid #e5e7eb;
  border-radius: 10px;

  transition: all 0.2s ease;
}

.location-modal .location-item:hover {
  border-color: #c7cad8;
  box-shadow: 0 3px 10px rgba(31, 36, 84, 0.06);
}

.location-modal .location-name {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.location-modal .location-status {
  display: inline-block;

  margin-top: 5px;
  padding: 4px 9px;

  border-radius: 999px;

  font-size: 11px;
  font-weight: 600;
}

.location-modal .status-active {
  color: #166534;
  background: #dcfce7;
}

.location-modal .status-inactive {
  color: #991b1b;
  background: #fee2e2;
}

/* Form tambah lokasi */

.location-modal .location-form {
  margin-top: 20px;
  padding: 18px;

  background: #f8f9fc !important;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
}

.location-modal .location-form h4 {
  margin: 0 0 16px;

  font-size: 15px;
  font-weight: 700;
  color: #1f2454;
}

.location-modal .location-form label {
  display: block;

  margin-bottom: 7px;

  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.location-modal .location-form input {
  width: 100%;
  box-sizing: border-box;

  padding: 10px 12px;

  border: 1px solid #d1d5db;
  border-radius: 8px;

  background: #ffffff;
  color: #1f2937;

  font-family: inherit;
  font-size: 13px;

  outline: none;
  transition: all 0.2s ease;
}

.location-modal .location-form input:focus {
  border-color: #1f2454;
  box-shadow: 0 0 0 3px rgba(31, 36, 84, 0.08);
}

.location-modal .form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;

  margin-top: 16px;
}

.location-modal .form-actions .btn-secondary,
.location-modal .form-actions .btn-primary {
  min-height: 36px;

  padding: 8px 14px;

  border-radius: 8px;

  font-family: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

/* Responsive */

@media (max-width: 600px) {
  .location-modal {
    width: calc(100vw - 30px);
  }

  .location-modal .modal-header {
    padding: 18px;
  }

  .location-modal .modal-body {
    padding: 18px;
  }

  .location-modal .modal-header-actions {
    gap: 6px;
  }

  .location-add-btn {
    padding: 8px 10px;
    font-size: 12px;
  }
}
.location-info {
  min-width: 0;
}

.location-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.location-edit-btn,
.location-status-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  min-height: 34px;
  padding: 7px 11px;

  border-radius: 7px;

  font-family: inherit;
  font-size: 12px;
  font-weight: 600;

  cursor: pointer;
  transition: 0.2s ease;
}

.location-edit-btn {
  border: 1px solid #d1d5db;
  background: #ffffff;
  color: #1f2454;
}

.location-edit-btn:hover {
  background: #f3f4f6;
}

.location-status-btn {
  border: none;
}

.location-status-btn.deactivate {
  background: #fff7ed;
  color: #c2410c;
}

.location-status-btn.deactivate:hover {
  background: #ffedd5;
}

.location-status-btn.activate {
  background: #dcfce7;
  color: #166534;
}

.location-status-btn.activate:hover {
  background: #bbf7d0;
}

.location-status-select {
  display: block;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  box-sizing: border-box;

  height: 40px;
  padding: 0 36px 0 12px;

  border: 1px solid #d1d5db;
  border-radius: 8px;

  background-color: #ffffff;
  color: #1f2937;

  font-family: inherit;
  font-size: 13px;
  line-height: 40px;

  outline: none;
  cursor: pointer;

  appearance: auto;
}

.location-status-select:focus {
  border-color: #1f2454;
  box-shadow: 0 0 0 3px rgba(31, 36, 84, 0.08);
}
.location-modal .location-form .form-group {
  width: 100%;
  min-width: 0;
}

.location-modal .location-form select {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

@media (max-width: 600px) {
  .location-item {
    align-items: flex-start;
    gap: 12px;
  }

  .location-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .location-edit-btn,
  .location-status-btn {
    white-space: nowrap;
  }
}
.location-modal .location-form {
  width: 100%;
  box-sizing: border-box;
  overflow: visible;
}
</style>
