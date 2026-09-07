<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'

const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)
const showPassword = ref(false)

const login = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await axios.post(
      'http://127.0.0.1:8000/api/login',
      {
        email: email.value,
        password: password.value
      }
    )

    // Simpan token
    localStorage.setItem('token', response.data.token)
    localStorage.setItem(
      'user',
      JSON.stringify(response.data.user)
    )

    // Redirect berdasarkan role
    const role = response.data.user.role

    if (role === 'admin') {
      router.push('/admin/dashboard')
    } else if (role === 'supervisor') {
      router.push('/supervisor/dashboard')
    } else if (role === 'satpam') {
      router.push('/satpam/dashboard')
    }

  } catch (err) {
    error.value =
      err.response?.data?.message ||
      'Email atau password tidak valid.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">

    <!-- Background Decoration -->
    <div class="bg-decoration decoration-1"></div>
    <div class="bg-decoration decoration-2"></div>

    <div class="login-wrapper">

      <!-- LEFT SIDE -->
      <div class="login-info">

        <div class="brand">
          <div class="brand-logo">
            KAI
          </div>

          <div class="brand-text">
            <h2>KAI SECURITY</h2>
            <span>MONITORING SYSTEM</span>
          </div>
        </div>

        <div class="info-content">
          

          <h1>
            Sistem Monitoring
            <span>Keamanan Kantor</span>
          </h1>

          <p>
            Platform terintegrasi untuk melakukan monitoring,
            pelaporan, dan pengelolaan keamanan di lingkungan
            PT Kereta Api Indonesia.
          </p>

          <div class="feature-list">

            <div class="feature-item">
              <div class="feature-icon">
                🛡
              </div>

              <div>
                <h4>Monitoring Keamanan</h4>
                <span>Pantau kondisi keamanan secara terpusat</span>
              </div>
            </div>

            <div class="feature-item">
              <div class="feature-icon">
                ⚠
              </div>

              <div>
                <h4>Laporan Insiden</h4>
                <span>Pencatatan dan penanganan kejadian</span>
              </div>
            </div>

            <div class="feature-item">
              <div class="feature-icon">
                ✓
              </div>

              <div>
                <h4>Manajemen Petugas</h4>
                <span>Pengelolaan aktivitas petugas keamanan</span>
              </div>
            </div>

          </div>
        </div>

        <div class="info-footer">
          © {{ new Date().getFullYear() }}
          PT Kereta Api Indonesia (Persero)
        </div>

      </div>


      <!-- RIGHT SIDE LOGIN -->
      <div class="login-section">

        <div class="login-card">

          <div class="login-header">

            <div class="mobile-logo">
              KAI
            </div>

            <h2>Selamat Datang</h2>

            <p>
              Silakan masuk untuk mengakses sistem monitoring keamanan.
            </p>

          </div>


          <!-- ERROR -->
          <div v-if="error" class="error-message">
            <span>⚠</span>
            {{ error }}
          </div>


          <!-- FORM -->
          <form @submit.prevent="login">

            <!-- EMAIL -->
            <div class="form-group">

              <label>Email</label>

              <div class="input-wrapper">

                <span class="input-icon">
                  ✉
                </span>

                <input
                  v-model="email"
                  type="email"
                  placeholder="Masukkan email"
                  required
                />

              </div>

            </div>


            <!-- PASSWORD -->
            <div class="form-group">

              <label>Password</label>

              <div class="input-wrapper">

                <span class="input-icon">
                  🔒
                </span>

                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Masukkan password"
                  required
                />

                <button
                  type="button"
                  class="toggle-password"
                  @click="showPassword = !showPassword"
                >
                  {{ showPassword ? 'Hide' : 'Show' }}
                </button>

              </div>

            </div>


            <!-- LOGIN BUTTON -->
            <button
              type="submit"
              class="login-button"
              :disabled="loading"
            >

              <span v-if="!loading">
                Masuk ke Sistem
              </span>

              <span v-else class="loading-content">
                <span class="spinner"></span>
                Memproses...
              </span>

            </button>

          </form>


          <div class="login-security">

            <span>🔒</span>

            <p>
              Sistem ini dilindungi dan hanya dapat diakses
              oleh pengguna yang memiliki otorisasi.
            </p>

          </div>

        </div>

      </div>

    </div>

  </div>
</template>


<style scoped>

/* ================================
   GLOBAL PAGE
================================ */

.login-page {
  min-height: 100vh;
  background:
    linear-gradient(
      135deg,
      #f4f5f7 0%,
      #e9ebef 100%
    );

  display: flex;
  align-items: center;
  justify-content: center;

  position: relative;
  overflow: hidden;

  font-family:
    "Segoe UI",
    Arial,
    sans-serif;
}


/* ================================
   BACKGROUND DECORATION
================================ */

.bg-decoration {
  position: absolute;
  border-radius: 50%;
  opacity: 0.08;
}

.decoration-1 {
  width: 500px;
  height: 500px;

  background: #1f2454;

  top: -250px;
  right: -150px;
}

.decoration-2 {
  width: 400px;
  height: 400px;

  background: #e87500;

  bottom: -200px;
  left: -150px;
}


/* ================================
   MAIN WRAPPER
================================ */

.login-wrapper {
  width: 1100px;
  min-height: 650px;

  display: grid;
  grid-template-columns: 1.1fr 0.9fr;

  background: #ffffff;

  border-radius: 24px;

  overflow: hidden;

  position: relative;
  z-index: 2;

  box-shadow:
    0 25px 60px rgba(31, 36, 84, 0.15);
}


/* ================================
   LEFT PANEL
================================ */

.login-info {
  background:
    linear-gradient(
      135deg,
      #1f2454 0%,
      #292f6b 100%
    );

  color: white;

  padding: 55px;

  display: flex;
  flex-direction: column;

  position: relative;

  overflow: hidden;
}

.login-info::after {
  content: "";

  position: absolute;

  width: 350px;
  height: 350px;

  border-radius: 50%;

  border: 50px solid rgba(255,255,255,0.04);

  right: -180px;
  bottom: -180px;
}


/* ================================
   BRAND
================================ */

.brand {
  display: flex;
  align-items: center;
  gap: 14px;

  position: relative;
  z-index: 2;
}

.brand-logo {
  font-size: 32px;
  font-weight: 900;
  font-style: italic;

  color: #ffffff;

  letter-spacing: -2px;
}

.brand-text {
  display: flex;
  flex-direction: column;
}

.brand-text h2 {
  margin: 0;

  font-size: 16px;

  letter-spacing: 1px;
}

.brand-text span {
  font-size: 10px;

  color: #c8cad9;

  letter-spacing: 2px;
}


/* ================================
   INFO CONTENT
================================ */

.info-content {
  margin-top: 80px;

  position: relative;
  z-index: 2;
}

.security-badge {
  display: inline-flex;

  align-items: center;
  gap: 8px;

  background: rgba(255,255,255,0.1);

  padding: 8px 14px;

  border-radius: 30px;

  font-size: 11px;

  font-weight: 700;

  letter-spacing: 1px;
}

.status-dot {
  width: 8px;
  height: 8px;

  border-radius: 50%;

  background: #e87500;

  box-shadow:
    0 0 10px #e87500;
}

.info-content h1 {
  margin-top: 24px;
  margin-bottom: 18px;

  font-size: 42px;

  line-height: 1.2;
}

.info-content h1 span {
  display: block;

  color: #e87500;
}

.info-content > p {
  color: #c8cad9;

  line-height: 1.8;

  max-width: 480px;

  font-size: 14px;
}


/* ================================
   FEATURES
================================ */

.feature-list {
  margin-top: 35px;

  display: flex;
  flex-direction: column;

  gap: 18px;
}

.feature-item {
  display: flex;

  align-items: center;

  gap: 14px;
}

.feature-icon {
  width: 42px;
  height: 42px;

  display: flex;

  align-items: center;
  justify-content: center;

  background: rgba(255,255,255,0.1);

  border-radius: 12px;

  color: #e87500;

  font-size: 18px;
}

.feature-item h4 {
  margin: 0 0 4px;

  font-size: 14px;
}

.feature-item span {
  font-size: 12px;

  color: #bfc2d5;
}


/* ================================
   INFO FOOTER
================================ */

.info-footer {
  margin-top: auto;

  padding-top: 40px;

  color: #9ea2bd;

  font-size: 11px;

  position: relative;

  z-index: 2;
}


/* ================================
   RIGHT LOGIN SECTION
================================ */

.login-section {
  display: flex;

  align-items: center;
  justify-content: center;

  padding: 40px;
}

.login-card {
  width: 100%;

  max-width: 370px;
}


/* ================================
   LOGIN HEADER
================================ */

.mobile-logo {
  display: none;
}

.login-header h2 {
  color: #1f2454;

  font-size: 30px;

  margin-bottom: 10px;
}

.login-header p {
  color: #8a8d9c;

  font-size: 14px;

  line-height: 1.6;

  margin-bottom: 35px;
}


/* ================================
   FORM
================================ */

.form-group {
  margin-bottom: 22px;
}

.form-group label {
  display: block;

  color: #1f2454;

  font-size: 13px;

  font-weight: 600;

  margin-bottom: 8px;
}

.input-wrapper {
  position: relative;
}

.input-icon {
  position: absolute;

  left: 14px;
  top: 50%;

  transform: translateY(-50%);

  font-size: 16px;
}

.input-wrapper input {
  width: 100%;

  height: 52px;

  box-sizing: border-box;

  border:
    1px solid #e2e4ea;

  border-radius: 10px;

  padding:
    0 75px 0 45px;

  font-size: 14px;

  outline: none;

  transition: 0.25s;
}

.input-wrapper input:focus {
  border-color: #e87500;

  box-shadow:
    0 0 0 3px rgba(232,117,0,0.1);
}

.toggle-password {
  position: absolute;

  right: 10px;
  top: 50%;

  transform: translateY(-50%);

  border: none;

  background: transparent;

  color: #e87500;

  font-size: 11px;

  font-weight: 700;

  cursor: pointer;
}


/* ================================
   LOGIN BUTTON
================================ */

.login-button {
  width: 100%;

  height: 52px;

  margin-top: 8px;

  border: none;

  border-radius: 10px;

  background:
    linear-gradient(
      135deg,
      #e87500,
      #f08b1a
    );

  color: white;

  font-size: 14px;

  font-weight: 700;

  cursor: pointer;

  transition: 0.25s;

  box-shadow:
    0 8px 20px rgba(232,117,0,0.25);
}

.login-button:hover:not(:disabled) {
  transform: translateY(-2px);

  box-shadow:
    0 12px 25px rgba(232,117,0,0.35);
}

.login-button:disabled {
  opacity: 0.7;

  cursor: not-allowed;
}


/* ================================
   LOADING
================================ */

.loading-content {
  display: flex;

  align-items: center;
  justify-content: center;

  gap: 10px;
}

.spinner {
  width: 16px;
  height: 16px;

  border-radius: 50%;

  border: 2px solid rgba(255,255,255,0.4);

  border-top-color: white;

  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}


/* ================================
   ERROR MESSAGE
================================ */

.error-message {
  display: flex;

  align-items: center;

  gap: 8px;

  margin-bottom: 20px;

  padding: 12px 14px;

  background: #fff1f1;

  color: #d63031;

  border-left:
    4px solid #d63031;

  border-radius: 8px;

  font-size: 12px;
}


/* ================================
   SECURITY NOTICE
================================ */

.login-security {
  display: flex;

  gap: 10px;

  margin-top: 28px;

  padding-top: 20px;

  border-top:
    1px solid #eceef2;
}

.login-security span {
  font-size: 15px;
}

.login-security p {
  margin: 0;

  color: #9a9dab;

  font-size: 11px;

  line-height: 1.6;
}


/* ================================
   RESPONSIVE
================================ */

@media (max-width: 900px) {

  .login-wrapper {
    width: 90%;

    grid-template-columns: 1fr;
  }

  .login-info {
    display: none;
  }

  .login-section {
    min-height: 650px;
  }

  .mobile-logo {
    display: block;

    font-size: 32px;

    font-weight: 900;

    font-style: italic;

    color: #1f2454;

    margin-bottom: 30px;
  }

}


@media (max-width: 480px) {

  .login-wrapper {
    width: 100%;

    min-height: 100vh;

    border-radius: 0;
  }

  .login-section {
    padding: 30px 24px;
  }

  .login-header h2 {
    font-size: 26px;
  }

}
</style>
