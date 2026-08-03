<template>
  <div class="min-h-screen bg-slate-50 pb-32">
    <header class="p-6 bg-white border-b sticky top-0 z-20 flex items-center gap-4">
      <button @click="$router.push(`/admin/members/${$route.params.id}`)" class="w-10 h-10 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-500">
        <span class="i-mdi-chevron-left text-xl"></span>
      </button>
      <div>
        <h1 class="text-xl font-black text-slate-800 tracking-tight">Wallet Allocation</h1>
        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-[0.2em]">{{ user?.full_name }}</p>
      </div>
    </header>

    <div v-if="loading" class="flex flex-col items-center py-20 space-y-4">
      <div class="w-12 h-12 border-4 border-emerald-100 border-t-emerald-600 rounded-full animate-spin"></div>
      <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Loading Wallet...</p>
    </div>

    <div v-else class="p-6 space-y-6 max-w-lg mx-auto">
      <div class="bg-amber-600 p-8 rounded-[2.5rem] text-white shadow-xl shadow-amber-200 text-center relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
        <p class="text-[10px] font-black uppercase tracking-[0.3em] opacity-60 mb-1">Available Wallet Balance</p>
        <p class="text-3xl font-black">₦{{ formatMoney(balance) }}</p>
      </div>

      <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm space-y-6">
        <div class="text-center">
          <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">Distribution</h3>
          <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Allocate funds to schemes</p>
        </div>

        <div class="space-y-4">
          <div v-for="(alloc, index) in allocations" :key="index" class="p-4 bg-slate-50 rounded-3xl relative">
            <button v-if="allocations.length > 1" @click="removeAllocation(index)" class="absolute -top-2 -right-2 w-6 h-6 bg-rose-500 text-white rounded-full flex items-center justify-center text-xs shadow-md">
              <span class="i-mdi-close"></span>
            </button>
            
            <div class="space-y-3">
              <select v-model="alloc.scheme_id" class="w-full bg-white border-none rounded-2xl px-4 py-3 text-xs font-black outline-none focus:ring-2 focus:ring-amber-500 transition-all">
                <option v-for="scheme in schemes" :key="scheme.id" :value="scheme.id">{{ scheme.name }}</option>
              </select>
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-black text-xs">₦</span>
                <input v-model="alloc.amount" type="number" step="0.01" class="w-full pl-8 pr-4 py-3 bg-white border-none rounded-2xl text-xs font-black outline-none focus:ring-2 focus:ring-amber-500 transition-all" placeholder="Amount" />
              </div>
            </div>
          </div>

          <button @click="addAllocation" class="w-full py-4 border-2 border-dashed border-slate-200 rounded-3xl text-[10px] font-black text-slate-400 uppercase tracking-widest hover:border-amber-300 hover:text-amber-500 transition-all">
            + Add Another Scheme
          </button>
        </div>

        <div class="pt-6 border-t border-slate-50 space-y-4">
          <div class="flex items-center justify-between px-4">
            <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Total to Allocate</p>
            <p class="text-lg font-black" :class="totalAllocated > balance ? 'text-rose-500' : 'text-slate-800'">₦{{ formatMoney(totalAllocated) }}</p>
          </div>

          <button 
            @click="submitAllocation" 
            :disabled="submitting || totalAllocated <= 0 || totalAllocated > balance"
            class="w-full bg-amber-600 py-5 rounded-[2rem] text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-amber-200 active:scale-95 transition-all disabled:opacity-50"
          >
            {{ submitting ? 'Processing...' : 'Confirm Allocation' }}
          </button>
        </div>
      </div>

      <!-- Recent Transactions -->
      <div class="space-y-4 pt-4">
        <div class="flex items-center justify-between px-4">
          <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Transaction History</h3>
          <button @click="fetchTransactions" class="text-[10px] font-black text-amber-600 uppercase tracking-widest">Refresh</button>
        </div>
        
        <div class="space-y-3">
          <div v-for="tx in transactions" :key="tx.id" class="bg-white p-4 rounded-[2rem] border border-slate-100 shadow-sm flex items-center justify-between group">
            <div class="flex items-center gap-4">
              <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="tx.type === 'credit' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'">
                <span :class="tx.type === 'credit' ? 'i-mdi-arrow-down-bold' : 'i-mdi-arrow-up-bold'" class="text-xl"></span>
              </div>
              <div>
                <p class="text-xs font-black text-slate-800">{{ tx.description || 'Wallet Transaction' }}</p>
                <p class="text-[9px] font-bold text-slate-400 uppercase">{{ new Date(tx.created_at).toLocaleDateString() }} • {{ tx.status }}</p>
              </div>
            </div>
            <div class="text-right flex items-center gap-4">
              <div>
                <p class="text-sm font-black" :class="tx.type === 'credit' ? 'text-emerald-600' : 'text-slate-800'">
                  {{ tx.type === 'credit' ? '+' : '-' }}₦{{ formatMoney(tx.amount) }}
                </p>
              </div>
              <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button @click="editTransaction(tx)" class="w-8 h-8 bg-slate-50 text-slate-400 rounded-lg flex items-center justify-center hover:bg-amber-50 hover:text-amber-600">
                  <span class="i-mdi-pencil text-sm"></span>
                </button>
                <button @click="confirmDeleteTransaction(tx)" class="w-8 h-8 bg-slate-50 text-rose-400 rounded-lg flex items-center justify-center hover:bg-rose-50">
                  <span class="i-mdi-trash-can text-sm"></span>
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <div v-if="pagination.next_page_url" class="text-center pt-2">
          <button @click="loadMoreTransactions" class="text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-amber-600 transition-colors">Load More History</button>
        </div>
      </div>
    </div>

    <!-- Edit Transaction Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center p-4">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showEditModal = false"></div>
      <div class="relative bg-white w-full max-w-md rounded-[2.5rem] p-8 space-y-6 animate-in slide-in-from-bottom duration-300">
        <div class="text-center">
          <h3 class="text-xl font-black text-slate-800 tracking-tight">Edit Transaction</h3>
          <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Ref: #{{ editingTx?.id }}</p>
        </div>

        <div class="space-y-4">
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Amount (₦)</label>
            <input v-model="editForm.amount" type="number" step="0.01" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-black outline-none focus:ring-2 focus:ring-amber-500 transition-all" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Type</label>
              <select v-model="editForm.type" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-xs font-black outline-none focus:ring-2 focus:ring-amber-500 transition-all">
                <option value="credit">Credit</option>
                <option value="debit">Debit</option>
              </select>
            </div>
            <div>
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Status</label>
              <select v-model="editForm.status" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-xs font-black outline-none focus:ring-2 focus:ring-amber-500 transition-all">
                <option value="pending">Pending</option>
                <option value="success">Success</option>
                <option value="failed">Failed</option>
              </select>
            </div>
          </div>

          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Description</label>
            <textarea v-model="editForm.description" rows="2" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-medium outline-none focus:ring-2 focus:ring-amber-500 transition-all"></textarea>
          </div>
        </div>

        <div class="flex gap-3 pt-4">
          <button @click="showEditModal = false" class="flex-1 py-4 text-sm font-black text-slate-400 uppercase tracking-widest hover:bg-slate-50 rounded-2xl transition-all">Cancel</button>
          <button @click="submitEditTransaction" :disabled="submitting" class="flex-1 bg-amber-600 py-4 rounded-2xl text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-amber-200 active:scale-95 transition-all disabled:opacity-50">
            {{ submitting ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '../../http'

const route = useRoute()
const router = useRouter()
const user = ref(null)
const balance = ref(0)
const loading = ref(true)
const schemes = ref([])
const allocations = ref([{ scheme_id: null, amount: 0 }])
const transactions = ref([])
const pagination = ref({ current_page: 1, next_page_url: null })
const submitting = ref(false)

const showEditModal = ref(false)
const editingTx = ref(null)
const editForm = ref({
  amount: 0,
  type: 'credit',
  status: 'success',
  description: ''
})

const formatMoney = (val) => new Intl.NumberFormat().format(val || 0)

const totalAllocated = computed(() => {
  return allocations.value.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0)
})

const fetchData = async () => {
  loading.value = true
  try {
    const [userRes, schemeRes] = await Promise.all([
      axios.get(`/api/admin/members/${route.params.id}`),
      axios.get('/api/schemes')
    ])
    user.value = userRes.data.user
    balance.value = userRes.data.balance
    schemes.value = schemeRes.data
    if (schemes.value.length > 0) allocations.value[0].scheme_id = schemes.value[0].id
    
    fetchTransactions()
  } catch (e) {
    console.error('Failed to fetch data', e)
  } finally {
    loading.value = false
  }
}

const fetchTransactions = async (page = 1) => {
  try {
    const { data } = await axios.get(`/api/admin/members/${route.params.id}/wallet-transactions?page=${page}`)
    if (page === 1) {
      transactions.value = data.data
    } else {
      transactions.value = [...transactions.value, ...data.data]
    }
    pagination.value = {
      current_page: data.current_page,
      next_page_url: data.next_page_url
    }
  } catch (e) {
    console.error('Failed to fetch transactions', e)
  }
}

const loadMoreTransactions = () => {
  if (pagination.value.next_page_url) {
    fetchTransactions(pagination.value.current_page + 1)
  }
}

const editTransaction = (tx) => {
  editingTx.value = tx
  editForm.value = {
    amount: tx.amount,
    type: tx.type,
    status: tx.status,
    description: tx.description
  }
  showEditModal.value = true
}

const confirmDeleteTransaction = async (tx) => {
  if (!confirm('Are you sure you want to delete this transaction? This will NOT automatically revert balance changes.')) return
  try {
    await axios.delete(`/api/admin/members/wallet-transactions/${tx.id}`)
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to delete transaction')
  }
}

const submitEditTransaction = async () => {
  submitting.value = true
  try {
    await axios.patch(`/api/admin/members/wallet-transactions/${editingTx.value.id}`, editForm.value)
    showEditModal.value = false
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to update transaction')
  } finally {
    submitting.value = false
  }
}

const addAllocation = () => {
  allocations.value.push({ 
    scheme_id: schemes.value.length > 0 ? schemes.value[0].id : null, 
    amount: 0 
  })
}

const removeAllocation = (index) => {
  allocations.value.splice(index, 1)
}

const submitAllocation = async () => {
  submitting.value = true
  try {
    await axios.post(`/api/admin/members/${route.params.id}/allocate-wallet`, {
      allocations: allocations.value
    })
    alert('Wallet funds allocated successfully')
    router.push(`/admin/members/${route.params.id}`)
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to allocate wallet funds')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>
