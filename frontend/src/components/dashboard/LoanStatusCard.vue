<template>
  <div>
    <div class="bg-white rounded-[2.5rem] p-7 shadow-sm border border-slate-100">
      <div class="flex justify-between items-center mb-6 cursor-pointer" @click="$router.push('/loans')">
        <h3 class="text-slate-800 font-bold text-lg">Qard Hasan (Loan) Status</h3>
        <div class="flex items-center gap-3">
          <router-link v-if="appStatusStore.features['apply-for-loan']" to="/loans" class="text-xs font-bold text-emerald-600 hover:text-emerald-700">Apply for Qard Hasan (Loan)</router-link>
        </div>
        <div class="w-10 h-10 bg-emerald-50 rounded-2xl flex items-center justify-center text-xl">💎</div>
      </div>
      
      <div class="flex items-end gap-1 mb-8 cursor-pointer" v-if="kpis.has_active_loan || kpis.total_due_amount > 0" @click="$router.push('/loans')">
        <template v-if="kpis.total_due_amount > 0">
          <span class="text-3xl font-black text-rose-600">₦ {{ hideBalances ? '***,***.**' : formatMoney(kpis.total_due_amount) }}</span>
          <span class="text-[10px] text-rose-500 font-bold uppercase mb-2 ml-1 tracking-wider">Overdue Amount</span>
        </template>
        <template v-else-if="kpis.is_defaulted">
          <span class="text-3xl font-black text-rose-600">₦ {{ hideBalances ? '***,***.**' : formatMoney(kpis.total_due_amount) }}</span>
          <span class="text-[10px] text-rose-500 font-bold uppercase mb-2 ml-1 tracking-wider">Defaulted Amount</span>
        </template>
        <template v-else-if="kpis.has_active_loan">
          <span class="text-3xl font-black text-amber-600">₦ {{ hideBalances ? '***,***.**' : formatMoney(kpis.loans) }}</span>
          <span class="text-[10px] text-amber-500 font-bold uppercase mb-2 ml-1 tracking-wider">Outstanding Balance</span>
        </template>
      </div>

      <!-- Progress Bar for Loan -->
      <div v-if="kpis.has_active_loan && kpis.total_loan_principal > 0" class="mb-6">
        <div class="flex justify-between items-center mb-2">
           <p class="text-[10px] text-slate-400 uppercase font-black">Repayment Progress</p>
           <p class="text-[10px] text-emerald-600 font-black">{{ Math.round((kpis.total_loan_paid / kpis.total_loan_principal) * 100) }}%</p>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
          <div class="bg-emerald-500 h-full transition-all duration-1000" :style="{ width: (kpis.total_loan_paid / kpis.total_loan_principal * 100) + '%' }"></div>
        </div>
        <div class="flex justify-between mt-1">
          <p class="text-[9px] text-slate-400">Paid: ₦{{ formatMoney(kpis.total_loan_paid) }}</p>
          <p class="text-[9px] text-slate-400">Total: ₦{{ formatMoney(kpis.total_loan_principal) }}</p>
        </div>
      </div>

      <div v-if="kpis.expected_amount_to_pay > 0" class="mb-6 cursor-pointer" @click="$router.push('/loans')">
         <p class="text-[10px] text-slate-400 uppercase font-black mb-1">Expected Amount to Pay (To Date)</p>
         <p class="text-lg font-black text-blue-600">₦ {{ hideBalances ? '***,***.**' : formatMoney(kpis.expected_amount_to_pay) }}</p>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <StatPill label="Savings" :value="currency + ' ' + (hideBalances ? '***,***.**' : formatMoney(kpis.savings_balance))" icon="💰" />
        <StatPill label="Shares" :value="currency + ' ' + (hideBalances ? '***,***.**' : formatMoney(kpis.shares_balance))" icon="📈" />
      </div>
      
      <div v-if="kpis.is_defaulted" class="mt-6 flex items-center gap-3 bg-rose-50 p-4 rounded-3xl border border-rose-100">
        <div class="text-lg">🛑</div>
        <div>
          <p class="text-[10px] text-rose-700 leading-tight font-medium">
            Your account is currently <span class="font-bold">in default</span> due to an unpaid Qard Hasan (Loan) repayment. You must clear your outstanding balance before you can access further credit.
          </p>
          <p v-if="kpis.default_duration" class="text-[10px] text-rose-600 mt-1 font-bold">
            Duration of Default: {{ kpis.default_duration }}
          </p>
        </div>
      </div>
    </div>

    <!-- KPI row -->
    <div class="mt-4 grid grid-cols-2 gap-3">
      <StatPill label="Contributions" :value="currency + ' ' + (hideBalances ? '***,***.**' : formatMoney(kpis.contributions))" hint="Total" intent="success" icon="💰" />
      <StatPill v-if="kpis.total_due_amount > 0" label="Overdue Amount" :value="currency + ' ' + (hideBalances ? '***,***.**' : formatMoney(kpis.total_due_amount))" hint="Pay Now" intent="danger" icon="⚠️" @click="$router.push('/loans')" class="cursor-pointer" />
      <StatPill v-else-if="kpis.expected_amount_to_pay > 0" label="Expected to Pay" :value="currency + ' ' + (hideBalances ? '***,***.**' : formatMoney(kpis.expected_amount_to_pay))" hint="Cumulative" intent="info" icon="📅" @click="$router.push('/loans')" class="cursor-pointer" />
      <StatPill v-else-if="appStatusStore.features['gold-savings-beta']" label="Gold Balance" :value="(hideBalances ? '***.**' : kpis.gold_balance?.toFixed(4)) + ' g'" :hint="hideBalances ? '≈ ₦ ***' : (kpis.gold_value_naira ? '≈ ₦ ' + formatMoney(kpis.gold_value_naira) : 'Digital Gold')" intent="warning" icon="🪙" @click="$router.push('/gold')" class="cursor-pointer" />
      <StatPill label="Qard Hasan (Loan)" :value="currency + ' ' + (hideBalances ? '***,***.**' : formatMoney(kpis.loans))" hint="Outstanding" intent="danger" icon="📊" @click="$router.push('/loans')" class="cursor-pointer" />
      <StatPill label="Attaqwa Score" :value="String(kpis.attaqwa_score || 0)" hint="Credit Rating" intent="info" icon="⭐" @click="$router.push('/profile')" class="cursor-pointer" />
    </div>
  </div>
</template>

<script setup>
import { useAppStatusStore } from '../../stores/appStatus'
import StatPill from '../StatPill.vue'

const props = defineProps({
  kpis: Object,
  hideBalances: Boolean,
  currency: String
})

const appStatusStore = useAppStatusStore()

const formatMoney = (amount) => {
  return Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
