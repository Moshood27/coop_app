<template>
  <div class="min-h-screen bg-slate-50 pb-32">
    <header class="p-6 bg-white border-b sticky top-0 z-20 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-black text-slate-800 tracking-tight">Admin Portal</h1>
        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-[0.2em]">Management Hub</p>
      </div>
      <button @click="$router.push('/dashboard')" class="w-10 h-10 bg-slate-100 rounded-2xl flex items-center justify-center text-slate-500 hover:bg-slate-200 transition-colors">
        <span class="i-mdi-close text-xl"></span>
      </button>
    </header>

    <div class="p-6 space-y-8 max-w-lg mx-auto">
      <!-- KPI Overview -->
      <section v-if="stats" class="space-y-4">
        <!-- Main Liquidity Card (Responsive) -->
        <div class="bg-emerald-900 p-6 rounded-[2.5rem] shadow-lg shadow-emerald-200 text-white relative overflow-hidden transition-all duration-300">
          <div class="absolute -right-6 -top-6 text-white/10 text-9xl">
             <span class="i-mdi-cash-multiple"></span>
          </div>
          <div class="relative z-10">
            <p class="text-[10px] font-black text-emerald-300 uppercase tracking-[0.2em] mb-2">Net Liquidity</p>
            <div class="flex items-baseline flex-wrap gap-x-2">
              <span class="text-xl font-bold opacity-70">₦</span>
              <h3 :class="[
                'font-black tracking-tighter leading-none',
                (liquidity.net || 0).toLocaleString('en-NG').length > 12 ? 'text-2xl sm:text-4xl' : 'text-3xl sm:text-5xl'
              ]">
                {{ formatMoney(liquidity.net) }}
              </h3>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="bg-white p-5 rounded-[2rem] shadow-sm border border-slate-100 flex flex-col justify-between">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Members</p>
            <h3 class="text-2xl font-black text-slate-800">{{ stats.total_users }}</h3>
          </div>
          <div class="bg-white p-5 rounded-[2rem] shadow-sm border border-slate-100 flex flex-col justify-between">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Active Loans</p>
            <h3 class="text-2xl font-black text-slate-800">{{ stats.active_loans }}</h3>
          </div>
        </div>
      </section>

      <!-- Action Required (Filament Features) -->
      <section v-if="stats" class="space-y-4">
        <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest px-1">Approvals Needed</h3>
        <div class="grid grid-cols-1 gap-3">
          <div v-if="stats.pending_loans > 0" class="bg-amber-50 border border-amber-100 p-4 rounded-3xl flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-white rounded-2xl flex items-center justify-center text-amber-600 shadow-sm">
                <span class="i-mdi-hand-coin text-xl"></span>
              </div>
              <div>
                <p class="text-sm font-bold text-slate-800">{{ stats.pending_loans }} Loan Requests</p>
                <p class="text-[10px] text-amber-700 font-bold uppercase">Awaiting review</p>
              </div>
            </div>
            <button @click="openFilament('loan-requests')" class="bg-amber-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-wider">Open</button>
          </div>

          <div v-if="stats.pending_withdrawals > 0" class="bg-rose-50 border border-rose-100 p-4 rounded-3xl flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-white rounded-2xl flex items-center justify-center text-rose-600 shadow-sm">
                <span class="i-mdi-bank-transfer-out text-xl"></span>
              </div>
              <div>
                <p class="text-sm font-bold text-slate-800">{{ stats.pending_withdrawals }} Withdrawals</p>
                <p class="text-[10px] text-rose-700 font-bold uppercase">Pending Payout</p>
              </div>
            </div>
            <button @click="openFilament('withdrawal-requests')" class="bg-rose-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-wider">Open</button>
          </div>

          <div v-if="stats.unread_support > 0" class="bg-indigo-50 border border-indigo-100 p-4 rounded-3xl flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-white rounded-2xl flex items-center justify-center text-indigo-600 shadow-sm">
                <span class="i-mdi-message-alert text-xl"></span>
              </div>
              <div>
                <p class="text-sm font-bold text-slate-800">{{ stats.unread_support }} Support Tickets</p>
                <p class="text-[10px] text-indigo-700 font-bold uppercase">Unread Messages</p>
              </div>
            </div>
            <button @click="openFilament('support-messages')" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-wider">Open</button>
          </div>

          <div v-if="stats.defaulters_count > 0" class="bg-rose-50 border border-rose-100 p-4 rounded-3xl flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-white rounded-2xl flex items-center justify-center text-rose-600 shadow-sm">
                <span class="i-mdi-alert-circle-outline text-xl"></span>
              </div>
              <div>
                <p class="text-sm font-bold text-slate-800">{{ stats.defaulters_count }} Defaulters</p>
                <p class="text-[10px] text-rose-700 font-bold uppercase">Loan repayment overdue</p>
              </div>
            </div>
            <button @click="$router.push('/admin/members?defaulters=1')" class="bg-rose-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-wider">View All</button>
          </div>
        </div>
      </section>

      <!-- VTU Balances -->
      <section v-if="Object.keys(vtu_balances).length" class="space-y-4">
        <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest px-1">VTU Provider Balances</h3>
        <div class="grid grid-cols-2 gap-3">
          <div v-for="(bal, provider) in vtu_balances" :key="provider" class="bg-white p-4 rounded-3xl border border-slate-100 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center text-slate-400">
              <span class="i-mdi-wallet text-sm"></span>
            </div>
            <div class="overflow-hidden">
              <p class="text-[9px] font-black text-slate-400 uppercase truncate">{{ provider }}</p>
              <p class="text-sm font-black text-slate-800 tracking-tight" :class="bal.available < 5000 ? 'text-rose-600' : ''">
                ₦{{ formatMoney(bal.available) }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- Management Modules -->
      <section class="space-y-4">
        <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest px-1">Mobile Management</h3>
        <div class="grid grid-cols-2 gap-4">
          <button v-if="canManageMembers" @click="$router.push('/admin/members')" class="col-span-2 bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-100 flex items-center gap-4 active:scale-95 transition-all text-left">
            <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-3xl flex items-center justify-center text-2xl shadow-inner">
              <span class="i-mdi-account-group"></span>
            </div>
            <div>
              <p class="text-sm font-black text-slate-700 uppercase tracking-wider">Member Management</p>
              <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Wallet, Passbook & Loans</p>
            </div>
            <span class="ml-auto i-mdi-chevron-right text-slate-300 text-xl"></span>
          </button>

          <button @click="$router.push('/admin/vendors')" class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-100 flex flex-col items-center gap-3 active:scale-95 transition-all">
            <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-3xl flex items-center justify-center text-2xl shadow-inner">
              <span class="i-mdi-storefront"></span>
            </div>
            <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Vendors</span>
          </button>

          <button @click="$router.push('/admin/products')" class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-100 flex flex-col items-center gap-3 active:scale-95 transition-all">
            <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-3xl flex items-center justify-center text-2xl shadow-inner">
              <span class="i-mdi-package-variant-closed"></span>
            </div>
            <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Products</span>
          </button>

          <button @click="$router.push('/admin/takaful')" class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-100 flex flex-col items-center gap-3 active:scale-95 transition-all">
            <div class="w-14 h-14 bg-rose-50 text-rose-600 rounded-3xl flex items-center justify-center text-2xl shadow-inner">
              <span class="i-mdi-shield-check"></span>
            </div>
            <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Takaful</span>
          </button>

          <button @click="$router.push('/admin/vtu')" class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-100 flex flex-col items-center gap-3 active:scale-95 transition-all">
            <div class="w-14 h-14 bg-amber-50 text-amber-600 rounded-3xl flex items-center justify-center text-2xl shadow-inner">
              <span class="i-mdi-cellphone-wireless"></span>
            </div>
            <span class="text-xs font-black text-slate-700 uppercase tracking-wider">VTU Monitor</span>
          </button>

          <button @click="$router.push('/admin/imports')" class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-100 flex flex-col items-center gap-3 active:scale-95 transition-all">
            <div class="w-14 h-14 bg-slate-50 text-slate-600 rounded-3xl flex items-center justify-center text-2xl shadow-inner">
              <span class="i-mdi-file-import"></span>
            </div>
            <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Imports</span>
          </button>

          <button @click="openFilament('')" class="bg-slate-800 p-6 rounded-[2.5rem] shadow-xl flex flex-col items-center gap-3 active:scale-95 transition-all">
            <div class="w-14 h-14 bg-white/10 text-white rounded-3xl flex items-center justify-center text-2xl shadow-inner">
              <span class="i-mdi-monitor-dashboard"></span>
            </div>
            <span class="text-xs font-black text-white uppercase tracking-wider">Full Filament</span>
          </button>
        </div>
      </section>

      <!-- Recent Users (New Members) -->
      <section v-if="recent_users.length" class="space-y-4">
        <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest px-1">New Members</h3>
        <div class="bg-white rounded-[2rem] border border-slate-100 overflow-hidden shadow-sm">
          <div v-for="u in recent_users" :key="u.id" class="p-4 flex items-center justify-between border-b border-slate-50 last:border-0">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center font-bold text-xs uppercase overflow-hidden">
                <img v-if="u.passport_url" :src="getImageUrl(u.passport_url)" class="w-full h-full object-cover" />
                <span v-else>{{ u.full_name.charAt(0) }}</span>
              </div>
              <div>
                <p class="text-sm font-bold text-slate-800">{{ u.full_name }}</p>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Membership NO: {{ u.membership_id }}</p>
              </div>
            </div>
            <p class="text-[10px] text-slate-400">{{ formatDate(u.created_at) }}</p>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import axios from '../../http'
import getImageUrl from '../../utils/image'

const stats = ref(null)
const user = ref(null)
const liquidity = ref(null)
const vtu_balances = ref({})
const recent_users = ref([])
const loading = ref(true)

const isBranchSuperAdmin = computed(() => {
  if (!user.value) return false
  const roles = user.value.roles || []
  const isSuper = roles.some(r => r.name === 'super_admin')
  return isSuper && !!user.value.branch_id
})

const isGlobalSuperAdmin = computed(() => {
  if (!user.value) return false
  const roles = user.value.roles || []
  return roles.some(r => r.name === 'super_admin') && !user.value.branch_id
})

const canManageMembers = computed(() => isBranchSuperAdmin.value || isGlobalSuperAdmin.value)

const load = async () => {
  try {
    const { data } = await axios.get('/api/admin/dashboard')
    user.value = data.user
    stats.value = data.stats
    liquidity.value = data.liquidity
    vtu_balances.value = data.vtu_balances || {}
    recent_users.value = data.recent_users
  } catch (e) {
    console.error('Failed to load admin dashboard', e)
  } finally {
    loading.value = false
  }
}

const formatMoney = (val) => {
  return new Intl.NumberFormat('en-NG').format(val || 0)
}

const formatDate = (dateStr) => {
  return new Date(dateStr).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
}

const openFilament = (resource) => {
  const baseUrl = window.location.origin + '/admin'
  const url = resource ? `${baseUrl}/${resource}` : baseUrl
  window.open(url, '_blank')
}

onMounted(load)
</script>

<style scoped>
</style>
