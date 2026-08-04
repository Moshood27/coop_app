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
      <div class="flex items-center gap-3">
        <div class="relative flex-1">
          <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 i-mdi-magnify text-xl"></span>
          <input 
            v-model="searchQuery" 
            @input="handleSearch"
            type="text" 
            placeholder="Search name, membership NO, phone..." 
            class="w-full pl-12 pr-4 py-4 bg-white border border-slate-100 rounded-[2rem] shadow-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none transition-all text-sm font-medium"
          />
        </div>
        <button @click="openCreateModal" class="w-14 h-14 bg-emerald-600 rounded-[1.5rem] flex items-center justify-center text-white hover:bg-emerald-700 transition-colors shadow-lg shadow-emerald-100 flex-shrink-0" title="Add New Member">
          <span class="i-mdi-plus text-2xl"></span>
        </button>
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
          <div class="flex items-center gap-4 flex-1" @click="$router.push(`/admin/members/${member.id}`)">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center font-black text-lg overflow-hidden">
              <img v-if="member.passport_url" :src="getImageUrl(member.passport_url)" class="w-full h-full object-cover" />
              <span v-else>{{ member.name.charAt(0) }}</span>
            </div>
            <div>
              <p class="text-sm font-black text-slate-800">{{ member.surname }} {{ member.name }}</p>
              <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Membership NO: {{ member.membership_number }}</p>
              <p class="text-[10px] font-bold text-emerald-600">{{ member.branch?.name }}</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button @click.stop="confirmDeleteMember(member)" class="w-8 h-8 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center hover:bg-rose-100 transition-colors">
              <span class="i-mdi-trash-can text-lg"></span>
            </button>
            <span class="i-mdi-chevron-right text-slate-300 text-xl"></span>
          </div>
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

    <!-- Create Member Modal -->
    <transition name="fade">
      <div v-if="showCreateModal" class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-4 sm:p-6 pb-[calc(2rem+env(safe-area-inset-bottom))]">
        <div class="absolute inset-0 bg-black/40" @click="showCreateModal = false"></div>
        <div class="relative w-full sm:max-w-md bg-white rounded-[2.5rem] shadow-2xl border border-slate-100 overflow-hidden">
          <div class="p-6 border-b border-slate-50 flex items-center justify-between">
            <h3 class="text-lg font-black text-slate-800 tracking-tight">Add New Member</h3>
            <button @click="showCreateModal = false" class="w-8 h-8 bg-slate-50 rounded-xl flex items-center justify-center text-slate-400">✕</button>
          </div>
          <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">Surname</label>
                <input v-model="newMember.surname" type="text" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500" placeholder="e.g. Doe" />
              </div>
              <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">First Name</label>
                <input v-model="newMember.name" type="text" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500" placeholder="e.g. John" />
              </div>
            </div>
            <div class="space-y-1">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">Other Names</label>
              <input v-model="newMember.other_names" type="text" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500" placeholder="e.g. Quincy" />
            </div>
            <div class="space-y-1">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">Email Address</label>
              <input v-model="newMember.email" type="email" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500" placeholder="john@example.com" />
            </div>
            <div class="space-y-1">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">Phone Number</label>
              <input v-model="newMember.phone" type="tel" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500" placeholder="08012345678" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">Gender</label>
                <select v-model="newMember.gender" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500 appearance-none">
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </div>
              <div class="space-y-1">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">Branch</label>
                <select v-model="newMember.branch_id" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500 appearance-none">
                  <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                </select>
              </div>
            </div>
            <div class="space-y-1">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">Initial Password</label>
              <input v-model="newMember.password" type="password" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500" placeholder="Min 8 characters" />
            </div>
            <div class="space-y-1">
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4">Residential Address</label>
              <textarea v-model="newMember.address" class="w-full px-5 py-3 bg-slate-50 rounded-2xl text-sm font-bold outline-none focus:ring-2 focus:ring-emerald-500 min-h-[80px]" placeholder="Enter full address"></textarea>
            </div>
          </div>
          <div class="p-6 border-t border-slate-50">
            <button 
              @click="handleCreateMember" 
              :disabled="creating"
              class="w-full bg-emerald-600 py-4 rounded-2xl text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-emerald-100 active:scale-[0.98] transition-all disabled:opacity-50"
            >
              {{ creating ? 'Creating Member...' : 'Create Member Profile' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from '../../http'
import { debounce } from 'lodash'
import getImageUrl from '../../utils/image'
import { useModal } from '../../composables/useModal'

const { alert, confirm } = useModal()

const members = ref([])
const loading = ref(false)
const searchQuery = ref('')
const currentPage = ref(1)
const lastPage = ref(1)

const showCreateModal = ref(false)
const creating = ref(false)
const branches = ref([])
const newMember = ref({
  name: '',
  surname: '',
  other_names: '',
  email: '',
  phone: '',
  gender: 'male',
  branch_id: '',
  password: '',
  address: ''
})

const fetchBranches = async () => {
  try {
    const { data } = await axios.get('/api/branches')
    branches.value = data
    if (data.length > 0) newMember.value.branch_id = data[0].id
  } catch (e) {
    console.error('Failed to fetch branches', e)
  }
}

const openCreateModal = () => {
  newMember.value = {
    name: '',
    surname: '',
    other_names: '',
    email: '',
    phone: '',
    gender: 'male',
    branch_id: branches.value.length > 0 ? branches.value[0].id : '',
    password: '',
    address: ''
  }
  showCreateModal.value = true
}

const handleCreateMember = async () => {
  if (!newMember.value.name || !newMember.value.surname || !newMember.value.email || !newMember.value.phone || !newMember.value.password || !newMember.value.branch_id) {
    alert('Please fill in all required fields.')
    return
  }

  creating.value = true
  try {
    await axios.post('/api/admin/members', newMember.value)
    alert('Member created successfully.', 'Success')
    showCreateModal.value = false
    fetchMembers(1)
  } catch (e) {
    const msg = e.response?.data?.message || 'Failed to create member.'
    alert(msg, 'Error')
  } finally {
    creating.value = false
  }
}

const confirmDeleteMember = async (member) => {
  const ok = await confirm(`Are you sure you want to delete ${member.surname} ${member.name}? This action cannot be undone.`, {
    title: 'Delete Member',
    confirmText: 'Delete',
    cancelText: 'Cancel'
  })

  if (ok) {
    try {
      await axios.delete(`/api/admin/members/${member.id}`)
      alert('Member deleted successfully.', 'Success')
      fetchMembers(currentPage.value)
    } catch (e) {
      const msg = e.response?.data?.message || 'Failed to delete member.'
      alert(msg, 'Error')
    }
  }
}

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
  fetchBranches()
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
