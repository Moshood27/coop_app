<template>
  <div class="bg-gradient-to-br from-emerald-700 to-emerald-900 rounded-[2rem] p-7 text-white shadow-xl relative overflow-hidden">
    <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full"></div>
    <div class="flex items-center gap-2 mb-2 relative z-10">
      <p class="text-emerald-100 text-sm font-medium">Available Balance</p>
      <div class="flex items-center gap-1">
        <button @click="$emit('toggleHide')" class="text-lg opacity-80 p-1 rounded-lg hover:bg-white/10 transition-colors">
          <span v-if="hideBalances" class="i-mdi-eye-off text-emerald-50 text-xl"></span>
          <span v-else class="i-mdi-eye text-emerald-50 text-xl"></span>
        </button>
        <button @click="$emit('refresh')" :disabled="refreshing" class="text-lg opacity-80 p-1 rounded-lg hover:bg-white/10 transition-colors disabled:opacity-40">
          <span class="i-mdi-refresh text-emerald-50 text-xl" :class="{'animate-spin': refreshing}"></span>
        </button>
      </div>
    </div>
    <h2 class="text-4xl font-bold mt-1 relative z-10" :class="{'text-rose-300': netBalance < 0}">
      ₦ {{ hideBalances ? '***,***.**' : formatMoney(netBalance) }}
    </h2>
    
    <div v-if="showAdminCharge && adminChargeBalance > 0" class="mt-2 text-emerald-100/80 text-[10px] uppercase font-bold relative z-10 flex gap-2 items-center">
       <span>Gross: ₦ {{ hideBalances ? '***,***.**' : formatMoney(balance) }}</span>
       <span>|</span>
       <span class="text-rose-200">Charges: ₦ {{ hideBalances ? '***,***.**' : formatMoney(adminChargeBalance) }}</span>
    </div>

    <div class="mt-2 text-emerald-100 text-xs flex justify-between gap-2 relative z-10">
      <span>Available for Withdrawal</span>
      <span class="font-bold">₦ {{ hideBalances ? '***,***.**' : formatMoney(availableForWithdrawal) }}</span>
    </div>
    <div class="mt-6 flex gap-2 flex-wrap relative z-10">
      <button @click="$emit('allocate')" class="bg-white/20 hover:bg-white/30 px-4 py-2.5 rounded-xl text-xs font-bold backdrop-blur-md transition-all border border-white/10">Allocate Funds</button>
      <button @click="$emit('fund')" class="bg-white text-emerald-900 px-4 py-2.5 rounded-xl text-xs font-bold shadow-lg transition-transform active:scale-95">Fund Wallet</button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  netBalance: Number,
  balance: Number,
  adminChargeBalance: Number,
  availableForWithdrawal: Number,
  hideBalances: Boolean,
  refreshing: Boolean,
  showAdminCharge: Boolean
})

defineEmits(['toggleHide', 'refresh', 'allocate', 'fund'])

const formatMoney = (val) => {
  return new Intl.NumberFormat('en-NG', { minimumFractionDigits: 2 }).format(val || 0)
}
</script>
