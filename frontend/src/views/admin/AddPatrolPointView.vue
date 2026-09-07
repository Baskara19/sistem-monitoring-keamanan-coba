<script setup>
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import Swal from "sweetalert2";

const router = useRouter();

const form = ref({
  name: "",
  location_address: "",
  latitude: "",
  longitude: "",
  radius_meters: 50,
  description: "",
  status: "aktif",
});

const error = ref("");
const loading = ref(false);
const displayName = computed(() => {
  const storedUser = localStorage.getItem("user");

  if (storedUser) {
    try {
      const user = JSON.parse(storedUser);
      return user.name || "Admin";
    } catch {
      return "Admin";
    }
  }

  return "Admin";
});

const goBack = () => {
  router.push("/admin/patrol-points");
};

const submitForm = async () => {
  error.value = "";

  if (!form.value.name.trim()) {
    Swal.fire({
      icon: "warning",
      title: "Data Belum Lengkap",
      text: "Nama titik patroli wajib diisi.",
      confirmButtonText: "OK",
    });
    return;
  }

  if (!form.value.location_address.trim()) {
    Swal.fire({
      icon: "warning",
      title: "Data Belum Lengkap",
      text: "Alamat atau lokasi wajib diisi.",
      confirmButtonText: "OK",
    });
    return;
  }
  
  if (!form.value.longitude) {
    Swal.fire({
      icon: "warning",
      title: "Data Belum Lengkap",
      text: "Longitude wajib diisi.",
      confirmButtonText: "OK",
    });
    return;
  }
  
  loading.value = true;

  try {
    const token = localStorage.getItem("token");

    await axios.post(
      "https://sistem-monitoring-keamanan-be.onrender.com/api/admin/patrol-points",
      {
        name: form.value.name,
        location_address: form.value.location_address,
        latitude: form.value.latitude || null,
        longitude: form.value.longitude || null,
        radius_meters: form.value.radius_meters || 50,
        description: form.value.description || null,
        status: form.value.status,
      },
      {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: "application/json",
        },
      },
    );

    await Swal.fire({
      icon: "success",
      title: "Berhasil!",
      text: "Titik patroli berhasil ditambahkan.",
      confirmButtonText: "OK",
    });

    router.push("/admin/patrol-points");
  } catch (err) {
    console.error(err);

    if (err.response?.status === 422) {
      const errors = err.response.data.errors;

      if (errors) {
        const messages = Object.values(errors).flat();

        error.value = messages.join(" ");
      } else {
        error.value = "Periksa kembali data yang dimasukkan.";
      }
    } else {
      error.value =
        err.response?.data?.message || "Terjadi kesalahan saat menyimpan titik patroli.";
    }
  }
};
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

        <router-link to="/admin/patrol-points" class="nav-item router-link-active">
          <span class="nav-icon">⌖</span>
          Titik Patroli
        </router-link>
      </nav>

      <div class="sidebar-footer">
        <button class="logout-button">
          <span>↪</span>
          Keluar
        </button>
      </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
      <!-- TOPBAR -->
      <header class="topbar">
        <div class="page-heading">
          <h1>Tambah Titik Patroli</h1>

          <p>Tambahkan titik patroli baru ke dalam sistem.</p>
        </div>

        <div class="user-profile">
          <div class="profile-avatar">
            {{ displayName.charAt(0).toUpperCase() }}
          </div>

          <div class="profile-info">
            <strong>{{ displayName }}</strong>
            <span>Administrator</span>
          </div>
        </div>
      </header>

      <!-- CONTENT -->
      <section class="content">
        <!-- BREADCRUMB -->
        <div class="breadcrumb">
          <button @click="goBack">Titik Patroli</button>

          <span>›</span>

          <strong>Tambah Titik Patroli</strong>
        </div>

        <!-- FORM CARD -->
        <div class="form-card">
          <div class="card-header">
            <div>
              <span class="section-label"> DATA CHECKPOINT </span>

              <h2>Informasi Titik Patroli</h2>

              <p>
                Masukkan informasi lokasi checkpoint yang akan digunakan oleh petugas saat melakukan
                patroli.
              </p>
            </div>
          </div>

          <!-- ERROR -->
          <div v-if="error" class="error-alert">
            <span>⚠</span>

            <div>
              <strong>Periksa Data</strong>
              <p>{{ error }}</p>
            </div>
          </div>

          <!-- FORM -->
          <form @submit.prevent="submitForm">
            <!-- NAMA -->
            <div class="form-group">
              <label>
                Nama Titik Patroli
                <span>*</span>
              </label>

              <input v-model="form.name" type="text" placeholder="Contoh: Pos Keamanan Utama" />

              <small> Berikan nama yang mudah dikenali oleh petugas. </small>
            </div>

            <!-- ALAMAT -->
            <div class="form-group">
              <label>
                Alamat / Lokasi
                <span>*</span>
              </label>

              <textarea
                v-model="form.location_address"
                placeholder="Contoh: Area Parkir Stasiun Yogyakarta"
                rows="3"
              ></textarea>
            </div>

            <!-- KOORDINAT -->
            <div class="form-row">
              <div class="form-group">
                <label> Latitude </label>

                <input v-model="form.latitude" type="number" step="any" placeholder="-7.7956" />
              </div>

              <div class="form-group">
                <label> Longitude </label>

                <input v-model="form.longitude" type="number" step="any" placeholder="110.3695" />
              </div>
            </div>

            <!-- RADIUS -->
            <div class="form-group">
              <label> Radius Validasi </label>

              <div class="input-with-suffix">
                <input v-model="form.radius_meters" type="number" min="1" placeholder="50" />

                <span>meter</span>
              </div>

              <small>
                Jarak maksimal petugas dari titik checkpoint agar scan dianggap valid.
              </small>
            </div>

            <!-- DESKRIPSI -->
            <div class="form-group">
              <label> Deskripsi </label>

              <textarea
                v-model="form.description"
                placeholder="Tambahkan keterangan mengenai titik patroli..."
                rows="4"
              ></textarea>
            </div>

            <!-- STATUS -->
            <div class="form-group">
              <label> Status </label>

              <select v-model="form.status">
                <option value="aktif">Aktif</option>

                <option value="nonaktif">Nonaktif</option>
              </select>
            </div>

            <!-- FOOTER -->
            <div class="form-footer">
              <button type="button" class="btn-cancel" @click="goBack">Batal</button>

              <button type="submit" class="btn-primary" :disabled="loading">
                {{ loading ? "Menyimpan..." : "Simpan Titik Patroli" }}
              </button>
            </div>
          </form>
        </div>
      </section>
    </main>
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

/* NAV */

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

/* SIDEBAR FOOTER */

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
  max-width: 1100px;

  margin: 0 auto;

  padding: 35px 42px 60px;

  box-sizing: border-box;
}

/* BREADCRUMB */

.breadcrumb {
  display: flex;
  align-items: center;

  gap: 8px;

  margin-bottom: 22px;

  color: var(--text-secondary);

  font-size: 12px;
}

.breadcrumb button {
  border: none;

  background: none;

  padding: 0;

  color: var(--accent);

  font-size: 12px;

  font-weight: 700;

  cursor: pointer;
}

.breadcrumb strong {
  color: var(--primary);
}

/* FORM CARD */

.form-card {
  background: rgba(255, 255, 255, 0.92);

  border: 1px solid var(--border);

  border-radius: 18px;

  box-shadow: 0 10px 30px rgba(31, 36, 84, 0.05);

  overflow: hidden;
}

.card-header {
  padding: 28px 32px;

  border-bottom: 1px solid var(--border);
}

.section-label {
  color: var(--accent);

  font-size: 10px;

  font-weight: 800;

  letter-spacing: 1.4px;
}

.card-header h2 {
  margin: 7px 0 6px;

  font-size: 22px;

  color: var(--primary);
}

.card-header p {
  margin: 0;

  color: var(--text-secondary);

  font-size: 13px;

  line-height: 1.6;
}

/* FORM */

form {
  padding: 30px 32px;
}

.form-group {
  margin-bottom: 22px;
}

.form-group label {
  display: block;

  margin-bottom: 8px;

  color: var(--primary);

  font-size: 13px;

  font-weight: 700;
}

.form-group label span {
  color: var(--danger);
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;

  box-sizing: border-box;

  border: 1px solid var(--border);

  border-radius: 10px;

  background: white;

  padding: 12px 14px;

  color: var(--primary);

  font-family: inherit;

  font-size: 13px;

  outline: none;

  transition: 0.2s;
}

.form-group input {
  height: 46px;
}

.form-group textarea {
  resize: vertical;
  min-height: 90px;
}

.form-group select {
  height: 46px;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  border-color: var(--accent);

  box-shadow: 0 0 0 3px rgba(232, 117, 0, 0.08);
}

.form-group small {
  display: block;

  margin-top: 7px;

  color: var(--text-secondary);

  font-size: 11px;
}

.form-row {
  display: grid;

  grid-template-columns: 1fr 1fr;

  gap: 18px;
}

/* RADIUS */

.input-with-suffix {
  position: relative;
}

.input-with-suffix input {
  padding-right: 75px;
}

.input-with-suffix span {
  position: absolute;

  right: 14px;
  top: 50%;

  transform: translateY(-50%);

  color: var(--text-secondary);

  font-size: 12px;

  font-weight: 600;
}

/* ERROR */

.error-alert {
  margin: 22px 32px 0;

  display: flex;
  align-items: flex-start;

  gap: 12px;

  padding: 14px 16px;

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
  margin: 4px 0 0;
  font-size: 12px;
}

/* FOOTER */

.form-footer {
  display: flex;

  justify-content: flex-end;

  gap: 12px;

  padding-top: 25px;

  margin-top: 10px;

  border-top: 1px solid var(--border);
}

.btn-primary,
.btn-cancel {
  min-height: 44px;

  padding: 0 20px;

  border-radius: 10px;

  font-size: 13px;

  font-weight: 700;

  cursor: pointer;

  transition: 0.2s;
}

.btn-primary {
  border: none;

  background: linear-gradient(135deg, var(--accent), var(--accent-light));

  color: white;

  box-shadow: 0 6px 16px rgba(232, 117, 0, 0.2);
}

.btn-primary:hover {
  transform: translateY(-1px);
}

.btn-cancel {
  border: 1px solid var(--border);

  background: white;

  color: var(--text-secondary);
}

.btn-cancel:hover {
  background: var(--background);
}

/* RESPONSIVE */

@media (max-width: 850px) {
  .sidebar {
    width: 76px;
    padding: 20px 10px;
  }

  .brand-info {
    display: none;
  }

  .sidebar-brand {
    justify-content: center;
    padding-bottom: 28px;
  }

  .nav-item {
    justify-content: center;
    padding: 0;
    font-size: 0;
  }

  .nav-icon {
    font-size: 20px;
  }

  .logout-button {
    justify-content: center;
    padding: 0;
    font-size: 0;
  }

  .main-content {
    margin-left: 76px;
  }

  .content {
    padding: 30px 25px;
  }
}

@media (max-width: 650px) {
  .sidebar {
    display: none;
  }

  .main-content {
    margin-left: 0;
  }

  .topbar {
    padding: 16px 20px;
  }

  .profile-info {
    display: none;
  }

  .content {
    padding: 25px 18px 40px;
  }

  .form-row {
    grid-template-columns: 1fr;
    gap: 0;
  }

  .card-header,
  form {
    padding-left: 20px;
    padding-right: 20px;
  }

  .error-alert {
    margin-left: 20px;
    margin-right: 20px;
  }

  .form-footer {
    flex-direction: column-reverse;
  }

  .btn-primary,
  .btn-cancel {
    width: 100%;
  }
}
</style>
