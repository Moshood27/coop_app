<template>
  <aside class="hidden md:flex flex-col w-64 h-screen sticky top-0 bg-white border-r border-slate-200 overflow-y-auto">
    <div class="p-6">
      <div class="flex items-center gap-3 mb-8">
        <img src="/images/attaqwa-favicon.svg" alt="Logo" class="w-8 h-8 rounded-xl" />
        <h1 class="text-xl font-black text-emerald-800 tracking-tight">Cooperative</h1>
      </div>

      <nav class="space-y-1">
        <button
          v-for="item in navItems"
          :key="item.path"
          @click="router.push(item.path)"
          class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl transition-all font-bold text-sm"
          :class="[isActive(item.path) ? 'bg-emerald-50 text-emerald-700 shadow-sm border border-emerald-100' : 'text-slate-500 hover:bg-slate-50']"
        >
          <span :class="[isActive(item.path) ? item.activeIcon : item.icon, 'text-xl']"></span>
          {{ item.label }}
        </button>
      </nav>

      <div v-if="serviceItems.length" class="mt-8 pt-8 border-t border-slate-100">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-4 mb-4">Services</p>
        <div class="grid grid-cols-1 gap-1">
          <button
            v-for="service in serviceItems"
            :key="service.path"
            @click="router.push(service.path)"
            class="flex items-center gap-3 px-4 py-3 rounded-2xl text-slate-500 hover:bg-slate-50 font-bold text-sm transition-all"
            :class="[isActive(service.path) ? 'bg-emerald-50 text-emerald-700' : '']"
          >
            <span :class="[service.icon, 'text-xl']"></span>
            {{ service.label }}
          </button>
        </div>
      </div>
    </div>

    <div class="mt-auto p-6 space-y-4">
      <button v-if="isAdmin" @click="router.push('/admin/vendors')" class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-emerald-700 bg-emerald-50 hover:bg-emerald-100 font-bold text-sm transition-all border border-emerald-100 shadow-sm">
        <span class="i-mdi-shield-account text-xl"></span>
        Admin Portal
      </button>

      <div v-if="user" class="bg-slate-50 rounded-3xl p-4 flex items-center gap-3 border border-slate-100">
        <div class="w-10 h-10 rounded-full overflow-hidden bg-emerald-100 shrink-0 border border-emerald-200">
          <img v-if="user.passport_url" :src="getImageUrl(user.passport_url)" alt="Profile" class="w-full h-full object-cover" />
          <span v-else class="i-mdi-account text-2xl text-emerald-600 flex items-center justify-center h-full"></span>
        </div>
        <div class="min-w-0">
          <p class="text-xs font-black text-slate-800 truncate uppercase">{{ user.full_name || 'Member' }}</p>
          <p class="text-[9px] text-slate-500 font-bold truncate">{{ user.membership_id }}</p>
        </div>
      </div>
      <button @click="logout" class="w-full mt-4 flex items-center justify-center gap-2 text-rose-600 font-bold text-xs py-3 rounded-xl hover:bg-rose-50 transition-colors">
        <span class="i-mdi-logout text-lg"></span>
        Logout
      </button>
    </div>
  </aside>
</template>

<script setup>
import { useRouter, useRoute } from 'vue-router'
import { useAppStatusStore } from '../stores/appStatus'
import { computed } from 'vue'
import getImageUrl from '../utils/image'
import axios from '../http'

const router = useRouter()
const route = useRoute()
const appStatusStore = useAppStatusStore()

const props = defineProps({
  user: Object
})

const isAdmin = computed(() => props.user?.is_admin || localStorage.getItem('is_admin') === 'true')

const navItems = computed(() => {
  const items = [
    { label: 'Home', path: '/dashboard', icon: 'i-mdi-home-outline', activeIcon: 'i-mdi-home' },
    { label: 'Wallet', path: '/wallet', icon: 'i-mdi-wallet-outline', activeIcon: 'i-mdi-wallet' },
    { label: 'Passbook', path: '/passbook', icon: 'i-mdi-book-open-variant', activeIcon: 'i-mdi-book-open-variant' },
    { label: 'Chat', path: '/chat', icon: 'i-mdi-chat-processing-outline', activeIcon: 'i-mdi-chat-processing' },
    { label: 'Profile', path: '/profile', icon: 'i-mdi-account-outline', activeIcon: 'i-mdi-account' },
  ]

  return items.filter(item => {
    if (item.path === '/chat') return appStatusStore.features['chat-help-enabled']
    return true
  })
})

const serviceItems = computed(() => {
  const allServices = [
    { label: 'Loans', path: '/loans', icon: 'i-mdi-hand-coin-outline', feature: null },
    { label: 'Store', path: '/store', icon: 'i-mdi-store-outline', feature: 'store-enabled' },
    { label: 'Takaful', path: '/takaful', icon: 'i-mdi-shield-check-outline', feature: 'takaful-enabled' },
    { label: 'Sadaqah', path: '/sadaqah', icon: 'i-mdi-heart-outline', feature: 'sadaq-enabled' },
    { label: 'VTU', path: '/vtu', icon: 'i-mdi-cellphone-wireless', feature: 'airtime-data-enabled' },
    { label: 'Gold', path: '/gold', icon: 'i-mdi-gold', feature: 'gold-savings-enabled' },
    { label: 'AGM', path: '/agm', icon: 'i-mdi-vote', feature: 'agm-voting-enabled' },
    { label: 'Attendance', path: '/attendance', icon: 'i-mdi-map-marker-radius', feature: null },
    { label: 'Group Savings', path: '/savings-groups', icon: 'i-mdi-account-group', feature: 'group-savings-enabled' },
  ]

  return allServices.filter(s => {
    if (!s.feature) return true
    return appStatusStore.features[s.feature] || appStatusStore.features[s.feature + '-beta']
  })
})

const isActive = (path) => {
  if (path === '/dashboard') return route.path === '/dashboard'
  return route.path.startsWith(path)
}

const logout = async () => {
  try {
    await axios.post('/api/logout')
  } catch (_) {}
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  localStorage.removeItem('user_id')
  localStorage.removeItem('is_admin')
  window.location.href = '/login'
}
</script>
