<template>
  <div class="min-h-screen bg-slate-50 pb-32">
    <header class="p-6 bg-white border-b sticky top-0 z-20 flex items-center gap-4">
      <button @click="$router.push(`/admin/members/${$route.params.id}`)" class="w-10 h-10 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-500">
        <span class="i-mdi-chevron-left text-xl"></span>
      </button>
      <div>
        <h1 class="text-xl font-black text-slate-800 tracking-tight">Loan Management</h1>
        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-[0.2em]">{{ user?.full_name }}</p>
      </div>
    </header>

    <div v-if="loading" class="flex flex-col items-center py-20 space-y-4">
      <div class="w-12 h-12 border-4 border-emerald-100 border-t-emerald-600 rounded-full animate-spin"></div>
      <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Loading Loans...</p>
    </div>

    <div v-else class="p-6 space-y-6 max-w-lg mx-auto">
      <div v-if="loans.length === 0" class="text-center py-12 bg-white rounded-[2.5rem] border border-slate-100 border-dashed">
        <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-3xl flex items-center justify-center text-3xl mx-auto mb-4">
          <span class="i-mdi-bank-off"></span>
        </div>
        <p class="text-sm font-bold text-slate-500">No loan records found</p>
      </div>

      <div v-for="loan in loans" :key="loan.id" class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-6 flex items-center justify-between border-b border-slate-50">
          <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Loan ID: QH-{{ loan.id }}</p>
            <p class="text-sm font-black text-slate-800">{{ formatDate(loan.created_at) }}</p>
          </div>
          <span class="px-3 py-1 text-[8px] font-black rounded-full uppercase tracking-widest" :class="statusClass(loan.status)">
            {{ loan.status }}
          </span>
        </div>
        
        <div class="p-6 bg-slate-50/30 grid grid-cols-2 gap-6">
          <div>
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Principal</p>
            <p class="text-sm font-black text-slate-800">₦{{ formatMoney(loan.principal_amount) }}</p>
          </div>
          <div>
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Paid</p>
            <p class="text-sm font-black text-emerald-600">₦{{ formatMoney(loan.paid_amount) }}</p>
          </div>
          <div class="col-span-2">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-2 text-center">Repayment Progress</p>
            <div class="w-full h-3 bg-slate-200 rounded-full overflow-hidden">
              <div class="h-full bg-emerald-500 transition-all duration-1000" :style="{ width: (loan.paid_amount / loan.principal_amount * 100) + '%' }"></div>
            </div>
          </div>
        </div>

        <div v-if="['active', 'defaulted'].includes(loan.status)" class="p-6 border-t border-slate-50">
          <button @click="openRepayModal(loan)" class="w-full bg-emerald-600 py-4 rounded-2xl text-[10px] font-black text-white uppercase tracking-[0.2em] shadow-lg shadow-emerald-200 active:scale-95 transition-all">
            Record Repayment
          </button>
        </div>

        <!-- Repayments List -->
        <div v-if="loan.repayments?.length > 0" class="border-t border-slate-50">
          <button @click="loan.showRepayments = !loan.showRepayments" class="w-full p-4 text-[9px] font-black text-slate-400 uppercase tracking-widest flex items-center justify-center gap-2">
            {{ loan.showRepayments ? 'Hide' : 'View' }} Repayments
            <span :class="loan.showRepayments ? 'i-mdi-chevron-up' : 'i-mdi-chevron-down'"></span>
          </button>
          <div v-if="loan.showRepayments" class="px-6 pb-6 space-y-3">
            <div v-for="rep in loan.repayments" :key="rep.id" class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100">
              <div>
                <p class="text-[10px] font-black text-slate-800">₦{{ formatMoney(rep.amount) }}</p>
                <p class="text-[8px] font-bold text-slate-400 uppercase">{{ rep.payment_method }} • {{ formatDate(rep.paid_at) }}</p>
              </div>
              <span class="i-mdi-check-circle text-emerald-500 text-lg"></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Repayment Modal -->
    <div v-if="showRepayModal" class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center p-4">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showRepayModal = false"></div>
      <div class="relative bg-white w-full max-w-md rounded-[2.5rem] p-8 space-y-6 animate-in slide-in-from-bottom duration-300">
        <div class="text-center">
          <h3 class="text-xl font-black text-slate-800 tracking-tight">Record Repayment</h3>
          <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">Loan QH-{{ selectedLoan?.id }}</p>
        </div>

        <div class="space-y-4">
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Amount (₦)</label>
            <input v-model="repayForm.amount" type="number" step="0.01" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Date Paid</label>
              <input v-model="repayForm.paid_at" type="date" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-xs font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all" />
            </div>
            <div>
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Method</label>
              <select v-model="repayForm.method" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-xs font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                <option value="cash">Cash</option>
                <option value="transfer">Transfer</option>
                <option value="pos">POS</option>
                <option value="wallet">Member Wallet</option>
                <option value="other">Other</option>
              </select>
            </div>
          </div>

          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Note (Optional)</label>
            <textarea v-model="repayForm.note" rows="2" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-medium outline-none focus:ring-2 focus:ring-emerald-500 transition-all"></textarea>
          </div>
        </div>

        <div class="flex gap-3 pt-4">
          <button @click="showRepayModal = false" class="flex-1 py-4 text-sm font-black text-slate-400 uppercase tracking-widest hover:bg-slate-50 rounded-2xl transition-all">Cancel</button>
          <button @click="submitRepayment" :disabled="submitting" class="flex-1 bg-emerald-600 py-4 rounded-2xl text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-emerald-200 active:scale-95 transition-all disabled:opacity-50">
            {{ submitting ? 'Saving...' : 'Confirm' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from '../../http'

const route = useRoute()
const user = ref(null)
const loans = ref([])
const loading = ref(true)

const showRepayModal = ref(false)
const selectedLoan = ref(null)
const submitting = ref(false)
const repayForm = ref({
  amount: 0,
  method: 'cash',
  paid_at: new Date().toISOString().split('T')[0],
  note: ''
})

const formatMoney = (val) => new Intl.NumberFormat().format(val || 0)
const formatDate = (date) => new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })

const statusClass = (status) => {
  switch (status) {
    case 'active': return 'bg-emerald-100 text-emerald-600'
    case 'completed': return 'bg-blue-100 text-blue-600'
    case 'defaulted': return 'bg-rose-100 text-rose-600'
    case 'pending': return 'bg-slate-100 text-slate-600'
    default: return 'bg-slate-100 text-slate-400'
  }
}

const fetchData = async () => {
  loading.value = true
  try {
    const [userRes, loansRes] = await Promise.all([
      axios.get(`/api/admin/members/${route.params.id}`),
      axios.get(`/api/admin/members/${route.params.id}/loans`)
    ])
    user.value = userRes.data.user
    loans.value = loansRes.data.map(l => ({ ...l, showRepayments: false }))
  } catch (e) {
    console.error('Failed to fetch loans', e)
  } finally {
    loading.value = false
  }
}

const openRepayModal = (loan) => {
  selectedLoan.value = loan
  repayForm.value.amount = loan.principal_amount - loan.paid_amount
  showRepayModal.value = true
}

const submitRepayment = async () => {
  if (repayForm.value.amount <= 0) return
  submitting.value = true
  try {
    await axios.post(`/api/admin/members/loans/${selectedLoan.value.id}/repay`, repayForm.value)
    showRepayModal.value = false
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to record repayment')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>
