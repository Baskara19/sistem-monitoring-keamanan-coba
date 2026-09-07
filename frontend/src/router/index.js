import { createRouter, createWebHistory } from "vue-router";

import LoginView from "../views/LoginView.vue";

// Admin
import AdminDashboardView from "../views/admin/AdminDashboardView.vue";
import UsersView from "../views/admin/UsersView.vue";
import PatrolPointsView from "../views/admin/PatrolPointsView.vue";
import AddPatrolPointView from "../views/admin/AddPatrolPointView.vue";
import EditPatrolPointView from "../views/admin/EditPatrolPointView.vue";
import ActivitiesView from "../views/admin/ActivitiesView.vue";
import RoutesView from "../views/admin/RoutesView.vue";

// Satpam
import SatpamLandingView from "../views/satpam/SatpamLandingView.vue";
import ScanQRView from "../views/satpam/ScanQRView.vue";
import CreateReportView from "../views/satpam/CreateReportView.vue";
import SatpamScheduleView from "../views/satpam/SatpamScheduleView.vue";
import SatpamHistoryView from "../views/satpam/SatpamHistoryView.vue";

// Supervisor
import SupervisorDashboardView from "../views/supervisor/SupervisorDashboardView.vue";
import ScheduleManagementView from "../views/supervisor/ScheduleManagementView.vue";
import MonitoringView from "../views/supervisor/MonitoringView.vue";
import ReportsView from "../views/supervisor/ReportsView.vue";
import ReportDetailView from "../views/supervisor/ReportDetailView.vue";

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),

  routes: [
    {
      path: "/",
      redirect: "/login",
    },

    // Login
    {
      path: "/login",
      name: "login",
      component: LoginView,
    },

    // =========================
    // ADMIN
    // =========================

    {
      path: "/admin/dashboard",
      name: "admin-dashboard",
      component: AdminDashboardView,
      meta: {
        requiresAuth: true,
        role: "admin",
      },
    },

    {
      path: "/admin/users",
      name: "admin-users",
      component: UsersView,
      meta: {
        requiresAuth: true,
        role: "admin",
      },
    },

    {
      path: "/admin/patrol-points",
      name: "admin-patrol-points",
      component: PatrolPointsView,
      meta: {
        requiresAuth: true,
        role: "admin",
      },
    },

    {
      path: "/admin/patrol-points/create",
      name: "admin-patrol-points-create",
      component: AddPatrolPointView,
      meta: {
        requiresAuth: true,
        role: "admin",
      },
    },

    {
      path: "/admin/patrol-points/:id/edit",
      name: "admin-patrol-points-edit",
      component: EditPatrolPointView,
      meta: {
        requiresAuth: true,
        role: "admin",
      },
    },

    {
      path: "/admin/activities",
      name: "admin-activities",
      component: ActivitiesView,
      meta: {
        requiresAuth: true,
        role: "admin",
      },
    },

    {
      path: "/admin/routes",
      name: "admin-routes",
      component: RoutesView,
      meta: {
        requiresAuth: true,
        role: "admin",
      },
    },

    // =========================
    // SATPAM
    // =========================

    {
      path: "/satpam/dashboard",
      name: "satpam-dashboard",
      component: SatpamLandingView,
      meta: {
        requiresAuth: true,
        role: "satpam",
      },
    },

    {
      path: "/satpam/scan",
      name: "satpam-scan",
      component: ScanQRView,
      meta: {
        requiresAuth: true,
        role: "satpam",
      },
    },

    {
      path: "/satpam/report",
      name: "satpam-report",
      component: CreateReportView,
      meta: {
        requiresAuth: true,
        role: "satpam",
      },
    },
    {
      path: "/satpam/jadwal",
      name: "satpam-schedule",
      component: SatpamScheduleView,
      meta: {
        requiresAuth: true,
        role: "satpam",
      },
    },
    {
      path: "/satpam/riwayat",
      name: "satpam-history",
      component: SatpamHistoryView,
      meta: {
        requiresAuth: true,
        role: "satpam",
      },
    },

    // =========================
    // SUPERVISOR
    // =========================

    {
      path: "/supervisor/dashboard",
      name: "supervisor-dashboard",
      component: SupervisorDashboardView,
      meta: {
        requiresAuth: true,
        role: "supervisor",
      },
    },

    {
      path: "/supervisor/schedules",
      name: "supervisor-schedules",
      component: ScheduleManagementView,
      meta: {
        requiresAuth: true,
        role: "supervisor",
      },
    },

    {
      path: "/supervisor/monitoring",
      name: "supervisor-monitoring",
      component: MonitoringView,
      meta: {
        requiresAuth: true,
        role: "supervisor",
      },
    },
    {
      path: "/supervisor/reports",
      component: ReportsView,
    },
    {
      path: "/supervisor/reports/:id",
      component: ReportDetailView,
    },

    // =========================
    // FALLBACK
    // =========================

    {
      path: "/:pathMatch(.*)*",
      redirect: "/login",
    },
  ],
});

router.beforeEach((to) => {
  const token = localStorage.getItem("token");

  let user = null;

  try {
    const userData = localStorage.getItem("user");

    if (userData) {
      user = JSON.parse(userData);
    }
  } catch (error) {
    console.error("Data user tidak valid:", error);

    localStorage.removeItem("token");
    localStorage.removeItem("user");
  }

  // =========================
  // HALAMAN YANG MEMBUTUHKAN LOGIN
  // =========================

  if (to.meta.requiresAuth) {
    // Token atau data user tidak ada
    if (!token || !user) {
      return {
        name: "login",
      };
    }

    // Role tidak sesuai
    if (to.meta.role && user.role !== to.meta.role) {
      return {
        name: "login",
      };
    }
  }

  // =========================
  // JIKA SUDAH LOGIN
  // JANGAN KEMBALI KE LOGIN
  // =========================

  if (to.name === "login" && token && user) {
    if (user.role === "admin") {
      return {
        name: "admin-dashboard",
      };
    }

    if (user.role === "satpam") {
      return {
        name: "satpam-dashboard",
      };
    }
    if (user.role === "supervisor") {
      return {
        name: "supervisor-dashboard",
      };
    }
  }

  return true;
});

export default router;
