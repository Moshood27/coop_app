<template>
  <div class="space-y-6">
    <!-- Search Bar -->
    <div class="relative group">
      <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-emerald-600 text-slate-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
      <input :value="searchQuery" @input="$emit('update:searchQuery', $event.target.value)" type="text" placeholder="Search transactions..."
             class="w-full bg-white pl-11 p-4 rounded-2xl border border-slate-100 text-sm outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all shadow-sm" />
    </div>

    <!-- Recent Wallet Transactions -->
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
      <div class="flex justify-between items-center mb-5 gap-2 flex-wrap">
        <h3 class="font-bold text-slate-800">Transaction History</h3>
        <button @click="$emit('loadMore')" class="text-emerald-700 text-[10px] font-black uppercase tracking-wider px-3 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 transition-colors">View All</button>
      </div>
      <div v-if="transactions.length" class="space-y-4">
        <div v-for="tx in transactions" :key="tx.id" class="flex items-center justify-between gap-3 group">
          <div class="flex items-center gap-3 min-w-0">
            <div :class="tx.type === 'credit' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'"
                 class="w-11 h-11 rounded-2xl flex items-center justify-center text-lg shrink-0 transition-transform group-active:scale-90">
              <svg v-if="tx.type === 'credit'" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
              </svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
              </svg>
            </div>
            <div class="min-w-0">
              <p class="text-sm font-bold text-slate-800 truncate leading-none mb-1">{{ titleFor(tx) }}</p>
              <p v-if="tx.meta?.notes" class="text-[10px] text-slate-500 italic mt-0.5">{{ tx.meta.notes }}</p>
              <p class="text-[10px] text-slate-400 font-medium uppercase tracking-tighter leading-relaxed flex items-center gap-1 cursor-pointer hover:text-emerald-600 transition-colors" @click="$emit('copy', tx.reference)" title="Click to copy">
                {{ new Date(tx.created_at).toLocaleDateString() }} • {{ tx.reference.length > 15 ? tx.reference.substring(0, 12) + '...' : tx.reference }}
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                </svg>
              </p>
              <div v-if="tx.meta?.maintenance_charge" class="mt-1 flex gap-2 text-[9px] font-bold text-slate-500 uppercase tracking-tighter bg-slate-50 px-2 py-1 rounded-lg w-fit">
                <span>Gross: ₦{{ formatMoney(tx.meta.gross_amount) }}</span>
                <span class="text-rose-600">Fee: ₦{{ formatMoney(tx.meta.maintenance_charge) }}</span>
              </div>
            </div>
          </div>
          <div class="text-right shrink-0">
            <p class="font-black text-sm" :class="tx.type === 'credit' ? 'text-emerald-700' : 'text-slate-800'">
              {{ tx.type === 'credit' ? '+' : '-' }}₦{{ formatMoney(tx.amount) }}
            </p>
            <a :href="getReceiptDownloadUrl(tx)" target="_blank" class="text-emerald-700 text-[9px] font-black uppercase tracking-widest hover:underline">Receipt</a>
          </div>
        </div>
      </div>
      <div v-else class="text-center py-8">
        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
          </svg>
        </div>
        <p class="text-xs text-slate-400 font-medium">{{ searchQuery ? 'No matching transactions found.' : 'No transactions recorded yet.' }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  transactions: Array,
  searchQuery: String,
  titleFor: Function,
  getReceiptDownloadUrl: Function
})

defineEmits(['update:searchQuery', 'loadMore', 'copy'])

const formatMoney = (val) => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
</script>
