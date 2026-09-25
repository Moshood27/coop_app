<template>
  <div class="space-y-6">
    <!-- Card Top-up Form -->
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 transition-all">
      <h3 class="font-bold text-slate-800 mb-2">Fund Wallet (Online)</h3>

      <div class="mb-4 bg-rose-50 border border-rose-100 p-3 rounded-2xl">
        <p class="text-xs text-rose-600 font-bold text-center italic">Note: A maintenance charge of {{ wallet?.maintenance_charge_config?.percentage || 1 }}% (max ₦{{ wallet?.maintenance_charge_config?.max_amount || 500 }}) applies.</p>
      </div>

      <div class="space-y-4">
        <div>
          <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Payment Gateway</label>
          <div class="grid grid-cols-2 gap-2 mb-4">
            <button v-for="gw in enabledGateways" :key="gw"
                    @click="$emit('update:selectedGateway', gw)"
                    :class="selectedGateway === gw ? 'bg-emerald-700 text-white border-emerald-700' : 'bg-slate-50 text-slate-600 border-slate-100'"
                    class="py-2.5 rounded-xl border text-[9px] font-black uppercase tracking-wider transition-all">
              {{ gw }}
            </button>
          </div>

          <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Amount to Fund</label>
          <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-emerald-600 text-slate-400 font-bold">₦</div>
            <input :value="topupAmount" @input="$emit('update:topupAmount', $event.target.value)" type="number" min="1" placeholder="0.00"
                   class="w-full bg-slate-50 pl-11 p-4 rounded-2xl border border-slate-100 text-sm outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all" />
          </div>

          <!-- Maintenance Charge Display -->
          <div v-if="topupAmount > 0 && wallet?.maintenance_charge_config" class="mt-3 p-3 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
            <div class="flex justify-between text-[10px] font-bold uppercase tracking-wider">
              <span class="text-slate-500">System Maintenance Charge</span>
              <span class="text-rose-600">₦ {{ formatMoney(calculatedCharge) }}</span>
            </div>
            <div class="flex justify-between text-xs font-black uppercase tracking-wider pt-2 border-t border-slate-200">
              <span class="text-slate-800">Net Credit to Wallet</span>
              <span class="text-emerald-700 font-black">₦ {{ formatMoney(Math.max(0, topupAmount - calculatedCharge)) }}</span>
            </div>
          </div>
        </div>
        <button @click="$emit('initTopup')" :disabled="loading || !topupAmount"
                class="w-full bg-emerald-700 text-white p-4 rounded-2xl font-bold shadow-lg shadow-emerald-700/20 disabled:opacity-50 transition-all active:scale-[0.98]">
          {{ loading ? 'Processing…' : 'Proceed to Payment' }}
        </button>
      </div>
      <p class="mt-3 text-[10px] text-slate-500 text-center">Powered by Paystack/Flutterwave. Securely top up using your debit card.</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  wallet: Object,
  enabledGateways: Array,
  selectedGateway: String,
  topupAmount: [Number, String],
  calculatedCharge: Number,
  loading: Boolean
})

defineEmits(['update:selectedGateway', 'update:topupAmount', 'initTopup'])

const formatMoney = (val) => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
</script>
