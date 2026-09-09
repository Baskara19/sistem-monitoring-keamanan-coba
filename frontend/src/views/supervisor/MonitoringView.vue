git<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();
const API_BASE = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";
const mapElement = ref(null),
  mapInstance = ref(null),
  markers = ref([]);
const loading = ref(false),
  mapLoading = ref(true),
  errorMessage = ref("");
const patrolPoints = ref([]),
  activeSatpams = ref([]);
const statistics = ref({
  berjalan: 0,
  selesai: 0,
  terlambat: 0,
  anomali: 0,
  skip: 0,
  terlewat: 0,
  offline: 0,
});
const user = ref({ name: "Supervisor" });
let refreshInterval = null;
const displayName = computed(() => user.value?.name || "Supervisor");
const activeCount = computed(() => activeSatpams.value.length);

function loadUser() {
  try {
    user.value = { ...user.value, ...JSON.parse(localStorage.getItem("user") || "{}") };
  } catch {
    localStorage.removeItem("user");
  }
}
async function loadMonitoring() {
  loading.value = true;
  errorMessage.value = "";
  try {
    const token = localStorage.getItem("token");
    if (!token) throw new Error("Sesi login tidak ditemukan.");
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 12000);
    let response;

    try {
      response = await fetch(`${API_BASE}/supervisor/monitoring`, {
        headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
        signal: controller.signal,
      });
    } finally {
      clearTimeout(timeout);
    }
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || "Data monitoring gagal dimuat.");
    patrolPoints.value = Array.isArray(data.patrol_points) ? data.patrol_points : [];
    activeSatpams.value = Array.isArray(data.active_satpams) ? data.active_satpams : [];
    statistics.value = { ...statistics.value, ...(data.statistics || {}) };
    await nextTick();
    updateMap();
  } catch (error) {
    errorMessage.value =
      error.name === "AbortError"
        ? "Server monitoring tidak merespons. Pastikan Laravel berjalan di port 8000."
        : error.message || "Terjadi kesalahan saat mengambil data monitoring.";
  } finally {
    loading.value = false;
  }
}
function loadLeaflet() {
  return new Promise((resolve, reject) => {
    if (window.L) return resolve(window.L);
    const existing = document.querySelector('script[data-leaflet="true"]');
    if (existing) {
      existing.addEventListener("load", () => resolve(window.L), { once: true });
      existing.addEventListener("error", reject, { once: true });
      return;
    }
    const stylesheet = document.createElement("link");
    stylesheet.rel = "stylesheet";
    stylesheet.href = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";
    document.head.appendChild(stylesheet);
    const script = document.createElement("script");
    script.src = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
    script.dataset.leaflet = "true";
    script.onload = () => resolve(window.L);
    script.onerror = () => reject(new Error("Library peta gagal dimuat."));
    document.body.appendChild(script);
  });
}
async function initializeMap() {
  try {
    const L = await loadLeaflet();
    if (!mapElement.value) return;
    mapInstance.value = L.map(mapElement.value, { zoomControl: false });
    L.control.zoom({ position: "bottomright" }).addTo(mapInstance.value);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: "&copy; OpenStreetMap contributors",
    }).addTo(mapInstance.value);
    mapInstance.value.setView([-6.2, 106.8], 11);
    updateMap();
  } catch {
    errorMessage.value = "Peta tidak dapat dimuat. Periksa koneksi internet.";
  } finally {
    mapLoading.value = false;
  }
}
function updateMap() {
  const L = window.L;
  if (!L || !mapInstance.value) return;
  markers.value.forEach((marker) => mapInstance.value.removeLayer(marker));
  markers.value = [];
  const bounds = [];
  patrolPoints.value.forEach((point) => {
    const lat = Number(point.latitude),
      lng = Number(point.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    const color = point.status === "aktif" ? "#2f9e63" : "#8a8d9c";
    const icon = L.divIcon({
      className: "patrol-marker",
      html: `<span style="background:${color}"></span>`,
      iconSize: [34, 42],
      iconAnchor: [17, 42],
      popupAnchor: [0, -38],
    });
    const marker = L.marker([lat, lng], { icon }).addTo(mapInstance.value);
    marker.bindPopup(
      `<strong>${escapeHtml(point.name)}</strong><br><small>${escapeHtml(point.location || "Lokasi tidak tersedia")}</small>`,
    );
    markers.value.push(marker);
    bounds.push([lat, lng]);
  });
  if (bounds.length) mapInstance.value.fitBounds(bounds, { padding: [42, 42], maxZoom: 16 });
}
function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}
function initials(name) {
  return String(name || "S")
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((word) => word[0])
    .join("")
    .toUpperCase();
}
function logout() {
  localStorage.removeItem("token");
  localStorage.removeItem("user");
  router.push("/login");
}
onMounted(() => {
  loadUser();
  // Peta dan API dimuat paralel, agar CDN peta tidak menahan data database.
  initializeMap();
  loadMonitoring();
  refreshInterval = setInterval(loadMonitoring, 30000);
});
onBeforeUnmount(() => {
  clearInterval(refreshInterval);
  if (mapInstance.value) mapInstance.value.remove();
});
</script>

<template>
  <div class="supervisor-layout">
    <aside class="sidebar">
      <div class="sidebar-brand">
        <div class="brand-mark">KAI</div>
        <div class="brand-info">
          <h2>KAI SECURITY</h2>
          <span>MONITORING SYSTEM</span>
        </div>
      </div>
      <nav class="sidebar-nav">
        <router-link to="/supervisor/dashboard" class="nav-item"
          ><span class="nav-icon">⌂</span>Dashboard</router-link
        >
        <router-link to="/supervisor/schedules" class="nav-item"
          ><span class="nav-icon">🗓</span>Kelola Jadwal</router-link
        >
        <router-link to="/supervisor/monitoring" class="nav-item"
          ><span class="nav-icon">◉</span>Monitoring</router-link
        >
        <router-link to="/supervisor/reports" class="nav-item"
          ><span class="nav-icon">▤</span>Laporan</router-link
        >
      </nav>
      <div class="sidebar-footer">
        <div class="user-sidebar">
          <div class="sidebar-avatar">{{ displayName.charAt(0).toUpperCase() }}</div>
          <div class="sidebar-user-info">
            <strong>{{ displayName }}</strong
            ><span>Supervisor</span>
          </div>
        </div>
        <button class="logout-button" @click="logout"><span>↪</span>Keluar</button>
      </div>
    </aside>
    <main class="main-content">
      <header class="topbar">
        <div>
          <h1>Monitoring</h1>
          <p>Pantau aktivitas patroli dan kondisi titik pengamanan.</p>
        </div>
        <button class="refresh-button" :disabled="loading" @click="loadMonitoring">
          {{ loading ? "Memuat..." : "Refresh" }}
        </button>
      </header>
      <section class="content">
        <div class="section-heading">
          <div>
            <span>MONITORING PATROLI</span>
            <h2>Monitoring Patroli</h2>
            <p>Status aktivitas patroli petugas keamanan hari ini.</p>
          </div>
          <div class="system-status"><i></i>Sistem Aktif</div>
        </div>
        <div v-if="errorMessage" class="error-alert">{{ errorMessage }}</div>
        <section class="stat-grid">
          <article class="stat-card status-berjalan">
            <span><i></i>Berjalan</span><strong>{{ statistics.berjalan }}</strong>
          </article>
          <article class="stat-card status-selesai">
            <span><i></i>Selesai</span><strong>{{ statistics.selesai }}</strong>
          </article>
          <article class="stat-card status-terlambat">
            <span><i></i>Terlambat</span><strong>{{ statistics.terlambat }}</strong>
          </article>
          <article class="stat-card status-anomali">
            <span><i></i>Anomali</span><strong>{{ statistics.anomali }}</strong>
          </article>
          <article class="stat-card status-skip">
            <span><i></i>Skip Scan</span><strong>{{ statistics.skip }}</strong>
          </article>
          <article class="stat-card status-terlewat">
            <span><i></i>Terlewat</span><strong>{{ statistics.terlewat }}</strong>
          </article>
          <article class="stat-card status-offline">
            <span><i></i>Offline</span><strong>{{ statistics.offline }}</strong>
          </article>
        </section>
        <section class="monitoring-grid">
          <article class="panel map-panel">
            <header>
              <div>
                <h3>Peta Titik Patroli</h3>
                <p>{{ patrolPoints.length }} titik patroli terdaftar</p>
              </div>
              <span class="map-badge">Titik aktif</span>
            </header>
            <div class="map-wrap">
              <div ref="mapElement" class="map"></div>
              <div v-if="mapLoading" class="map-state">Menyiapkan peta...</div>
              <div v-else-if="patrolPoints.length === 0" class="map-state">
                Belum ada titik patroli yang dapat ditampilkan.
              </div>
            </div>
            <footer>
              <span><i class="green"></i>Titik aktif</span
              ><span><i class="grey"></i>Titik nonaktif</span>
            </footer>
          </article>
          <aside class="panel guards-panel">
            <header>
              <div>
                <h3>Satpam Aktif</h3>
                <p>Petugas yang sedang bertugas.</p>
              </div>
              <b>{{ activeCount }}</b>
            </header>
            <div v-if="activeSatpams.length" class="guard-list">
              <article v-for="satpam in activeSatpams" :key="satpam.id" class="guard">
                <div class="avatar">{{ initials(satpam.name) }}</div>
                <div class="guard-info">
                  <strong>{{ satpam.name }}</strong
                  ><span>{{
                    satpam.current_point || satpam.shift || "Belum ada lokasi patroli"
                  }}</span>
                </div>
              </article>
            </div>
            <div v-else class="empty-guards">Belum ada satpam aktif.</div>
          </aside>
        </section>
      </section>
    </main>
  </div>
</template>

<style scoped>
/* =========================================================
   COLOR SYSTEM
========================================================= */

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
  --warning: #e87500;
  --info: #3578e5;

  min-height: 100vh;

  display: flex;

  background: linear-gradient(135deg, var(--background) 0%, var(--background-soft) 100%);

  font-family: "Segoe UI", Arial, sans-serif;

  color: var(--text-primary);
}

/* =========================================================
   SIDEBAR
========================================================= */

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

/* =========================================================
   NAVIGATION
========================================================= */

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

/* =========================================================
   SIDEBAR USER
========================================================= */

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

/* =========================================================
   MAIN
========================================================= */

.main-content {
  width: 100%;

  min-height: 100vh;

  margin-left: 260px;
}

/* =========================================================
   TOPBAR
========================================================= */

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

.topbar h1 {
  margin: 0;

  font-size: 23px;

  font-weight: 700;

  color: var(--primary);
}

.topbar p {
  margin: 4px 0 0;

  color: var(--text-secondary);

  font-size: 12px;
}

.refresh-button {
  border: none;

  border-radius: 10px;

  padding: 10px 16px;

  background: linear-gradient(135deg, var(--primary), var(--primary-light));

  color: white;

  font-size: 11px;

  font-weight: 700;

  cursor: pointer;

  transition: 0.25s;
}

.refresh-button:hover:not(:disabled) {
  transform: translateY(-1px);

  box-shadow: 0 7px 18px rgba(31, 36, 84, 0.18);
}

.refresh-button:disabled {
  opacity: 0.65;

  cursor: not-allowed;
}

/* =========================================================
   CONTENT
========================================================= */

.content {
  max-width: 1400px;

  margin: 0 auto;

  padding: 38px 42px 50px;

  box-sizing: border-box;
}

/* =========================================================
   SECTION HEADING
========================================================= */

.section-heading {
  display: flex;
  align-items: center;

  justify-content: space-between;

  margin-bottom: 28px;
}

.section-heading > div:first-child {
  display: flex;
  flex-direction: column;
}

.section-heading > span,
.section-heading > div > span {
  color: var(--accent);

  font-size: 10px;

  font-weight: 800;

  letter-spacing: 1.4px;
}

.section-heading h2 {
  margin: 6px 0 8px;

  color: var(--primary);

  font-size: 28px;

  line-height: 1.2;
}

.section-heading p {
  margin: 0;

  color: var(--text-secondary);

  font-size: 14px;
}

.system-status {
  display: flex;
  align-items: center;

  gap: 8px;

  padding: 10px 16px;

  background: rgba(255, 255, 255, 0.8);

  border: 1px solid var(--border);

  border-radius: 30px;

  color: var(--primary);

  font-size: 12px;

  font-weight: 700;
}

.system-status i {
  width: 8px;
  height: 8px;

  flex-shrink: 0;

  border-radius: 50%;

  background: var(--success);

  box-shadow: 0 0 10px rgba(47, 158, 99, 0.6);
}

/* =========================================================
   ERROR
========================================================= */

.error-alert {
  display: flex;
  align-items: center;

  margin-bottom: 24px;

  padding: 15px 18px;

  background: #fff1f1;

  border-left: 4px solid var(--danger);

  border-radius: 10px;

  color: var(--danger);

  font-size: 12px;
}

/* =========================================================
   STATISTICS
========================================================= */

.stat-grid {
  display: grid;

  grid-template-columns: repeat(4, minmax(0, 1fr));

  gap: 22px;

  margin-bottom: 25px;
}

.stat-card {
  min-height: 130px;

  padding: 24px;

  box-sizing: border-box;

  display: flex;
  align-items: center;

  justify-content: space-between;

  border: 1px solid rgba(226, 228, 234, 0.9);

  border-radius: 18px;

  background: rgba(255, 255, 255, 0.88);

  box-shadow: 0 10px 30px rgba(31, 36, 84, 0.04);

  transition: 0.25s;
}

.stat-card:hover {
  transform: translateY(-4px);

  box-shadow: 0 16px 35px rgba(31, 36, 84, 0.1);
}

.stat-card span {
  display: flex;
  align-items: center;

  gap: 8px;

  font-size: 12px;

  font-weight: 700;
}

.stat-card span i {
  width: 8px;
  height: 8px;

  border-radius: 50%;

  background: currentColor;
}

.stat-card strong {
  font-size: 34px;

  line-height: 1;
}

.status-berjalan {
  color: var(--info);
}

.status-selesai {
  color: var(--success);
}

.status-terlambat {
  color: var(--warning);
}

.status-anomali {
  color: var(--danger);
}

.status-skip {
  color: #7c4dff;
}

.status-terlewat {
  color: #b05f00;
}

.status-offline {
  color: #747887;
}

/* =========================================================
   MONITORING GRID
========================================================= */

.monitoring-grid {
  display: grid;

  grid-template-columns:
    minmax(0, 1.8fr)
    minmax(310px, 0.9fr);

  gap: 25px;
}

/* =========================================================
   PANEL
========================================================= */

.panel {
  overflow: hidden;

  padding: 26px;

  box-sizing: border-box;

  background: rgba(255, 255, 255, 0.88);

  border: 1px solid rgba(226, 228, 234, 0.9);

  border-radius: 18px;

  box-shadow: 0 10px 30px rgba(31, 36, 84, 0.04);
}

.panel header {
  display: flex;
  align-items: flex-start;

  justify-content: space-between;

  margin-bottom: 24px;
}

.panel header h3 {
  margin: 5px 0 0;

  color: var(--primary);

  font-size: 19px;
}

.panel header p {
  margin: 4px 0 0;

  color: var(--text-secondary);

  font-size: 12px;
}

/* =========================================================
   BADGE
========================================================= */

.map-badge,
.guards-panel header > b {
  min-width: 25px;

  padding: 6px 10px;

  box-sizing: border-box;

  border-radius: 20px;

  background: rgba(47, 158, 99, 0.12);

  color: var(--success);

  font-size: 10px;

  font-weight: 700;

  text-align: center;
}

/* =========================================================
   MAP
========================================================= */

.map-wrap {
  position: relative;

  height: 450px;

  overflow: hidden;

  border-radius: 14px;

  background: #e9ebef;
}

.map {
  width: 100%;

  height: 100%;
}

.map-state {
  position: absolute;

  inset: 0;

  display: grid;

  place-items: center;

  padding: 30px;

  background: rgba(244, 245, 247, 0.84);

  color: var(--text-secondary);

  font-size: 12px;

  text-align: center;
}

.map-panel footer {
  display: flex;
  align-items: center;

  gap: 16px;

  padding-top: 14px;

  color: var(--text-secondary);

  font-size: 11px;
}

.map-panel footer span {
  display: flex;
  align-items: center;

  gap: 6px;
}

.map-panel footer i {
  width: 8px;
  height: 8px;

  border-radius: 50%;
}

.green {
  background: var(--success);
}

.grey {
  background: #8a8d9c;
}

/* =========================================================
   SATPAM LIST
========================================================= */

.guard-list {
  max-height: 500px;

  overflow-y: auto;

  margin: 0 -26px -26px;
}

.guard {
  display: flex;
  align-items: center;

  gap: 12px;

  padding: 14px 20px;

  border-bottom: 1px solid #eceef2;
}

.guard:last-child {
  border-bottom: none;
}

.avatar {
  width: 38px;
  height: 38px;

  flex-shrink: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  border-radius: 11px;

  background: rgba(31, 36, 84, 0.1);

  color: var(--primary);

  font-size: 11px;

  font-weight: 800;
}

.guard-info {
  min-width: 0;

  flex: 1;
}

.guard-info strong {
  display: block;

  overflow: hidden;

  text-overflow: ellipsis;

  white-space: nowrap;

  color: var(--primary);

  font-size: 12px;
}

.guard-info span {
  display: block;

  margin-top: 3px;

  overflow: hidden;

  text-overflow: ellipsis;

  white-space: nowrap;

  color: var(--text-secondary);

  font-size: 10px;
}

/* =========================================================
   EMPTY
========================================================= */

.empty-guards {
  min-height: 220px;

  display: flex;
  align-items: center;
  justify-content: center;

  text-align: center;

  color: var(--text-secondary);

  font-size: 12px;
}

/* =========================================================
   LEAFLET
========================================================= */

:deep(.patrol-marker) {
  background: transparent;

  border: 0;
}

:deep(.patrol-marker span) {
  display: block;

  width: 28px;
  height: 28px;

  border: 3px solid #fff;

  border-radius: 50% 50% 50% 0;

  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.25);

  transform: rotate(-45deg);
}

:deep(.leaflet-popup-content-wrapper) {
  border-radius: 9px;

  font-family: "Segoe UI", Arial, sans-serif;

  color: var(--primary);
}

/* =========================================================
   RESPONSIVE - TABLET
========================================================= */

@media (max-width: 1150px) {
  .stat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .monitoring-grid {
    grid-template-columns: 1fr;
  }
}

/* =========================================================
   RESPONSIVE - SIDEBAR COLLAPSE
========================================================= */

@media (max-width: 850px) {
  .sidebar {
    width: 76px;

    padding: 20px 10px;
  }

  .brand-info,
  .sidebar-user-info {
    display: none;
  }

  .sidebar-brand {
    justify-content: center;

    padding-bottom: 28px;
  }

  .brand-mark {
    font-size: 22px;
  }

  .nav-item {
    justify-content: center;

    padding: 0;

    font-size: 0;
  }

  .nav-icon {
    font-size: 20px;
  }

  .user-sidebar {
    justify-content: center;
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

  .topbar {
    padding: 0 25px;
  }

  .content {
    padding: 30px 25px 40px;
  }
}

/* =========================================================
   RESPONSIVE - MOBILE
========================================================= */

@media (max-width: 650px) {
  .sidebar {
    display: none;
  }

  .main-content {
    margin-left: 0;
  }

  .topbar {
    min-height: auto;

    padding: 18px 20px;
  }

  .topbar p {
    display: none;
  }

  .content {
    padding: 25px 20px 40px;
  }

  .section-heading {
    align-items: flex-start;

    flex-direction: column;

    gap: 18px;
  }

  .section-heading h2 {
    font-size: 24px;
  }

  .section-heading p {
    font-size: 12px;
  }

  .stat-grid {
    grid-template-columns: 1fr;
  }

  .stat-card {
    min-height: 110px;
  }

  .monitoring-grid {
    grid-template-columns: 1fr;
  }

  .panel {
    padding: 20px;
  }

  .guard-list {
    margin: 0 -20px -20px;
  }

  .map-wrap {
    height: 360px;
  }

  .system-status {
    font-size: 11px;
  }

  .refresh-button {
    padding: 9px 12px;

    font-size: 10px;
  }
}
</style>
