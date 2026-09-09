import { reactive } from "vue";

// Modul-level state: listener beforeinstallprompt harus dipasang sekali di
// awal (lihat main.js) sebelum browser menembakkan event-nya, jadi state
// ini dibuat global lalu dibaca ulang oleh komponen manapun yang butuh.
const state = reactive({
  deferredEvent: null,
  isInstallable: false,
  isInstalled: isStandalone(),
});

function isStandalone() {
  return (
    window.matchMedia?.("(display-mode: standalone)").matches ||
    window.navigator.standalone === true
  );
}

export function isIos() {
  return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
}

export function initPwaInstallListeners() {
  window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    state.deferredEvent = event;
    state.isInstallable = true;
  });

  window.addEventListener("appinstalled", () => {
    state.deferredEvent = null;
    state.isInstallable = false;
    state.isInstalled = true;
  });
}

export function usePwaInstall() {
  const promptInstall = async () => {
    if (!state.deferredEvent) {
      return null;
    }

    state.deferredEvent.prompt();
    const choice = await state.deferredEvent.userChoice;

    state.deferredEvent = null;
    state.isInstallable = false;

    return choice;
  };

  return {
    state,
    promptInstall,
  };
}
