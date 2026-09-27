<template>
  <transition name="fade">
    <div v-if="isOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
      
      <!-- Modal Content -->
      <div class="relative w-full max-w-sm bg-white rounded-[2.5rem] shadow-2xl overflow-hidden animate-in zoom-in duration-300">
        <!-- Header/Status Icon -->
        <div class="pt-10 pb-6 flex flex-col items-center">
          <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4 shadow-inner" :class="statusBgClass">
             <span class="text-4xl">{{ statusIcon }}</span>
          </div>
          <h3 class="text-xl font-black text-slate-800 text-center px-6 leading-tight">{{ txTitle }}</h3>
          <p class="text-3xl font-black mt-2 tracking-tight" :class="amountClass">
             {{ amountPrefix }} ₦{{ formatMoney(transaction.amount) }}
          </p>
          <div class="mt-4 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-[0.2em] shadow-sm border border-slate-50" :class="statusBadgeClass">
            {{ transaction.status || 'Successful' }}
          </div>
        </div>

        <!-- Details -->
        <div class="px-8 pb-8 space-y-4">
          <div class="bg-slate-50/50 rounded-[2rem] p-6 space-y-4 border border-slate-100">
            <div class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Reference</span>
              <span class="text-xs font-black text-slate-700">{{ transaction.reference || transaction.id }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Date & Time</span>
              <span class="text-xs font-black text-slate-700">{{ formatDate(transaction.created_at) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Type</span>
              <span class="text-xs font-black text-slate-700 uppercase">{{ transaction.type }}</span>
            </div>
            <div v-if="transaction.meta?.to_name || transaction.meta?.to_membership" class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Recipient</span>
              <span class="text-xs font-black text-slate-700">{{ transaction.meta.to_name || transaction.meta.to_membership }}</span>
            </div>
            <div v-if="transaction.meta?.from_name || transaction.meta?.from_membership" class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Sender</span>
              <span class="text-xs font-black text-slate-700">{{ transaction.meta.from_name || transaction.meta.from_membership }}</span>
            </div>
            <div v-if="transaction.description" class="pt-2 border-t border-slate-200/50">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Remark</span>
              <span class="text-xs font-black text-slate-700 leading-relaxed">{{ transaction.description }}</span>
            </div>
          </div>

          <!-- Actions -->
          <div class="grid grid-cols-2 gap-3">
            <button @click="shareTransaction" class="flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 py-4 rounded-2xl font-bold transition-all active:scale-95">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0-12.814a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5zm0 10.628a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5z" />
              </svg>
              Share
            </button>
            <button @click="downloadTransaction" class="flex items-center justify-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white py-4 rounded-2xl font-bold transition-all shadow-lg shadow-emerald-100 active:scale-95">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12l4.5 4.5m0 0l4.5-4.5M12 3v13.5" />
              </svg>
              Receipt
            </button>
          </div>
          
          <button @click="close" class="w-full text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] py-2 hover:text-slate-600 transition-colors">
            Close
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  isOpen: Boolean,
  transaction: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['close', 'download', 'share'])

const close = () => emit('close')

const txTitle = computed(() => {
  if (props.transaction.title) return props.transaction.title
  if (props.transaction.type === 'credit') return 'Credit Transaction'
  if (props.transaction.type === 'debit') return 'Debit Transaction'
  return 'Transaction Detail'
})

const statusIcon = computed(() => {
  if (props.transaction.type === 'credit') return '✅'
  if (props.transaction.type === 'debit') return '💸'
  return '📝'
})

const statusBgClass = computed(() => {
  if (props.transaction.type === 'credit') return 'bg-emerald-50 text-emerald-600'
  if (props.transaction.type === 'debit') return 'bg-rose-50 text-rose-600'
  return 'bg-blue-50 text-blue-600'
})

const amountClass = computed(() => {
  if (props.transaction.type === 'credit') return 'text-emerald-600'
  if (props.transaction.type === 'debit') return 'text-rose-600'
  return 'text-slate-800'
})

const amountPrefix = computed(() => {
  if (props.transaction.type === 'credit') return '+'
  if (props.transaction.type === 'debit') return '-'
  return ''
})

const statusBadgeClass = computed(() => {
  return 'bg-emerald-50 text-emerald-600'
})

const formatMoney = (amount) => {
  return new Intl.NumberFormat('en-NG', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount || 0)
}

const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleString('en-NG', {
    dateStyle: 'medium',
    timeStyle: 'short'
  })
}

const shareTransaction = () => emit('share', props.transaction)
const downloadTransaction = () => emit('download', props.transaction)
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
