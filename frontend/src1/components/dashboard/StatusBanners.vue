<template>
  <div class="space-y-4 mt-4">
    <!-- PIN Warning -->
    <div v-if="appStatusStore.setTransactionPinEnabled && kpis && !kpis.has_pin"
         class="p-4 rounded-3xl bg-amber-50 border border-amber-200 flex items-center gap-3 cursor-pointer"
         @click="$router.push('/profile')">
      <div class="text-2xl">🔑</div>
      <div class="flex-1">
        <p class="text-sm font-bold text-amber-900">Transaction PIN not set</p>
        <p class="text-xs text-amber-700">You need a PIN to transfer or withdraw funds.</p>
      </div>
      <div class="text-amber-400">➡️</div>
    </div>

    <!-- Attendance Reminder -->
    <div v-if="kpis && kpis.has_ongoing_meeting"
         class="p-4 rounded-3xl bg-emerald-900 text-white flex items-center gap-3 shadow-lg shadow-emerald-200 cursor-pointer"
         @click="$router.push('/attendance')">
      <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center text-xl animate-pulse">📍</div>
      <div class="flex-1">
        <p class="text-sm font-bold">Meeting Ongoing</p>
        <p class="text-[10px] text-white/70 uppercase tracking-widest font-black">Tap to mark attendance</p>
      </div>
      <div class="text-white/40">➡️</div>
    </div>

    <!-- Outstanding Fines Warning -->
    <div v-if="kpis && kpis.outstanding_fines > 0"
         class="p-4 rounded-3xl bg-rose-50 border border-rose-200 flex items-center gap-3 cursor-pointer"
         @click="$router.push('/passbook')">
      <div class="text-2xl">⚠️</div>
      <div class="flex-1">
        <p class="text-sm font-bold text-rose-900">Outstanding Fines: ₦{{ formatMoney(kpis.outstanding_fines) }}</p>
        <p class="text-xs text-rose-700">These will be deducted from your next wallet funding.</p>
      </div>
      <div class="text-rose-400">➡️</div>
    </div>

    <!-- Tahkim Dispute Warning -->
    <div v-if="kpis && kpis.active_disputes_count > 0"
         class="p-4 rounded-3xl bg-slate-900 text-white flex items-center gap-3 shadow-lg shadow-slate-200 cursor-pointer"
         @click="$router.push('/sharia-board/history')">
      <div class="w-10 h-10 bg-white/10 rounded-full flex items-center justify-center text-xl">⚖️</div>
      <div class="flex-1">
        <p class="text-sm font-bold">Active Tahkim ({{ kpis.active_disputes_count }})</p>
        <p class="text-[10px] text-white/70 uppercase tracking-widest font-black">Sharia Board Mediation in progress</p>
      </div>
      <div class="text-white/40">➡️</div>
    </div>

    <!-- Shura Voting Banner -->
    <div v-if="appStatusStore.features['shura-voting-active']"
         class="p-4 rounded-3xl bg-indigo-600 text-white flex items-center gap-3 shadow-lg shadow-indigo-200 cursor-pointer"
         @click="$router.push('/agm')">
      <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center text-xl animate-bounce">🗳️</div>
      <div class="flex-1">
        <p class="text-sm font-bold">AGM Voting Live</p>
        <p class="text-[10px] text-white/70 uppercase tracking-widest font-black">Cast your vote for the Shura Council</p>
      </div>
      <div class="text-white/40">➡️</div>
    </div>

    <!-- Migration Discrepancy Banner -->
    <div v-if="migration?.discrepancy_reported_at && !migration?.verified_at"
         class="p-4 rounded-3xl bg-blue-50 border border-blue-200 flex items-center gap-3">
      <div class="text-2xl">⏳</div>
      <div class="flex-1">
        <p class="text-sm font-bold text-blue-900">Balance Under Review</p>
        <p class="text-xs text-blue-700">You reported a discrepancy. Our officers are currently reconciling your records.</p>
      </div>
    </div>

    <!-- Next Due Installment Banner -->
    <div v-if="kpis && kpis.next_due_date"
         class="p-4 rounded-3xl bg-white border border-slate-100 flex items-center gap-3 shadow-sm cursor-pointer"
         @click="$router.push('/loans')">
      <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl shrink-0"
           :class="kpis.total_due_amount > 0 ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600'">
        {{ kpis.total_due_amount > 0 ? '🚨' : '🔔' }}
      </div>
      <div class="flex-1">
        <div class="flex justify-between items-start">
          <p class="text-[10px] font-bold uppercase tracking-widest mb-0.5"
             :class="kpis.total_due_amount > 0 ? 'text-rose-600' : 'text-amber-600'">
            Next Due Installment
          </p>
          <span v-if="kpis.total_due_amount > 0" class="text-[9px] font-black text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full uppercase tracking-tighter">Action Required</span>
        </div>
        <p class="text-sm font-black text-slate-800">
          {{ formatDate(kpis.next_due_date) }} • {{ currency }} {{ hideBalances ? '***,***.**' : formatMoney(kpis.next_due_amount) }}
        </p>
        <p v-if="kpis.total_due_amount > 0" class="text-[10px] text-rose-500 font-bold mt-1 uppercase tracking-tighter">
          Overdue Amount: {{ currency }} {{ hideBalances ? '***,***.**' : formatMoney(kpis.total_due_amount) }}
        </p>
        <p v-if="kpis.expected_amount_to_pay > 0" class="text-[10px] text-blue-500 font-bold mt-1 uppercase tracking-tighter">
          Expected to Pay: {{ currency }} {{ hideBalances ? '***,***.**' : formatMoney(kpis.expected_amount_to_pay) }}
        </p>
      </div>
      <div class="text-slate-300">➡️</div>
    </div>
  </div>
</template>

<script setup>
import { useAppStatusStore } from '../../stores/appStatus'

const props = defineProps({
  kpis: Object,
  migration: Object,
  hideBalances: Boolean,
  currency: String,
  formatMoney: Function,
  formatDate: Function
})

const appStatusStore = useAppStatusStore()
</script>
