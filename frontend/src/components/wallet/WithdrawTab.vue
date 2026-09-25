<template>
  <div class="space-y-6">
    <!-- Withdraw to Bank Form -->
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 transition-all">
      <h3 class="font-bold text-slate-800 mb-2">Withdraw to Bank</h3>
      <p class="text-[11px] text-slate-500 mb-4 leading-relaxed">Withdrawals are sent to your verified bank account in Profile settings.</p>
      <div class="space-y-4">
        <div v-if="features['wallet-withdrawal-enabled'] || features['special-savings-withdrawal-enabled']">
          <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Source account</label>
          <div class="grid grid-cols-2 gap-2">
            <button v-if="features['wallet-withdrawal-enabled']"
                    @click="$emit('update:withdrawType', 'wallet')" 
                    :class="withdrawType === 'wallet' ? 'bg-emerald-700 text-white shadow-md shadow-emerald-700/20' : 'bg-slate-50 text-slate-500 border border-slate-100'"
                    class="py-3 px-4 rounded-2xl text-[10px] font-black uppercase tracking-wider transition-all">Wallet</button>
            <button v-if="features['special-savings-withdrawal-enabled']"
                    @click="$emit('update:withdrawType', 'special_savings')" 
                    :class="withdrawType === 'special_savings' ? 'bg-emerald-700 text-white shadow-md shadow-emerald-700/20' : 'bg-slate-50 text-slate-500 border border-slate-100'"
                    class="py-3 px-4 rounded-2xl text-[10px] font-black uppercase tracking-wider transition-all">Special Savings</button>
          </div>
        </div>
        <div>
          <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Amount</label>
          <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 font-bold">₦</div>
            <input :value="withdrawAmount" @input="$emit('update:withdrawAmount', $event.target.value)" type="number" min="1" :max="availableAmount"
                   class="w-full bg-slate-50 pl-11 p-4 rounded-2xl border border-slate-100 text-sm outline-none focus:border-emerald-500 transition-all"
                   placeholder="0.00" />
          </div>
          <div class="mt-2 flex justify-between items-center px-1">
            <span class="text-[10px] text-slate-400 font-bold uppercase">Available in {{ withdrawType === 'wallet' ? 'Wallet' : 'Special Savings' }}</span>
            <span class="text-[10px] text-emerald-700 font-black">₦ {{ hideBalances ? '***,***.**' : formatMoney(availableAmount) }}</span>
          </div>
        </div>
        <div>
          <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Note (Optional)</label>
          <input :value="withdrawNote" @input="$emit('update:withdrawNote', $event.target.value)" type="text" maxlength="200"
                 class="w-full bg-slate-50 p-4 rounded-2xl border border-slate-100 text-sm outline-none focus:border-emerald-500 transition-all"
                 placeholder="Purpose of withdrawal" />
        </div>
        <button @click="$emit('withdraw')" :disabled="loading || !canWithdraw"
                class="w-full bg-emerald-700 text-white p-4 rounded-2xl font-bold shadow-lg shadow-emerald-700/20 disabled:opacity-50 transition-all active:scale-[0.98]">
          {{ loading ? 'Submitting…' : 'Request Cashout' }}
        </button>
        <p class="text-[10px] text-slate-500 text-center">Confirmation with Transaction PIN required.</p>
      </div>
    </div>

    <!-- Withdrawal Breakdown -->
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 relative overflow-hidden">
      <div class="absolute right-0 top-0 w-32 h-32 bg-slate-50 rounded-full -mr-16 -mt-16 opacity-50" />
      <h3 class="font-bold text-slate-800 mb-4 relative z-10">Withdrawal Breakdown</h3>
      <div class="space-y-3 relative z-10">
        <div class="flex justify-between items-center p-3 rounded-xl bg-slate-50/50 border border-slate-100">
          <span class="text-slate-500 text-[10px] font-black uppercase tracking-wider">Credits (Withdrawable)</span>
          <span class="font-bold text-slate-800 text-sm">₦ {{ hideBalances ? '***,***.**' : formatMoney(wallet?.breakdown?.credits_withdrawable || 0) }}</span>
        </div>
        <div class="flex justify-between items-center p-3 rounded-xl bg-slate-50/50 border border-slate-100">
          <span class="text-slate-500 text-[10px] font-black uppercase tracking-wider">Credits (Restricted)</span>
          <span class="font-bold text-slate-800 text-sm">₦ {{ hideBalances ? '***,***.**' : formatMoney(wallet?.breakdown?.credits_restricted || 0) }}</span>
        </div>
        <div class="flex justify-between items-center p-3 rounded-xl bg-slate-50/50 border border-slate-100">
          <span class="text-slate-500 text-[10px] font-black uppercase tracking-wider">Total Debits</span>
          <span class="font-bold text-rose-600 text-sm">₦ {{ hideBalances ? '***,***.**' : formatMoney(wallet?.breakdown?.total_debits || 0) }}</span>
        </div>
        <div class="flex justify-between items-center p-4 rounded-xl bg-emerald-50 border border-emerald-100 mt-2">
          <span class="text-emerald-800 text-[10px] font-black uppercase tracking-wider">Net Withdrawable</span>
          <span class="font-black text-emerald-700 text-lg">₦ {{ hideBalances ? '***,***.**' : formatMoney(wallet?.breakdown?.remaining_withdrawable || 0) }}</span>
        </div>
      </div>
      <p class="text-[10px] text-slate-400 mt-4 leading-relaxed italic">Restricted funds (e.g. loan disbursements) can be spent on utilities/store but cannot be withdrawn to bank unless unlocked.</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  features: Object,
  withdrawType: String,
  withdrawAmount: [Number, String],
  withdrawNote: String,
  wallet: Object,
  loading: Boolean,
  canWithdraw: Boolean,
  hideBalances: Boolean
})

defineEmits(['update:withdrawType', 'update:withdrawAmount', 'update:withdrawNote', 'withdraw'])

const formatMoney = (val) => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })

const availableAmount = computed(() => {
  return Number(props.withdrawType === 'wallet' 
    ? (props.wallet?.available_for_withdrawal || 0) 
    : (props.wallet?.special_savings_available_for_withdrawal || props.wallet?.special_savings_balance || 0))
})
</script>
