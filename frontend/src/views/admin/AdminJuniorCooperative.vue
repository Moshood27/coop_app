<template>
  <div class="min-h-screen bg-slate-50/50">
    <header class="p-6 bg-white border-b sticky top-0 z-20 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button @click="$router.push(`/admin/members/${memberId}`)" class="w-10 h-10 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-500">
          <span class="i-mdi-chevron-left text-xl"></span>
        </button>
        <div>
          <h1 class="text-xl font-black text-slate-800 tracking-tight">Junior Cooperative</h1>
          <p class="text-[10px] font-bold text-sky-600 uppercase tracking-[0.2em]">Manage {{ member?.name }}'s Children Accounts</p>
        </div>
      </div>
      <button @click="openCreate" class="px-4 py-2 bg-sky-50 text-sky-600 rounded-xl text-[10px] font-black uppercase tracking-widest active:scale-95 transition-all flex items-center gap-2">
        <span class="i-mdi-plus text-sm"></span>
        New Account
      </button>
    </header>

    <div class="p-4 pb-32 max-w-2xl mx-auto space-y-4">
      <div v-if="loadingMember" class="flex flex-col items-center py-20 space-y-4">
        <div class="w-12 h-12 border-4 border-sky-100 border-t-sky-600 rounded-full animate-spin"></div>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Loading Member Data...</p>
      </div>

      <template v-else>
        <!-- Member Brief -->
        <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-4">
          <div class="w-12 h-12 bg-sky-100 text-sky-600 rounded-2xl flex items-center justify-center font-black text-xl overflow-hidden">
            <span v-if="member">{{ member.name.charAt(0) }}</span>
          </div>
          <div>
            <h2 class="text-sm font-black text-slate-800">{{ member?.surname }} {{ member?.name }}</h2>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Wallet: ₦ {{ formatMoney(netBalance) }}</p>
          </div>
        </div>

        <div class="bg-gradient-to-br from-sky-600 to-sky-800 rounded-[2rem] p-7 text-white shadow-lg relative overflow-hidden">
          <div class="absolute -right-10 -top-10 w-32 h-32 bg-white/10 rounded-full" />
          <p class="text-sky-100 text-sm font-medium mb-1 relative z-10">Total Junior Savings</p>
          <h2 class="text-3xl font-black relative z-10">₦ {{ formatMoney(totalBalance) }}</h2>
        </div>

        <div v-if="accounts.length" class="space-y-4">
          <div v-for="acc in accounts" :key="acc.id" class="bg-white p-6 rounded-[2.5rem] border border-slate-100 shadow-sm">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest mb-1">Junior Account</p>
                <h3 class="text-lg font-bold text-slate-800">{{ acc.child_name }}</h3>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Purpose: {{ acc.purpose }}</p>
              </div>
              <div class="flex flex-col items-end">
                <button @click="openEdit(acc)" class="p-2 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-sky-600 mb-1">
                  <span class="i-mdi-pencil text-lg"></span>
                </button>
                <div class="text-right">
                  <p class="text-lg font-black text-slate-900">₦ {{ formatMoney(acc.balance) }}</p>
                  <p v-if="acc.locked_until" class="text-[10px] text-slate-400 font-bold uppercase" :class="isLocked(acc) ? 'text-red-500' : 'text-green-500'">
                    {{ isLocked(acc) ? 'Locked until ' + formatDate(acc.locked_until) : 'Unlocked (' + formatDate(acc.locked_until) + ')' }}
                  </p>
                </div>
              </div>
            </div>
            <div class="mt-5 flex gap-2">
              <button @click="openDeposit(acc)" class="flex-1 px-4 py-3 bg-sky-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest active:scale-95 transition-all">Deposit</button>
              <button @click="openWithdraw(acc)" class="flex-1 px-4 py-3 bg-slate-100 text-slate-600 rounded-2xl text-[10px] font-black uppercase tracking-widest active:scale-95 transition-all">Withdraw</button>
              <button @click="viewDetails(acc)" class="px-4 py-3 bg-slate-50 text-slate-400 rounded-2xl text-[10px] font-black uppercase tracking-widest active:scale-95 transition-all">History</button>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-12 bg-white rounded-[2.5rem] border border-dashed border-slate-300">
          <div class="w-16 h-16 bg-sky-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <span class="i-mdi-baby-face-outline w-8 h-8 text-sky-600"></span>
          </div>
          <p class="text-slate-500 font-medium mb-4">No junior accounts for this member.</p>
          <button @click="openCreate" class="px-6 py-3 bg-sky-600 text-white rounded-2xl text-xs font-black uppercase tracking-widest">Create Account</button>
        </div>
      </template>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showCreate" class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center p-4 bg-slate-900/40 backdrop-blur-sm">
      <div class="w-full max-w-lg bg-white rounded-[2.5rem] p-8 shadow-2xl animate-in slide-in-from-bottom duration-300">
        <div class="flex items-center justify-between mb-8">
          <h3 class="text-xl font-black text-slate-800">{{ editingId ? 'Edit' : 'New' }} Junior Account</h3>
          <button @click="showCreate=false" class="w-10 h-10 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-400">
            <span class="i-mdi-close text-xl"></span>
          </button>
        </div>
        <div class="space-y-5">
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2 block">Child's Full Name</label>
            <input v-model="form.child_name" placeholder="e.g. Ahmed Musa" class="w-full h-14 px-6 bg-slate-50 border-none rounded-2xl text-sm font-bold text-slate-800 placeholder:text-slate-300 focus:ring-2 focus:ring-sky-500 transition-all" />
          </div>
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2 block">Date of Birth</label>
            <input v-model="form.child_dob" type="date" class="w-full h-14 px-6 bg-slate-50 border-none rounded-2xl text-sm font-bold text-slate-800 focus:ring-2 focus:ring-sky-500 transition-all" />
          </div>
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2 block">Savings Purpose</label>
            <input v-model="form.purpose" placeholder="e.g. University Education" class="w-full h-14 px-6 bg-slate-50 border-none rounded-2xl text-sm font-bold text-slate-800 placeholder:text-slate-300 focus:ring-2 focus:ring-sky-500 transition-all" />
          </div>
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2 block">Lock Until (Optional)</label>
            <input v-model="form.locked_until" type="date" class="w-full h-14 px-6 bg-slate-50 border-none rounded-2xl text-sm font-bold text-slate-800 focus:ring-2 focus:ring-sky-500 transition-all" />
          </div>
          <div class="grid grid-cols-2 gap-4 mt-8">
            <button @click="showCreate=false" class="h-14 bg-slate-50 text-slate-400 rounded-2xl text-[10px] font-black uppercase tracking-widest">Cancel</button>
            <button @click="saveAccount" class="h-14 bg-sky-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-sky-100 disabled:opacity-50" :disabled="submitting || !form.child_name || !form.child_dob">{{ submitting ? 'Saving...' : 'Save Account' }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Deposit Modal -->
    <div v-if="showDeposit" class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center p-4 bg-slate-900/40 backdrop-blur-sm">
      <div class="w-full max-w-lg bg-white rounded-[2.5rem] p-8 shadow-2xl">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-xl font-black text-slate-800">Deposit Savings</h3>
          <button @click="showDeposit=false" class="w-10 h-10 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-400">
            <span class="i-mdi-close text-xl"></span>
          </button>
        </div>
        <p class="text-xs font-bold text-slate-400 mb-8 uppercase tracking-wider">Adding to <span class="text-slate-800">{{ activeAccount?.child_name }}</span></p>
        
        <div class="bg-sky-50 p-6 rounded-[2rem] mb-8">
          <div class="flex justify-between items-center mb-1">
            <span class="text-[10px] font-black text-sky-800 uppercase tracking-widest">Member Wallet Balance</span>
            <span class="font-black text-sky-900" :class="{'text-rose-600': netBalance < 0}">₦ {{ formatMoney(netBalance) }}</span>
          </div>
        </div>

        <div class="space-y-6">
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2 block">Amount to Deposit</label>
            <div class="relative">
              <span class="absolute left-6 top-1/2 -translate-y-1/2 font-black text-slate-400 text-lg">₦</span>
              <input v-model="amount" type="number" placeholder="0.00" class="w-full h-16 pl-12 pr-6 bg-slate-50 border-none rounded-2xl text-xl font-black text-slate-800 placeholder:text-slate-200 focus:ring-2 focus:ring-sky-500 transition-all" />
            </div>
          </div>
          <button @click="handleDeposit" class="w-full h-16 bg-sky-600 text-white rounded-2xl text-xs font-black uppercase tracking-widest shadow-lg shadow-sky-100 disabled:opacity-50 mt-4" :disabled="submitting || !amount || amount <= 0 || amount > netBalance">{{ submitting ? 'Processing...' : 'Complete Deposit' }}</button>
        </div>
      </div>
    </div>

    <!-- Withdraw Modal -->
    <div v-if="showWithdraw" class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center p-4 bg-slate-900/40 backdrop-blur-sm">
      <div class="w-full max-w-lg bg-white rounded-[2.5rem] p-8 shadow-2xl">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-xl font-black text-slate-800">Withdraw Savings</h3>
          <button @click="showWithdraw=false" class="w-10 h-10 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-400">
            <span class="i-mdi-close text-xl"></span>
          </button>
        </div>
        <p class="text-xs font-bold text-slate-400 mb-8 uppercase tracking-wider">From <span class="text-slate-800">{{ activeAccount?.child_name }}</span> to Member Wallet</p>
        
        <div class="bg-rose-50 p-6 rounded-[2rem] mb-8">
          <div class="flex justify-between items-center">
            <span class="text-[10px] font-black text-rose-800 uppercase tracking-widest">Account Balance</span>
            <span class="font-black text-rose-900">₦ {{ formatMoney(activeAccount?.balance) }}</span>
          </div>
        </div>

        <div class="space-y-6">
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 mb-2 block">Amount to Withdraw</label>
            <div class="relative">
              <span class="absolute left-6 top-1/2 -translate-y-1/2 font-black text-slate-400 text-lg">₦</span>
              <input v-model="amount" type="number" placeholder="0.00" class="w-full h-16 pl-12 pr-6 bg-slate-50 border-none rounded-2xl text-xl font-black text-slate-800 placeholder:text-slate-200 focus:ring-2 focus:ring-rose-500 transition-all" />
            </div>
          </div>
          <button @click="handleWithdraw" class="w-full h-16 bg-rose-600 text-white rounded-2xl text-xs font-black uppercase tracking-widest shadow-lg shadow-rose-100 disabled:opacity-50 mt-4" :disabled="submitting || !amount || amount <= 0 || amount > activeAccount?.balance">{{ submitting ? 'Processing...' : 'Complete Withdrawal' }}</button>
        </div>
      </div>
    </div>

    <!-- History Modal -->
    <div v-if="showHistory" class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center p-4 bg-slate-900/40 backdrop-blur-sm">
      <div class="w-full max-w-lg bg-white rounded-[2.5rem] p-8 shadow-2xl max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h3 class="text-xl font-black text-slate-800">Transaction History</h3>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ activeAccount?.child_name }}</p>
          </div>
          <button @click="showHistory=false" class="w-10 h-10 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-400">
            <span class="i-mdi-close text-xl"></span>
          </button>
        </div>
        
        <div class="flex-1 overflow-y-auto pr-2 space-y-3">
          <div v-if="historyLoading" class="py-10 text-center text-xs font-bold text-slate-400 uppercase">Loading...</div>
          <div v-else-if="!history.length" class="py-10 text-center text-xs font-bold text-slate-400 uppercase">No transactions yet</div>
          <div v-for="tx in history" :key="tx.id" class="p-4 bg-slate-50 rounded-2xl flex items-center justify-between">
            <div>
              <p class="text-xs font-black text-slate-800">{{ tx.type === 'credit' ? 'Deposit' : 'Withdrawal' }}</p>
              <p class="text-[9px] text-slate-400 font-bold">{{ formatDate(tx.created_at) }}</p>
              <p v-if="tx.meta?.description" class="text-[9px] text-slate-500 mt-1 italic">{{ tx.meta.description }}</p>
            </div>
            <p class="font-black text-sm" :class="tx.type === 'credit' ? 'text-emerald-600' : 'text-rose-600'">
              {{ tx.type === 'credit' ? '+' : '-' }}₦{{ formatMoney(tx.amount) }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { toast } from 'vue3-toastify'

const route = useRoute()
const memberId = route.params.id

const member = ref(null)
const accounts = ref([])
const loadingMember = ref(true)
const submitting = ref(false)

const balance = ref(0)
const adminChargeBalance = ref(0)

const netBalance = computed(() => balance.value - adminChargeBalance.value)
const totalBalance = computed(() => accounts.value.reduce((acc, curr) => acc + parseFloat(curr.balance), 0))

// Modals
const showCreate = ref(false)
const showDeposit = ref(false)
const showWithdraw = ref(false)
const showHistory = ref(false)
const historyLoading = ref(false)

const editingId = ref(null)
const activeAccount = ref(null)
const amount = ref('')
const history = ref([])

const form = ref({
  child_name: '',
  child_dob: '',
  purpose: 'Education',
  locked_until: ''
})

const fetchMemberData = async () => {
  loadingMember.value = true
  try {
    const [mRes, aRes] = await Promise.all([
      axios.get(`/api/admin/members/${memberId}`),
      axios.get(`/api/admin/members/${memberId}/junior-cooperative`)
    ])
    member.value = mRes.data.user
    accounts.value = aRes.data.accounts
    balance.value = aRes.data.balance
    adminChargeBalance.value = aRes.data.admin_charge_balance
  } catch (error) {
    toast.error('Failed to load member data')
  } finally {
    loadingMember.value = false
  }
}

const openCreate = () => {
  editingId.value = null
  form.value = { child_name: '', child_dob: '', purpose: 'Education', locked_until: '' }
  showCreate.value = true
}

const openEdit = (acc) => {
  editingId.value = acc.id
  form.value = {
    child_name: acc.child_name,
    child_dob: acc.child_dob ? acc.child_dob.split('T')[0] : '',
    purpose: acc.purpose,
    locked_until: acc.locked_until ? acc.locked_until.split('T')[0] : ''
  }
  showCreate.value = true
}

const saveAccount = async () => {
  submitting.value = true
  try {
    if (editingId.value) {
      await axios.patch(`/api/admin/members/${memberId}/junior-cooperative/${editingId.value}`, form.value)
      toast.success('Account updated')
    } else {
      await axios.post(`/api/admin/members/${memberId}/junior-cooperative`, form.value)
      toast.success('Account created')
    }
    showCreate.value = false
    fetchMemberData()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to save account')
  } finally {
    submitting.value = false
  }
}

const openDeposit = (acc) => {
  activeAccount.value = acc
  amount.value = ''
  showDeposit.value = true
}

const handleDeposit = async () => {
  submitting.value = true
  try {
    await axios.post(`/api/admin/members/${memberId}/junior-cooperative/${activeAccount.value.id}/deposit`, { amount: amount.value })
    toast.success('Deposit successful')
    showDeposit.value = false
    fetchMemberData()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Deposit failed')
  } finally {
    submitting.value = false
  }
}

const openWithdraw = (acc) => {
  activeAccount.value = acc
  amount.value = ''
  showWithdraw.value = true
}

const handleWithdraw = async () => {
  submitting.value = true
  try {
    await axios.post(`/api/admin/members/${memberId}/junior-cooperative/${activeAccount.value.id}/withdraw`, { amount: amount.value })
    toast.success('Withdrawal successful')
    showWithdraw.value = false
    fetchMemberData()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Withdrawal failed')
  } finally {
    submitting.value = false
  }
}

const viewDetails = async (acc) => {
  activeAccount.value = acc
  showHistory.value = true
  historyLoading.value = true
  try {
    const res = await axios.get(`/api/admin/members/${memberId}/junior-cooperative/${acc.id}/history`)
    history.value = res.data.transactions
  } catch (error) {
    toast.error('Failed to load history')
  } finally {
    historyLoading.value = false
  }
}

const formatMoney = (val) => {
  if (!val) return '0.00'
  return parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

const isLocked = (acc) => {
  if (!acc.locked_until) return false
  return new Date(acc.locked_until) > new Date()
}

onMounted(fetchMemberData)
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
