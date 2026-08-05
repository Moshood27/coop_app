<template>
  <div class="min-h-screen bg-slate-50 pb-32">
    <header class="p-6 bg-white border-b sticky top-0 z-20 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button @click="$router.push('/admin/members')" class="w-10 h-10 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-500">
          <span class="i-mdi-chevron-left text-xl"></span>
        </button>
        <div>
          <h1 class="text-xl font-black text-slate-800 tracking-tight">Member Details</h1>
          <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-[0.2em]">Manage member funds & loans</p>
        </div>
      </div>
    </header>

    <div v-if="loading" class="flex flex-col items-center py-20 space-y-4">
      <div class="w-12 h-12 border-4 border-emerald-100 border-t-emerald-600 rounded-full animate-spin"></div>
      <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Loading Member...</p>
    </div>

    <div v-else-if="user" class="p-6 space-y-6 max-w-lg mx-auto">
      <!-- Member Profile Card -->
      <div class="bg-white p-6 rounded-[2.5rem] border border-slate-100 shadow-sm relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-full -mr-16 -mt-16 opacity-50"></div>
        <div class="relative flex items-center gap-6">
          <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-[2rem] flex items-center justify-center font-black text-3xl overflow-hidden">
            <img v-if="user.passport_url" :src="getImageUrl(user.passport_url)" class="w-full h-full object-cover" />
            <span v-else>{{ user.name.charAt(0) }}</span>
          </div>
          <div>
            <h2 class="text-xl font-black text-slate-800 leading-tight">{{ user.surname }} {{ user.name }}</h2>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-2">Membership NO: {{ user.membership_number }}</p>
            <div class="flex items-center gap-2">
              <span class="px-2 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-bold rounded-lg uppercase tracking-wider">
                {{ user.branch?.name }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Financial Summary -->
      <div class="grid grid-cols-2 gap-4">
        <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Wallet Balance</p>
          <p class="text-lg font-black text-slate-800">₦{{ formatMoney(balance) }}</p>
        </div>
        <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Savings</p>
          <p class="text-lg font-black text-slate-800">₦{{ formatMoney(total_savings) }}</p>
        </div>
        <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Shares</p>
          <p class="text-lg font-black text-slate-800">₦{{ formatMoney(total_shares) }}</p>
        </div>
<!--        <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm">-->
<!--          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Grand Total</p>-->
<!--          <p class="text-lg font-black text-emerald-600">₦{{ formatMoney(total_balance) }}</p>-->
<!--        </div>-->
        <div class="col-span-2 bg-rose-50 p-6 rounded-[2rem] border border-rose-100/50 shadow-sm">
          <p class="text-[10px] font-bold text-rose-400 uppercase tracking-widest mb-1">Outstanding Loans</p>
          <p class="text-lg font-black text-rose-600">₦{{ formatMoney(outstanding_loans) }}</p>
        </div>
      </div>

      <!-- Management Actions -->
      <div class="space-y-4">
        <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] px-4">Management Tools</h3>
        
        <div class="grid gap-3">
          <button @click="$router.push(`/admin/members/${user.id}/passbook`)" class="w-full bg-white p-5 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-4 active:scale-[0.98] transition-all text-left">
            <div class="w-12 h-12 bg-indigo-50 text-indigo-500 rounded-2xl flex items-center justify-center text-2xl">
              <span class="i-mdi-book-open-page-variant"></span>
            </div>
            <div>
              <p class="text-sm font-black text-slate-800">Passbook Management</p>
              <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">View & record contributions</p>
            </div>
            <span class="ml-auto i-mdi-chevron-right text-slate-300"></span>
          </button>

          <button @click="$router.push(`/admin/members/${user.id}/wallet`)" class="w-full bg-white p-5 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-4 active:scale-[0.98] transition-all text-left">
            <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center text-2xl">
              <span class="i-mdi-wallet"></span>
            </div>
            <div>
              <p class="text-sm font-black text-slate-800">Wallet Allocation</p>
              <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Distribute wallet to schemes</p>
            </div>
            <span class="ml-auto i-mdi-chevron-right text-slate-300"></span>
          </button>

          <button @click="$router.push(`/admin/members/${user.id}/loans`)" class="w-full bg-white p-5 rounded-[2rem] border border-slate-100 shadow-sm flex items-center gap-4 active:scale-[0.98] transition-all text-left">
            <div class="w-12 h-12 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center text-2xl">
              <span class="i-mdi-bank-transfer"></span>
            </div>
            <div>
              <p class="text-sm font-black text-slate-800">Loan Management</p>
              <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Repayments & loan history</p>
            </div>
            <span class="ml-auto i-mdi-chevron-right text-slate-300"></span>
          </button>

          <button @click="showLoanModal = true" class="w-full bg-emerald-600 p-5 rounded-[2rem] border border-emerald-500 shadow-lg shadow-emerald-100 flex items-center gap-4 active:scale-[0.98] transition-all text-left text-white">
            <div class="w-12 h-12 bg-white/20 text-white rounded-2xl flex items-center justify-center text-2xl">
              <span class="i-mdi-plus-circle"></span>
            </div>
            <div>
              <p class="text-sm font-black">Create New Loan</p>
              <p class="text-[10px] text-white/70 font-bold uppercase tracking-wider">Issue new Qard Hasan loan</p>
            </div>
            <span class="ml-auto i-mdi-chevron-right text-white/50"></span>
          </button>
        </div>
      </div>
    </div>

    <!-- Create Loan Modal -->
    <transition name="fade">
      <div v-if="showLoanModal" class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-4 sm:p-6 pb-[calc(2rem+env(safe-area-inset-bottom))]">
        <div class="absolute inset-0 bg-black/40" @click="showLoanModal = false"></div>
        <div class="relative w-full sm:max-w-md bg-white rounded-[2.5rem] shadow-2xl border border-slate-100 overflow-hidden">
          <div class="p-6 border-b border-slate-50 flex items-center justify-between">
            <h3 class="text-lg font-black text-slate-800 tracking-tight">Create New Loan</h3>
            <button @click="showLoanModal = false" class="w-8 h-8 bg-slate-50 rounded-xl flex items-center justify-center text-slate-400">✕</button>
          </div>
          <div class="p-6 space-y-4">
            <div class="space-y-1">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">Loan Amount (₦)</label>
              <input v-model="loanForm.amount" type="number" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500" placeholder="e.g. 50000" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">Installments</label>
                <input v-model="loanForm.total_installments" type="number" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500" placeholder="e.g. 10" />
              </div>
              <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">Interval</label>
                <select v-model="loanForm.interval" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500 appearance-none">
                  <option value="monthly">Monthly</option>
                  <option value="weekly">Weekly</option>
                  <option value="daily">Daily</option>
                </select>
              </div>
            </div>
            <div class="space-y-1">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">Repayment Start Date</label>
              <input v-model="loanForm.repayment_start_date" type="date" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>
            <div class="space-y-1">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">Description/Note</label>
              <textarea v-model="loanForm.description" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500 min-h-[80px]" placeholder="Reason for loan..."></textarea>
            </div>
          </div>
          <div class="p-6 border-t border-slate-50">
            <button 
              @click="handleCreateLoan" 
              :disabled="creatingLoan"
              class="w-full bg-emerald-600 py-4 rounded-2xl text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-emerald-100 active:scale-[0.98] transition-all disabled:opacity-50"
            >
              {{ creatingLoan ? 'Creating Loan...' : 'Disburse Loan' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from '../../http'
import getImageUrl from '../../utils/image'
import { useModal } from '../../composables/useModal'

const { alert } = useModal()

const route = useRoute()
const user = ref(null)
const balance = ref(0)
const total_savings = ref(0)
const total_shares = ref(0)
const total_balance = ref(0)
const outstanding_loans = ref(0)
const loading = ref(true)

const showLoanModal = ref(false)
const creatingLoan = ref(false)
const loanForm = ref({
  amount: '',
  total_installments: 10,
  interval: 'monthly',
  description: '',
  repayment_start_date: ''
})

const handleCreateLoan = async () => {
  if (!loanForm.value.amount || !loanForm.value.total_installments) {
    alert('Please enter amount and installments.')
    return
  }

  creatingLoan.value = true
  try {
    await axios.post(`/api/admin/members/${route.params.id}/loans`, loanForm.value)
    alert('Loan created and disbursed successfully.', 'Success')
    showLoanModal.value = false
    fetchData()
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to create loan.'
    alert(msg, 'Error')
  } finally {
    creatingLoan.value = false
  }
}

const formatMoney = (val) => {
  return new Intl.NumberFormat().format(val || 0)
}

const fetchData = async () => {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/admin/members/${route.params.id}`)
    user.value = data.user
    balance.value = data.balance
    total_savings.value = data.total_savings
    total_shares.value = data.total_shares
    total_balance.value = data.total_balance
    outstanding_loans.value = data.outstanding_loans
  } catch (e) {
    console.error('Failed to fetch member details', e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
