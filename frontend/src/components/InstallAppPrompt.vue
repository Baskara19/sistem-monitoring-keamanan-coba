<script setup>
import { computed, ref } from "vue";
import { isIos, usePwaInstall } from "@/composables/usePwaInstall";

const DISMISS_KEY = "pwa_install_dismissed_at";
const DISMISS_DAYS = 7;

const { state, promptInstall } = usePwaInstall();

const wasDismissedRecently = () => {
  const dismissedAt = Number(localStorage.getItem(DISMISS_KEY) || 0);

  if (!dismissedAt) {
    return false;
  }

  const elapsedDays = (Date.now() - dismissedAt) / (1000 * 60 * 60 * 24);

  return elapsedDays < DISMISS_DAYS;
};

const dismissed = ref(wasDismissedRecently());
const showIosHelp = ref(false);

const visible = computed(() => {
  if (state.isInstalled || dismissed.value) {
    return false;
  }

  return state.isInstallable || isIos();
});

const dismiss = () => {
  localStorage.setItem(DISMISS_KEY, String(Date.now()));
  dismissed.value = true;
  showIosHelp.value = false;
};

const handleInstallClick = async () => {
  if (isIos()) {
    showIosHelp.value = true;
    return;
  }

  await promptInstall();
};
</script>

<template>
  <div v-if="visible" class="install-prompt">
    <div class="install-card">
      <button class="close-btn" type="button" aria-label="Tutup" @click="dismiss">
        &times;
      </button>

      <div class="install-icon">
        <img src="/icons/icon-192.png" alt="KAI Security" />
      </div>

      <div class="install-text">
        <h3>Install Aplikasi KAI Security</h3>
        <p v-if="!showIosHelp">
          Pasang aplikasi ini di HP Anda supaya lebih cepat diakses saat patroli, tanpa perlu buka browser lagi.
        </p>
        <p v-else>
          Di Safari: ketuk tombol <strong>Share</strong>
          <span aria-hidden="true">⤴</span>, lalu pilih
          <strong>Add to Home Screen</strong>.
        </p>
      </div>

      <div class="install-actions">
        <button v-if="!showIosHelp" class="btn-secondary" type="button" @click="dismiss">
          Nanti Saja
        </button>
        <button v-if="!showIosHelp" class="btn-primary" type="button" @click="handleInstallClick">
          Install
        </button>
        <button v-else class="btn-primary" type="button" @click="dismiss">
          Mengerti
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.install-prompt {
  position: fixed;
  left: 16px;
  right: 16px;
  bottom: 16px;
  z-index: 1000;
  display: flex;
  justify-content: center;
}

.install-card {
  position: relative;
  width: 100%;
  max-width: 420px;
  background: #ffffff;
  border-radius: 16px;
  padding: 18px 18px 16px;
  box-shadow: 0 12px 32px rgba(31, 36, 84, 0.25);
  display: flex;
  flex-direction: column;
  gap: 12px;
  animation: slide-up 0.25s ease-out;
}

@keyframes slide-up {
  from {
    transform: translateY(16px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.close-btn {
  position: absolute;
  top: 8px;
  right: 10px;
  border: none;
  background: transparent;
  font-size: 18px;
  line-height: 1;
  color: #9a9dab;
  cursor: pointer;
}

.install-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  overflow: hidden;
  flex-shrink: 0;
}

.install-icon img {
  width: 100%;
  height: 100%;
  display: block;
}

.install-text h3 {
  margin: 0 0 4px;
  font-size: 15px;
  color: #1f2454;
}

.install-text p {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  color: #6b6e7d;
}

.install-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 4px;
}

.btn-secondary,
.btn-primary {
  border: none;
  border-radius: 8px;
  padding: 9px 16px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-secondary {
  background: #f2f3f6;
  color: #6b6e7d;
}

.btn-primary {
  background: linear-gradient(135deg, #e87500, #f08b1a);
  color: #ffffff;
  box-shadow: 0 6px 14px rgba(232, 117, 0, 0.3);
}
</style>
