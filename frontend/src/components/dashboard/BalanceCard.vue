<template>
  <div id="balance-card" class="bg-gradient-to-br from-emerald-700 to-emerald-900 rounded-[2rem] p-7 text-white shadow-xl relative overflow-hidden">
    <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full"></div>
    <div class="flex items-center gap-2 mb-2 relative z-10">
      <p class="text-emerald-100 text-sm font-medium">Available Balance</p>
      <div class="flex items-center gap-1">
        <button @click="$emit('toggle-visibility')" class="text-lg opacity-80 p-1 rounded-lg hover:bg-white/10 transition-colors" title="Toggle visibility">
          <svg v-if="hideBalances" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.076m3.313-3.313A9.959 9.959 0 0112 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-1.447 0-2.811-.31-4.04-.864m1.107-1.107l1.107-1.107m2.774-2.774l.553-.553m2.21-2.21l.553-.553" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
          </svg>
        </button>
        <button @click="$emit('refresh')" :disabled="refreshing" class="text-lg opacity-80 p-1 rounded-lg hover:bg-white/10 transition-colors disabled:opacity-40" title="Refresh balance">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-50" :class="{'animate-spin': refreshing}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>
      </div>
    </div>
    <h1 class="text-3xl sm:text-4xl leading-tight font-bold relative z-10 tracking-tight" :class="{'text-rose-300': netBalance < 0}">
      ₦ {{ hideBalances ? '***,***.**' : formatMoney(netBalance) }}
    </h1>
    <div v-if="appStatusStore.features['display-admin-charge-in-wallet'] && user.admin_charge_balance > 0" 
         class="mt-2 text-emerald-100/80 text-[10px] uppercase font-bold relative z-10 flex gap-2 items-center">
       <span>Gross: ₦ {{ hideBalances ? '***,***.**' : formatMoney(user.balance) }}</span>
       <span>|</span>
       <span class="text-rose-200">Charges: ₦ {{ hideBalances ? '***,***.**' : formatMoney(user.admin_charge_balance) }}</span>
    </div>
    <div class="mt-8 flex items-center justify-between flex-wrap gap-2 relative z-10">
      <div class="flex items-center gap-2">
        <p class="text-xs text-emerald-100 font-mono tracking-widest">ID: {{ user.membership_id }}</p>
        <button v-if="appStatusStore.features['digital-id-card-enabled']" 
                @click="$router.push('/digital-id')"
                class="bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all flex items-center gap-1.5 border border-white/5">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5zM6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" />
          </svg>
          Show ID
        </button>
        <button v-else @click="$emit('copy', user.membership_id)" class="text-xs text-white/80 underline">Copy</button>
      </div>
      <div class="flex gap-2">
        <button @click="$router.push('/pay')" class="bg-emerald-500 hover:bg-emerald-600 px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-lg border border-emerald-400">
          Allocate Fund
        </button>
        <button @click="$router.push('/wallet')" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-xl text-xs font-bold backdrop-blur-md transition-all">
          + Fund Wallet
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useAppStatusStore } from '../../stores/appStatus'

const props = defineProps({
  user: Object,
  netBalance: Number,
  hideBalances: Boolean,
  refreshing: Boolean
})

defineEmits(['toggle-visibility', 'refresh', 'copy'])

const appStatusStore = useAppStatusStore()

const formatMoney = (amount) => {
  return Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
