<template>
  <div class="min-h-screen bg-slate-50 pb-32">
    <header class="p-6 bg-white border-b sticky top-0 z-20 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-black text-slate-800 tracking-tight">Accounting Ops</h1>
        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-[0.2em]">Year-End | Auto-Reverse | Rebuild | FX</p>
      </div>
      <button @click="$router.back()" class="w-10 h-10 bg-slate-100 rounded-2xl flex items-center justify-center text-slate-500 hover:bg-slate-200 transition-colors">
        <span class="i-mdi-arrow-left"></span>
      </button>
    </header>

    <div class="p-6 space-y-8 max-w-3xl mx-auto">
      <div v-if="banner" :class="['p-4 rounded-2xl', banner.type==='error'?'bg-rose-50 border border-rose-200 text-rose-700':'bg-emerald-50 border border-emerald-200 text-emerald-700']">
        <div class="flex items-start gap-3">
          <span :class="banner.type==='error'?'i-mdi-alert-circle text-rose-600':'i-mdi-check-circle text-emerald-600'" class="text-xl"></span>
          <div>
            <p class="text-sm font-bold">{{ banner.title }}</p>
            <p class="text-xs whitespace-pre-line">{{ banner.message }}</p>
          </div>
        </div>
      </div>

      <!-- Year-End Close -->
      <section class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 space-y-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-xl"><span class="i-mdi-calendar-end"></span></div>
          <div>
            <h3 class="text-sm font-black text-slate-800">Year-End Close</h3>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Transfer net P&L to retained earnings</p>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <select v-model="yec.period_id" class="input">
            <option value="" disabled>Select Period</option>
            <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }} ({{ p.starts_on }} – {{ p.ends_on }})</option>
          </select>
          <input v-model="yec.retained_code" placeholder="Retained Earnings Code (3100)" class="input" />
          <label class="flex items-center gap-2 text-xs"><input type="checkbox" v-model="yec.dry_run"/> Preview Only</label>
          <label class="flex items-center gap-2 text-xs"><input type="checkbox" v-model="yec.close"/> Close Period After</label>
        </div>
        <div class="flex gap-3">
          <button @click="runYearEndClose" class="btn bg-amber-600 text-white">Run</button>
        </div>
      </section>

      <!-- Auto Reversals -->
      <section class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 space-y-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl"><span class="i-mdi-arrow-u-left-top"></span></div>
          <div>
            <h3 class="text-sm font-black text-slate-800">Auto-Reversals</h3>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Reverse flagged journals</p>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <input type="date" v-model="rev.date" class="input"/>
          <input type="number" v-model="rev.journal" placeholder="Specific Journal ID (optional)" class="input"/>
          <label class="flex items-center gap-2 text-xs"><input type="checkbox" v-model="rev.dry_run"/> Preview Only</label>
        </div>
        <div class="flex gap-3">
          <button @click="runAutoReverse" class="btn bg-indigo-600 text-white">Run</button>
        </div>
      </section>

      <!-- Monthly Balances Rebuild -->
      <section class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 space-y-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center text-xl"><span class="i-mdi-database-refresh"></span></div>
          <div>
            <h3 class="text-sm font-black text-slate-800">Rebuild Monthly Balances</h3>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Aggregate ledger by month</p>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
          <input type="date" v-model="mb.from" class="input" placeholder="From"/>
          <input type="date" v-model="mb.to" class="input" placeholder="To"/>
          <input type="number" v-model.number="mb.branch_id" class="input" placeholder="Branch ID (optional)"/>
          <label class="flex items-center gap-2 text-xs"><input type="checkbox" v-model="mb.truncate"/> Clear Existing</label>
        </div>
        <div class="flex gap-3">
          <button @click="runRebuildMonthly" class="btn bg-purple-600 text-white">Run</button>
        </div>
      </section>

      <!-- FX Revaluation (Scaffold) -->
      <section class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 space-y-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-sky-50 text-sky-600 rounded-2xl flex items-center justify-center text-xl"><span class="i-mdi-cash-sync"></span></div>
          <div>
            <h3 class="text-sm font-black text-slate-800">FX Revaluation (Scaffold)</h3>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Prepare for multi-currency rollout</p>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <input type="date" v-model="fx.date" class="input"/>
          <input v-model="fx.currency" class="input" placeholder="Currency (optional, e.g., USD)"/>
          <label class="flex items-center gap-2 text-xs"><input type="checkbox" v-model="fx.dry_run"/> Preview Only</label>
        </div>
        <div class="flex gap-3">
          <button @click="runFxRevalue" class="btn bg-sky-600 text-white">Run</button>
        </div>
      </section>

      <!-- CSV Exports -->
      <section class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 space-y-3">
        <h3 class="text-sm font-black text-slate-800">CSV Exports</h3>
        <div class="flex flex-wrap gap-3 text-xs">
          <a :href="`/api/admin/accounting/reports/trial-balance.csv?from=${exports.from}&to=${exports.to}`" class="chip">Trial Balance CSV</a>
          <a :href="`/api/admin/accounting/reports/income-expenditure.csv?from=${exports.from}&to=${exports.to}`" class="chip">Income & Expenditure CSV</a>
          <a :href="`/api/admin/accounting/reports/balance-sheet.csv?as_of=${exports.as_of}`" class="chip">Balance Sheet CSV</a>
          <a :href="`/api/admin/accounting/reports/cash-flows.csv?from=${exports.from}&to=${exports.to}`" class="chip">Cash Flows CSV</a>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <input type="date" v-model="exports.from" class="input" placeholder="From"/>
          <input type="date" v-model="exports.to" class="input" placeholder="To"/>
          <input type="date" v-model="exports.as_of" class="input" placeholder="As Of"/>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '../../http'

const banner = ref(null)
const periods = ref([])

const yec = ref({ period_id: '', retained_code: '', dry_run: true, close: true })
const rev = ref({ date: new Date().toISOString().slice(0,10), journal: '', dry_run: false })
const mb = ref({ from: '', to: '', branch_id: '', truncate: false })
const fx = ref({ date: new Date().toISOString().slice(0,10), currency: '', dry_run: true })
const exports = ref({ from: '', to: '', as_of: new Date().toISOString().slice(0,10) })

const notice = (type, title, message) => { banner.value = { type, title, message } ; setTimeout(() => banner.value=null, 8000) }

const load = async () => {
  try {
    const { data } = await axios.get('/api/admin/accounting/periods')
    periods.value = data || []
  } catch (e) {
    // Ignore if migrations pending
  }
}

const runYearEndClose = async () => {
  try {
    const { data } = await axios.post('/api/admin/accounting/ops/year-end-close', yec.value)
    notice('success', 'Year-End Close', `Net: ${data.net}; Journal: ${data.posted_journal_id || 'N/A'}`)
  } catch (e) {
    const msg = e?.response?.data?.message || e.message
    notice('error', 'Failed', msg)
  }
}

const runAutoReverse = async () => {
  try {
    const payload = { ...rev.value }
    if (!payload.journal) delete payload.journal
    const { data } = await axios.post('/api/admin/accounting/ops/auto-reverse', payload)
    notice('success', 'Auto-Reversals', data.output || 'Done')
  } catch (e) {
    const msg = e?.response?.data?.message || e.message
    notice('error', 'Failed', msg)
  }
}

const runRebuildMonthly = async () => {
  try {
    const payload = { ...mb.value }
    if (!payload.branch_id) delete payload.branch_id
    const { data } = await axios.post('/api/admin/accounting/ops/rebuild-monthly', payload)
    notice('success', 'Monthly Balances', `Months: ${data.months}; Rows: ${data.rows}`)
  } catch (e) {
    const msg = e?.response?.data?.message || e.message
    notice('error', 'Failed', msg)
  }
}

const runFxRevalue = async () => {
  try {
    const { data } = await axios.post('/api/admin/accounting/ops/fx/revalue', fx.value)
    notice('success', 'FX Revaluation', data.reason || 'Completed')
  } catch (e) {
    const msg = e?.response?.data?.message || e.message
    notice('error', 'Failed', msg)
  }
}

onMounted(load)
</script>

<style scoped>
@reference '../../style.css';
.input { @apply w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm; }
.btn { @apply px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider; }
.chip { @apply inline-flex items-center gap-1 px-3 py-1 rounded-full bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors; }
</style>
