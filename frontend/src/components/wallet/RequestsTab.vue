<template>
  <div class="space-y-6">
    <!-- Search Bar -->
    <div class="relative group">
      <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-emerald-600 text-slate-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
      <input :value="searchQuery" @input="$emit('update:searchQuery', $event.target.value)" type="text" placeholder="Search requests..."
             class="w-full bg-white pl-11 p-4 rounded-2xl border border-slate-100 text-sm outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all shadow-sm" />
    </div>

    <!-- Your Withdrawal Requests -->
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
      <div class="flex justify-between items-center mb-5 gap-2 flex-wrap">
        <h3 class="font-bold text-slate-800">Withdrawal Requests</h3>
        <button v-if="page < lastPage" @click="$emit('loadMore')" class="text-emerald-700 text-[10px] font-black uppercase tracking-wider px-3 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 transition-colors">Load more</button>
      </div>
      <div v-if="requests.length" class="space-y-4">
        <div v-for="wr in requests" :key="wr.id" class="group border border-slate-100 rounded-2xl p-4 active:bg-slate-50 transition-colors">
          <div class="flex items-center justify-between gap-3 mb-2">
            <div class="min-w-0">
              <p class="text-base font-black text-slate-800">₦ {{ formatMoney(wr.amount) }}</p>
              <p class="text-[10px] uppercase font-mono text-slate-400 tracking-tighter flex items-center gap-1 cursor-pointer hover:text-emerald-600 transition-colors" @click="$emit('copy', wr.reference)" title="Click to copy">
                REF: {{ wr.reference.length > 15 ? wr.reference.substring(0, 12) + '...' : wr.reference }}
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                </svg>
              </p>
            </div>
            <div class="shrink-0 flex items-center gap-2">
              <span v-if="wr.type" class="text-[8px] font-black uppercase px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 border border-slate-200">{{ wr.type.replace('_', ' ') }}</span>
              <span :class="statusClass(wr.status)" class="text-[10px] font-black uppercase px-2 py-1 rounded-lg tracking-wider">{{ wr.status }}</span>
              <button v-if="wr.status === 'pending'" @click="$emit('cancel', wr)" class="text-rose-700 text-[10px] font-black uppercase px-2 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 transition-colors">Cancel</button>
            </div>
          </div>
          <div class="flex items-center justify-between mt-1 pt-2 border-t border-slate-50">
            <p class="text-[10px] text-slate-400 font-medium">{{ new Date(wr.created_at).toLocaleDateString() }} • {{ new Date(wr.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</p>
            <p v-if="wr.bank?.account_number || wr.account_number" class="text-[10px] text-slate-400 font-bold uppercase truncate max-w-[140px]">
              {{ wr.bank?.bank_name || wr.bank_name }}
            </p>
          </div>
        </div>
      </div>
      <div v-else class="text-center py-6">
        <p class="text-xs text-slate-400">{{ searchQuery ? 'No matching requests found.' : 'No withdrawal requests found.' }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  requests: Array,
  searchQuery: String,
  page: Number,
  lastPage: Number
})

defineEmits(['update:searchQuery', 'loadMore', 'copy', 'cancel'])

const formatMoney = (val) => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })

const statusClass = (status) => {
  if (status === 'paid') return 'bg-emerald-100 text-emerald-700'
  if (status === 'declined') return 'bg-rose-100 text-rose-700'
  return 'bg-amber-100 text-amber-700'
}
</script>
