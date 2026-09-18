<template>
  <transition name="slide-up">
    <div v-if="visible" class="install-banner">
      <div class="install-icon">
        <img src="/icons/icon-192.png" alt="Ikon Aplikasi" />
      </div>

      <div class="install-text">
        <strong>Instal Aplikasi Ini</strong>
        <p v-if="isIos">
          Tap tombol Share <span class="ios-share-icon">⎋</span> lalu pilih
          "Add to Home Screen" biar bisa dibuka seperti aplikasi.
        </p>
        <p v-else>
          Biar lebih gampang diakses, instal aplikasi ini di HP kamu seperti
          aplikasi biasa.
        </p>
      </div>

      <div class="install-actions">
        <button v-if="!isIos" type="button" class="btn-install" @click="promptInstall">
          Instal
        </button>
        <button type="button" class="btn-dismiss" @click="dismiss">
          Nanti Saja
        </button>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref } from "vue";

const DISMISS_KEY = "pwa_install_dismissed";

const visible = ref(false);
const isIos = ref(false);
let deferredPrompt = null;

const isStandaloneMode = () => {
  return (
    window.matchMedia?.("(display-mode: standalone)").matches ||
    window.navigator.standalone === true
  );
};

const isSatpam = () => {
  try {
    const user = JSON.parse(localStorage.getItem("user") || "null");
    return user?.role === "satpam";
  } catch {
    return false;
  }
};

const handleBeforeInstallPrompt = (event) => {
  event.preventDefault();
  deferredPrompt = event;

  if (isSatpam() && !localStorage.getItem(DISMISS_KEY) && !isStandaloneMode()) {
    visible.value = true;
  }
};

const handleAppInstalled = () => {
  visible.value = false;
  deferredPrompt = null;
};

const promptInstall = async () => {
  if (!deferredPrompt) {
    visible.value = false;
    return;
  }

  deferredPrompt.prompt();
  await deferredPrompt.userChoice;
  deferredPrompt = null;
  visible.value = false;
};

const dismiss = () => {
  visible.value = false;
  localStorage.setItem(DISMISS_KEY, "1");
};

onMounted(() => {
  if (!isSatpam() || isStandaloneMode() || localStorage.getItem(DISMISS_KEY)) {
    return;
  }

  const ua = window.navigator.userAgent;
  const iosDevice = /iPad|iPhone|iPod/.test(ua);
  const isSafari = /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS/.test(ua);

  if (iosDevice && isSafari) {
    isIos.value = true;
    visible.value = true;
  }

  window.addEventListener("beforeinstallprompt", handleBeforeInstallPrompt);
  window.addEventListener("appinstalled", handleAppInstalled);
});

onBeforeUnmount(() => {
  window.removeEventListener("beforeinstallprompt", handleBeforeInstallPrompt);
  window.removeEventListener("appinstalled", handleAppInstalled);
});
</script>

<style scoped>
.install-banner {
  position: fixed;
  z-index: 50;
  bottom: 92px;
  left: 50%;
  transform: translateX(-50%);
  width: calc(100% - 32px);
  max-width: 420px;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 12px 30px rgba(31, 36, 84, 0.25);
  padding: 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-family: "Segoe UI", Arial, sans-serif;
}

.install-icon {
  flex-shrink: 0;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  overflow: hidden;
}

.install-icon img {
  width: 100%;
  height: 100%;
  display: block;
}

.install-text {
  flex: 1;
  min-width: 0;
}

.install-text strong {
  display: block;
  font-size: 13px;
  color: #1f2454;
  margin-bottom: 2px;
}

.install-text p {
  margin: 0;
  font-size: 11px;
  color: #6b6f80;
  line-height: 1.4;
}

.ios-share-icon {
  font-weight: 700;
}

.install-actions {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.btn-install {
  border: none;
  border-radius: 8px;
  padding: 7px 14px;
  background: #e87500;
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  white-space: nowrap;
}

.btn-dismiss {
  border: none;
  background: transparent;
  color: #9a9dab;
  font-size: 10px;
  font-family: inherit;
  cursor: pointer;
  white-space: nowrap;
}

.slide-up-enter-active,
.slide-up-leave-active {
  transition: all 0.25s ease;
}

.slide-up-enter-from,
.slide-up-leave-to {
  opacity: 0;
  transform: translate(-50%, 20px);
}

@media (min-width: 700px) {
  .install-banner {
    max-width: 460px;
  }
}
</style>
