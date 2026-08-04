<template>
  <div class="min-h-screen bg-slate-50 pb-32">
    <header class="p-6 bg-white border-b sticky top-0 z-20 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button @click="$router.push(`/admin/members/${$route.params.id}`)" class="w-10 h-10 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-500">
          <span class="i-mdi-chevron-left text-xl"></span>
        </button>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center font-black text-sm overflow-hidden">
            <img v-if="user?.passport_url" :src="getImageUrl(user.passport_url)" class="w-full h-full object-cover" />
            <span v-else>{{ user?.full_name?.charAt(0) }}</span>
          </div>
          <div>
            <h1 class="text-base font-black text-slate-800 tracking-tight leading-none">Passbook</h1>
            <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-[0.2em] mt-1">{{ user?.full_name }}</p>
          </div>
        </div>
      </div>
      <div class="flex gap-2">
        <select v-model="selectedYear" @change="fetchPassbook" class="bg-slate-100 border-none rounded-xl text-xs font-black px-3 py-2 outline-none">
          <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
        </select>
        <button @click="showAddModal = true" class="w-10 h-10 bg-emerald-600 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-200">
          <span class="i-mdi-plus text-xl"></span>
        </button>
      </div>
    </header>

    <div v-if="loading" class="flex flex-col items-center py-20 space-y-4">
      <div class="w-12 h-12 border-4 border-emerald-100 border-t-emerald-600 rounded-full animate-spin"></div>
      <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Loading Passbook...</p>
    </div>

    <div v-else class="p-6 space-y-6 max-w-lg mx-auto">
      <div v-for="row in matrix" :key="row.scheme_id" class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-6 bg-slate-50/50 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-sm font-black text-slate-800">{{ row.scheme_name }}</h3>
          <p class="text-xs font-black text-emerald-600">Total: ₦{{ formatMoney(row.total) }}</p>
        </div>
        <div class="p-4 grid grid-cols-4 gap-2">
          <div class="p-3 bg-slate-50 rounded-2xl text-center">
            <p class="text-[8px] font-bold text-slate-400 uppercase tracking-tighter">B/F</p>
            <p class="text-[10px] font-black text-slate-600">{{ formatMoney(row.bf) }}</p>
          </div>
          <div v-for="(amount, month) in row.months" :key="month" 
               class="p-3 rounded-2xl text-center"
               :class="amount > 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-50 text-slate-300'"
          >
            <p class="text-[8px] font-bold uppercase tracking-tighter">{{ monthLabels[month-1] }}</p>
            <p class="text-[10px] font-black">{{ formatMoney(amount) }}</p>
          </div>
        </div>
      </div>

        <div class="bg-emerald-600 p-8 rounded-[2.5rem] text-white shadow-xl shadow-emerald-200 text-center">
        <p class="text-[10px] font-black uppercase tracking-[0.3em] opacity-60 mb-1">Grand Total ({{ selectedYear }})</p>
        <p class="text-3xl font-black">₦{{ formatMoney(grandTotal) }}</p>
      </div>

      <!-- Recent Contributions -->
      <div class="space-y-4 pt-4">
        <div class="flex items-center justify-between px-4">
          <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Recent History</h3>
          <button @click="fetchContributions" class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Refresh</button>
        </div>
        
        <div class="space-y-3">
          <div v-for="con in contributions" :key="con.id" class="bg-white p-4 rounded-[2rem] border border-slate-100 shadow-sm flex items-center justify-between group">
            <div class="flex items-center gap-4">
              <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-slate-400">
                <span class="i-mdi-cash-multiple text-xl"></span>
              </div>
              <div>
                <p class="text-xs font-black text-slate-800">{{ con.scheme?.name }}</p>
                <p class="text-[9px] font-bold text-slate-400 uppercase">{{ new Date(con.paid_at || con.created_at).toLocaleDateString() }} • {{ con.payment_method }}</p>
              </div>
            </div>
            <div class="text-right flex items-center gap-4">
              <div>
                <p class="text-sm font-black text-slate-800">₦{{ formatMoney(con.amount) }}</p>
                <p class="text-[8px] font-bold uppercase tracking-tighter" :class="con.status === 'success' ? 'text-emerald-500' : 'text-amber-500'">{{ con.status }}</p>
              </div>
              <div class="flex gap-1">
                <button @click="editContribution(con)" class="w-8 h-8 bg-slate-50 text-slate-400 rounded-lg flex items-center justify-center hover:bg-emerald-50 hover:text-emerald-600">
                  <span class="i-mdi-pencil text-sm"></span>
                </button>
                <button @click="confirmDeleteContribution(con)" class="w-8 h-8 bg-slate-50 text-rose-400 rounded-lg flex items-center justify-center hover:bg-rose-50">
                  <span class="i-mdi-trash-can text-sm"></span>
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <div v-if="pagination.next_page_url" class="text-center pt-2">
          <button @click="loadMoreContributions" class="text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-emerald-600 transition-colors">Load More History</button>
        </div>
      </div>
    </div>

    <!-- Add Contribution Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center p-4">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showAddModal = false"></div>
      <div class="relative bg-white w-full max-w-md rounded-[2.5rem] p-8 space-y-6 animate-in slide-in-from-bottom duration-300">
        <div class="text-center">
          <h3 class="text-xl font-black text-slate-800 tracking-tight">{{ editingCon ? 'Edit' : 'Add' }} Contribution</h3>
          <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">Manual entry</p>
        </div>

        <div class="space-y-4">
          <div v-if="!editingCon" class="bg-slate-50 p-4 rounded-2xl flex items-center justify-between">
            <label class="text-xs font-black text-slate-600 uppercase tracking-wider">Split 50/50 Savings/Shares</label>
            <input v-model="form.split_50_50" type="checkbox" class="w-5 h-5 accent-emerald-600" />
          </div>

          <div v-if="!form.split_50_50">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Select Scheme</label>
            <select v-model="form.scheme_id" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
              <option v-for="scheme in schemes" :key="scheme.id" :value="scheme.id">{{ scheme.name }}</option>
            </select>
          </div>

          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Amount (₦)</label>
            <input v-model="form.amount" type="number" step="0.01" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Date Paid</label>
              <input v-model="form.paid_at" type="date" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-xs font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all" />
            </div>
            <div>
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Method</label>
              <select v-model="form.method" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-xs font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                <option value="cash">Cash</option>
                <option value="transfer">Transfer</option>
                <option value="pos">POS</option>
                <option value="other">Other</option>
              </select>
            </div>
          </div>

          <div v-if="editingCon">
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Status</label>
            <select v-model="form.status" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-xs font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
              <option value="pending">Pending</option>
              <option value="success">Success</option>
              <option value="failed">Failed</option>
            </select>
          </div>

          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Note (Optional)</label>
            <textarea v-model="form.note" rows="2" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-medium outline-none focus:ring-2 focus:ring-emerald-500 transition-all"></textarea>
          </div>
        </div>

        <div class="flex gap-3 pt-4">
          <button @click="closeModal" class="flex-1 py-4 text-sm font-black text-slate-400 uppercase tracking-widest hover:bg-slate-50 rounded-2xl transition-all">Cancel</button>
          <button @click="submitContribution" :disabled="submitting" class="flex-1 bg-emerald-600 py-4 rounded-2xl text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-emerald-200 active:scale-95 transition-all disabled:opacity-50">
            {{ submitting ? 'Saving...' : 'Save Entry' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import axios from '../../http'
import { useModal } from '../../composables/useModal'
import getImageUrl from '../../utils/image'

const route = useRoute()
const { confirm, alert } = useModal()
const user = ref(null)
const matrix = ref([])
const monthLabels = ref(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'])
const contributions = ref([])
const pagination = ref({ current_page: 1, next_page_url: null })
const grandTotal = ref(0)
const loading = ref(true)
const selectedYear = ref(new Date().getFullYear())
const schemes = ref([])
const showAddModal = ref(false)
const submitting = ref(false)
const editingCon = ref(null)
const form = ref({
  scheme_id: null,
  amount: 0,
  paid_at: new Date().toISOString().split('T')[0],
  method: 'transfer',
  note: '',
  split_50_50: false,
  status: 'success'
})
const years = computed(() => {
  const current = new Date().getFullYear()
  return [current, current - 1, current - 2, current - 3]
})

const formatMoney = (val) => new Intl.NumberFormat().format(val || 0)

const fetchPassbook = async () => {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/admin/members/${route.params.id}/passbook/${selectedYear.value}`)
    user.value = data.user
    matrix.value = data.matrix
    grandTotal.value = data.grand_total
    if (data.month_labels) monthLabels.value = data.month_labels
  } catch (e) {
    console.error('Failed to fetch passbook', e)
  } finally {
    loading.value = false
  }
}

const fetchContributions = async (page = 1) => {
  try {
    const { data } = await axios.get(`/api/admin/members/${route.params.id}/contributions?page=${page}`)
    if (page === 1) {
      contributions.value = data.data
    } else {
      contributions.value = [...contributions.value, ...data.data]
    }
    pagination.value = {
      current_page: data.current_page,
      next_page_url: data.next_page_url
    }
  } catch (e) {
    console.error('Failed to fetch contributions', e)
  }
}

const loadMoreContributions = () => {
  if (pagination.value.next_page_url) {
    fetchContributions(pagination.value.current_page + 1)
  }
}

const fetchSchemes = async () => {
  try {
    const { data } = await axios.get('/api/schemes')
    schemes.value = data
    if (data.length > 0) form.value.scheme_id = data[0].id
  } catch (e) {
    console.error('Failed to fetch schemes', e)
  }
}

const editContribution = (con) => {
  editingCon.value = con
  form.value = {
    scheme_id: con.scheme_id,
    amount: con.amount,
    paid_at: new Date(con.paid_at || con.created_at).toISOString().split('T')[0],
    method: con.payment_method,
    note: con.notes,
    split_50_50: false,
    status: con.status
  }
  showAddModal.value = true
}

const closeModal = () => {
  showAddModal.value = false
  editingCon.value = null
  form.value = {
    scheme_id: schemes.value.length > 0 ? schemes.value[0].id : null,
    amount: 0,
    paid_at: new Date().toISOString().split('T')[0],
    method: 'transfer',
    note: '',
    split_50_50: false,
    status: 'success'
  }
}

const confirmDeleteContribution = async (con) => {
  const ok = await confirm('Are you sure you want to delete this contribution? This action cannot be undone and will affect the member balances.', {
    title: 'Delete Contribution',
    confirmText: 'Delete',
    cancelText: 'Cancel'
  })
  if (!ok) return
  
  try {
    await axios.delete(`/api/admin/members/contributions/${con.id}`)
    fetchPassbook()
    fetchContributions()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to delete contribution', 'Error')
  }
}

const submitContribution = async () => {
  if (!form.value.split_50_50 && !form.value.scheme_id) return
  if (form.value.amount <= 0) return
  
  submitting.value = true
  try {
    if (editingCon.value) {
      await axios.patch(`/api/admin/members/contributions/${editingCon.value.id}`, {
        scheme_id: form.value.scheme_id,
        amount: form.value.amount,
        paid_at: form.value.paid_at,
        payment_method: form.value.method,
        notes: form.value.note,
        status: form.value.status
      })
      alert('Contribution updated successfully', 'Success')
    } else {
      await axios.post(`/api/admin/members/${route.params.id}/distribute-funds`, form.value)
      alert('Contribution recorded successfully', 'Success')
    }
    
    closeModal()
    fetchPassbook()
    fetchContributions()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to save contribution', 'Error')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchPassbook()
  fetchContributions()
  fetchSchemes()
})
</script>
