<template>
  <div class="min-h-screen bg-slate-50 font-sans">
    <AppHeader title="Passbook" :showBack="true">
      <template #right>
        <div class="flex items-center gap-1">
          <a :href="getDownloadUrl('pdf')" target="_blank" class="p-2 text-xs font-bold text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors">PDF</a>
          <a :href="getDownloadUrl('csv')" target="_blank" class="p-2 text-xs font-bold text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors">CSV</a>
        </div>
      </template>
    </AppHeader>

    <div class="max-w-5xl mx-auto p-4 pb-32 space-y-6">
      <div v-if="loadError" class="card p-4 border border-rose-200 bg-rose-50 text-rose-700 text-sm">
        {{ loadError }}
      </div>
      <!-- Yearly summary -->
      <div v-if="!isLoading" class="bg-gradient-to-br from-emerald-700 to-emerald-900 rounded-[2rem] p-6 text-white shadow-xl">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-emerald-100 text-[10px] font-bold uppercase tracking-widest">Total Account Balance (₦)</p>
            <p class="text-3xl font-extrabold tracking-tight mt-1">{{ Number(grandTotal).toLocaleString() }}</p>
          </div>
          <div class="bg-white/10 rounded-xl px-3 py-2 text-xs">
            <span class="opacity-80 mr-1">Year</span>
            <select v-model.number="selectedYear" @change="fetchPassbook" class="bg-transparent outline-none font-bold">
              <option v-for="y in years" :key="y" :value="y" class="text-slate-900">{{ y }}</option>
            </select>
          </div>
        </div>
        <p v-if="dividendAmount !== null" class="mt-2 text-[11px] text-emerald-100">
          Est. Dividend ({{ selectedYear }}):
          <span class="font-black text-white">₦ {{ Number(dividendAmount).toLocaleString() }}</span>
        </p>
      </div>
      <div v-else class="rounded-[2rem] p-6 shadow-xl bg-slate-200/60 animate-pulse h-28"></div>

      <!-- Grid -->
      <div v-if="!isLoading" class="card card-elevated overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-800 text-white text-[10px] uppercase">
                <th class="p-3 sticky left-0 bg-slate-800 z-10 border-r border-slate-700">Scheme</th>
                <th class="p-3 text-center min-w-[64px] border-r border-slate-700 bg-slate-900/40">BF</th>
                <th v-for="(m, i) in months" :key="i" class="p-3 text-center min-w-[64px] border-r border-slate-700">{{ m }}</th>
                <th class="p-3 text-center bg-emerald-700">Total</th>
              </tr>
            </thead>
            <tbody class="text-[11px]">
              <tr v-for="(row, idx) in matrix" :key="idx" 
                  :class="[row.is_exceptional ? 'bg-amber-50/30' : 'hover:bg-slate-50', 'border-b border-slate-100']">
                <td class="p-3 font-bold text-slate-700 sticky left-0 border-r border-slate-100 shadow-[2px_0_5px_rgba(0,0,0,0.05)]"
                    :class="row.is_exceptional ? 'bg-amber-50' : 'bg-white'">
                  {{ row.scheme_name }}
                  <span v-if="row.is_exceptional" class="block text-[8px] text-amber-600 font-normal uppercase mt-0.5">Exceptional</span>
                </td>
                <td class="p-3 text-center border-r border-slate-50 text-slate-700 font-semibold">
                  {{ Number(row.bf ?? row.brought_forward ?? 0) !== 0 ? Number(row.bf ?? row.brought_forward ?? 0).toLocaleString() : '-' }}
                </td>
                <td v-for="mIdx in 12" :key="mIdx" class="p-3 text-center border-r border-slate-50"
                    :class="Number(row.months[mIdx] || 0) < 0 ? 'text-rose-600' : 'text-slate-600'">
                  {{ Number(row.months[mIdx] || 0) !== 0 ? Number(row.months[mIdx]).toLocaleString() : '-' }}
                </td>
                <td class="p-3 text-center font-black text-slate-900 bg-slate-50">
                  {{ Number(row.total).toLocaleString() }}
                </td>
              </tr>
            </tbody>
            <tfoot class="text-[11px] bg-slate-50 font-black border-t-2 border-slate-200">
              <tr>
                <td class="p-3 sticky left-0 bg-slate-50 z-10 border-r border-slate-100">GRAND TOTAL</td>
                <td class="p-3 text-center border-r border-slate-50 text-slate-900"
                    :class="bfTotal < 0 ? 'text-rose-600' : ''">
                  {{ Number(bfTotal).toLocaleString() }}
                </td>
                <td v-for="mIdx in 12" :key="mIdx" class="p-3 text-center border-r border-slate-50 text-slate-900"
                    :class="monthlyTotals[mIdx] < 0 ? 'text-rose-600' : ''">
                  {{ monthlyTotals[mIdx] !== 0 ? Number(monthlyTotals[mIdx]).toLocaleString() : '-' }}
                </td>
                <td class="p-3 text-center bg-emerald-50"
                    :class="grandTotal < 0 ? 'text-rose-700 bg-rose-50' : 'text-emerald-700'">
                  {{ Number(grandTotal).toLocaleString() }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
      <div v-else class="card card-elevated p-6 animate-pulse space-y-3">
        <div class="h-4 bg-slate-200 rounded"></div>
        <div class="h-4 bg-slate-200 rounded"></div>
        <div class="h-4 bg-slate-200 rounded"></div>
      </div>

      <p class="text-[10px] text-gray-400 mt-4 px-2 italic text-center">Swipe left/right to view all months</p>

      <div v-if="!isLoading && showAgm" class="card p-4 border-emerald-200 bg-emerald-50">
        <div class="flex items-center justify-between mb-2">
          <p class="text-[10px] text-emerald-700 font-black uppercase tracking-widest">{{ selectedYear }} AGM Fee</p>
          <span :class="agmPaid ? 'bg-emerald-200 text-emerald-800' : 'bg-yellow-200 text-yellow-800'"
                class="px-2 py-1 rounded-full text-[10px] font-black uppercase">
            {{ agmPaid ? 'Paid' : 'Pending' }}
          </span>
        </div>
        <div class="flex items-center justify-between">
          <p class="text-slate-700 text-sm">Mandatory annual meeting fee</p>
          <p class="text-slate-900 font-black">₦ {{ Number(agmAmount).toLocaleString() }}</p>
        </div>
      </div>
      <div v-else-if="!isLoading && !showAgm" class="hidden"></div>
      <div v-else class="card p-4 animate-pulse h-20"></div>

      <!-- Recent History -->
      <div v-if="!isLoading" class="space-y-4">
        <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest ml-2">Recent History</h3>
        <div class="space-y-3">
          <div v-for="con in contributions" :key="con.id" class="bg-white p-4 rounded-[2rem] border border-slate-100 shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <p class="text-xs font-black text-slate-800">{{ con.scheme?.name }}</p>
                <p class="text-[9px] font-bold text-slate-400 uppercase">{{ new Date(con.paid_at || con.created_at).toLocaleDateString() }} • {{ con.payment_method }}</p>
                <p v-if="con.notes" class="text-[9px] font-medium text-slate-500 mt-1 italic">{{ con.notes }}</p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-sm font-black text-slate-800" :class="con.amount < 0 ? 'text-rose-600' : ''">
                {{ con.amount < 0 ? '-' : '' }}₦{{ Number(Math.abs(con.amount)).toLocaleString() }}
              </p>
              <p class="text-[8px] font-bold uppercase tracking-tighter" :class="con.status === 'success' ? 'text-emerald-500' : 'text-amber-500'">{{ con.status }}</p>
            </div>
          </div>

          <div v-if="contributions.length === 0" class="text-center py-8 bg-white rounded-[2rem] border border-dashed border-slate-200">
            <p class="text-xs font-bold text-slate-400">No recent history found.</p>
          </div>
        </div>
      </div>
      <div v-else class="space-y-3">
        <div v-for="i in 3" :key="i" class="h-20 bg-slate-200/60 animate-pulse rounded-[2rem]"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import AppHeader from '../components/AppHeader.vue'
import { ref, onMounted, computed, onUnmounted } from 'vue'
import axios from '../http.js'
import { getEcho } from '../realtime/echo'

const months = ref(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'])
const years = [new Date().getFullYear() - 1, new Date().getFullYear(), new Date().getFullYear() + 1]
const selectedYear = ref(new Date().getFullYear())
const matrix = ref([])
const contributions = ref([])
const grandTotal = ref(0)
const bfTotal = ref(0)
const agmAmount = ref(0)
const agmPaid = ref(false)
const dividendAmount = ref(null)
const isLoading = ref(true)
const loadError = ref('')

// Only show the AGM card if backend provided data (amount > 0) or payment status is true
const showAgm = computed(() => Number(agmAmount.value) > 0 || Boolean(agmPaid.value))

const monthlyTotals = computed(() => {
  const totals = Array(13).fill(0)
  matrix.value.forEach(row => {
    if (row.is_exceptional) return
    for (let m = 1; m <= 12; m++) {
      totals[m] += Number(row.months[m] || 0)
    }
  })
  return totals
})

const fetchPassbook = async () => {
  const token = localStorage.getItem('token')
  isLoading.value = true
  loadError.value = ''
  try {
    const { data } = await axios.get(`/api/passbook/${selectedYear.value}`, { headers: { Authorization: `Bearer ${token}` } })
    matrix.value = data.matrix
    if (data.month_labels) {
      months.value = data.month_labels
    }
    grandTotal.value = data.grand_total
    bfTotal.value = data.bf_total || 0

    // Fetch recent history
    await fetchContributions()

    // Optional fields for AGM fee tracking; dynamic per year with sensible fallbacks
    const amountKey = `agm_fee_${selectedYear.value}_amount`
    const paidKey = `agm_fee_${selectedYear.value}_paid`
    agmAmount.value = (data && (data[amountKey] ?? data.agm_fee_amount)) ?? 0
    agmPaid.value = Boolean(data && (data[paidKey] ?? data.agm_fee_paid ?? false))

    // Also fetch dividend for selected year; failure is non-fatal
    try {
      const { data: div } = await axios.get(`/api/reports/dividend/${selectedYear.value}`, { headers: { Authorization: `Bearer ${token}` } })
      dividendAmount.value = div?.dividend ?? 0
    } catch (_) {
      dividendAmount.value = null
    }
  } catch (e) {
    console.error('Failed to load passbook', e)
    loadError.value = e?.response?.data?.message || 'Failed to load passbook'
    // Provide safe defaults when API fails
    agmAmount.value = 0
    agmPaid.value = false
    dividendAmount.value = null
  } finally {
    isLoading.value = false
  }
}

const fetchContributions = async () => {
  try {
    const { data } = await axios.get('/api/passbook/contributions')
    contributions.value = data.data || []
  } catch (e) {
    console.error('Failed to fetch contributions', e)
  }
}

const getDownloadUrl = (format) => {
  const token = localStorage.getItem('token')
  const baseUrl = axios.defaults.baseURL || ''
  const endpoint = format === 'csv' ? 'download-passbook-csv' : 'download-passbook'
  return `${baseUrl}/api/${endpoint}?year=${selectedYear.value}&token=${encodeURIComponent(token)}`
}
onMounted(async () => {
  await fetchPassbook()

  // Real-time listener
  try {
    const echo = getEcho()
    if (!echo) return

    const token = localStorage.getItem('token')
    if (token) {
      // Get profile to know user ID
      const { data: userData } = await axios.get('/api/profile', { headers: { Authorization: `Bearer ${token}` } })
      const userId = userData.id

      if (userId) {
        echo.private(`user.${userId}`)
          .listen('UserAccountUpdated', (e) => {
            console.log('Real-time update received in Passbook:', e)
            fetchPassbook() // Just refresh everything to be sure
          })
      }
    }
  } catch (err) {
    console.error('Failed to initialize real-time listener in Passbook:', err)
  }
})

onUnmounted(() => {
  // Echo cleanup
  try {
    const echo = getEcho()
    const userId = localStorage.getItem('user_id')
    if (echo && userId) {
      echo.leave(`user.${userId}`)
    }
  } catch (_) {}
})
</script>

<style scoped>
.overflow-x-auto {
  -webkit-overflow-scrolling: touch;
}
</style>
