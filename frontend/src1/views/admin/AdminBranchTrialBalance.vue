<template>
  <div class="min-h-screen bg-gradient-to-br from-purple-50 to-slate-50 p-4 sm:p-6 pb-24">
    <div class="max-w-5xl mx-auto">
      <div class="flex items-center gap-4 mb-6">
        <button @click="$router.push('/admin/portal')" class="w-10 h-10 bg-white rounded-2xl shadow-sm flex items-center justify-center text-slate-500 active:scale-95 transition-all">
          <span class="i-mdi-chevron-left text-2xl"></span>
        </button>
        <div>
          <p class="text-[10px] font-bold tracking-[0.2em] text-purple-700 uppercase">Admin Portal</p>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Branch Trial Balance</h1>
        </div>
      </div>

      <div v-if="migrationPending" class="mb-6 p-4 border border-amber-300 bg-amber-50 rounded">
        <p class="text-amber-800 text-sm">Branch segmentation is not yet enabled. Please run database migrations on the VPS after deployment, then refresh this page.</p>
      </div>

      <div v-if="!hasAccess" class="mb-6 p-4 border border-rose-300 bg-rose-50 rounded">
        <p class="text-rose-800 text-sm">You are not logged in as admin. Please <router-link to="/admin/login" class="underline font-semibold">login</router-link> to continue.</p>
      </div>

      <section class="card p-5">
        <h2 class="font-bold text-lg text-slate-800 mb-2">Generate Trial Balance</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
          <div>
            <label class="lbl">Branch</label>
            <select v-if="branches.length" v-model.number="filters.branchId" class="inp">
              <option :value="null" disabled>Select branch</option>
              <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }} ({{ b.code || b.id }})</option>
            </select>
            <input v-else v-model.number="filters.branchId" type="number" class="inp" placeholder="Enter Branch ID" />
          </div>
          <div>
            <label class="lbl">From</label>
            <input v-model="filters.from" type="date" class="inp" />
          </div>
          <div>
            <label class="lbl">To</label>
            <input v-model="filters.to" type="date" class="inp" />
          </div>
          <div class="flex gap-2">
            <button :disabled="!hasAccess || loading" @click="loadTb" class="btn-primary flex-1">
              <span v-if="loading" class="spinner mr-2"></span>
              Load
            </button>
            <button :disabled="!tb || !tbRows.length" @click="exportCsv" class="btn-muted">Export CSV</button>
          </div>
        </div>
        <p v-if="errors.main" class="err mt-2">{{ errors.main }}</p>
      </section>

      <section v-if="tb" class="card p-5 mt-6">
        <h3 class="font-bold text-slate-800 mb-3">Trial Balance</h3>
        <div v-if="tbRows.length" class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-[10px] uppercase text-slate-400 border-b">
                <th class="py-2 pr-4">Code</th>
                <th class="py-2 pr-4">Account</th>
                <th class="py-2 pr-4 text-right">Debit</th>
                <th class="py-2 pr-4 text-right">Credit</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, idx) in tbRows" :key="idx" class="border-b last:border-0">
                <td class="py-2 pr-4 font-mono text-xs">{{ r.code || '-' }}</td>
                <td class="py-2 pr-4">{{ r.name || r.account || '-' }}</td>
                <td class="py-2 pr-4 text-right">{{ formatMoney(r.debit || 0) }}</td>
                <td class="py-2 pr-4 text-right">{{ formatMoney(r.credit || 0) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <pre v-else class="bg-slate-50 p-3 rounded-xl text-xs overflow-x-auto">{{ tb }}</pre>
      </section>
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
const loading = ref(false)
const errors = ref({ main: '' })

const filters = ref({ branchId: null, from: '', to: '' })
const tb = ref(null)
const branches = ref([])

const handle503 = (e) => {
  const status = e?.response?.status
  if (status === 503) migrationPending.value = true
}

const loadTb = async () => {
  errors.value.main = ''
  tb.value = null
  if (!filters.value.branchId) {
    errors.value.main = 'Branch ID is required'
    return
  }
  const params = {}
  if (filters.value.from) params.from = filters.value.from
  if (filters.value.to) params.to = filters.value.to
  try {
    loading.value = true
    const { data } = await axios.get(`/api/admin/accounting/branches/${filters.value.branchId}/trial-balance`, { params })
    tb.value = data
  } catch (e) {
    handle503(e)
    errors.value.main = e?.response?.data?.message || 'Failed to load trial balance'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/branches')
    branches.value = Array.isArray(data) ? data : (Array.isArray(data?.data) ? data.data : [])
    if (!filters.value.branchId && branches.value.length) {
      filters.value.branchId = branches.value[0]?.id || null
    }
  } catch (_) {}
})

const tbRows = computed(() => {
  if (!tb.value) return []
  // Try common shapes: { accounts: [...] } or array
  if (Array.isArray(tb.value)) return tb.value
  if (Array.isArray(tb.value.accounts)) return tb.value.accounts
  if (Array.isArray(tb.value.rows)) return tb.value.rows
  return []
})

const formatMoney = (n) => {
  try { return Number(n || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) } catch { return n }
}

const exportCsv = () => {
  const header = 'Code,Account,Debit,Credit\n'
  const lines = tbRows.value.map(r => [r.code || '', escapeCsv(r.name || r.account || ''), (r.debit || 0), (r.credit || 0)].join(','))
  const csv = header + lines.join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `branch_tb_${filters.value.branchId}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

function escapeCsv(v) {
  if (v == null) return ''
  const s = String(v)
  if (s.includes(',') || s.includes('"') || s.includes('\n')) {
    return '"' + s.replaceAll('"', '""') + '"'
  }
  return s
}
</script>

<style scoped>
@reference '../../style.css';
.card { @apply bg-white rounded-2xl border border-slate-200 shadow-sm; }
.inp { @apply w-full px-4 py-3 bg-slate-50 rounded-2xl text-sm font-medium outline-none border border-slate-200 focus:ring-2 focus:ring-purple-500; }
.lbl { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-1 block; }
.btn-primary { @apply bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg shadow flex items-center; }
.btn-muted { @apply bg-white border border-slate-200 text-slate-700 px-3 py-2 rounded-lg shadow-sm; }
.spinner { @apply inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin; }
.err { @apply text-rose-600 text-sm; }
</style>
