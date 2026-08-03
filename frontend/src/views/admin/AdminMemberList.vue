<template>
  <div class="min-h-screen bg-slate-50 pb-32">
    <header class="p-6 bg-white border-b sticky top-0 z-20 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-black text-slate-800 tracking-tight">Member Management</h1>
        <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-[0.2em]">Select a member</p>
      </div>
      <button @click="$router.push('/admin/portal')" class="w-10 h-10 bg-slate-100 rounded-2xl flex items-center justify-center text-slate-500 hover:bg-slate-200 transition-colors">
        <span class="i-mdi-close text-xl"></span>
      </button>
    </header>

    <div class="p-6 space-y-6 max-w-lg mx-auto">
      <div class="relative">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 i-mdi-magnify text-xl"></span>
        <input 
          v-model="searchQuery" 
          @input="handleSearch"
          type="text" 
          placeholder="Search name, member #, phone..." 
          class="w-full pl-12 pr-4 py-4 bg-white border border-slate-100 rounded-[2rem] shadow-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none transition-all text-sm font-medium"
        />
      </div>

      <div v-if="loading" class="flex flex-col items-center py-12 space-y-4">
        <div class="w-12 h-12 border-4 border-emerald-100 border-t-emerald-600 rounded-full animate-spin"></div>
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Searching Members...</p>
      </div>

      <div v-else-if="members.length > 0" class="space-y-3">
        <div 
          v-for="member in members" 
          :key="member.id"
          @click="$router.push(`/admin/members/${member.id}`)"
          class="bg-white p-4 rounded-[2rem] border border-slate-100 shadow-sm flex items-center justify-between active:scale-[0.98] active:bg-slate-50 transition-all cursor-pointer"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center font-black text-lg">
              {{ member.name.charAt(0) }}
            </div>
            <div>
              <p class="text-sm font-black text-slate-800">{{ member.surname }} {{ member.name }}</p>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ member.membership_number }}</p>
              <p class="text-[10px] font-bold text-emerald-600">{{ member.branch?.name }}</p>
            </div>
          </div>
          <span class="i-mdi-chevron-right text-slate-300 text-xl"></span>
        </div>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="flex items-center justify-center gap-4 mt-8">
          <button 
            :disabled="currentPage === 1"
            @click="fetchMembers(currentPage - 1)"
            class="p-2 rounded-xl bg-white border border-slate-100 text-slate-600 disabled:opacity-50"
          >
            <span class="i-mdi-chevron-left text-xl"></span>
          </button>
          <span class="text-xs font-black text-slate-400 uppercase tracking-widest">Page {{ currentPage }} of {{ lastPage }}</span>
          <button 
            :disabled="currentPage === lastPage"
            @click="fetchMembers(currentPage + 1)"
            class="p-2 rounded-xl bg-white border border-slate-100 text-slate-600 disabled:opacity-50"
          >
            <span class="i-mdi-chevron-right text-xl"></span>
          </button>
        </div>
      </div>

      <div v-else class="text-center py-12 bg-white rounded-[2.5rem] border border-slate-100 border-dashed">
        <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-3xl flex items-center justify-center text-3xl mx-auto mb-4">
          <span class="i-mdi-account-search"></span>
        </div>
        <p class="text-sm font-bold text-slate-500">No members found</p>
        <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest mt-1">Try a different search term</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '../../http'
import { debounce } from 'lodash'

const members = ref([])
const loading = ref(false)
const searchQuery = ref('')
const currentPage = ref(1)
const lastPage = ref(1)

const fetchMembers = async (page = 1) => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/members', {
      params: {
        page,
        search: searchQuery.value
      }
    })
    members.value = data.data
    currentPage.value = data.current_page
    lastPage.value = data.last_page
  } catch (e) {
    console.error('Failed to fetch members', e)
  } finally {
    loading.value = false
  }
}

const handleSearch = debounce(() => {
  fetchMembers(1)
}, 500)

onMounted(() => {
  fetchMembers()
})
</script>
