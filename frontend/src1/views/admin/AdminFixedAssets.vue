<template>
  <div class="min-h-screen bg-gradient-to-br from-indigo-50 to-slate-50 p-4 sm:p-6 pb-24">
    <div class="max-w-5xl mx-auto">
      <div class="flex items-center gap-4 mb-6">
        <button @click="$router.push('/admin/portal')" class="w-10 h-10 bg-white rounded-2xl shadow-sm flex items-center justify-center text-slate-500 active:scale-95 transition-all">
          <span class="i-mdi-chevron-left text-2xl"></span>
        </button>
        <div>
          <p class="text-[10px] font-bold tracking-[0.2em] text-indigo-700 uppercase">Admin Portal</p>
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Fixed Assets</h1>
        </div>
      </div>

      <div v-if="migrationPending" class="mb-6 p-4 border border-amber-300 bg-amber-50 rounded">
        <p class="text-amber-800 text-sm">Fixed assets module is not yet installed. Please run database migrations on the VPS after deployment, then refresh this page.</p>
      </div>

      <div v-if="!hasAccess" class="mb-6 p-4 border border-rose-300 bg-rose-50 rounded">
        <p class="text-rose-800 text-sm">You are not logged in as admin. Please <router-link to="/admin/login" class="underline font-semibold">login</router-link> to continue.</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Create Category -->
        <section class="card p-5">
          <h2 class="font-bold text-lg text-slate-800 mb-2">Create Asset Category</h2>
          <p class="text-sm text-slate-600 mb-3">Define default accounts and depreciation method.</p>
          <div class="space-y-3">
            <div>
              <label class="lbl">Name</label>
              <input v-model="catForm.name" type="text" class="inp" placeholder="e.g. Motor Vehicles" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="lbl">Method</label>
                <select v-model="catForm.method" class="inp">
                  <option value="straight_line">Straight Line</option>
                  <option value="reducing_balance">Reducing Balance</option>
                </select>
              </div>
              <div>
                <label class="lbl">Useful Life (months)</label>
                <input v-model.number="catForm.useful_life_months" type="number" min="1" class="inp" />
              </div>
            </div>
            <div>
              <label class="lbl">Rate (%)</label>
              <input v-model.number="catForm.rate_percent" type="number" step="0.01" class="inp" />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label class="lbl">Asset Account ID</label>
                <input v-model.number="catForm.asset_account_id" type="number" class="inp" />
              </div>
              <div>
                <label class="lbl">Accum Depreciation Acct ID</label>
                <input v-model.number="catForm.accum_dep_account_id" type="number" class="inp" />
              </div>
              <div>
                <label class="lbl">Dep Expense Acct ID</label>
                <input v-model.number="catForm.dep_expense_account_id" type="number" class="inp" />
              </div>
            </div>
            <div class="flex items-center gap-3">
              <button :disabled="!hasAccess || loading.cat" @click="createCategory" class="btn-primary">
                <span v-if="loading.cat" class="spinner mr-2"></span>
                Create Category
              </button>
              <p v-if="catCreated" class="text-xs text-emerald-700">Created ID: <span class="font-bold">{{ catCreated.id }}</span></p>
            </div>
            <p v-if="errors.cat" class="err">{{ errors.cat }}</p>
          </div>
        </section>

        <!-- Create Asset -->
        <section class="card p-5">
          <h2 class="font-bold text-lg text-slate-800 mb-2">Create Asset</h2>
          <p class="text-sm text-slate-600 mb-3">Record a new fixed asset and optionally pre-generate schedule.</p>
          <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="lbl">Category ID</label>
                <input v-model.number="assetForm.asset_category_id" type="number" class="inp" />
              </div>
              <div>
                <label class="lbl">Code</label>
                <input v-model="assetForm.code" type="text" class="inp" placeholder="Optional" />
              </div>
            </div>
            <div>
              <label class="lbl">Name</label>
              <input v-model="assetForm.name" type="text" class="inp" placeholder="e.g. Toyota Corolla" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="lbl">Acquisition Date</label>
                <input v-model="assetForm.acquisition_date" type="date" class="inp" />
              </div>
              <div>
                <label class="lbl">Acquisition Cost</label>
                <input v-model.number="assetForm.acquisition_cost" type="number" step="0.01" class="inp" />
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="lbl">Residual Value</label>
                <input v-model.number="assetForm.residual_value" type="number" step="0.01" class="inp" />
              </div>
              <div>
                <label class="lbl">Branch ID</label>
                <input v-model.number="assetForm.branch_id" type="number" class="inp" placeholder="Optional" />
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label class="lbl">Asset Account ID</label>
                <input v-model.number="assetForm.asset_account_id" type="number" class="inp" placeholder="Optional" />
              </div>
              <div>
                <label class="lbl">Accum Depreciation Acct ID</label>
                <input v-model.number="assetForm.accum_dep_account_id" type="number" class="inp" placeholder="Optional" />
              </div>
              <div>
                <label class="lbl">Dep Expense Acct ID</label>
                <input v-model.number="assetForm.dep_expense_account_id" type="number" class="inp" placeholder="Optional" />
              </div>
            </div>
            <div class="flex items-center gap-2">
              <input id="schedule" v-model="assetForm.schedule" type="checkbox" class="w-4 h-4" />
              <label for="schedule" class="text-sm text-slate-700">Pre-generate depreciation schedule</label>
            </div>
            <div class="flex items-center gap-3">
              <button :disabled="!hasAccess || loading.asset" @click="createAsset" class="btn-primary">
                <span v-if="loading.asset" class="spinner mr-2"></span>
                Create Asset
              </button>
              <p v-if="assetCreated" class="text-xs text-emerald-700">Created ID: <span class="font-bold">{{ assetCreated.id }}</span></p>
            </div>
            <p v-if="errors.asset" class="err">{{ errors.asset }}</p>
          </div>
        </section>

        <!-- Post Depreciation -->
        <section class="card p-5">
          <h2 class="font-bold text-lg text-slate-800 mb-2">Post Depreciation</h2>
          <p class="text-sm text-slate-600 mb-3">Post a single scheduled depreciation by ID (from schedule).</p>
          <div class="grid grid-cols-[1fr_auto] gap-3 items-end">
            <div>
              <label class="lbl">Depreciation ID</label>
              <input v-model.number="depId" type="number" class="inp" />
            </div>
            <button :disabled="!hasAccess || loading.dep" @click="postDepreciation" class="btn-muted h-12 px-4">
              <span v-if="loading.dep" class="spinner mr-2"></span>
              Post
            </button>
          </div>
          <div v-if="depResult" class="mt-3 p-3 bg-emerald-50 border border-emerald-200 rounded text-emerald-800 text-sm">
            <p class="font-semibold">Posted.</p>
            <p v-if="depResult.journal">Journal ID: {{ depResult.journal.id }} <span v-if="depResult.journal.number">(No. {{ depResult.journal.number }})</span></p>
          </div>
          <p v-if="errors.dep" class="err">{{ errors.dep }}</p>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from '../../http.js'

const adminToken = localStorage.getItem('admin_token')
const memberToken = localStorage.getItem('token')
const isAdmin = localStorage.getItem('is_admin') === 'true'
const hasAccess = computed(() => !!adminToken || (!!memberToken && isAdmin))

const migrationPending = ref(false)
const loading = ref({ cat: false, asset: false, dep: false })
const errors = ref({ cat: '', asset: '', dep: '' })

const catForm = ref({
  name: '', method: 'straight_line', useful_life_months: 60, rate_percent: 0,
  asset_account_id: null, accum_dep_account_id: null, dep_expense_account_id: null
})
const catCreated = ref(null)

const assetForm = ref({
  asset_category_id: null, name: '', code: '', acquisition_date: '', acquisition_cost: 0,
  residual_value: 0, asset_account_id: null, accum_dep_account_id: null, dep_expense_account_id: null,
  branch_id: null, schedule: true
})
const assetCreated = ref(null)

const depId = ref(null)
const depResult = ref(null)

const handle503 = (e) => {
  const status = e?.response?.status
  if (status === 503) migrationPending.value = true
}

const createCategory = async () => {
  errors.value.cat = ''
  catCreated.value = null
  try {
    loading.value.cat = true
    const { data } = await axios.post('/api/admin/accounting/assets/categories', catForm.value)
    catCreated.value = data
  } catch (e) {
    handle503(e)
    errors.value.cat = e?.response?.data?.message || 'Failed to create category'
  } finally {
    loading.value.cat = false
  }
}

const createAsset = async () => {
  errors.value.asset = ''
  assetCreated.value = null
  try {
    loading.value.asset = true
    const { data } = await axios.post('/api/admin/accounting/assets', assetForm.value)
    assetCreated.value = data
  } catch (e) {
    handle503(e)
    errors.value.asset = e?.response?.data?.message || 'Failed to create asset'
  } finally {
    loading.value.asset = false
  }
}

const postDepreciation = async () => {
  errors.value.dep = ''
  depResult.value = null
  if (!depId.value) {
    errors.value.dep = 'Depreciation ID is required'
    return
  }
  try {
    loading.value.dep = true
    const { data } = await axios.post(`/api/admin/accounting/assets/depreciations/${depId.value}/post`)
    depResult.value = data
  } catch (e) {
    handle503(e)
    errors.value.dep = e?.response?.data?.message || 'Failed to post depreciation'
  } finally {
    loading.value.dep = false
  }
}
</script>

<style scoped>
@reference '../../style.css';
.card { @apply bg-white rounded-2xl border border-slate-200 shadow-sm; }
.inp { @apply w-full px-4 py-3 bg-slate-50 rounded-2xl text-sm font-medium outline-none border border-slate-200 focus:ring-2 focus:ring-indigo-500; }
.lbl { @apply text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-1 block; }
.btn-primary { @apply bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow flex items-center; }
.btn-muted { @apply bg-white border border-slate-200 text-slate-700 px-3 py-2 rounded-lg shadow-sm; }
.spinner { @apply inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin; }
.err { @apply text-rose-600 text-sm mt-2; }
</style>
