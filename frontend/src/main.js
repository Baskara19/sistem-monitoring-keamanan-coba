import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { initPwaInstallListeners } from './composables/usePwaInstall'

initPwaInstallListeners()

const app = createApp(App)

app.use(router)

app.mount('#app')
