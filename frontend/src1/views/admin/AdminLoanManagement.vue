<template>
  <div class="min-h-screen bg-slate-50 pb-32">
    <header class="p-6 bg-white border-b sticky top-0 z-20 flex items-center gap-4">
      <button @click="$router.push(`/admin/members/${$route.params.id}`)" class="w-10 h-10 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-500">
        <span class="i-mdi-chevron-left text-xl"></span>
      </button>
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center font-black text-sm overflow-hidden">
          <img v-if="user?.passport_url" :src="getImageUrl(user.passport_url)" class="w-full h-full object-cover" />
          <span v-else>{{ user?.full_name?.charAt(0) }}</span>
        </div>
        <div>
          <h1 class="text-base font-black text-slate-800 tracking-tight leading-none">Loan Management</h1>
          <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-[0.2em] mt-1">{{ user?.full_name }}</p>
        </div>
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
          <div class="flex items-center gap-2">
            <span class="px-3 py-1 text-[8px] font-black rounded-full uppercase tracking-widest" :class="statusClass(loan.status)">
              {{ loan.status }}
            </span>
            <div class="flex gap-1">
              <button @click="editLoan(loan)" class="w-7 h-7 bg-slate-50 text-slate-400 rounded-lg flex items-center justify-center hover:bg-emerald-50 hover:text-emerald-600">
                <span class="i-mdi-pencil text-xs"></span>
              </button>
              <button @click="confirmDeleteLoan(loan)" class="w-7 h-7 bg-slate-50 text-rose-400 rounded-lg flex items-center justify-center hover:bg-rose-50">
                <span class="i-mdi-trash-can text-xs"></span>
              </button>
            </div>
          </div>
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
          <div v-if="loan.repayment_start_date">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Repayment Start</p>
            <p class="text-sm font-black text-slate-800">{{ formatDate(loan.repayment_start_date) }}</p>
          </div>
          <div v-if="loan.description" class="col-span-2">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Description</p>
            <p class="text-xs font-medium text-slate-600">{{ loan.description }}</p>
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
            <div v-for="rep in loan.repayments" :key="rep.id" class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100 group">
              <div>
                <p class="text-[10px] font-black text-slate-800">₦{{ formatMoney(rep.amount) }}</p>
                <p class="text-[8px] font-bold text-slate-400 uppercase">{{ rep.payment_method }} • {{ formatDate(rep.paid_at) }}</p>
                <p v-if="rep.notes" class="text-[8px] font-medium text-slate-500 italic mt-0.5">{{ rep.notes }}</p>
              </div>
              <div class="flex items-center gap-2">
                <div class="flex gap-1">
                  <button @click="editRepayment(loan, rep)" class="w-6 h-6 bg-white text-slate-400 rounded-lg flex items-center justify-center hover:text-emerald-600 shadow-sm">
                    <span class="i-mdi-pencil text-[10px]"></span>
                  </button>
                  <button @click="confirmDeleteRepayment(rep)" class="w-6 h-6 bg-white text-rose-400 rounded-lg flex items-center justify-center hover:bg-rose-50 shadow-sm">
                    <span class="i-mdi-trash-can text-[10px]"></span>
                  </button>
                </div>
                <span class="i-mdi-check-circle text-emerald-500 text-lg"></span>
              </div>
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
          <h3 class="text-xl font-black text-slate-800 tracking-tight">{{ editingRep ? 'Edit' : 'Record' }} Repayment</h3>
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
            <textarea v-model="repayForm.notes" rows="2" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-medium outline-none focus:ring-2 focus:ring-emerald-500 transition-all"></textarea>
          </div>
        </div>

        <div class="flex gap-3 pt-4">
          <button @click="closeRepayModal" class="flex-1 py-4 text-sm font-black text-slate-400 uppercase tracking-widest hover:bg-slate-50 rounded-2xl transition-all">Cancel</button>
          <button @click="submitRepayment" :disabled="submitting" class="flex-1 bg-emerald-600 py-4 rounded-2xl text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-emerald-200 active:scale-95 transition-all disabled:opacity-50">
            {{ submitting ? 'Saving...' : 'Confirm' }}
          </button>
        </div>
      </div>
    </div>
    <!-- Edit Loan Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center p-4">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showEditModal = false"></div>
      <div class="relative bg-white w-full max-w-md rounded-[2.5rem] p-8 space-y-6 animate-in slide-in-from-bottom duration-300">
        <div class="text-center">
          <h3 class="text-xl font-black text-slate-800 tracking-tight">Edit Loan</h3>
          <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-1">Loan QH-{{ selectedLoan?.id }}</p>
        </div>

        <div class="space-y-4">
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Principal Amount (₦)</label>
            <input v-model="loanForm.principal_amount" type="number" step="0.01" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all" />
          </div>

          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Status</label>
            <select v-model="loanForm.status" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
              <option value="pending">Pending</option>
              <option value="active">Active</option>
              <option value="completed">Completed</option>
              <option value="defaulted">Defaulted</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>

          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Description</label>
            <textarea v-model="loanForm.description" rows="3" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-medium outline-none focus:ring-2 focus:ring-emerald-500 transition-all"></textarea>
          </div>
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Repayment Start Date</label>
            <input v-model="loanForm.repayment_start_date" type="date" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-medium outline-none focus:ring-2 focus:ring-emerald-500 transition-all" />
          </div>
        </div>

        <div class="flex gap-3 pt-4">
          <button @click="showEditModal = false" class="flex-1 py-4 text-sm font-black text-slate-400 uppercase tracking-widest hover:bg-slate-50 rounded-2xl transition-all">Cancel</button>
          <button @click="submitLoanUpdate" :disabled="submitting" class="flex-1 bg-emerald-600 py-4 rounded-2xl text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-emerald-200 active:scale-95 transition-all disabled:opacity-50">
            {{ submitting ? 'Updating...' : 'Save Changes' }}
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
import { useModal } from '../../composables/useModal'
import getImageUrl from '../../utils/image'

const route = useRoute()
const { confirm, alert } = useModal()
const user = ref(null)
const loans = ref([])
const loading = ref(true)

const showRepayModal = ref(false)
const showEditModal = ref(false)
const selectedLoan = ref(null)
const editingRep = ref(null)
const submitting = ref(false)
const repayForm = ref({
  amount: 0,
  method: 'cash',
  paid_at: new Date().toISOString().split('T')[0],
  notes: ''
})
const loanForm = ref({
  principal_amount: 0,
  status: 'pending',
  description: '',
  repayment_start_date: ''
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
  repayForm.value.notes = ''
  editingRep.value = null
  showRepayModal.value = true
}

const editRepayment = (loan, rep) => {
  selectedLoan.value = loan
  editingRep.value = rep
  repayForm.value = {
    amount: rep.amount,
    method: rep.payment_method,
    paid_at: new Date(rep.paid_at || rep.created_at).toISOString().split('T')[0],
    notes: rep.notes
  }
  showRepayModal.value = true
}

const closeRepayModal = () => {
  showRepayModal.value = false
  editingRep.value = null
  repayForm.value = {
    amount: 0,
    method: 'cash',
    paid_at: new Date().toISOString().split('T')[0],
    notes: ''
  }
}

const confirmDeleteRepayment = async (rep) => {
  const ok = await confirm('Are you sure you want to delete this repayment record? This will adjust the loan balance.', {
    title: 'Delete Repayment',
    confirmText: 'Delete',
    cancelText: 'Cancel'
  })
  if (!ok) return
  try {
    await axios.delete(`/api/admin/members/loan-repayments/${rep.id}`)
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to delete repayment', 'Error')
  }
}

const editLoan = (loan) => {
  selectedLoan.value = loan
  loanForm.value = {
    principal_amount: loan.principal_amount,
    status: loan.status,
    description: loan.description,
    repayment_start_date: loan.repayment_start_date ? new Date(loan.repayment_start_date).toISOString().split('T')[0] : ''
  }
  showEditModal.value = true
}

const confirmDeleteLoan = async (loan) => {
  const ok = await confirm('Are you sure you want to delete this loan? All repayment records will also be removed.', {
    title: 'Delete Loan',
    confirmText: 'Delete',
    cancelText: 'Cancel'
  })
  if (!ok) return
  try {
    await axios.delete(`/api/admin/members/loans/${loan.id}`)
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to delete loan', 'Error')
  }
}

const submitLoanUpdate = async () => {
  submitting.value = true
  try {
    await axios.patch(`/api/admin/members/loans/${selectedLoan.value.id}`, loanForm.value)
    alert('Loan details updated successfully', 'Success')
    showEditModal.value = false
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to update loan', 'Error')
  } finally {
    submitting.value = false
  }
}

const submitRepayment = async () => {
  if (repayForm.value.amount <= 0) return
  submitting.value = true
  try {
    if (editingRep.value) {
      await axios.patch(`/api/admin/members/loan-repayments/${editingRep.value.id}`, {
        amount: repayForm.value.amount,
        payment_method: repayForm.value.method,
        paid_at: repayForm.value.paid_at,
        notes: repayForm.value.notes
      })
      alert('Repayment record updated successfully', 'Success')
    } else {
      await axios.post(`/api/admin/members/loans/${selectedLoan.value.id}/repay`, repayForm.value)
      alert('Repayment recorded successfully', 'Success')
    }
    closeRepayModal()
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to record repayment', 'Error')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>
