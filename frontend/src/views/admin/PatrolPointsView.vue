<script setup>
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";
import QRCode from "qrcode";
import Swal from "sweetalert2";

const router = useRouter();
const patrolPoints = ref([]);
const loading = ref(true);
const error = ref("");
const search = ref("");
const showQrModal = ref(false);
const selectedQrPoint = ref(null);
const qrImage = ref("");

const user = ref({
  id: null,
  name: "Admin",
  role: "admin",
});

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
    try {
      user.value = JSON.parse(storedUser);
    } catch {
      console.error("Data user tidak valid");
    }
  }
};

const fetchPatrolPoints = async () => {
  loading.value = true;
  error.value = "";

  try {
    const response = await axios.get(
      "http://127.0.0.1:8000/api/admin/patrol-points",
      getAuthHeaders(),
    );

    patrolPoints.value = response.data.patrol_points ?? [];
  } catch (err) {
    console.error(err);

    error.value = err.response?.data?.message || "Gagal memuat data titik patroli.";
  } finally {
    loading.value = false;
  }
};

const filteredPatrolPoints = computed(() => {
  const keyword = search.value.toLowerCase().trim();

  if (!keyword) {
    return patrolPoints.value;
  }

  return patrolPoints.value.filter((point) => {
    return (
      point.name?.toLowerCase().includes(keyword) ||
      point.qr_code?.toLowerCase().includes(keyword) ||
      point.location_address?.toLowerCase().includes(keyword)
    );
  });
});

const goToCreate = () => {
  router.push("/admin/patrol-points/create");
};

const goToRoutes = () => {
  router.push("/admin/routes");
};

const editPatrolPoint = (id) => {
  router.push(`/admin/patrol-points/${id}/edit`);
};
const showQrCode = async (point) => {
  try {
    if (!point.qr_code) {
      error.value = "QR Code untuk titik patroli ini belum tersedia.";
      return;
    }

    selectedQrPoint.value = point;

    qrImage.value = await QRCode.toDataURL(point.qr_code, {
      width: 300,
      margin: 2,
      errorCorrectionLevel: "H",
    });

    showQrModal.value = true;
  } catch (err) {
    console.error(err);
    error.value = "Gagal membuat tampilan QR Code.";
  }
};
const closeQrModal = () => {
  showQrModal.value = false;
  selectedQrPoint.value = null;
  qrImage.value = "";
};
const printQrCode = () => {
  if (!qrImage.value || !selectedQrPoint.value) {
    Swal.fire({
      icon: "warning",
      title: "QR Code Tidak Tersedia",
      text: "QR Code belum siap untuk dicetak.",
      confirmButtonText: "OK",
    });

    return;
  }

  const point = selectedQrPoint.value;

  const printWindow = window.open("", "_blank", "width=800,height=900");

  if (!printWindow) {
    Swal.fire({
      icon: "error",
      title: "Popup Diblokir",
      text: "Izinkan popup pada browser untuk mencetak QR Code.",
      confirmButtonText: "OK",
    });

    return;
  }

  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>QR Code - ${point.name}</title>

        <style>
          * {
            box-sizing: border-box;
          }

          body {
            margin: 0;
            padding: 30px;
            background: white;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2454;
          }

          .print-container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            text-align: center;
            border: 3px solid #1f2454;
            border-radius: 20px;
            padding: 35px;
          }

          .brand {
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #1f2454;
            margin-bottom: 5px;
          }

          .brand-subtitle {
            font-size: 10px;
            color: #777;
            letter-spacing: 1.5px;
            margin-bottom: 25px;
          }

          .title {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 8px;
          }

          .location {
            font-size: 15px;
            color: #666;
            margin-bottom: 25px;
          }

          .qr-wrapper {
            display: flex;
            justify-content: center;
            margin: 20px 0;
          }

          .qr {
            width: 360px;
            height: 360px;
            image-rendering: pixelated;
          }

          .code-label {
            font-size: 10px;
            color: #888;
            font-weight: bold;
            letter-spacing: 1.5px;
            margin-top: 15px;
          }

          .code {
            margin-top: 5px;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 1px;
            word-break: break-all;
          }

          .instruction {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            line-height: 1.6;
            color: #666;
          }

          .warning {
            margin-top: 15px;
            font-size: 10px;
            color: #999;
          }

          @media print {
            body {
              padding: 0;
            }

            .print-container {
              max-width: none;
              border: 3px solid #1f2454;
              margin: 0;
            }
          }
        </style>
      </head>

      <body>
        <div class="print-container">

          <div class="brand">
            KAI SECURITY
          </div>

          <div class="brand-subtitle">
            MONITORING SYSTEM
          </div>

          <div class="title">
            ${point.name}
          </div>

          <div class="location">
            ${point.location_address || "Lokasi Checkpoint"}
          </div>

          <div class="qr-wrapper">
            <img
              src="${qrImage.value}"
              class="qr"
              alt="QR Code ${point.name}"
            />
          </div>

          <div class="code-label">
            KODE CHECKPOINT
          </div>

          <div class="code">
            ${point.qr_code}
          </div>

          <div class="instruction">
            SCAN QR CODE INI SAAT MELAKUKAN PATROLI
          </div>

          <div class="warning">
            Jangan memindahkan atau merusak QR Code ini.
          </div>

        </div>
      </body>
    </html>
  `);

  printWindow.document.close();

  printWindow.onload = () => {
    printWindow.focus();
    printWindow.print();
  };
};
const deletePatrolPoint = async (id) => {
  const result = await Swal.fire({
    icon: "warning",
    title: "Hapus Titik Patroli?",
    text: "Data titik patroli yang dihapus tidak dapat dikembalikan.",
    showCancelButton: true,
    confirmButtonText: "Ya, Hapus",
    cancelButtonText: "Batal",
    reverseButtons: true,
    focusCancel: true,
  });

  if (!result.isConfirmed) return;

  try {
    await axios.delete(`http://127.0.0.1:8000/api/admin/patrol-points/${id}`, getAuthHeaders());

    await Swal.fire({
      icon: "success",
      title: "Berhasil!",
      text: "Titik patroli berhasil dihapus.",
      confirmButtonText: "OK",
    });

    await fetchPatrolPoints();
  } catch (err) {
    console.error(err);

    await Swal.fire({
      icon: "error",
      title: "Gagal Menghapus",
      text: err.response?.data?.message || "Gagal menghapus titik patroli.",
      confirmButtonText: "OK",
    });
  }
};

const logout = () => {
  localStorage.removeItem("token");
  localStorage.removeItem("user");

  router.push("/");
};

const displayName = computed(() => {
  return user.value?.name || "Admin";
});

onMounted(() => {
  loadUser();
  fetchPatrolPoints();
});
</script>

<template>
  <div class="admin-layout">
    <!-- =========================
         SIDEBAR
    ========================== -->

    <aside class="sidebar">
      <div class="sidebar-brand">
        <div class="brand-mark">KAI</div>

        <div class="brand-info">
          <h2>KAI SECURITY</h2>

          <span> MONITORING SYSTEM </span>
        </div>
      </div>

      <nav class="sidebar-nav">
        <router-link to="/admin/dashboard" class="nav-item">
          <span class="nav-icon"> ⌂ </span>

          Dashboard
        </router-link>

        <router-link to="/admin/users" class="nav-item">
          <span class="nav-icon"> ♟ </span>

          Kelola User
        </router-link>

        <router-link to="/admin/patrol-points" class="nav-item">
          <span class="nav-icon"> ⌖ </span>

          Titik Patroli
        </router-link>
      </nav>

      <div class="sidebar-footer">
        <button class="logout-button" @click="logout">
          <span> ↪ </span>

          Keluar
        </button>
      </div>
    </aside>

    <!-- =========================
         MAIN CONTENT
    ========================== -->

    <main class="main-content">
      <!-- TOPBAR -->

      <header class="topbar">
        <div class="page-heading">
          <h1>Titik Patroli</h1>

          <p>Kelola titik pemeriksaan patroli keamanan.</p>
        </div>

        <div class="user-profile">
          <div class="profile-avatar">
            {{ displayName.charAt(0).toUpperCase() }}
          </div>

          <div class="profile-info">
            <strong>
              {{ displayName }}
            </strong>

            <span> Administrator </span>
          </div>
        </div>
      </header>

      <!-- CONTENT -->

      <section class="dashboard-content">
        <!-- HEADER -->

        <div class="content-header">
          <div>
            <div class="section-label">MONITORING PATROLI</div>

            <h2>Daftar Titik Patroli</h2>

            <p>
              Total {{ filteredPatrolPoints.length }}
              titik patroli
            </p>
          </div>

          <div class="header-actions">
            <button class="btn-outline" @click="goToRoutes">
              🗺 Kelola Rute
            </button>

            <button class="btn-primary" @click="goToCreate">
              <span> + </span>

              Tambah Titik Patroli
            </button>
          </div>
        </div>

        <!-- ERROR -->

        <div v-if="error" class="error-alert">
          <span>⚠</span>

          <div>
            <strong>Terjadi kesalahan</strong>

            <p>{{ error }}</p>
          </div>

          <button @click="error = ''">Tutup</button>
        </div>

        <!-- SEARCH -->

        <div class="filter-row">
          <div class="search-wrapper">
            <span class="search-icon"> ⌕ </span>

            <input
              v-model="search"
              type="text"
              placeholder="Cari nama, kode, atau lokasi..."
              class="search-input"
            />
          </div>
        </div>

        <!-- LOADING -->

        <div v-if="loading" class="loading-box">
          <div class="spinner"></div>

          <p>Memuat titik patroli...</p>
        </div>

        <!-- TABLE -->

        <div v-else class="table-card">
          <table>
            <thead>
              <tr>
                <th>Kode</th>

                <th>Nama Titik</th>

                <th>Lokasi</th>

                <th>Status</th>

                <th>QR Code</th>

                <th>Aksi</th>
              </tr>
            </thead>

            <tbody>
              <!-- EMPTY -->

              <tr v-if="filteredPatrolPoints.length === 0">
                <td colspan="6" class="empty-state">
                  <div class="empty-icon">⌖</div>

                  <strong> Belum ada titik patroli </strong>

                  <p>Tambahkan titik patroli pertama untuk memulai sistem monitoring.</p>

                  <button class="btn-primary small" @click="goToCreate">+ Tambah Titik</button>
                </td>
              </tr>

              <!-- DATA -->

              <tr v-for="point in filteredPatrolPoints" :key="point.id">
                <td>
                  <span class="point-code">
                    {{ point.qr_code || "-" }}
                  </span>
                </td>

                <td>
                  <div class="point-name">
                    <div class="point-icon">⌖</div>

                    <div>
                      <strong>
                        {{ point.name }}
                      </strong>

                      <small> ID #{{ point.id }} </small>
                    </div>
                  </div>
                </td>

                <td class="text-secondary">
                  {{ point.location_address || "-" }}
                </td>

                <td>
                  <span
                    class="status-badge"
                    :class="point.status === 'aktif' ? 'status-active' : 'status-inactive'"
                  >
                    <span class="status-dot"></span>

                    {{ point.status === "aktif" ? "Aktif" : "Nonaktif" }}
                  </span>
                </td>

                <td>
                  <div v-if="point.qr_code" class="qr-cell">
                    <span class="qr-available"> ✓ Tersedia </span>

                    <button class="btn-qr" @click="showQrCode(point)" title="Lihat QR Code">
                      ▣ Lihat QR
                    </button>
                  </div>

                  <span v-else class="qr-empty"> Belum dibuat </span>
                </td>

                <td>
                  <div class="action-buttons">
                    <button class="btn-icon" @click="editPatrolPoint(point.id)" title="Edit">
                      ✏️
                    </button>

                    <button
                      class="btn-icon btn-icon-danger"
                      @click="deletePatrolPoint(point.id)"
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
      </section>
    </main>
    <!-- =========================
     QR CODE MODAL
========================== -->

    <div v-if="showQrModal" class="qr-modal-overlay" @click.self="closeQrModal">
      <div class="qr-modal">
        <div class="qr-modal-header">
          <div>
            <div class="section-label">QR CODE PATROLI</div>

            <h3>
              {{ selectedQrPoint?.name }}
            </h3>

            <p>ID #{{ selectedQrPoint?.id }}</p>
          </div>

          <button class="modal-close" @click="closeQrModal">✕</button>
        </div>

        <div class="qr-modal-body">
          <div class="qr-image-wrapper">
            <img v-if="qrImage" :src="qrImage" alt="QR Code Patrol" class="qr-image" />
          </div>

          <div class="qr-info">
            <span class="qr-info-label"> KODE QR </span>

            <strong>
              {{ selectedQrPoint?.qr_code }}
            </strong>
          </div>

          <p class="qr-instruction">
            Tampilkan atau cetak QR Code ini dan pasang pada titik patroli yang sesuai.
          </p>
        </div>

        <div class="qr-modal-footer">
          <button class="btn-cancel" @click="closeQrModal">Tutup</button>

          <button class="btn-print" @click="printQrCode">🖨️ Cetak QR</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* =========================================
   COLOR SYSTEM
========================================= */

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

/* =========================================
   SIDEBAR
========================================= */

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

  padding-top: 20px;
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

/* =========================================
   MAIN
========================================= */

.main-content {
  width: 100%;

  min-height: 100vh;

  margin-left: 260px;
}

/* =========================================
   TOPBAR
========================================= */

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

/* =========================================
   CONTENT
========================================= */

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

  margin-bottom: 22px;
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

/* =========================================
   BUTTON
========================================= */

.header-actions {
  display: flex;
  gap: 10px;
}

.btn-outline {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 11px 20px;
  border: 1px solid var(--primary);
  border-radius: 10px;
  background: white;
  color: var(--primary);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: 0.25s;
}

.btn-outline:hover {
  background: var(--background);
}

.btn-primary {
  display: inline-flex;

  align-items: center;
  justify-content: center;

  gap: 8px;

  padding: 11px 20px;

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

.btn-primary.small {
  margin-top: 15px;

  padding: 9px 16px;

  font-size: 12px;
}

/* =========================================
   FILTER
========================================= */

.filter-row {
  display: flex;

  margin-bottom: 20px;
}

.search-wrapper {
  position: relative;

  width: 100%;
}

.search-icon {
  position: absolute;

  left: 15px;
  top: 50%;

  transform: translateY(-50%);

  color: var(--text-secondary);

  font-size: 18px;
}

.search-input {
  width: 100%;

  height: 44px;

  box-sizing: border-box;

  border: 1px solid var(--border);

  border-radius: 10px;

  padding: 0 16px 0 42px;

  background: var(--white);

  font-size: 13px;

  outline: none;

  transition: 0.2s;
}

.search-input:focus {
  border-color: var(--accent);

  box-shadow: 0 0 0 3px rgba(232, 117, 0, 0.08);
}

/* =========================================
   ERROR
========================================= */

.error-alert {
  display: flex;
  align-items: center;

  gap: 12px;

  margin-bottom: 20px;

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

/* =========================================
   LOADING
========================================= */

.loading-box {
  display: flex;

  flex-direction: column;

  align-items: center;

  padding: 70px;

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

/* =========================================
   TABLE
========================================= */

.table-card {
  background: var(--white);

  border: 1px solid var(--border);

  border-radius: 16px;

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
  padding: 15px 20px;

  font-size: 13px;

  color: var(--text-primary);
}

.text-secondary {
  color: var(--text-secondary);
}

/* =========================================
   POINT
========================================= */

.point-code {
  display: inline-block;

  padding: 5px 9px;

  border-radius: 7px;

  background: rgba(31, 36, 84, 0.08);

  color: var(--primary);

  font-size: 11px;

  font-weight: 800;
}

.point-name {
  display: flex;

  align-items: center;

  gap: 10px;
}

.point-icon {
  width: 36px;
  height: 36px;

  display: flex;

  align-items: center;
  justify-content: center;

  border-radius: 10px;

  background: rgba(232, 117, 0, 0.1);

  color: var(--accent);

  font-size: 17px;
}

.point-name strong {
  display: block;

  font-size: 13px;
}

.point-name small {
  display: block;

  margin-top: 3px;

  color: var(--text-secondary);

  font-size: 10px;
}

/* =========================================
   STATUS
========================================= */

.status-badge {
  display: inline-flex;

  align-items: center;

  gap: 6px;

  padding: 5px 10px;

  border-radius: 20px;

  font-size: 10px;

  font-weight: 700;
}

.status-active {
  background: rgba(47, 158, 99, 0.1);

  color: var(--success);
}

.status-inactive {
  background: rgba(214, 48, 49, 0.1);

  color: var(--danger);
}

.status-dot {
  width: 6px;
  height: 6px;

  border-radius: 50%;

  background: currentColor;
}

/* =========================================
   QR
========================================= */

.qr-available {
  color: var(--success);

  font-size: 11px;

  font-weight: 700;
}
.btn-cancel {
  min-height: 42px;
  padding: 0 20px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  gap: 7px;

  border: 1px solid var(--border);
  border-radius: 10px;

  background: #f7f8fa;
  color: var(--primary);

  font-family: inherit;
  font-size: 12px;
  font-weight: 700;

  cursor: pointer;

  transition: all 0.2s ease;
}

.btn-cancel:hover {
  background: #eef0f4;

  border-color: #d5d8e1;

  transform: translateY(-1px);

  box-shadow: 0 5px 14px rgba(31, 36, 84, 0.08);
}

.btn-cancel {
  min-height: 42px;
  padding: 0 20px;

  display: inline-flex;
  align-items: center;
  justify-content: center;

  gap: 7px;

  border: 1px solid var(--border);
  border-radius: 10px;

  background: #f7f8fa;
  color: var(--primary);

  font-family: inherit;
  font-size: 12px;
  font-weight: 700;

  cursor: pointer;

  transition: all 0.2s ease;
}

.btn-cancel:hover {
  background: #eef0f4;

  border-color: #d5d8e1;

  transform: translateY(-1px);

  box-shadow: 0 5px 14px rgba(31, 36, 84, 0.08);
}




.qr-empty {
  color: var(--text-secondary);

  font-size: 11px;
}
/* =========================================
   QR BUTTON
========================================= */

.qr-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.btn-qr {
  border: 1px solid rgba(31, 36, 84, 0.12);
  border-radius: 8px;
  padding: 7px 10px;
  background: rgba(31, 36, 84, 0.05);
  color: var(--primary);
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: 0.2s;
}

.btn-qr:hover {
  background: var(--primary);
  color: white;
  transform: translateY(-1px);
}

/* =========================================
   QR MODAL
========================================= */

.qr-modal-overlay {
  position: fixed;
  inset: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  background: rgba(31, 36, 84, 0.45);

  backdrop-filter: blur(5px);

  z-index: 1000;

  padding: 20px;
}

.qr-modal {
  width: 430px;
  max-width: 100%;

  background: white;

  border-radius: 20px;

  overflow: hidden;

  box-shadow: 0 30px 80px rgba(31, 36, 84, 0.25);

  animation: qrModalIn 0.2s ease-out;
}

@keyframes qrModalIn {
  from {
    opacity: 0;
    transform: translateY(10px) scale(0.98);
  }

  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.qr-modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;

  padding: 24px 26px;

  border-bottom: 1px solid var(--border);
}

.qr-modal-header h3 {
  margin: 6px 0 3px;

  color: var(--primary);

  font-size: 19px;
}

.qr-modal-header p {
  margin: 0;

  color: var(--text-secondary);

  font-size: 11px;
}

.modal-close {
  width: 34px;
  height: 34px;

  border: none;

  border-radius: 9px;

  background: var(--background);

  color: var(--text-secondary);

  font-size: 16px;

  cursor: pointer;

  transition: 0.2s;
}

.modal-close:hover {
  background: var(--border);

  color: var(--primary);
}

.qr-modal-body {
  padding: 28px;

  text-align: center;
}
.btn-print {
  min-height: 42px;

  padding: 0 18px;

  border: none;

  border-radius: 10px;

  background: linear-gradient(135deg, var(--accent), var(--accent-light));

  color: white;

  font-size: 12px;

  font-weight: 700;

  cursor: pointer;

  transition: 0.2s;

  box-shadow: 0 6px 16px rgba(232, 117, 0, 0.2);
}

.btn-print:hover {
  transform: translateY(-1px);

  box-shadow: 0 8px 20px rgba(232, 117, 0, 0.3);
}

.qr-image-wrapper {
  width: 320px;
  height: 320px;

  max-width: 100%;

  margin: 0 auto 22px;

  display: flex;
  align-items: center;
  justify-content: center;

  background: white;

  border: 1px solid var(--border);

  border-radius: 14px;

  box-shadow: 0 8px 25px rgba(31, 36, 84, 0.08);
}



.qr-image {
  width: 290px;
  height: 290px;

  max-width: 90%;
  max-height: 90%;

  display: block;
}

.qr-info {
  padding: 13px 16px;

  border-radius: 10px;

  background: var(--background);

  text-align: center;
}

.qr-info-label {
  display: block;

  margin-bottom: 5px;

  color: var(--text-secondary);

  font-size: 9px;

  font-weight: 800;

  letter-spacing: 1px;
}

.qr-info strong {
  color: var(--primary);

  font-size: 13px;

  letter-spacing: 0.5px;

  word-break: break-all;
}

.qr-instruction {
  margin: 16px 10px 0;

  color: var(--text-secondary);

  font-size: 11px;

  line-height: 1.6;
}

.qr-modal-footer {
  display: flex;
  justify-content: flex-end;

  gap: 10px;

  padding: 18px 26px;

  border-top: 1px solid var(--border);
}
/* =========================================
   ACTION
========================================= */

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

  cursor: pointer;

  transition: 0.2s;
}

.btn-icon:hover {
  background: var(--border);
}

.btn-icon-danger:hover {
  background: rgba(214, 48, 49, 0.1);
}

/* =========================================
   EMPTY
========================================= */

.empty-state {
  padding: 70px 20px !important;

  text-align: center;

  color: var(--text-secondary);
}

.empty-icon {
  width: 60px;
  height: 60px;

  display: flex;

  align-items: center;
  justify-content: center;

  margin: 0 auto 15px;

  border-radius: 18px;

  background: rgba(31, 36, 84, 0.07);

  color: var(--primary);

  font-size: 28px;
}

.empty-state strong {
  display: block;

  color: var(--primary);

  font-size: 15px;
}

.empty-state p {
  margin: 7px 0 0;

  font-size: 12px;
}

/* =========================================
   RESPONSIVE
========================================= */

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

  .logout-button span {
    font-size: 20px;
  }

  .main-content {
    margin-left: 76px;
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

  .page-heading p,
  .profile-info {
    display: none;
  }

  .dashboard-content {
    padding: 25px 20px 40px;
  }

  .content-header {
    align-items: flex-start;

    flex-direction: column;

    gap: 18px;
  }

  .btn-primary {
    width: 100%;
  }

  .header-actions {
    width: 100%;
    flex-direction: column;
  }

  .btn-outline {
    width: 100%;
  }

  .table-card {
    overflow-x: auto;
  }

  table {
    min-width: 850px;
  }
}
</style>
