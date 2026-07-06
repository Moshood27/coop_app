<template>
  <div class="min-h-screen auth-bg relative flex items-center justify-center p-4 overflow-hidden">
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
      <div class="absolute -top-24 -right-20 w-72 h-72 bg-gradient-to-br from-emerald-400/25 to-sky-400/25 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-28 -left-16 w-80 h-80 bg-gradient-to-tr from-emerald-300/20 to-indigo-300/20 rounded-full blur-3xl"></div>
    </div>
    <div class="w-full max-w-md relative">
      <div aria-hidden="true" class="pointer-events-none absolute -inset-1 bg-gradient-to-r from-emerald-500/20 to-teal-500/20 rounded-[2.5rem] blur-2xl opacity-50"></div>

      <div class="card card-elevated relative overflow-hidden p-8 sm:p-10 bg-white/90 backdrop-blur-2xl border border-white/80 shadow-2xl rounded-[2rem]">
        <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-emerald-400 via-teal-500 to-emerald-400 opacity-80"></div>

        <div class="flex flex-col items-center text-center mb-8">
          <div class="mb-4 transform hover:scale-105 transition-transform duration-300">
            <img :src="brand.logo" :alt="brand.name" class="h-16 w-auto drop-shadow-sm" />
          </div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">Enter PIN</h1>
          <p class="text-slate-500 text-sm mt-2 font-medium">Please enter your 4-digit transaction PIN to continue</p>
        </div>

        <div class="space-y-6">
          <div class="flex justify-center gap-3">
            <template v-for="(digit, index) in 4" :key="index">
              <input
                ref="pinInputs"
                v-model="pinDigits[index]"
                type="password"
                inputmode="numeric"
                maxlength="1"
                class="w-14 h-16 text-center text-2xl font-bold bg-slate-50 border-2 border-slate-100 rounded-2xl focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all"
                @input="handleInput($event, index)"
                @keydown.delete="handleDelete($event, index)"
                @paste="handlePaste"
              />
            </template>
          </div>

          <button
            @click="verifyPin"
            :disabled="loading || !isPinComplete"
            class="w-full h-14 text-lg rounded-2xl font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 focus:outline-none focus:ring-4 focus:ring-emerald-500/30 shadow-lg shadow-emerald-500/20 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="loading" class="inline-block animate-spin border-3 border-white/30 border-t-white rounded-full w-6 h-6 mr-2 align-middle"></span>
            <span>{{ loading ? 'Verifying...' : 'Unlock App' }}</span>
          </button>

          <p v-if="error" class="text-center p-3 bg-rose-50 rounded-xl text-rose-600 text-sm font-medium">{{ error }}</p>

          <button
            @click="logout"
            class="w-full py-2 text-slate-400 hover:text-rose-500 font-bold transition-colors text-sm"
          >
            Switch Account / Logout
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from '../http'
import brand from '../brand'
import { useAppStatusStore } from '../stores/appStatus'

const router = useRouter()
const route = useRoute()
const appStatusStore = useAppStatusStore()

const pinDigits = ref(['', '', '', ''])
const pinInputs = ref([])
const loading = ref(false)
const error = ref('')

const isPinComplete = computed(() => pinDigits.value.every(d => d !== ''))

onMounted(async () => {
  // Check if user has a PIN
  try {
    const { data } = await axios.get('/api/security/pin/status')
    if (!data.has_pin) {
      // If user hasn't set a PIN, they can't unlock, so just let them in (they will be prompted to set it on dashboard)
      appStatusStore.isPinVerified = true
      const redirect = route.query.redirect || '/dashboard'
      router.push(redirect)
      return
    }
  } catch (err) {
    console.error('Failed to check PIN status', err)
  }

  nextTick(() => {
    if (pinInputs.value[0]) pinInputs.value[0].focus()
  })
})

const handleInput = (event, index) => {
  const val = event.target.value
  if (val && index < 3) {
    pinInputs.value[index + 1].focus()
  }
}

const handleDelete = (event, index) => {
  if (!pinDigits.value[index] && index > 0) {
    pinDigits.value[index - 1] = ''
    pinInputs.value[index - 1].focus()
  }
}

const handlePaste = (event) => {
  const pasteData = event.clipboardData.getData('text').slice(0, 4)
  if (/^\d+$/.test(pasteData)) {
    for (let i = 0; i < pasteData.length; i++) {
      pinDigits.value[i] = pasteData[i]
    }
    if (pasteData.length === 4) {
      verifyPin()
    } else {
      pinInputs.value[pasteData.length].focus()
    }
  }
}

const verifyPin = async () => {
  if (!isPinComplete.value) return
  loading.value = true
  error.value = ''
  
  const pin = pinDigits.value.join('')
  
  try {
    await axios.post('/api/security/pin/verify', { pin })
    appStatusStore.isPinVerified = true
    const redirect = route.query.redirect || '/dashboard'
    router.push(redirect)
  } catch (err) {
    error.value = err?.response?.data?.message || 'Invalid PIN'
    pinDigits.value = ['', '', '', '']
    pinInputs.value[0].focus()
  } finally {
    loading.value = false
  }
}

const logout = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('user_id')
  localStorage.removeItem('is_admin')
  router.push('/login')
}
</script>

<style scoped>
.auth-bg {
  background-color: #f8fafc;
}
</style>
