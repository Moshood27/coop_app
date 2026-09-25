<template>
  <div class="mt-12">
    <!-- Tabs Navigation -->
    <div class="flex p-1.5 bg-slate-200/50 rounded-[1.5rem] gap-1 shadow-inner mb-6">
      <button 
        v-for="tab in ['transactions', 'passbook', 'vtu']" 
        :key="tab"
        @click="switchTab(tab)"
        :class="activeTab === tab ? 'bg-white text-emerald-700 shadow-md scale-[1.02]' : 'text-slate-500 hover:bg-white/30'"
        class="flex-1 py-3 rounded-2xl text-[10px] font-black uppercase tracking-wider transition-all duration-300 ease-out"
      >
        {{ tab }}
      </button>
    </div>

    <!-- Search Bar -->
    <div v-if="activeTab !== 'passbook'" class="relative group mb-6">
      <input 
        :value="searchQuery" 
        @input="$emit('update:searchQuery', $event.target.value)"
        type="text" 
        :placeholder="activeTab === 'transactions' ? 'Search transactions...' : 'Search airtime/data...'"
        class="w-full pl-12 pr-4 py-4 bg-white border border-slate-100 rounded-2xl text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all shadow-sm"
      >
      <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-emerald-500 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
    </div>

    <!-- Transactions Tab -->
    <div v-if="activeTab === 'transactions'" class="space-y-6">
      <!-- Live Activity Feed -->
      <div v-if="liveActions && liveActions.length" class="animate-in fade-in slide-in-from-bottom duration-500">
        <h3 class="font-bold text-slate-800 text-sm mb-3 flex items-center gap-2">
          <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
          Live Activity
        </h3>
        <div class="space-y-3">
          <div v-for="action in liveActions" :key="action.id" 
               class="bg-white p-4 rounded-2xl flex items-center justify-between gap-3 border-2 border-emerald-100 shadow-sm">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center text-lg shrink-0">
                🔔
              </div>
              <div>
                <p class="font-bold text-slate-800 text-sm">{{ action.message }}</p>
                <p class="text-[10px] text-emerald-600 font-mono uppercase tracking-widest font-black">{{ action.time }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-between items-center">
        <h3 class="font-bold text-slate-800 text-lg">Recent Transactions</h3>
        <button class="text-emerald-700 text-sm font-bold" @click="$router.push('/passbook')">See All</button>
      </div>

      <div v-if="filteredTransactions && filteredTransactions.length" class="space-y-3">
        <div v-for="tx in filteredTransactions" :key="tx.id"
             class="bg-white p-4 rounded-2xl flex items-center justify-between gap-3 overflow-hidden border border-slate-100 shadow-sm">
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <div :class="tx.type === 'credit' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'"
                 class="w-10 h-10 rounded-full flex items-center justify-center text-lg shrink-0">
              {{ tx.type === 'credit' ? '+' : '−' }}
            </div>
            <div class="min-w-0 overflow-hidden">
              <div class="flex items-center gap-2 flex-wrap">
                <p class="font-bold text-slate-800 text-sm truncate max-w-[160px] sm:max-w-none">{{ txTitle(tx) }}</p>
                <span v-if="isFine(tx)" class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[10px] font-black uppercase">Fine</span>
              </div>
              <p class="text-[10px] text-gray-500 uppercase font-medium">{{ formatDate(tx.created_at) }}</p>
              <p class="text-[10px] text-slate-400 font-mono truncate cursor-pointer hover:text-emerald-600 transition-colors flex items-center gap-1" @click="$emit('copy', txPrefix(tx))" title="Click to copy">
                {{ txPrefix(tx).length > 15 ? txPrefix(tx).substring(0, 12) + '...' : txPrefix(tx) }}
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                </svg>
              </p>
            </div>
          </div>
          <div class="text-right">
            <p class="font-bold text-slate-800">₦ {{ hideBalances ? '***,***.**' : formatMoney(tx.amount) }}</p>
          </div>
        </div>
      </div>
      <div v-else class="text-center py-10 text-gray-400 bg-white rounded-3xl border border-dashed border-slate-200">
        <p>No transactions found.</p>
      </div>
    </div>

    <!-- Passbook Tab -->
    <div v-if="activeTab === 'passbook'" class="space-y-6">
      <div v-if="isLoadingPassbook" class="space-y-4">
        <div class="h-32 bg-slate-200 rounded-[2rem] animate-pulse"></div>
        <div class="h-20 bg-slate-100 rounded-3xl animate-pulse"></div>
      </div>
      <div v-else-if="passbookSummary" class="space-y-6 animate-in fade-in slide-in-from-bottom duration-500">
        <!-- Yearly Summary Card -->
        <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-[2rem] p-7 text-white shadow-xl relative overflow-hidden">
          <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/5 rounded-full"></div>
          <p class="text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] mb-1">Yearly Cumulative</p>
          <h2 class="text-4xl font-black tracking-tight">₦ {{ formatMoney(passbookSummary.grand_total) }}</h2>
          
          <div class="mt-8 pt-6 border-t border-white/10 flex justify-between items-center">
            <div>
              <p class="text-slate-400 text-[10px] uppercase font-bold">Current Year</p>
              <p class="text-sm font-bold">{{ new Date().getFullYear() }}</p>
            </div>
            <button @click="$router.push('/passbook')" class="bg-emerald-600 hover:bg-emerald-500 px-6 py-3 rounded-2xl text-xs font-bold transition-all shadow-lg shadow-emerald-900/20">
              View Full Passbook
            </button>
          </div>
        </div>

        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-white p-5 rounded-[2rem] border border-slate-100 shadow-sm">
            <p class="text-[10px] text-slate-400 uppercase font-black mb-1">Schemes</p>
            <p class="text-xl font-black text-slate-800">{{ passbookSummary.matrix?.length || 0 }}</p>
          </div>
          <div v-if="passbookSummary.agm_fee_amount" class="bg-white p-5 rounded-[2rem] border border-slate-100 shadow-sm">
            <p class="text-[10px] text-slate-400 uppercase font-black mb-1">AGM Fee</p>
            <div class="flex items-center gap-2">
              <p class="text-xl font-black text-slate-800">₦{{ formatMoney(passbookSummary.agm_fee_amount) }}</p>
              <span :class="passbookSummary.agm_fee_paid ? 'text-emerald-500' : 'text-amber-500'" class="text-xs">
                {{ passbookSummary.agm_fee_paid ? '✓' : '⌛' }}
              </span>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="text-center py-10 text-gray-400 bg-white rounded-3xl border border-dashed border-slate-200">
        <p>Could not load passbook summary.</p>
        <button @click="$emit('fetchPassbookSummary')" class="mt-4 text-emerald-700 font-bold underline">Retry</button>
      </div>
    </div>

    <!-- VTU Tab -->
    <div v-if="activeTab === 'vtu'" class="space-y-6">
      <div class="flex justify-between items-center">
        <h3 class="font-bold text-slate-800 text-lg">Recent Airtime/Data</h3>
        <button class="text-emerald-700 text-sm font-bold" @click="$router.push('/vtu/history')">See All</button>
      </div>

      <div v-if="filteredUtilityTransactions && filteredUtilityTransactions.length" class="space-y-3">
        <div v-for="ux in filteredUtilityTransactions" :key="ux.id"
             class="bg-white p-4 rounded-2xl flex items-center justify-between gap-3 overflow-hidden border border-slate-100 shadow-sm">
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <div :class="ux.status === 'success' ? 'bg-emerald-100 text-emerald-600' : (ux.status === 'failed' ? 'bg-rose-100 text-rose-600' : 'bg-yellow-100 text-yellow-600')"
                 class="w-10 h-10 rounded-full flex items-center justify-center text-lg shrink-0">
              {{ ux.status === 'success' ? '✓' : (ux.status === 'failed' ? '✕' : '⌛') }}
            </div>
            <div class="min-w-0 overflow-hidden">
              <p class="font-bold text-slate-800 text-sm capitalize truncate max-w-[180px] sm:max-w-none">{{ utilLabel(ux) }}</p>
              <p class="text-[10px] text-gray-500 uppercase font-medium">{{ formatDate(ux.created_at) }}</p>
              <p class="text-[10px] text-slate-400 font-mono truncate">{{ ux.reference }}</p>
            </div>
          </div>
          <div class="text-right shrink-0">
            <p class="font-bold text-slate-800">₦ {{ formatMoney(ux.amount) }}</p>
          </div>
        </div>
      </div>
      <div v-else class="text-center py-10 text-gray-400 bg-white rounded-3xl border border-dashed border-slate-200">
        <p>No VTU activity found.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useAppStatusStore } from '../../stores/appStatus'

const props = defineProps({
  activeTab: String,
  searchQuery: String,
  liveActions: Array,
  filteredTransactions: Array,
  filteredUtilityTransactions: Array,
  passbookSummary: Object,
  isLoadingPassbook: Boolean,
  hideBalances: Boolean
})

const emit = defineEmits(['update:activeTab', 'update:searchQuery', 'switchTab', 'fetchPassbookSummary', 'copy'])

const appStatusStore = useAppStatusStore()

const switchTab = (tab) => {
  emit('update:activeTab', tab)
  emit('update:searchQuery', '')
  emit('switchTab', tab)
}

const formatMoney = (val) => Number(val ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
const formatDate = (dateStr) => {
    if (!dateStr) return ''
    return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

const txTitle = (tx) => {
  const src = tx?.source
  if (src === 'wallet_allocation') return 'Allocation to Schemes'
  if (src === 'paystack_dva') return 'Bank Transfer (DVA)'
  if (src === 'vtu_airtime') return 'Airtime Purchase'
  if (src === 'vtu_data') return 'Data Purchase'
  if (src === 'p2p_transfer') {
    if (tx.type === 'debit') {
      const name = tx?.meta?.to_name || tx?.meta?.to_membership
      return name ? `Transfer to ${name}` : 'Transfer Sent'
    } else {
      const name = tx?.meta?.from_name || tx?.meta?.from_membership
      return name ? `Transfer from ${name}` : 'Transfer Received'
    }
  }
  if (src === 'contribution' || (tx.meta && tx.meta.scheme_name)) return tx.meta.scheme_name || 'Contribution'
  return 'Wallet Transaction'
}

const txPrefix = (tx) => {
  return tx.reference || tx.tx_ref || `tx_${tx.id}`
}

const isFine = (tx) => {
  const src = (tx.source || '').toLowerCase()
  const meta = tx.meta || {}
  const schemeName = (meta.scheme_name || '').toLowerCase()
  return src.includes('fine') || schemeName.includes('fine') || schemeName.includes('lateness') || schemeName.includes('apology')
}

const utilLabel = (ux) => {
  const type = (ux.type || '').toLowerCase()
  const net = (ux.network || '').toUpperCase()
  const phone = ux.phone_number || ''
  if (type === 'airtime') return `Airtime — ${net} (${phone})`
  if (type === 'data') return `Data — ${net} (${phone})`
  return `${type || 'utility'} — ${net} (${phone})`
}
</script>
