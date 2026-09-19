<template>
  <div class="min-h-screen bg-gradient-to-br from-emerald-50 to-slate-50 p-4 sm:p-6 pb-24">
    <div class="max-w-5xl mx-auto">
      <div class="flex items-center gap-4 mb-6">
        <button @click="$router.push('/admin/portal')" class="w-10 h-10 bg-white rounded-2xl shadow-sm flex items-center justify-center text-slate-500 active:scale-95 transition-all">
          <span class="i-mdi-chevron-left text-2xl"></span>
        </button>
        <div>
          <p class="text-[10px] font-bold tracking-[0.2em] text-emerald-700 uppercase">Admin Portal</p>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Bank Reconciliation</h1>
        </div>
      </div>

      <div v-if="migrationPending" class="mb-6 p-4 border border-amber-300 bg-amber-50 rounded">
        <p class="text-amber-800 text-sm">Accounting extensions are not yet installed. Please run database migrations on the VPS after deployment, then refresh this page.</p>
      </div>

      <div v-if="!hasAccess" class="mb-6 p-4 border border-rose-300 bg-rose-50 rounded">
        <p class="text-rose-800 text-sm">You are not logged in as admin. Please <router-link to="/admin/login" class="underline font-semibold">login</router-link> to continue.</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Create Statement -->
        <section class="card p-5">
          <h2 class="font-bold text-lg text-slate-800 mb-2">Create Bank Statement</h2>
          <p class="text-sm text-slate-600 mb-4">Create a statement record for a bank account and period.</p>
          <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="lbl">Bank Account</label>
                <select v-if="bankAccounts.length" v-model.number="createStmt.bank_account_id" class="inp">
                  <option :value="null" disabled>Select bank account</option>
                  <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.name }} • {{ a.account_number }}</option>
                </select>
                <input v-else v-model.number="createStmt.bank_account_id" type="number" class="inp" placeholder="Enter Account ID" />
              </div>
              <div>
                <label class="lbl">Opening Balance</label>
                <input v-model.number="createStmt.opening_balance" type="number" step="0.01" class="inp" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="lbl">Period From</label>
                <input v-model="createStmt.period_from" type="date" class="inp" />
              </div>
              <div>
                <label class="lbl">Period To</label>
                <input v-model="createStmt.period_to" type="date" class="inp" />
              </div>
            </div>
            <div>
              <label class="lbl">Closing Balance</label>
              <input v-model.number="createStmt.closing_balance" type="number" step="0.01" class="inp" />
            </div>
            <div class="flex items-center gap-3">
              <button :disabled="!hasAccess || loading.create" @click="createStatement" class="btn-primary">
                <span v-if="loading.create" class="spinner mr-2"></span>
                Create Statement
              </button>
              <p v-if="createdStatement" class="text-xs text-emerald-700">Created ID: <span class="font-bold">{{ createdStatement.id }}</span></p>
            </div>
            <p v-if="errors.create" class="err">{{ errors.create }}</p>
          </div>
        </section>

        <!-- Import Lines -->
        <section class="card p-5">
          <h2 class="font-bold text-lg text-slate-800 mb-2">Import Statement Lines</h2>
          <p class="text-sm text-slate-600 mb-3">Paste CSV (date,amount,description,reference) or JSON array. One row per line.</p>
          <div class="grid grid-cols-2 gap-3 mb-3">
            <div>
              <label class="lbl">Statement ID</label>
              <input v-model.number="importForm.statement_id" type="number" class="inp" placeholder="e.g. 10" />
            </div>
            <div>
              <label class="lbl">Delimiter</label>
              <select v-model="importForm.delimiter" class="inp">
                <option value=",">Comma</option>
                <option value="\t">Tab</option>
                <option value=";">Semicolon</option>
              </select>
            </div>
          </div>
          <textarea v-model="importForm.raw" rows="6" class="w-full p-3 bg-slate-50 rounded-2xl text-sm font-mono border border-slate-200" placeholder="2026-09-01,12000,POS REVERSAL,PR123\n2026-09-02,-5000,ATM Cash Withdrawal,ATM002"></textarea>
          <div class="flex items-center gap-3 mt-3">
            <button :disabled="!hasAccess || loading.import" @click="importLines" class="btn-primary">
              <span v-if="loading.import" class="spinner mr-2"></span>
              Import Lines
            </button>
            <p v-if="importedCount !== null" class="text-xs text-emerald-700">Imported: <span class="font-bold">{{ importedCount }}</span></p>
          </div>
          <p v-if="errors.import" class="err">{{ errors.import }}</p>
        </section>

        <!-- Auto Match -->
        <section class="card p-5">
          <h2 class="font-bold text-lg text-slate-800 mb-2">Auto Match</h2>
          <p class="text-sm text-slate-600 mb-3">Automatically match statement lines to ledger entries within date tolerance.</p>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="lbl">Statement ID</label>
              <input v-model.number="matchForm.statement_id" type="number" class="inp" />
            </div>
            <div>
              <label class="lbl">Days Tolerance</label>
              <input v-model.number="matchForm.days" type="number" class="inp" />
            </div>
          </div>
          <div class="flex items-center gap-3 mt-3">
            <button :disabled="!hasAccess || loading.match" @click="autoMatch" class="btn-primary">
              <span v-if="loading.match" class="spinner mr-2"></span>
              Run Auto-Match
            </button>
            <p v-if="matchedCount !== null" class="text-xs text-emerald-700">Matched: <span class="font-bold">{{ matchedCount }}</span></p>
          </div>
          <p v-if="errors.match" class="err">{{ errors.match }}</p>
        </section>

        <!-- Reconciliation Session -->
        <section class="card p-5">
          <h2 class="font-bold text-lg text-slate-800 mb-2">Reconciliation Session</h2>
          <p class="text-sm text-slate-600 mb-4">Start and finalize reconciliation for a period.</p>
          <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="lbl">Bank Account</label>
                <select v-if="bankAccounts.length" v-model.number="recForm.bank_account_id" class="inp">
                  <option :value="null" disabled>Select bank account</option>
                  <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.name }} • {{ a.account_number }}</option>
                </select>
                <input v-else v-model.number="recForm.bank_account_id" type="number" class="inp" placeholder="Enter Account ID" />
              </div>
              <div>
                <label class="lbl">Ending Balance</label>
                <input v-model.number="recForm.ending_balance" type="number" step="0.01" class="inp" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="lbl">From</label>
                <input v-model="recForm.period_from" type="date" class="inp" />
              </div>
              <div>
                <label class="lbl">To</label>
                <input v-model="recForm.period_to" type="date" class="inp" />
              </div>
            </div>
            <div class="flex items-center gap-3">
              <button :disabled="!hasAccess || loading.recStart" @click="startReconciliation" class="btn-primary">
                <span v-if="loading.recStart" class="spinner mr-2"></span>
                Start Reconciliation
              </button>
              <p v-if="reconciliation" class="text-xs text-emerald-700">Started ID: <span class="font-bold">{{ reconciliation.id }}</span></p>
            </div>
            <div class="grid grid-cols-[1fr_auto] gap-3 items-end">
              <div>
                <label class="lbl">Reconciliation ID</label>
                <input v-model.number="recFinalizeId" type="number" class="inp" />
              </div>
              <button :disabled="!hasAccess || loading.recFinalize" @click="finalizeReconciliation" class="btn-muted h-12 px-4">
                <span v-if="loading.recFinalize" class="spinner mr-2"></span>
                Finalize
              </button>
            </div>
            <p v-if="errors.rec" class="err">{{ errors.rec }}</p>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '../../http.js'

const adminToken = localStorage.getItem('admin_token')
const memberToken = localStorage.getItem('token')
const isAdmin = localStorage.getItem('is_admin') === 'true'
const hasAccess = computed(() => !!adminToken || (!!memberToken && isAdmin))

const migrationPending = ref(false)
const bankAccounts = ref([])

const loading = ref({ create: false, import: false, match: false, recStart: false, recFinalize: false })
const errors = ref({ create: '', import: '', match: '', rec: '' })

const createStmt = ref({ bank_account_id: null, period_from: '', period_to: '', opening_balance: 0, closing_balance: 0 })
const createdStatement = ref(null)

const importForm = ref({ statement_id: null, raw: '', delimiter: ',' })
const importedCount = ref(null)

const matchForm = ref({ statement_id: null, days: 7 })
const matchedCount = ref(null)

const recForm = ref({ bank_account_id: null, period_from: '', period_to: '', ending_balance: 0 })
const reconciliation = ref(null)
const recFinalizeId = ref(null)

const handle503 = (e) => {
  const status = e?.response?.status
  if (status === 503) migrationPending.value = true
}

const createStatement = async () => {
  errors.value.create = ''
  createdStatement.value = null
  try {
    loading.value.create = true
    const { data } = await axios.post('/api/admin/accounting/bank-statements', createStmt.value)
    createdStatement.value = data
  } catch (e) {
    handle503(e)
    errors.value.create = e?.response?.data?.message || 'Failed to create statement'
  } finally {
    loading.value.create = false
  }
}

function parseCsv(text, delimiter) {
  const lines = (text || '').split(/\r?\n/).filter(l => l.trim().length > 0)
  return lines.map(l => {
    const parts = l.split(delimiter)
    return {
      date: parts[0]?.trim() || null,
      amount: Number(parts[1] || 0),
      description: parts[2]?.trim() || null,
      reference: parts[3]?.trim() || null,
    }
  })
}

const importLines = async () => {
  errors.value.import = ''
  importedCount.value = null
  if (!importForm.value.statement_id) {
    errors.value.import = 'Statement ID is required'
    return
  }
  let lines
  try {
    const raw = importForm.value.raw.trim()
    if (raw.startsWith('[')) {
      lines = JSON.parse(raw)
    } else {
      lines = parseCsv(raw, importForm.value.delimiter)
    }
  } catch (e) {
    errors.value.import = 'Invalid CSV/JSON input'
    return
  }
  try {
    loading.value.import = true
    const { data } = await axios.post(`/api/admin/accounting/bank-statements/${importForm.value.statement_id}/import-lines`, { lines })
    importedCount.value = data?.imported ?? 0
  } catch (e) {
    handle503(e)
    errors.value.import = e?.response?.data?.message || 'Failed to import lines'
  } finally {
    loading.value.import = false
  }
}

const autoMatch = async () => {
  errors.value.match = ''
  matchedCount.value = null
  if (!matchForm.value.statement_id) {
    errors.value.match = 'Statement ID is required'
    return
  }
  try {
    loading.value.match = true
    const { data } = await axios.post(`/api/admin/accounting/bank-statements/${matchForm.value.statement_id}/auto-match`, { days_tolerance: matchForm.value.days })
    matchedCount.value = data?.matched ?? 0
  } catch (e) {
    handle503(e)
    errors.value.match = e?.response?.data?.message || 'Auto-match failed'
  } finally {
    loading.value.match = false
  }
}

const startReconciliation = async () => {
  errors.value.rec = ''
  reconciliation.value = null
  try {
    loading.value.recStart = true
    const { data } = await axios.post('/api/admin/accounting/reconciliations/start', recForm.value)
    reconciliation.value = data
    recFinalizeId.value = data?.id || null
  } catch (e) {
    handle503(e)
    errors.value.rec = e?.response?.data?.message || 'Failed to start reconciliation'
  } finally {
    loading.value.recStart = false
  }
}

const finalizeReconciliation = async () => {
  errors.value.rec = ''
  if (!recFinalizeId.value) {
    errors.value.rec = 'Reconciliation ID is required'
    return
  }
  try {
    loading.value.recFinalize = true
    await axios.post(`/api/admin/accounting/reconciliations/${recFinalizeId.value}/finalize`)
    alert('Reconciliation finalized')
  } catch (e) {
    handle503(e)
    errors.value.rec = e?.response?.data?.message || 'Failed to finalize reconciliation'
  } finally {
    loading.value.recFinalize = false
  }
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/admin/accounting/bank-accounts')
    bankAccounts.value = Array.isArray(data) ? data : []
  } catch (e) {
    handle503(e)
  }
})
</script>

<style scoped>
@reference '../../style.css';
.card { @apply bg-white rounded-2xl border border-slate-200 shadow-sm; }
.inp { @apply w-full px-4 py-3 bg-slate-50 rounded-2xl text-sm font-medium outline-none border border-slate-200 focus:ring-2 focus:ring-emerald-500; }
.lbl { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-1 block; }
.btn-primary { @apply bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg shadow flex items-center; }
.btn-muted { @apply bg-white border border-slate-200 text-slate-700 px-3 py-2 rounded-lg shadow-sm; }
.spinner { @apply inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin; }
.err { @apply text-rose-600 text-sm mt-2; }
</style>
