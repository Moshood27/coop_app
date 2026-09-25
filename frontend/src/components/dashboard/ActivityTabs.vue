<template>
  <div class="mt-12">
    <div class="flex items-center justify-between mb-6 overflow-x-auto no-scrollbar">
      <div class="flex gap-4 min-w-max">
        <button @click="$emit('update:activeTab', 'transactions')" 
                :class="activeTab === 'transactions' ? 'text-slate-800 border-emerald-500' : 'text-slate-400 border-transparent'"
                class="pb-2 font-bold text-sm border-b-2 transition-all">
          Wallet
        </button>
        <button @click="$emit('update:activeTab', 'savings')" 
                :class="activeTab === 'savings' ? 'text-slate-800 border-emerald-500' : 'text-slate-400 border-transparent'"
                class="pb-2 font-bold text-sm border-b-2 transition-all">
          Savings
        </button>
        <button @click="$emit('update:activeTab', 'shares')" 
                :class="activeTab === 'shares' ? 'text-slate-800 border-emerald-500' : 'text-slate-400 border-transparent'"
                class="pb-2 font-bold text-sm border-b-2 transition-all">
          Shares
        </button>
        <button v-if="appStatusStore.features['airtime-data-enabled']"
                @click="$emit('update:activeTab', 'vtu')" 
                :class="activeTab === 'vtu' ? 'text-slate-800 border-emerald-500' : 'text-slate-400 border-transparent'"
                class="pb-2 font-bold text-sm border-b-2 transition-all">
          VTU
        </button>
      </div>
      
      <div class="relative hidden sm:block">
        <input :value="searchQuery" @input="$emit('update:searchQuery', $event.target.value)" type="text" placeholder="Search..." 
               class="bg-white border border-slate-200 rounded-full py-1.5 px-4 text-xs focus:ring-2 focus:ring-emerald-500/20 outline-none w-48 shadow-sm transition-all" />
      </div>
    </div>

    <!-- Wallet Transactions -->
    <div v-if="activeTab === 'transactions'" class="animate-in fade-in duration-300">
      <div v-if="transactions.length" class="space-y-3">
        <div v-for="tx in transactions" :key="tx.id"
             class="bg-white p-4 rounded-2xl flex items-center justify-between gap-3 overflow-hidden border border-slate-100 shadow-sm">
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <div :class="tx.type === 'credit' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'"
                 class="w-10 h-10 rounded-full flex items-center justify-center text-lg shrink-0">
              {{ tx.type === 'credit' ? '↓' : '↑' }}
            </div>
            <div class="min-w-0 overflow-hidden">
              <p class="font-bold text-slate-800 text-sm capitalize truncate max-w-[180px] sm:max-w-none">{{ tx.source.replace(/_/g, ' ') }}</p>
              <p class="text-[10px] text-gray-500 uppercase font-medium">{{ formatDate(tx.created_at) }}</p>
              <p class="text-[10px] text-slate-400 font-mono truncate">{{ tx.reference }}</p>
            </div>
          </div>
          <div class="text-right shrink-0">
            <p class="font-bold" :class="tx.type === 'credit' ? 'text-emerald-600' : 'text-rose-600'">
              {{ tx.type === 'credit' ? '+' : '-' }} ₦ {{ formatMoney(tx.amount) }}
            </p>
            <p v-if="tx.meta && tx.meta.new_balance" class="text-[9px] text-slate-400 font-medium">Bal: ₦{{ formatMoney(tx.meta.new_balance) }}</p>
          </div>
        </div>
      </div>
      <div v-else class="text-center py-10 text-gray-400 bg-white rounded-3xl border border-dashed border-slate-200">
        <p>No wallet transactions found.</p>
      </div>
    </div>

    <!-- Savings Transactions -->
    <div v-if="activeTab === 'savings'" class="animate-in fade-in duration-300">
      <div v-if="savings.length" class="space-y-3">
        <div v-for="sx in savings" :key="sx.id"
             class="bg-white p-4 rounded-2xl flex items-center justify-between gap-3 overflow-hidden border border-slate-100 shadow-sm">
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <div :class="sx.amount > 0 ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'"
                 class="w-10 h-10 rounded-full flex items-center justify-center text-lg shrink-0">
              {{ sx.amount > 0 ? '💰' : '💸' }}
            </div>
            <div class="min-w-0 overflow-hidden">
              <p class="font-bold text-slate-800 text-sm capitalize truncate max-w-[180px] sm:max-w-none">{{ sx.category.replace(/_/g, ' ') }}</p>
              <p class="text-[10px] text-gray-500 uppercase font-medium">{{ formatDate(sx.paid_at || sx.created_at) }}</p>
              <p class="text-[10px] text-slate-400 font-mono truncate">{{ sx.reference }}</p>
            </div>
          </div>
          <div class="text-right shrink-0">
            <p class="font-bold" :class="sx.amount > 0 ? 'text-emerald-600' : 'text-rose-600'">
              {{ sx.amount > 0 ? '+' : '' }} ₦ {{ formatMoney(sx.amount) }}
            </p>
          </div>
        </div>
      </div>
      <div v-else class="text-center py-10 text-gray-400 bg-white rounded-3xl border border-dashed border-slate-200">
        <p>No savings history found.</p>
      </div>
    </div>

    <!-- Shares Transactions -->
    <div v-if="activeTab === 'shares'" class="animate-in fade-in duration-300">
      <div v-if="shares.length" class="space-y-3">
        <div v-for="sh in shares" :key="sh.id"
             class="bg-white p-4 rounded-2xl flex items-center justify-between gap-3 overflow-hidden border border-slate-100 shadow-sm">
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <div class="bg-indigo-100 text-indigo-600 w-10 h-10 rounded-full flex items-center justify-center text-lg shrink-0">
              📈
            </div>
            <div class="min-w-0 overflow-hidden">
              <p class="font-bold text-slate-800 text-sm capitalize truncate max-w-[180px] sm:max-w-none">{{ sh.category.replace(/_/g, ' ') }}</p>
              <p class="text-[10px] text-gray-500 uppercase font-medium">{{ formatDate(sh.paid_at || sh.created_at) }}</p>
              <p class="text-[10px] text-slate-400 font-mono truncate">{{ sh.reference }}</p>
            </div>
          </div>
          <div class="text-right shrink-0">
            <p class="font-bold text-indigo-600">+ ₦ {{ formatMoney(sh.amount) }}</p>
          </div>
        </div>
      </div>
      <div v-else class="text-center py-10 text-gray-400 bg-white rounded-3xl border border-dashed border-slate-200">
        <p>No shares history found.</p>
      </div>
    </div>

    <!-- VTU Transactions -->
    <div v-if="activeTab === 'vtu'" class="animate-in fade-in duration-300">
      <div v-if="vtuTransactions.length" class="space-y-3">
        <div v-for="ux in vtuTransactions" :key="ux.id"
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
  transactions: Array,
  savings: Array,
  shares: Array,
  vtuTransactions: Array
})

defineEmits(['update:activeTab', 'update:searchQuery'])

const appStatusStore = useAppStatusStore()

const formatMoney = (amount) => {
  return Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const formatDate = (date) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
}

const utilLabel = (ux) => {
  const type = ux.type || 'VTU'
  const sub = ux.meta?.vtu_type || ''
  const phone = ux.meta?.phone || ux.meta?.customer_id || ''
  return `${type} ${sub} ${phone}`.trim()
}
</script>
