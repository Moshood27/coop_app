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
          <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-[2rem] flex items-center justify-center font-black text-3xl">
            {{ user.name.charAt(0) }}
          </div>
          <div>
            <h2 class="text-xl font-black text-slate-800 leading-tight">{{ user.surname }} {{ user.name }}</h2>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-2">{{ user.membership_number }}</p>
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
          <p class="text-lg font-black text-slate-800">₦{{ formatMoney(total_balance) }}</p>
        </div>
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
const balance = ref(0)
const total_balance = ref(0)
const outstanding_loans = ref(0)
const loading = ref(true)

const formatMoney = (val) => {
  return new Intl.NumberFormat().format(val || 0)
}

const fetchData = async () => {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/admin/members/${route.params.id}`)
    user.value = data.user
    balance.value = data.balance
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
