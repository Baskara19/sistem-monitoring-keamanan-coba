<script setup>
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import InstallAppPrompt from "@/components/InstallAppPrompt.vue";

const route = useRoute();
const router = useRouter();
const mobileNavOpen = ref(false);

const navItems = computed(() => {
  if (route.path.startsWith("/admin")) {
    return [
      { label: "Dashboard", icon: "⌂", to: "/admin/dashboard" },
      { label: "Pengguna", icon: "♟", to: "/admin/users" },
      { label: "Titik Patroli", icon: "⌖", to: "/admin/patrol-points" },
      { label: "Aktivitas", icon: "▤", to: "/admin/activities" },
    ];
  }

  if (route.path.startsWith("/supervisor")) {
    return [
      { label: "Dashboard", icon: "⌂", to: "/supervisor/dashboard" },
      { label: "Jadwal", icon: "🗓", to: "/supervisor/schedules" },
      { label: "Monitoring", icon: "◉", to: "/supervisor/monitoring" },
      { label: "Laporan", icon: "▤", to: "/supervisor/reports" },
    ];
  }

  return [];
});

const isManagementRoute = computed(() => navItems.value.length > 0);

function closeMobileNav() {
  mobileNavOpen.value = false;
}

function logout() {
  localStorage.removeItem("token");
  localStorage.removeItem("user");
  closeMobileNav();
  router.push("/login");
}
</script>

<template>
  <RouterView />
  <InstallAppPrompt />

  <div v-if="isManagementRoute" class="mobile-navigation">
    <button
      class="mobile-navigation-toggle"
      type="button"
      aria-label="Buka navigasi"
      :aria-expanded="mobileNavOpen"
      @click="mobileNavOpen = !mobileNavOpen"
    >
      <span v-if="!mobileNavOpen">☰</span>
      <span v-else>×</span>
    </button>

    <div v-if="mobileNavOpen" class="mobile-navigation-backdrop" @click="closeMobileNav">
      <aside class="mobile-navigation-drawer" @click.stop>
        <div class="mobile-navigation-header">
          <div>
            <strong>KAI SECURITY</strong>
            <span>MONITORING SYSTEM</span>
          </div>
          <button type="button" aria-label="Tutup navigasi" @click="closeMobileNav">×</button>
        </div>

        <nav class="mobile-navigation-links" aria-label="Navigasi utama">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="mobile-navigation-link"
            @click="closeMobileNav"
          >
            <span class="mobile-navigation-icon">{{ item.icon }}</span>
            <span>{{ item.label }}</span>
          </RouterLink>

          <button
            type="button"
            class="mobile-navigation-link mobile-navigation-logout"
            @click="logout"
          >
            <span class="mobile-navigation-icon">⏻</span>
            <span>Log Out</span>
          </button>
        </nav>
      </aside>
    </div>
  </div>
</template>

<style>
/* Shared responsive safeguards for all admin and supervisor screens. */
*,
*::before,
*::after {
  box-sizing: border-box;
}

html,
body,
#app {
  min-width: 0;
  max-width: 100%;
  margin: 0;
}

img,
svg,
video,
canvas {
  max-width: 100%;
}

.mobile-navigation {
  display: none;
}

@media (max-width: 768px) {
  .admin-layout,
  .supervisor-layout {
    width: 100%;
    max-width: 100vw;
    min-width: 0;
    overflow-x: hidden;
  }

  .admin-layout > .sidebar,
  .supervisor-layout > .sidebar {
    display: none !important;
  }

  .admin-layout .main-content,
  .supervisor-layout .main-content {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    margin-left: 0 !important;
  }

  .admin-layout .topbar,
  .supervisor-layout .topbar {
    width: 100%;
    min-width: 0;
    padding: 16px 20px;
    gap: 12px;
  }

  .admin-layout .topbar > *,
  .supervisor-layout .topbar > *,
  .admin-layout .topbar-left,
  .supervisor-layout .topbar-left,
  .admin-layout .topbar-right,
  .supervisor-layout .topbar-right {
    min-width: 0;
  }

  .admin-layout .topbar-right,
  .supervisor-layout .topbar-right {
    gap: 8px;
  }

  .admin-layout .dashboard-content,
  .admin-layout .content,
  .admin-layout .page-content,
  .supervisor-layout .dashboard-content,
  .supervisor-layout .content,
  .supervisor-layout .page-content {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    padding: 24px 16px 40px;
  }

  .admin-layout .welcome-section,
  .admin-layout .content-header,
  .admin-layout .page-intro,
  .supervisor-layout .welcome-section,
  .supervisor-layout .content-header,
  .supervisor-layout .page-header,
  .supervisor-layout .detail-heading {
    min-width: 0;
    flex-wrap: wrap;
    gap: 14px;
  }

  .admin-layout .stats-grid,
  .supervisor-layout .stats-grid,
  .supervisor-layout .stat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .admin-layout .dashboard-grid,
  .supervisor-layout .dashboard-grid,
  .supervisor-layout .detail-layout,
  .supervisor-layout .monitoring-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .admin-layout .panel,
  .admin-layout .table-card,
  .admin-layout .activity-card,
  .supervisor-layout .panel,
  .supervisor-layout .table-panel,
  .supervisor-layout .detail-card,
  .supervisor-layout .timeline-card {
    min-width: 0;
    max-width: 100%;
  }

  .admin-layout .table-card,
  .admin-layout .activity-table-wrapper,
  .supervisor-layout .table-wrapper,
  .supervisor-layout .table-panel,
  .supervisor-layout .table-scroll {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch;
  }

  .admin-layout table,
  .supervisor-layout table {
    min-width: 680px;
  }

  .admin-layout input,
  .admin-layout select,
  .admin-layout textarea,
  .supervisor-layout input,
  .supervisor-layout select,
  .supervisor-layout textarea {
    max-width: 100%;
  }

  .admin-layout .modal,
  .supervisor-layout .modal {
    width: min(520px, calc(100vw - 24px));
    max-width: calc(100vw - 24px);
  }

  .mobile-navigation {
    display: block;
  }

  .mobile-navigation-toggle {
    position: fixed;
    top: 14px;
    left: 14px;
    z-index: 120;
    width: 42px;
    height: 42px;
    border: 0;
    border-radius: 12px;
    color: #ffffff;
    background: #1f2454;
    box-shadow: 0 6px 18px rgba(31, 36, 84, 0.22);
    cursor: pointer;
    font-size: 24px;
    line-height: 1;
  }

  .mobile-navigation-backdrop {
    position: fixed;
    inset: 0;
    z-index: 110;
    background: rgba(15, 18, 47, 0.42);
  }

  .mobile-navigation-drawer {
    width: min(82vw, 300px);
    height: 100%;
    padding: 22px 16px;
    color: #ffffff;
    background: linear-gradient(180deg, #1f2454 0%, #181c43 100%);
    box-shadow: 8px 0 28px rgba(15, 18, 47, 0.2);
  }

  .mobile-navigation-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 8px 24px;
  }

  .mobile-navigation-header strong,
  .mobile-navigation-header span {
    display: block;
  }

  .mobile-navigation-header strong {
    font-size: 16px;
    letter-spacing: 0.5px;
  }

  .mobile-navigation-header span {
    margin-top: 4px;
    color: rgba(255, 255, 255, 0.65);
    font-size: 10px;
    letter-spacing: 0.8px;
  }

  .mobile-navigation-header button {
    border: 0;
    color: #ffffff;
    background: transparent;
    cursor: pointer;
    font-size: 28px;
    line-height: 1;
  }

  .mobile-navigation-links {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .mobile-navigation-link {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 48px;
    padding: 10px 12px;
    border-radius: 10px;
    color: rgba(255, 255, 255, 0.76);
    font-size: 13px;
    text-decoration: none;
  }

  .mobile-navigation-link.router-link-active {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.13);
  }

  .mobile-navigation-logout {
    width: 100%;
    margin-top: 12px;
    padding-top: 18px;
    border: 0;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 10px 10px 0 0;
    background: transparent;
    color: #ff8686;
    font-size: 13px;
    font-family: inherit;
    text-align: left;
    cursor: pointer;
  }

  .mobile-navigation-icon {
    width: 24px;
    text-align: center;
    font-size: 19px;
  }
}

@media (max-width: 480px) {
  .admin-layout .stats-grid,
  .supervisor-layout .stats-grid,
  .supervisor-layout .stat-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .admin-layout .topbar,
  .supervisor-layout .topbar {
    padding: 14px 16px;
  }

  .admin-layout .dashboard-content,
  .admin-layout .content,
  .admin-layout .page-content,
  .supervisor-layout .dashboard-content,
  .supervisor-layout .content,
  .supervisor-layout .page-content {
    padding-right: 14px;
    padding-left: 14px;
  }

  .admin-layout .profile-info,
  .supervisor-layout .profile-info,
  .supervisor-layout .profile-text,
  .supervisor-layout .date-info {
    display: none;
  }
}
</style>
