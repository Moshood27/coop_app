<template>
  <transition name="fade">
    <div v-if="isOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="close"></div>
      
      <!-- Modal Content -->
      <div class="relative w-full max-w-full sm:max-w-md md:max-w-lg lg:max-w-xl xl:max-w-2xl bg-white rounded-3xl md:rounded-[2.5rem] shadow-2xl overflow-hidden animate-in zoom-in duration-300 max-h-[90vh] flex flex-col">
        <!-- Header/Status Icon -->
        <div class="pt-8 sm:pt-10 pb-4 sm:pb-6 flex flex-col items-center">
          <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full flex items-center justify-center mb-4 shadow-inner" :class="statusBgClass">
             <span :class="[statusIcon, 'text-3xl sm:text-4xl w-10 h-10 sm:w-12 sm:h-12']"></span>
          </div>
          <h3 class="text-lg sm:text-xl font-black text-slate-800 text-center px-6 leading-tight">{{ txTitle }}</h3>
          <p class="text-2xl sm:text-3xl font-black mt-2 tracking-tight" :class="amountClass">
             {{ amountPrefix }} ₦{{ formatMoney(transaction.amount) }}
          </p>
          <div class="mt-4 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-[0.2em] shadow-sm border border-slate-50" :class="statusBadgeClass">
            {{ transaction.status || 'Successful' }}
          </div>
        </div>

        <!-- Details -->
        <div class="px-6 sm:px-8 pb-6 sm:pb-8 space-y-4 overflow-y-auto flex-1">
          <!-- Hidden Printable Receipt for Image Generation -->
          <div ref="receiptRef" class="fixed -left-[9999px] top-0 w-[450px] bg-white p-8 font-sans border border-slate-100" style="font-family: 'Inter', sans-serif;">
            <div class="flex flex-col items-center">
              <!-- Header with Logo -->
              <div class="flex items-center gap-2 mb-6">
                <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A4.877 4.877 0 0012 9c-1.38 0-2.63.571-3.525 1.485V21" />
                  </svg>
                </div>
                <span class="text-xl font-black text-slate-800 tracking-tight">Cooperative</span>
              </div>

              <!-- Status Icon -->
              <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4 shadow-inner" :class="statusBgClass">
                 <span :class="[statusIcon, 'text-4xl w-12 h-12']"></span>
              </div>
              
              <h3 class="text-sm font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">{{ txTitle }}</h3>
              <p class="text-4xl font-black tracking-tight" :class="amountClass">
                 {{ amountPrefix }} ₦{{ formatMoney(transaction.amount) }}
              </p>
              
              <div class="mt-4 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-[0.2em] border" :class="statusBadgeClass">
                {{ transaction.status || 'Successful' }}
              </div>

              <div class="mt-8 w-full space-y-4 border-y border-slate-100 py-8">
                <div class="flex justify-between items-center">
                  <span class="text-sm font-bold text-slate-400 uppercase tracking-widest">Reference</span>
                  <span class="text-sm font-black text-slate-700 font-mono">{{ transaction.reference || transaction.id }}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-sm font-bold text-slate-400 uppercase tracking-widest">Date & Time</span>
                  <span class="text-sm font-black text-slate-700">{{ formatDate(transaction.created_at) }}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-sm font-bold text-slate-400 uppercase tracking-widest">Channel / Source</span>
                  <span class="text-sm font-black text-slate-700">{{ sourceLabel }}</span>
                </div>
                
                <div v-if="transaction.meta?.to_name || transaction.meta?.to_membership" class="flex justify-between items-center">
                  <span class="text-sm font-bold text-slate-400 uppercase tracking-widest">Recipient</span>
                  <span class="text-sm font-black text-slate-700">{{ transaction.meta.to_name || transaction.meta.to_membership }}</span>
                </div>
                <div v-if="transaction.meta?.from_name || transaction.meta?.from_membership" class="flex justify-between items-center">
                  <span class="text-sm font-bold text-slate-400 uppercase tracking-widest">Sender</span>
                  <span class="text-sm font-black text-slate-700">{{ transaction.meta.from_name || transaction.meta.from_membership }}</span>
                </div>
              </div>

              <!-- Allocation Details (Matches PDF/UI) -->
              <div v-if="isWalletAllocation && allocationItems.length" class="w-full mt-6 bg-slate-50 rounded-[2rem] p-6 space-y-4 border border-slate-100">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Allocation Details</div>
                <div class="divide-y divide-slate-200">
                  <div v-for="(item, idx) in allocationItems" :key="idx" class="py-3 flex items-center justify-between">
                    <div class="text-xs font-bold text-slate-700">
                      {{ allocationLabel(item) }}
                    </div>
                    <div class="text-xs font-black text-slate-800">₦{{ formatMoney(item.amount || 0) }}</div>
                  </div>
                </div>
              </div>

              <div class="mt-12 text-center">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-2">Generated from Cooperative Portal</p>
                <div class="text-[8px] text-slate-300 max-w-[250px] mx-auto">
                  This transaction is subject to the terms and conditions of the cooperative. For support, contact the office with the reference number above.
                </div>
              </div>
            </div>
          </div>

          <div class="bg-slate-50/50 rounded-[2rem] p-4 sm:p-6 space-y-4 border border-slate-100">
            <div class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Reference</span>
              <!-- Right side container is shrinkable so long refs don't break layout -->
              <div class="flex items-center gap-2 min-w-0 max-w-[70%] sm:max-w-[75%] md:max-w-[80%] justify-end">
                <!-- Truncate visually, keep full value in title for hover and copy button for exact value -->
                <span
                  class="text-xs font-black text-slate-700 font-mono truncate max-w-full select-text"
                  :title="transaction.reference || transaction.id"
                >
                  {{ transaction.reference || transaction.id }}
                </span>
                <button
                  @click="copy(transaction.reference || transaction.id)"
                  class="text-emerald-600 hover:text-emerald-700 shrink-0"
                  :aria-label="`Copy reference ${transaction.reference || transaction.id}`"
                  :title="'Copy reference'"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                  </svg>
                </button>
              </div>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Date & Time</span>
              <span class="text-xs font-black text-slate-700">{{ formatDate(transaction.created_at) }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Type</span>
              <span class="text-xs font-black text-slate-700 uppercase">{{ transaction.type }}</span>
            </div>
            <!-- Channel / Source (matches PDF mapping) -->
            <div class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Channel / Source</span>
              <span class="text-xs font-black text-slate-700">{{ sourceLabel }}</span>
            </div>

            <!-- Amount Breakdown if maintenance charge exists (matches PDF) -->
            <template v-if="hasMaintenanceCharge">
              <div class="flex justify-between items-center pt-2 border-t border-slate-200/50">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Gross Amount</span>
                <span class="text-xs font-black text-slate-700">₦{{ formatMoney(grossAmount) }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Maintenance Charge</span>
                <span class="text-xs font-black text-rose-600">- ₦{{ formatMoney(maintenanceCharge) }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-[10px] font-black text-slate-700 uppercase tracking-widest">Net Amount</span>
                <span class="text-xs font-black text-slate-800">₦{{ formatMoney(transaction.amount) }}</span>
              </div>
            </template>

            <div v-if="transaction.meta?.to_name || transaction.meta?.to_membership" class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Recipient</span>
              <span class="text-xs font-black text-slate-700">{{ transaction.meta.to_name || transaction.meta.to_membership }}</span>
            </div>
            <div v-if="transaction.meta?.from_name || transaction.meta?.from_membership" class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Sender</span>
              <span class="text-xs font-black text-slate-700">{{ transaction.meta.from_name || transaction.meta.from_membership }}</span>
            </div>
            <!-- Notes (combine description + meta.note + to/from like PDF) -->
            <div v-if="hasNotes" class="pt-2 border-t border-slate-200/50">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Notes</span>
              <div class="text-xs font-black text-slate-700 leading-relaxed space-y-0.5">
                <div v-if="transaction.description">{{ transaction.description }}</div>
                <div v-if="transaction.meta?.note">{{ transaction.meta.note }}</div>
                <div v-if="transaction.meta?.to_name">To: {{ transaction.meta.to_name }}</div>
                <div v-if="!transaction.meta?.to_name && transaction.meta?.to_membership">To: {{ transaction.meta.to_membership }}</div>
                <div v-if="transaction.meta?.from_name">From: {{ transaction.meta.from_name }}</div>
                <div v-if="!transaction.meta?.from_name && transaction.meta?.from_membership">From: {{ transaction.meta.from_membership }}</div>
              </div>
            </div>
          </div>

          <!-- Allocation Details (wallet_allocation) -->
          <div v-if="isWalletAllocation && allocationItems.length" class="bg-white rounded-[2rem] p-4 sm:p-6 space-y-3 border border-slate-100 shadow-sm">
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Allocation Details</div>
            <div class="divide-y divide-slate-100">
              <div v-for="(item, idx) in allocationItems" :key="idx" class="py-2 flex items-center justify-between">
                <div class="text-xs font-bold text-slate-700">
                  {{ allocationLabel(item) }}
                </div>
                <div class="text-xs font-black text-slate-800">₦{{ formatMoney(item.amount || 0) }}</div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="grid grid-cols-2 gap-3">
            <button 
              @click="shareReceipt" 
              :disabled="generatingImage"
              class="flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 py-4 rounded-2xl font-bold transition-all active:scale-95 disabled:opacity-50"
            >
              <template v-if="!generatingImage">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0-12.814a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5zm0 10.628a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5z" />
                </svg>
                Share Image
              </template>
              <div v-else class="flex items-center gap-2">
                <div class="w-4 h-4 border-2 border-slate-700 border-t-transparent rounded-full animate-spin"></div>
                Preparing...
              </div>
            </button>
            
            <button 
              @click="shareAsPdf"
              class="flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 py-4 rounded-2xl font-bold transition-all active:scale-95"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 text-rose-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
              </svg>
              Share PDF
            </button>
          </div>

          <div class="grid grid-cols-2 gap-3 mt-1">
            <button @click="downloadTransaction" class="flex items-center justify-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white py-4 rounded-2xl font-bold transition-all shadow-lg shadow-emerald-100 active:scale-95">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12l4.5 4.5m0 0l4.5-4.5M12 3v13.5" />
              </svg>
              Receipt
            </button>
            <button @click="close" class="bg-slate-50 text-slate-400 text-[10px] font-black uppercase tracking-[0.2em] rounded-2xl hover:text-slate-600 transition-colors">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import axios from '../../http'
import { toPng } from 'html-to-image'

const props = defineProps({
  isOpen: Boolean,
  transaction: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['close', 'download'])

const close = () => emit('close')

const txTitle = computed(() => {
  if (props.transaction.title) return props.transaction.title
  if (props.transaction.type === 'credit') return 'Credit Transaction'
  if (props.transaction.type === 'debit') return 'Debit Transaction'
  return 'Transaction Detail'
})

const statusIcon = computed(() => {
  const status = String(props.transaction.status || '').toLowerCase()
  if (status.includes('fail') || status.includes('decline')) return 'i-mdi-close-circle'
  if (status.includes('pend')) return 'i-mdi-clock-outline'
  if (props.transaction.type === 'credit') return 'i-mdi-check-circle'
  if (props.transaction.type === 'debit') return 'i-mdi-cash-minus'
  return 'i-mdi-text-box-search'
})

const statusBgClass = computed(() => {
  const status = String(props.transaction.status || '').toLowerCase()
  if (status.includes('fail') || status.includes('decline')) return 'bg-rose-50 text-rose-600'
  if (status.includes('pend')) return 'bg-amber-50 text-amber-600'
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
  const status = String(props.transaction.status || 'success').toLowerCase()
  if (status.includes('success') || status.includes('paid')) return 'bg-emerald-50 text-emerald-600'
  if (status.includes('fail') || status.includes('decline')) return 'bg-rose-50 text-rose-600'
  if (status.includes('pend')) return 'bg-amber-50 text-amber-600'
  return 'bg-blue-50 text-blue-600'
})

const copy = (text) => {
  navigator.clipboard.writeText(text)
  emit('copy', text)
}

// Helpers for PDF-parity details
const meta = computed(() => props.transaction?.meta || {})

const sourceLabel = computed(() => {
  const src = String(props.transaction?.source || '')
  if (src === 'paystack_dva') return 'Bank Transfer (DVA)'
  if (src === 'paystack_charge') return 'Card Payment'
  if (src === 'paystack_autosave') return 'Smart Savings (Autosave)'
  if (src === 'wallet_allocation') return 'Allocation to Schemes'
  if (src === 'p2p_transfer') return 'Member P2P Transfer'
  if (src.startsWith('vtu_')) return 'Value-added Services (VTU)'
  return src || '—'
})

const hasMaintenanceCharge = computed(() => {
  const m = meta.value || {}
  return m && (m.maintenance_charge !== undefined && m.maintenance_charge !== null)
})

const maintenanceCharge = computed(() => {
  const m = meta.value || {}
  return Number(m.maintenance_charge || 0)
})

const grossAmount = computed(() => {
  const m = meta.value || {}
  // fallback: if gross_amount missing, infer from tx.amount + maintenance_charge
  const gross = Number(m.gross_amount || 0)
  if (gross) return gross
  return Number(props.transaction?.amount || 0) + Number(m.maintenance_charge || 0)
})

const hasNotes = computed(() => {
  const m = meta.value || {}
  return Boolean(props.transaction?.description || m.note || m.to_name || m.to_membership || m.from_name || m.from_membership)
})

const isWalletAllocation = computed(() => String(props.transaction?.source || '') === 'wallet_allocation')

const allocationItems = computed(() => {
  const m = meta.value || {}
  const dist = Array.isArray(m.distribution) ? m.distribution : []
  // Filter out inactive/null-ish items gracefully
  return dist.filter(i => i && (i.amount || i.category || i.scheme_name || i.scheme_id))
})

// Lightweight cache of schemes so we can resolve scheme_id -> scheme name in preview
const schemeMap = ref({})
const schemesLoaded = ref(false)
const loadingSchemes = ref(false)

const buildSchemeMap = (list) => {
  const map = {}
  ;(list || []).forEach(s => {
    if (s && (s.id !== undefined && s.id !== null)) {
      map[String(s.id)] = s.name || s.title || s.code || `Scheme #${s.id}`
    }
  })
  schemeMap.value = map
}

const ensureSchemes = async () => {
  if (schemesLoaded.value || loadingSchemes.value) return
  try {
    loadingSchemes.value = true
    const { data } = await axios.get('/api/schemes')
    // Accept either {data: [...]} or plain array
    const list = Array.isArray(data) ? data : (Array.isArray(data?.data) ? data.data : [])
    buildSchemeMap(list)
    schemesLoaded.value = true
  } catch (e) {
    // Fail silently; we will keep showing (#id) fallback
  } finally {
    loadingSchemes.value = false
  }
}

// When modal opens for a wallet_allocation and some items lack scheme_name, load schemes once
const needsSchemeNames = computed(() => {
  if (!allocationItems.value?.length) return false
  return allocationItems.value.some(i => i && !i.scheme_name && i.scheme_id)
})

watch(
  () => [props.isOpen, isWalletAllocation.value, needsSchemeNames.value],
  async ([open, isAlloc, needs]) => {
    if (open && isAlloc && needs) {
      await ensureSchemes()
    }
  },
  { immediate: false }
)

onMounted(async () => {
  if (props.isOpen && isWalletAllocation.value && needsSchemeNames.value) {
    await ensureSchemes()
  }
})

const allocationLabel = (item) => {
  const cat = item?.category || 'deposit'
  let label = ''
  if (cat === 'deposit') label = 'Contribution'
  else if (cat === 'loan_repayment') label = 'Loan Repayment'
  else if (cat === 'fine') label = 'Fine Payment'
  else if (cat === 'withdrawal') label = 'Withdrawal'
  else label = String(cat).replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase())

  // Prefer pre-resolved scheme_name from API if available, else show (#id)
  if (item?.scheme_name) return `${label} (${item.scheme_name})`
  if (item?.scheme_id) {
    const resolved = schemeMap.value[String(item.scheme_id)]
    if (resolved) return `${label} (${resolved})`
    return `${label} (#${item.scheme_id})`
  }
  return label
}

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

const receiptRef = ref(null)
const generatingImage = ref(false)

const getReceiptDownloadUrl = () => {
  if (props.transaction.isUtility) return null
  const id = props.transaction.id
  const token = localStorage.getItem('token') || localStorage.getItem('admin_token')
  const baseUrl = axios.defaults.baseURL || ''
  return `${baseUrl}/api/wallet/transactions/${id}/receipt?token=${encodeURIComponent(token)}`
}

const generateReceiptImage = async () => {
  if (!receiptRef.value) return null
  try {
    generatingImage.value = true
    // Wait for icons and styles to settle
    await new Promise(resolve => setTimeout(resolve, 200))
    const dataUrl = await toPng(receiptRef.value, {
      backgroundColor: '#ffffff',
      pixelRatio: 2,
      cacheBust: true,
    })
    return dataUrl
  } catch (err) {
    console.error('Failed to generate receipt image', err)
    return null
  } finally {
    generatingImage.value = false
  }
}

const shareReceipt = async () => {
  const dataUrl = await generateReceiptImage()
  
  // Prepare text version as fallback or caption
  const title = txTitle.value
  const amount = formatMoney(props.transaction.amount)
  const ref = props.transaction.reference || props.transaction.id
  const dateStr = props.transaction.created_at 
    ? new Date(props.transaction.created_at).toLocaleDateString('en-NG', { dateStyle: 'medium' })
    : 'N/A'

  let text = `${title}\nAmount: ₦${amount}\nRef: ${ref}\nStatus: ${props.transaction.status || 'Successful'}\nDate: ${dateStr}`
  
  if (props.transaction.meta?.to_name || props.transaction.meta?.to_membership) {
    text += `\nRecipient: ${props.transaction.meta.to_name || props.transaction.meta.to_membership}`
  }
  if (isWalletAllocation.value && allocationItems.value.length) {
    text += `\n\nAllocation Details:`
    allocationItems.value.forEach(item => {
      text += `\n- ${allocationLabel(item)}: ₦${formatMoney(item.amount || 0)}`
    })
  }
  text += `\n\nhttps://attaqwacooposg.com/`

  if (!dataUrl) {
    // If image generation failed, fallback to text-only share
    if (navigator.share) {
      await navigator.share({ title: 'Transaction Receipt', text }).catch(() => copy(text))
    } else {
      copy(text)
    }
    return
  }
  
  try {
    const blob = await (await fetch(dataUrl)).blob()
    const filename = `Receipt_${ref}.png`
    const file = new File([blob], filename, { type: 'image/png' })
    
    if (navigator.share) {
      const shareData = {
        title: 'Transaction Receipt',
        text: text,
      }
      
      // Try to include file if browser supports it
      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        shareData.files = [file]
      }
      
      await navigator.share(shareData)
    } else {
      // Fallback to direct download
      const link = document.createElement('a')
      link.href = dataUrl
      link.download = filename
      link.click()
    }
  } catch (err) {
    console.error('Share failed', err)
    // Final fallback to text-only if something went wrong with file share
    try {
      if (navigator.share) await navigator.share({ title: 'Transaction Receipt', text })
    } catch (_) {}
  }
}

const shareAsPdf = async () => {
  const url = getReceiptDownloadUrl()
  if (!url) return
  
  try {
    const response = await fetch(url)
    const blob = await response.blob()
    const filename = `Receipt_${props.transaction.reference || props.transaction.id}.pdf`
    const file = new File([blob], filename, { type: 'application/pdf' })
    
    if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
      await navigator.share({
        files: [file],
        title: 'Transaction Receipt',
      })
    } else {
      window.open(url, '_blank')
    }
  } catch (err) {
    window.open(url, '_blank')
  }
}

const downloadTransaction = () => emit('download', props.transaction)
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
