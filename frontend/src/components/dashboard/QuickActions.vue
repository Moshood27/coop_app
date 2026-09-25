<template>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4 mt-8">
    <button v-if="appStatusStore.features['chat-help-enabled']" @click="$router.push('/chat')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-2xl">💬</div>
      <span class="text-sm font-bold text-slate-700">Chat & Help</span>
    </button>
    <button v-if="appStatusStore.features['projects-enabled']" @click="$router.push('/projects')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-purple-50 rounded-2xl flex items-center justify-center text-2xl">📦</div>
      <span class="text-sm font-bold text-slate-700">Projects</span>
    </button>
    <button v-if="appStatusStore.features['sadaq-enabled']" @click="$router.push('/sadaqah')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-rose-50 rounded-2xl flex items-center justify-center text-2xl">🌙</div>
      <span class="text-sm font-bold text-slate-700">Sadaqah</span>
    </button>
    <button @click="$router.push('/attendance')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-2xl">📍</div>
      <span class="text-sm font-bold text-slate-700">Attendance</span>
    </button>
    <button v-if="appStatusStore.features['group-savings-enabled']" @click="$router.push('/savings-groups')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center text-2xl">🤝</div>
      <span class="text-sm font-bold text-slate-700">Group Savings</span>
    </button>
    <button v-if="appStatusStore.features['airtime-data-enabled']" @click="$router.push('/vtu')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center text-2xl">📶</div>
      <span class="text-sm font-bold text-slate-700">Airtime/Data</span>
    </button>
    <button id="loan-btn" @click="$router.push('/loans')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-2xl">📊</div>
      <span class="text-sm font-bold text-slate-700">Qard Hasan (Loan) Records</span>
    </button>
    <button @click="$router.push('/reports')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-2xl">📈</div>
      <span class="text-sm font-bold text-slate-700">Reports</span>
    </button>
    <button v-if="appStatusStore.features['takaful-enabled']" @click="$router.push('/takaful')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-cyan-50 rounded-2xl flex items-center justify-center text-2xl">🛡️</div>
      <span class="text-sm font-bold text-slate-700">Takaful</span>
    </button>
    <button @click="$router.push('/transparency')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-lime-50 rounded-2xl flex items-center justify-center text-2xl">🧾</div>
      <span class="text-sm font-bold text-slate-700">Transparency</span>
    </button>
    <button v-if="appStatusStore.features['store-enabled']" @click="$router.push('/store')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-teal-50 rounded-2xl flex items-center justify-center text-2xl">🛒</div>
      <span class="text-sm font-bold text-slate-700">Store</span>
    </button>
    <button v-if="appStatusStore.features['gold-savings-enabled'] || appStatusStore.features['gold-savings-beta']" @click="$router.push('/gold')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-yellow-50 rounded-2xl flex items-center justify-center text-2xl">🪙</div>
      <span class="text-sm font-bold text-slate-700">Gold Savings</span>
    </button>
    <button v-if="appStatusStore.features['merchant-pay-enabled']" @click="$router.push('/merchant/pay')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-2xl">📸</div>
      <span class="text-sm font-bold text-slate-700">Pay Merchant</span>
    </button>
    <button v-if="appStatusStore.features['receive-qr-enabled']" @click="$router.push('/merchant/receive')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center text-2xl">🔲</div>
      <span class="text-sm font-bold text-slate-700">Receive QR</span>
    </button>
    <button v-if="appStatusStore.features['agm-voting-enabled']" @click="$router.push('/agm')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-fuchsia-50 rounded-2xl flex items-center justify-center text-2xl">🗳️</div>
      <span class="text-sm font-bold text-slate-700">AGM & Voting</span>
    </button>
    <button v-if="appStatusStore.features['zakat-enabled']" @click="$emit('check-zakat')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-amber-50 rounded-2xl flex items-center justify-center text-2xl">🕌</div>
      <span class="text-sm font-bold text-slate-700">Zakat</span>
    </button>
    <button v-if="user.is_ramadan && appStatusStore.features['zakat-enabled']" @click="$emit('pay-zakat-fitr')" class="bg-emerald-50 p-5 rounded-3xl shadow-sm border border-emerald-100 flex flex-col items-center gap-2 active:bg-emerald-100 transition-all">
      <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center text-2xl">🥣</div>
      <span class="text-sm font-bold text-emerald-800">Zakat Al-Fitr</span>
    </button>
    <button v-if="appStatusStore.features['hajj-umrah-enabled']" @click="$router.push('/goals')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-2xl">🕋</div>
      <span class="text-sm font-bold text-slate-700">Hajj & Umrah</span>
    </button>
    <button v-if="appStatusStore.features['junior-coop-enabled']" @click="$router.push('/junior-cooperative')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center text-2xl">👶</div>
      <span class="text-sm font-bold text-slate-700">Junior Coop</span>
    </button>
    <button v-if="appStatusStore.features['wassiyah-enabled']" @click="$router.push('/wasiyyah')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center text-2xl">📋</div>
      <span class="text-sm font-bold text-slate-700">Wasiyyah</span>
    </button>
    <button v-if="appStatusStore.features['vendor-enabled'] && user.kpis?.vendor?.is_vendor" @click="$router.push('/vendor/dashboard')" class="bg-emerald-50 p-5 rounded-3xl shadow-sm border border-emerald-100 flex flex-col items-center gap-2 active:bg-emerald-100 transition-all">
      <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center text-2xl">🏪</div>
      <span class="text-sm font-bold text-emerald-800">Vendor Portal</span>
    </button>
    <button v-else-if="appStatusStore.features['vendor-enabled']" @click="$router.push('/vendor/apply')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-2xl">🏪</div>
      <span class="text-sm font-bold text-slate-700">Become a Vendor</span>
    </button>
    <button @click="$router.push('/sharia-board')" class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50 transition-all">
      <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-2xl">⚖️</div>
      <span class="text-sm font-bold text-slate-700">Sharia Board</span>
    </button>
    <button v-if="user.is_admin" @click="$router.push('/admin/portal')" class="bg-rose-50 p-5 rounded-3xl shadow-sm border border-rose-100 flex flex-col items-center gap-2 active:bg-rose-100 transition-all">
      <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center text-2xl">👮</div>
      <span class="text-sm font-bold text-rose-800">Admin Portal</span>
    </button>
  </div>
</template>

<script setup>
import { useAppStatusStore } from '../../stores/appStatus'

const props = defineProps({
  user: Object
})

defineEmits(['check-zakat', 'pay-zakat-fitr'])

const appStatusStore = useAppStatusStore()
</script>
