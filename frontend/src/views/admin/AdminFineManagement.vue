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
          <h1 class="text-base font-black text-slate-800 tracking-tight leading-none">Fine Management</h1>
          <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-[0.2em] mt-1">{{ user?.full_name }}</p>
        </div>
      </div>
    </header>

    <div v-if="loading" class="flex flex-col items-center py-20 space-y-4">
      <div class="w-12 h-12 border-4 border-emerald-100 border-t-emerald-600 rounded-full animate-spin"></div>
      <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Loading Fines...</p>
    </div>

    <div v-else class="p-6 space-y-6 max-w-lg mx-auto">
      <!-- Summary Card -->
      <div class="bg-rose-600 p-8 rounded-[2.5rem] text-white shadow-xl shadow-rose-200 text-center relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
        <p class="text-[10px] font-black uppercase tracking-[0.3em] opacity-60 mb-1">Total Outstanding Fines</p>
        <p class="text-3xl font-black">₦{{ formatMoney(user?.outstanding_fines || 0) }}</p>
        
        <button 
          v-if="fines.length > 0"
          @click="waiveAllFines" 
          :disabled="processing"
          class="mt-6 px-6 py-3 bg-white/20 hover:bg-white/30 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all disabled:opacity-50"
        >
          {{ processing ? 'Processing...' : 'Waive All Fines' }}
        </button>
      </div>

      <div v-if="fines.length === 0" class="text-center py-12 bg-white rounded-[2.5rem] border border-slate-100 border-dashed">
        <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-3xl flex items-center justify-center text-3xl mx-auto mb-4">
          <span class="i-mdi-check-circle-outline"></span>
        </div>
        <p class="text-sm font-bold text-slate-500">No outstanding fines found</p>
      </div>

      <div v-for="fine in fines" :key="fine.id" class="bg-white p-6 rounded-[2.5rem] border border-slate-100 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
              {{ fine.status === 'fine_pending' ? 'Absence Fine' : 'Lateness Fine' }}
            </p>
            <h3 class="text-sm font-black text-slate-800">{{ fine.meeting?.title || 'General Meeting' }}</h3>
            <p class="text-[9px] font-bold text-slate-400 uppercase">{{ formatDate(fine.created_at) }}</p>
          </div>
          <div class="text-right">
            <p class="text-lg font-black text-rose-600">₦{{ formatMoney(fine.status === 'fine_pending' ? (fine.meeting?.fine_amount || 500) : fine.lateness_fine_amount) }}</p>
          </div>
        </div>

        <div class="pt-4 border-t border-slate-50">
          <button 
            @click="waiveFine(fine)" 
            :disabled="processing"
            class="w-full py-4 bg-slate-50 hover:bg-rose-50 hover:text-rose-600 rounded-2xl text-[10px] font-black text-slate-400 uppercase tracking-widest transition-all disabled:opacity-50"
          >
            {{ processing ? 'Processing...' : 'Waive This Fine' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '../../http'
import { useModal } from '../../composables/useModal'
import getImageUrl from '../../utils/image'

const route = useRoute()
const router = useRouter()
const { confirm, alert } = useModal()

const user = ref(null)
const fines = ref([])
const loading = ref(true)
const processing = ref(false)

const formatMoney = (val) => new Intl.NumberFormat().format(val || 0)
const formatDate = (date) => new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })

const fetchData = async () => {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/admin/members/${route.params.id}/fines`)
    user.value = data.user
    fines.value = data.fines
  } catch (e) {
    console.error('Failed to fetch fines', e)
  } finally {
    loading.value = false
  }
}

const waiveFine = async (fine) => {
  const isConfirmed = await confirm(
    'Are you sure you want to waive this fine? This should only be done if the member has paid manually or for special exemptions.',
    {
      title: 'Waive Fine',
      confirmText: 'Yes, Waive'
    }
  )

  if (!isConfirmed) return

  processing.value = true
  try {
    await axios.post(`/api/admin/members/${route.params.id}/waive-fine/${fine.id}`)
    await alert('Fine waived successfully.', 'Success')
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to waive fine.', 'Error')
  } finally {
    processing.value = false
  }
}

const waiveAllFines = async () => {
  const isConfirmed = await confirm(
    `Are you sure you want to waive ALL outstanding fines (₦${formatMoney(user.value.outstanding_fines)}) for this member?`,
    {
      title: 'Waive All Fines',
      confirmText: 'Yes, Waive All'
    }
  )

  if (!isConfirmed) return

  processing.value = true
  try {
    await axios.post(`/api/admin/members/${route.params.id}/waive-all-fines`)
    await alert('All fines waived successfully.', 'Success')
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to waive all fines.', 'Error')
  } finally {
    processing.value = false
  }
}

onMounted(fetchData)
</script>
