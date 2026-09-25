<template>
  <div class="min-h-screen overflow-x-hidden bg-slate-50">
    <AppHeader :user="dashboardData" :showSettings="true" />

    <div class="max-w-5xl mx-auto px-4 pb-32">
      <!-- Global System Announcement -->
      <div v-if="appStatusStore.systemAnnouncement" 
           class="mt-4 bg-emerald-600 text-white px-4 py-3 rounded-2xl text-center text-xs font-bold flex items-center justify-center gap-3 shadow-md animate-in fade-in slide-in-from-top duration-500 mb-6">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 shrink-0">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.007.51.011.77.011h3.39c.8 0 1.545-.466 1.89-1.159L18.42 12l-1.12-2.25c-.345-.693-1.09-1.159-1.89-1.159h-3.39c-.26 0-.517.004-.77.011m0 9.18c.612.016 1.221.031 1.83.042m-1.83-9.222c.61-.011 1.218-.026 1.83-.042m-1.83 9.222v-9.18m1.83 9.138A17.944 17.944 0 0 1 12 18c-1.353 0-2.65-.148-3.903-.432m10.343-9.43A17.944 17.944 0 0 0 12 6c-1.353 0-2.65.148-3.903.432" />
        </svg>
        <p class="leading-tight">{{ appStatusStore.systemAnnouncement }}</p>
      </div>

      <div class="lg:grid lg:grid-cols-12 lg:gap-8 items-start">
        <!-- Left Column: Primary Info & Warnings -->
        <div class="lg:col-span-7 space-y-4">
          <BalanceCard 
            :user="dashboardData" 
            :netBalance="netBalance" 
            :hideBalances="hideBalances" 
            :refreshing="refreshing" 
            @toggle-visibility="toggleBalances" 
            @refresh="load" 
            @copy="copy" 
          />

      <!-- Dashboard Swiper (First Login) -->
      <div v-if="appStatusStore.onboardingSwiperEnabled && !hasSeenDashboardSwiper && appStatusStore.onboardingSwiperSlides.length > 0"
           class="mt-4 relative group">
        <Swiper
            :modules="[Pagination, Autoplay]"
            :pagination="{ clickable: true }"
            :autoplay="{ delay: 5000, disableOnInteraction: false }"
            class="rounded-[2.5rem] overflow-hidden shadow-sm border border-slate-100 bg-white"
        >
          <SwiperSlide v-for="(s, i) in appStatusStore.onboardingSwiperSlides" :key="i">
            <div class="p-6 flex items-center gap-4">
              <div class="w-14 h-14 flex-shrink-0 flex items-center justify-center bg-emerald-50 rounded-2xl" v-html="s.icon"></div>
              <div class="flex-1 pr-4">
                <h3 class="font-bold text-slate-800 text-sm">{{ s.title }}</h3>
                <p class="text-[10px] text-slate-500 leading-tight mt-0.5">{{ s.description || s.desc }}</p>
              </div>
            </div>
          </SwiperSlide>
        </Swiper>
        <button @click="dismissSwiper" class="absolute top-3 right-3 z-10 p-1 bg-slate-50 hover:bg-slate-100 rounded-full text-slate-400 transition-colors shadow-sm">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

          <StatusBanners 
            :kpis="kpis" 
            :migration="dashboardData.migration" 
            :hideBalances="hideBalances" 
            :currency="currency" 
            :formatMoney="formatMoney" 
            :formatDate="formatDate" 
          />
    </div> <!-- end left col -->

    <!-- Right Column: Status & Performance -->
    <div class="lg:col-span-5 space-y-6 mt-6 lg:mt-0">
      <LoanStatusCard 
        :kpis="kpis" 
        :hideBalances="hideBalances" 
        :currency="currency" 
      />
    </div> <!-- end right col -->
  </div> <!-- end grid -->

      <!-- Trend chart -->
      <FinCard class="mt-6" :padded="true" :elevated="true">
        <template #title>
          Activity Trend
        </template>
        <TrendChart :series="chart.series" :categories="chart.categories" :currency="currency" />
      </FinCard>

      <QuickActions 
        :user="dashboardData" 
        @check-zakat="checkZakat" 
        @pay-zakat-fitr="payZakatFitr" 
      />

    <!-- Quick guide links -->
    <div class="mt-6 text-[12px] text-slate-600">
      <p>
        New here? Learn about
        <button class="text-emerald-700 font-semibold underline" @click="showPassbookInfo">Passbook</button>,
        <template v-if="appStatusStore.features['zakat-enabled']">
          <button class="text-emerald-700 font-semibold underline" @click="showZakatInfo">Zakat</button>,
        </template>
        and
        <button class="text-emerald-700 font-semibold underline" @click="showHajjInfo">Hajj & Umrah</button>.
      </p>
    </div>

    <!-- Dashboard Tabs Content -->
    <DashboardTabs 
      v-model:activeTab="activeTab"
      v-model:searchQuery="searchQuery"
      :liveActions="liveActions"
      :filteredTransactions="filteredTransactions"
      :filteredUtilityTransactions="filteredUtilityTransactions"
      :passbookSummary="passbookSummary"
      :isLoadingPassbook="isLoadingPassbook"
      :hideBalances="hideBalances"
      @switchTab="switchTab"
      @fetchPassbookSummary="fetchPassbookSummary"
      @copy="copy"
    />
  </div> <!-- end max-w container -->

    <!-- Reusable Custom Notice Modal for Zakat/info alerts -->
    <CustomNotice
      v-model="notice.visible"
      :type="notice.type"
      :title="notice.title"
      :message="notice.message"
      @close="closeNotice"
    />

    <!-- Registration Guarantor Request Modal -->
    <div v-if="showRegGuarantorModal && activeRegRequest" class="fixed inset-0 bg-slate-900/80 backdrop-blur-md flex items-center justify-center z-[110] p-4 sm:p-6">
      <div class="bg-white rounded-3xl sm:rounded-[2.5rem] shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto animate-in zoom-in duration-300 border border-slate-100">
        <div class="p-6 sm:p-8">
           <div class="w-16 h-16 sm:w-20 sm:h-20 bg-emerald-50 rounded-2xl sm:rounded-3xl flex items-center justify-center text-3xl sm:text-4xl mx-auto mb-4 sm:mb-6 shadow-sm border border-emerald-100">🤝</div>
           
           <h3 class="text-xl sm:text-2xl font-black text-slate-800 text-center mb-2 uppercase tracking-tight">Guarantor Request</h3>
           <p class="text-slate-500 text-center text-[10px] sm:text-xs mb-6 sm:mb-8 leading-relaxed font-medium">
             <strong>{{ activeRegRequest.member_name }}</strong> has requested you to be their guarantor for Cooperative registration.
           </p>

           <div class="bg-slate-50 p-4 sm:p-6 rounded-2xl border border-slate-100 mb-6 sm:mb-8">
             <h4 class="text-[9px] sm:text-[10px] font-black text-emerald-800 uppercase tracking-widest mb-2 sm:mb-3">Islamic Testimony</h4>
             <p class="text-[11px] sm:text-xs text-slate-600 italic leading-relaxed">
               "{{ activeRegRequest.testimony }}"
             </p>
           </div>

           <div v-if="!processingRegRequest" class="mb-6 sm:mb-8">
             <SignaturePad 
               v-model="guarantorSignature" 
               label="Your Signature" 
               hint="Please sign above to testify"
             />
           </div>
           
           <div class="flex flex-col gap-3">
             <button 
               @click="handleRegGuarantorAction('accept')" 
               :disabled="processingRegRequest || !guarantorSignature"
               class="w-full bg-emerald-600 text-white font-black py-4 sm:py-5 rounded-2xl shadow-xl shadow-emerald-100 flex items-center justify-center gap-3 uppercase tracking-[0.2em] text-[9px] sm:text-[10px] active:scale-95 transition-all disabled:opacity-50"
             >
               <span v-if="processingRegRequest" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
               <span>Accept & Testify</span>
             </button>
             
             <button 
               @click="handleRegGuarantorAction('decline')" 
               :disabled="processingRegRequest"
               class="w-full bg-white text-rose-600 border border-rose-100 font-black py-4 sm:py-5 rounded-2xl flex items-center justify-center gap-3 uppercase tracking-[0.2em] text-[9px] sm:text-[10px] active:scale-95 transition-all disabled:opacity-50"
             >
               <span>Decline Request</span>
             </button>
           </div>
        </div>
        
        <div class="p-4 sm:p-6 bg-slate-50 border-t border-slate-100">
          <p class="text-[8px] sm:text-[9px] text-slate-400 text-center font-bold uppercase tracking-widest opacity-60">
            By accepting, you agree to the Islamic testimony above before Allah (SWT).
          </p>
        </div>
      </div>
    </div>

    <!-- Force Gender Update Modal -->
    <div v-if="showGenderModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-md flex items-center justify-center z-[100] p-4 sm:p-6">
      <div class="bg-white rounded-3xl sm:rounded-[2.5rem] shadow-2xl w-full max-w-sm max-h-[90vh] overflow-y-auto animate-in zoom-in duration-300 border border-slate-100">
        <div class="p-6 sm:p-8">
           <div class="w-16 h-16 sm:w-20 sm:h-20 bg-emerald-50 rounded-2xl sm:rounded-3xl flex items-center justify-center text-3xl sm:text-4xl mx-auto mb-4 sm:mb-6 shadow-sm border border-emerald-100">👤</div>
           
           <h3 class="text-xl sm:text-2xl font-black text-slate-800 text-center mb-2 uppercase tracking-tight">Update Gender</h3>
           <p class="text-slate-500 text-center text-[10px] sm:text-xs mb-6 sm:mb-8 leading-relaxed font-medium">To provide you with tailored services and accurate records, please select your gender.</p>
           
           <div class="space-y-3">
             <button 
               @click="selectedGender = 'male'"
               :class="selectedGender === 'male' ? 'bg-emerald-600 text-white border-emerald-600 scale-[1.02] shadow-lg shadow-emerald-100' : 'bg-slate-50 text-slate-600 border-slate-100 hover:bg-slate-100'"
               class="w-full p-4 sm:p-5 rounded-2xl border-2 font-black uppercase tracking-widest text-[10px] sm:text-xs transition-all flex items-center justify-between"
             >
               <span>Male</span>
               <span v-if="selectedGender === 'male'" class="text-lg">✓</span>
             </button>
             
             <button 
               @click="selectedGender = 'female'"
               :class="selectedGender === 'female' ? 'bg-emerald-600 text-white border-emerald-600 scale-[1.02] shadow-lg shadow-emerald-100' : 'bg-slate-50 text-slate-600 border-slate-100 hover:bg-slate-100'"
               class="w-full p-4 sm:p-5 rounded-2xl border-2 font-black uppercase tracking-widest text-[10px] sm:text-xs transition-all flex items-center justify-between"
             >
               <span>Female</span>
               <span v-if="selectedGender === 'female'" class="text-lg">✓</span>
             </button>
           </div>
        </div>
        
        <div class="p-4 sm:p-6 bg-slate-50 border-t border-slate-100">
          <button 
            @click="updateGender" 
            :disabled="!selectedGender || updatingGender"
            class="w-full bg-slate-800 text-white font-black py-4 sm:py-5 rounded-2xl shadow-xl shadow-slate-200 flex items-center justify-center gap-3 uppercase tracking-[0.2em] text-[9px] sm:text-[10px] disabled:opacity-50 active:scale-95 transition-all"
          >
            <span v-if="updatingGender" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
            <span v-else>Confirm Profile Update</span>
          </button>
          
          <p class="text-[8px] sm:text-[9px] text-slate-400 text-center mt-3 sm:mt-4 font-bold uppercase tracking-widest opacity-60">This is required to proceed to your dashboard</p>
        </div>
      </div>
    </div>

    <!-- Force Email Update Modal -->
    <div v-if="showEmailModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-md flex items-center justify-center z-[101] p-4 sm:p-6">
      <div class="bg-white rounded-3xl sm:rounded-[2.5rem] shadow-2xl w-full max-w-sm max-h-[90vh] overflow-y-auto animate-in zoom-in duration-300 border border-slate-100">
        <div class="p-6 sm:p-8">
           <div class="w-16 h-16 sm:w-20 sm:h-20 bg-emerald-50 rounded-2xl sm:rounded-3xl flex items-center justify-center text-3xl sm:text-4xl mx-auto mb-4 sm:mb-6 shadow-sm border border-emerald-100">📧</div>
           
           <h3 class="text-xl sm:text-2xl font-black text-slate-800 text-center mb-2 uppercase tracking-tight">Update Email</h3>
           <p class="text-slate-500 text-center text-[10px] sm:text-xs mb-6 sm:mb-8 leading-relaxed font-medium">Your current email address is invalid. Please provide a valid email to receive notifications and secure your account.</p>
           
           <div class="space-y-4">
             <div>
               <label class="block text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 sm:mb-2 ml-1">New Email Address</label>
               <input 
                 v-model="emailForm.email" 
                 type="email" 
                 placeholder="yourname@example.com"
                 class="w-full p-3.5 sm:p-4 rounded-2xl bg-slate-50 border-2 border-slate-100 focus:border-emerald-500 focus:bg-white outline-none transition-all font-bold text-slate-700 text-sm sm:text-base"
               />
               <p v-if="emailErrors.email" class="text-[9px] sm:text-[10px] text-rose-500 mt-1 ml-1 font-bold">{{ emailErrors.email[0] }}</p>
             </div>

             <div>
               <label class="block text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 sm:mb-2 ml-1">Confirm Password</label>
               <input 
                 v-model="emailForm.password" 
                 type="password" 
                 placeholder="••••••••"
                 class="w-full p-3.5 sm:p-4 rounded-2xl bg-slate-50 border-2 border-slate-100 focus:border-emerald-500 focus:bg-white outline-none transition-all font-bold text-slate-700 text-sm sm:text-base"
               />
               <p v-if="emailErrors.password" class="text-[9px] sm:text-[10px] text-rose-500 mt-1 ml-1 font-bold">{{ emailErrors.password[0] }}</p>
             </div>
           </div>
        </div>
        
        <div class="p-4 sm:p-6 bg-slate-50 border-t border-slate-100">
          <button 
            @click="updateEmail" 
            :disabled="emailSaving"
            class="w-full bg-slate-800 text-white font-black py-4 sm:py-5 rounded-2xl shadow-xl shadow-slate-200 flex items-center justify-center gap-3 uppercase tracking-[0.2em] text-[9px] sm:text-[10px] disabled:opacity-50 active:scale-95 transition-all"
          >
            <span v-if="emailSaving" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
            <span v-else>Update Email Address</span>
          </button>
          
          <p class="text-[8px] sm:text-[9px] text-slate-400 text-center mt-3 sm:mt-4 font-bold uppercase tracking-widest opacity-60">This is required to proceed to your dashboard</p>
        </div>
      </div>
    </div>

    <!-- Force PIN Setup Modal -->
    <div v-if="showPinModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-md flex items-center justify-center z-[101] p-4 sm:p-6">
      <div class="bg-white rounded-3xl sm:rounded-[2.5rem] shadow-2xl w-full max-w-sm max-h-[90vh] overflow-y-auto animate-in zoom-in duration-300 border border-slate-100">
        <div class="p-6 sm:p-8">
           <div class="w-16 h-16 sm:w-20 sm:h-20 bg-amber-50 rounded-2xl sm:rounded-3xl flex items-center justify-center text-3xl sm:text-4xl mx-auto mb-4 sm:mb-6 shadow-sm border border-amber-100">🔐</div>
           
           <h3 class="text-xl sm:text-2xl font-black text-slate-800 text-center mb-2 uppercase tracking-tight">Set Security PIN</h3>
           <p class="text-slate-500 text-center text-[10px] sm:text-xs mb-6 sm:mb-8 leading-relaxed font-medium">Please set a 4-digit transaction PIN to secure your withdrawals and transfers.</p>
           
           <div class="space-y-4">
             <div>
               <label class="block text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 sm:mb-2 ml-1">New 4-Digit PIN</label>
               <input 
                 v-model="pinForm.new_pin" 
                 type="password" 
                 inputmode="numeric"
                 maxlength="4"
                 placeholder="••••"
                 class="w-full p-3.5 sm:p-4 rounded-2xl bg-slate-50 border-2 border-slate-100 focus:border-amber-500 focus:bg-white outline-none transition-all font-bold text-slate-700 text-center text-xl sm:text-2xl tracking-[0.5em]"
               />
               <p v-if="pinErrors.new_pin" class="text-[9px] sm:text-[10px] text-rose-500 mt-1 ml-1 font-bold">{{ Array.isArray(pinErrors.new_pin) ? pinErrors.new_pin[0] : pinErrors.new_pin }}</p>
             </div>

             <div>
               <label class="block text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 sm:mb-2 ml-1">Confirm PIN</label>
               <input 
                 v-model="pinForm.confirm_pin" 
                 type="password" 
                 inputmode="numeric"
                 maxlength="4"
                 placeholder="••••"
                 class="w-full p-3.5 sm:p-4 rounded-2xl bg-slate-50 border-2 border-slate-100 focus:border-amber-500 focus:bg-white outline-none transition-all font-bold text-slate-700 text-center text-xl sm:text-2xl tracking-[0.5em]"
               />
               <p v-if="pinErrors.confirm_pin" class="text-[9px] sm:text-[10px] text-rose-500 mt-1 ml-1 font-bold">{{ Array.isArray(pinErrors.confirm_pin) ? pinErrors.confirm_pin[0] : pinErrors.confirm_pin }}</p>
             </div>

             <div>
               <label class="block text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 sm:mb-2 ml-1">Account Password</label>
               <input 
                 v-model="pinForm.current_password" 
                 type="password" 
                 placeholder="••••••••"
                 class="w-full p-3.5 sm:p-4 rounded-2xl bg-slate-50 border-2 border-slate-100 focus:border-amber-500 focus:bg-white outline-none transition-all font-bold text-slate-700 text-sm sm:text-base"
               />
               <p v-if="pinErrors.current_password" class="text-[9px] sm:text-[10px] text-rose-500 mt-1 ml-1 font-bold">{{ Array.isArray(pinErrors.current_password) ? pinErrors.current_password[0] : pinErrors.current_password }}</p>
             </div>
           </div>
        </div>
        
        <div class="p-4 sm:p-6 bg-slate-50 border-t border-slate-100">
          <button 
            @click="updatePin" 
            :disabled="pinSaving"
            class="w-full bg-slate-800 text-white font-black py-4 sm:py-5 rounded-2xl shadow-xl shadow-slate-200 flex items-center justify-center gap-3 uppercase tracking-[0.2em] text-[9px] sm:text-[10px] disabled:opacity-50 active:scale-95 transition-all"
          >
            <span v-if="pinSaving" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
            <span v-else>Set Transaction PIN</span>
          </button>
          
          <p class="text-[8px] sm:text-[9px] text-slate-400 text-center mt-3 sm:mt-4 font-bold uppercase tracking-widest opacity-60">This is required for account security</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import AppHeader from '../components/AppHeader.vue'
import BalanceCard from '../components/dashboard/BalanceCard.vue'
import QuickActions from '../components/dashboard/QuickActions.vue'
import DashboardTabs from '../components/dashboard/DashboardTabs.vue'
import LoanStatusCard from '../components/dashboard/LoanStatusCard.vue'
import { ref, onMounted, computed, onUnmounted } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Pagination, Autoplay } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/pagination'
import { isValidEmail } from '../utils/validation'
import { getEcho } from '../realtime/echo'
import { useAppStatusStore } from '../stores/appStatus'
import axios from '../http'
import getImageUrl from '../utils/image'
import { useModal } from '../composables/useModal'
import CustomNotice from '../components/CustomNotice.vue'
import { useNotice } from '../composables/useNotice'
import FinCard from '../components/FinCard.vue'
import StatPill from '../components/StatPill.vue'
import TrendChart from '../components/TrendChart.vue'
import SignaturePad from '../components/SignaturePad.vue'
import StatusBanners from '../components/dashboard/StatusBanners.vue'
import { useMemberStore } from '../stores/member'
import { useWalletStore } from '../stores/wallet'
import { startDashboardTour } from '../utils/tour'
import { useBalanceVisibility } from '../composables/useBalanceVisibility'

import { useDashboardStore } from '../stores/dashboard'
import { useAuthStore } from '../stores/auth'
const modal = useModal()
const { notice, showNotice, closeNotice } = useNotice()
const appStatusStore = useAppStatusStore()
const memberStore = useMemberStore()
const walletStore = useWalletStore()
const dashboardStore = useDashboardStore()
const authStore = useAuthStore()

const currency = '₦'
const dashboardData = computed(() => dashboardStore.data || {})
const refreshing = ref(false)
const liveActions = ref([])
const activeTab = ref('transactions')
const searchQuery = ref('')
const passbookSummary = ref(null)
const isLoadingPassbook = ref(false)
const showGenderModal = ref(false)
const regGuarantorRequests = ref([])
const showRegGuarantorModal = ref(false)
const activeRegRequest = ref(null)
const guarantorSignature = ref('')
const processingRegRequest = ref(false)

const fetchRegGuarantorRequests = async () => {
  try {
    const { data } = await axios.get('/api/guarantor/registration-requests')
    regGuarantorRequests.value = data || []
    if (regGuarantorRequests.value.length > 0) {
      // Find the first pending one to show
      const pending = regGuarantorRequests.value.find(r => r.guarantor_status === 'pending')
      if (pending) {
        activeRegRequest.value = pending
        showRegGuarantorModal.value = true
      }
    }
  } catch (err) {
    console.error('Failed to fetch registration guarantor requests', err)
  }
}

const handleRegGuarantorAction = async (action) => {
  if (!activeRegRequest.value) return
  processingRegRequest.value = true
  try {
    const endpoint = `/api/guarantor/registration-requests/${activeRegRequest.value.id}/${action}`
    const payload = action === 'accept' ? { signature_base64: guarantorSignature.value } : {}
    await axios.post(endpoint, payload)
    showNotice('success', action === 'accept' ? 'Request Accepted' : 'Request Declined', 
      action === 'accept' ? 'You have successfully vouched for the member.' : 'You have declined the request.')
    showRegGuarantorModal.value = false
    activeRegRequest.value = null
    guarantorSignature.value = ''
    fetchRegGuarantorRequests() // Check for next one
  } catch (err) {
    showNotice('error', 'Action Failed', err.response?.data?.message || 'Something went wrong.')
  } finally {
    processingRegRequest.value = false
  }
}
const selectedGender = ref('')
const updatingGender = ref(false)

const showEmailModal = ref(false)
const emailForm = ref({ email: '', password: '' })
const emailSaving = ref(false)
const emailErrors = ref({})

const showPinModal = ref(false)
const pinForm = ref({ current_password: '', new_pin: '', confirm_pin: '' })
const pinSaving = ref(false)
const pinErrors = ref({})

const { hideBalances, toggleBalances } = useBalanceVisibility()
const netBalance = computed(() => {
  const d = dashboardData.value || {}
  if (!appStatusStore.features['display-admin-charge-in-wallet']) return d.balance || 0
  return (d.balance || 0) - (d.admin_charge_balance || 0)
})

const baseRaw = import.meta?.env?.BASE_URL || '/'
const basePath = (baseRaw && baseRaw.endsWith('/')) ? baseRaw : `${baseRaw}/`

const hasSeenDashboardSwiper = ref(localStorage.getItem('has_seen_dashboard_swiper') === 'true')
const dismissSwiper = () => {
  localStorage.setItem('has_seen_dashboard_swiper', 'true')
  hasSeenDashboardSwiper.value = true
}

const filteredTransactions = computed(() => {
  const query = searchQuery.value.toLowerCase().trim()
  const txs = dashboardData.value.transactions || []
  if (!query) return txs
  return txs.filter(tx => 
    txTitle(tx).toLowerCase().includes(query) ||
    txPrefix(tx).toLowerCase().includes(query) ||
    formatMoney(tx.amount).includes(query)
  )
})

const filteredUtilityTransactions = computed(() => {
  const query = searchQuery.value.toLowerCase().trim()
  const utils = dashboardData.value.utility_transactions || []
  if (!query) return utils
  return utils.filter(ux => 
    utilLabel(ux).toLowerCase().includes(query) ||
    (ux.reference || '').toLowerCase().includes(query) ||
    formatMoney(ux.amount).includes(query) ||
    (ux.phone_number || '').includes(query)
  )
})

const fetchPassbookSummary = async () => {
  if (passbookSummary.value) return
  isLoadingPassbook.value = true
  try {
    const year = new Date().getFullYear()
    const { data } = await axios.get(`/api/passbook/${year}`)
    passbookSummary.value = data
  } catch (e) {
    console.error('Failed to fetch passbook summary', e)
  } finally {
    isLoadingPassbook.value = false
  }
}

const switchTab = (tab) => {
  activeTab.value = tab
  searchQuery.value = ''
  if (tab === 'passbook') {
    fetchPassbookSummary()
  }
}

const formatMoney = (val) => Number(val ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
const formatDate = (dateStr) => new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
const copy = async (text) => {
  try {
    await navigator.clipboard.writeText(String(text || ''))
    await modal.alert('Copied to clipboard')
  } catch (_) {}
}

const kpis = computed(() => {
  const d = dashboardData.value || {}
  if (d.kpis) return d.kpis

  const txs = Array.isArray(d.transactions) ? d.transactions : []
  const utils = Array.isArray(d.utility_transactions) ? d.utility_transactions : []
  const totalContrib = txs.reduce((sum, t) => sum + Number(t.amount || 0), 0)
  const outstandingLoans = txs.filter(t => (t.type === 'loan' || String(t.scheme?.name || '').toLowerCase().includes('loan')))
    .reduce((sum, t) => sum + Number(t.balance || 0), 0)
  const utilSpent = utils.reduce((sum, u) => sum + Number(u.amount || 0), 0)
  return { contributions: totalContrib, loans: outstandingLoans, utilities: utilSpent, attaqwa_score: d.attaqwa_score || 0, is_defaulted: false, defaulted_amount: 0, total_due_amount: 0, has_active_loan: false, loan_limit: 0, savings_balance: 0, shares_balance: 0, next_due_date: null, next_due_amount: 0, total_loan_principal: 0, total_loan_paid: 0 }
})

const chart = computed(() => {
  const d = dashboardData.value || {}
  const txs = Array.isArray(d.transactions) ? d.transactions.slice().sort((a,b) => new Date(a.created_at) - new Date(b.created_at)) : []
  // build simple last-10 points
  const points = txs.slice(-10)
  const categories = points.map(p => new Date(p.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }))
  const series = [{ name: 'Balance', data: points.map(p => Number(p.balance_after || p.running_balance || 0)) }]
  return { categories, series }
})

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

const checkMigration = async () => {
  if (!appStatusStore.openingBalanceVerificationEnabled) return
  const m = dashboardData.value.migration
  if (!m || !m.migrated_at) return
  if (m.discrepancy_reported_at || m.verified_at) return

  // Show verification modal
  const total = formatMoney(m.total_balance)
  const breakdownLines = Object.entries(m.breakdown || {})
    .filter(([_, val]) => Number(val) > 0)
    .map(([key, val]) => `• ${key}: ${currency} ${formatMoney(val)}`)
    .join('\n')

  const ok = await modal.prompt(
    'Verify Opening Balance',
    `Assalamu Alaikum! Welcome to Attaqwa Mobile App. Based on our system migration from paper/Excel records, here is your opening balance breakdown:\n\n${breakdownLines}\n\nTotal: ${currency} ${total}\n\nIs this correct?`,
    [
      { label: 'Yes, it is correct', value: 'verify', primary: true },
      { label: 'No, report discrepancy', value: 'report', danger: true },
      { label: 'Ask me later', value: 'cancel' }
    ]
  )

  const token = localStorage.getItem('token')
  if (ok === 'verify') {
    try {
      await axios.post('/api/profile/verify-migration', {}, { headers: { Authorization: `Bearer ${token}` } })
      showNotice('Success', 'Mashallah! Thank you! Your account is now fully verified.', 'success')
      dashboardData.value.migration.verified_at = new Date().toISOString()
    } catch (e) {
      showNotice('Error', 'Failed to verify balance. Please try again.', 'error')
    }
  } else if (ok === 'report') {
    const details = await modal.promptText(
      'Report Discrepancy',
      'Please describe the difference between your records and the amount shown above. Our officers will investigate and update your account.',
      { placeholder: 'e.g. My savings should be N50,000 not N45,000...' }
    )
    if (details) {
      try {
        await axios.post('/api/profile/report-migration-error', { details }, { headers: { Authorization: `Bearer ${token}` } })
        showNotice('Reported', 'Your report has been submitted. We will review it shortly.', 'info')
        dashboardData.value.migration.discrepancy_reported_at = new Date().toISOString()
      } catch (e) {
        showNotice('Error', 'Failed to submit report. Please try again.', 'error')
      }
    }
  }
}

const load = async () => {
  try {
    refreshing.value = true
    const data = await dashboardStore.fetchDashboard(true)
    authStore.isAdmin = data.is_admin ? true : false
    
    if (data.features) {
      appStatusStore.setFeatures(data.features)
    }
    
    // Check Migration status
    checkMigration()
    
    // Check Gender
    if (!data.gender) {
      showGenderModal.value = true
    } else if (!isValidEmail(data.email)) {
      // Check Email
      showEmailModal.value = true
      // If the email is clearly invalid (like a membership number or nonsense), clear it for them to type fresh
      emailForm.value.email = '' 
    } else if (appStatusStore.setTransactionPinEnabled && !data.kpis?.has_pin) {
      // Check PIN
      showPinModal.value = true
    }

    // Show Zakat alert if reached nisab but not yet paid (or simply reached nisab)
    if (appStatusStore.features['zakat-enabled'] && data.zakat_status?.reached_nisab) {
      const due = formatMoney(data.zakat_status.zakat_due)
      const nisab = formatMoney(data.zakat_status.nisab)
      
      if (data.zakat_status.eligible) {
        showNotice('Zakat Alert', `Your savings have reached the Nisab. Your Zakat due is ${currency} ${due}.`, 'info')
      } else {
        showNotice('Zakat Update', `Your savings have reached the Nisab. Keep tracking your savings to know when your Zakat becomes due!`, 'info')
      }
    }
  } catch (e) {
  } finally {
    refreshing.value = false
  }
}

const updateGender = async () => {
  if (!selectedGender.value) return
  updatingGender.value = true
  try {
    const token = localStorage.getItem('token')
    await axios.post('/api/profile/gender', { gender: selectedGender.value }, { headers: { Authorization: `Bearer ${token}` } })
    showGenderModal.value = false
    dashboardData.value.gender = selectedGender.value
    showNotice('Success', 'Mashallah! Profile updated. Jazakallah Khair!', 'success')
  } catch (e) {
    showNotice('Error', 'Failed to update gender. Please try again.', 'error')
  } finally {
    updatingGender.value = false
  }
}

const updateEmail = async () => {
  if (!emailForm.value.email || !emailForm.value.password) {
     emailErrors.value = { 
       email: !emailForm.value.email ? ['Email is required'] : [],
       password: !emailForm.value.password ? ['Password is required to confirm change'] : []
     }
     return
  }
  
  if (!isValidEmail(emailForm.value.email)) {
    emailErrors.value = { email: ['Please provide a valid email address.'] }
    return
  }
  
  emailSaving.value = true
  emailErrors.value = {}
  try {
    const token = localStorage.getItem('token')
    const { data } = await axios.post('/api/profile/email', emailForm.value, { headers: { Authorization: `Bearer ${token}` } })
    showEmailModal.value = false
    dashboardData.value.email = data.email
    showNotice('Success', 'Mashallah! Email updated successfully!', 'success')
  } catch (e) {
    if (e.response?.data?.errors) {
      emailErrors.value = e.response.data.errors
    } else {
      showNotice('Error', e.response?.data?.message || 'Failed to update email. Please try again.', 'error')
    }
  } finally {
    emailSaving.value = false
  }
}

const updatePin = async () => {
  pinErrors.value = {}
  if (!pinForm.value.current_password) {
    pinErrors.value.current_password = ['Current password is required.']
  }
  if (!pinForm.value.new_pin) {
    pinErrors.value.new_pin = ['PIN is required.']
  } else if (!/^\d{4}$/.test(String(pinForm.value.new_pin))) {
    pinErrors.value.new_pin = ['PIN must be exactly 4 digits.']
  }
  if (String(pinForm.value.confirm_pin) !== String(pinForm.value.new_pin)) {
    pinErrors.value.confirm_pin = ['PIN confirmation does not match.']
  }

  if (Object.keys(pinErrors.value).length > 0) return

  pinSaving.value = true
  try {
    await axios.post('/api/security/pin/set', {
      current_password: pinForm.value.current_password,
      new_pin: String(pinForm.value.new_pin),
      confirm_pin: String(pinForm.value.confirm_pin),
    })
    showPinModal.value = false
    dashboardData.value.kpis.has_pin = true
    showNotice('Success', 'Mashallah! Transaction PIN set successfully!', 'success')
  } catch (err) {
    const e = err?.response?.data
    if (e?.errors) {
      pinErrors.value = e.errors
    } else {
      showNotice('Error', e?.message || 'Failed to save PIN. Please try again.', 'error')
    }
  } finally {
    pinSaving.value = false
  }
}

const logout = async () => {
  try {
    await axios.post('/api/logout')
  } catch (_) {}
  localStorage.removeItem('token')
  localStorage.removeItem('is_admin')
  window.location.assign(`${basePath}login`)
}

const checkZakat = async () => {
  try {
    const token = localStorage.getItem('token')
    const { data } = await axios.get('/api/zakat/estimate', { headers: { Authorization: `Bearer ${token}` } })

    if (!data || !data.base) {
      showNotice('Zakat', 'Could not compute your Zakat at this time. Please try again later.', 'error')
      return
    }

    if (!data.eligible) {
      const msg = data.base < data.nisab
        ? `You are currently below the Nisab (${currency} ${formatMoney(data.nisab)}).`
        : `You will be eligible on ${new Date(data.eligible_on).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}.`
      showNotice('Zakat', `Zakat not yet due.\n${msg}`, 'info')
      return
    }

    const due = formatMoney(data.zakat_due)
    const ok = await modal.confirm(`Your Zakat for this year is ${currency} ${due}. Would you like to pay now?`, { confirmText: 'Pay Now' })
    if (!ok) return

    const gateway = appStatusStore.paymentGateways?.primary || 'paystack'
    const callback_url = `${window.location.origin}${basePath}payment-callback?gateway=${gateway}`
    const payResp = await axios.post('/api/zakat/pay', { gateway, callback_url }, { headers: { Authorization: `Bearer ${token}` } })
    const url = payResp.data?.checkout_url || payResp.data?.authorization_url
    if (url) {
      window.location.assign(url)
    } else {
      showNotice('Zakat', 'Failed to start payment. Please try again.', 'error')
    }
  } catch (e) {
    const msg = e?.response?.data?.message || 'An error occurred while checking Zakat.'
    showNotice('Zakat', msg, 'error')
  }
}

const payZakatFitr = async () => {
  try {
    const amount = formatMoney(dashboardData.value.fitr_amount)
    const ok = await modal.confirm(`Quick-pay Zakat Al-Fitr for this year: ${currency} ${amount}. Proceed to payment?`, {
      confirmText: 'Pay Now',
      title: 'Zakat Al-Fitr'
    })
    if (!ok) return

    const token = localStorage.getItem('token')
    const gateway = appStatusStore.paymentGateways?.primary || 'paystack'
    const callback_url = `${window.location.origin}${basePath}payment-callback?gateway=${gateway}`
    const { data } = await axios.post('/api/zakat/pay-fitr', { gateway, callback_url }, { headers: { Authorization: `Bearer ${token}` } })
    const url = data?.checkout_url
    if (url) {
      window.location.assign(url)
    } else {
      showNotice('Zakat Al-Fitr', 'Failed to start payment. Please try again.', 'error')
    }
  } catch (e) {
    const msg = e?.response?.data?.message || 'An error occurred while initiating Zakat Al-Fitr payment.'
    showNotice('Zakat Al-Fitr', msg, 'error')
  }
}

// Quick guide: inline explanations for key features
const showPassbookInfo = () => {
  const msg = [
    'Your digital ledger with the cooperative.',
    '• See every contribution, withdrawal, Qard Hasan (Loan) disbursement/repayment, fines, and adjustments.',
    '• Tap a row to view full details and reference.',
    '• Use filters (date range, scheme/type) to find entries fast.'
  ].join('\n')
  showNotice('Passbook', msg, 'info')
}

const showZakatInfo = () => {
  const msg = [
    'We help you check if Zakat is due and estimate the amount.',
    '• Eligibility: compares your eligible wealth with the Nisab and timing (haul).',
    '• Rate: typically 2.5% on eligible holdings once due.',
    '• Data source: based on balances and assets recorded with the cooperative.',
    'You can run an estimate now and, if due, pay securely in-app.'
  ].join('\n')
  showNotice('Zakat', msg, 'info')
}

const showHajjInfo = () => {
  const msg = [
    'Plan and save towards your Hajj or Umrah journey.',
    '• Set a goal amount and target date on the Goals page.',
    '• Track progress with each deposit and stay on schedule.',
    '• Withdrawals are protected to keep your pilgrimage savings intact.'
  ].join('\n')
  showNotice('Hajj & Umrah', msg, 'info')
}

onMounted(async () => {
  fetchRegGuarantorRequests()
  try {
    await load()
  } catch (_) {}

  // Real-time listener for balance updates and notifications
  try {
    const echo = getEcho()
    if (!echo) return

    const userId = dashboardData.value.id
    if (userId) {
      echo.private(`user.${userId}`)
        .listen('UserAccountUpdated', (e) => {
          console.log('Real-time update received:', e)
          
          // 1. Update balances smoothly
          if (e.balances) {
            dashboardData.value.balance = e.balances.wallet
            if (dashboardData.value.kpis) {
              dashboardData.value.kpis.savings_balance = e.balances.savings
              dashboardData.value.kpis.gold_balance = e.balances.gold
              dashboardData.value.kpis.special_savings_balance = e.balances.special_savings
              dashboardData.value.kpis.shares_balance = e.balances.shares
              dashboardData.value.kpis.takaful_balance = e.balances.takaful
              dashboardData.value.kpis.outstanding_fines = e.balances.outstanding_fines
              dashboardData.value.kpis.loan_limit = e.balances.loan_limit
              dashboardData.value.kpis.attaqwa_score = e.balances.attaqwa_score
            }
          }

          // 2. Show a real-time notification
          if (e.message) {
            showNotice('Real-time Update', e.message, 'success')
            
            // 3. Add to live actions feed
            liveActions.value.unshift({
              id: Date.now(),
              message: e.message,
              time: new Date(e.time || Date.now()).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            })
            // Keep only last 3 to avoid clutter
            if (liveActions.value.length > 3) liveActions.value.pop()

            // 4. Play a subtle sound (Optional)
            try { 
              const audio = new Audio('/sounds/notification.mp3')
              audio.volume = 0.5
              audio.play().catch(() => {}) // Handle browsers blocking autoplay
            } catch (_) {}
          }
        })
    }
  } catch (err) {
    console.error('Failed to initialize real-time listener:', err)
  }

  // Ensure DOM is fully painted and elements are visible before starting tour
  setTimeout(() => {
    try { startDashboardTour() } catch (_) {}
  }, 500)
})

onUnmounted(() => {
  try {
    const echo = getEcho()
    const userId = dashboardData.value.id
    if (echo && userId) {
      echo.leave(`user.${userId}`)
    }
  } catch(_) {}
})
</script>

<style scoped>
:deep(.swiper-pagination-bullet) {
  background: rgb(203 213 225); /* slate-300 */
  opacity: 1;
  width: 6px;
  height: 6px;
}
:deep(.swiper-pagination-bullet-active) {
  background: rgb(16 185 129); /* emerald-500 */
  width: 12px;
  border-radius: 3px;
}
:deep(.swiper-pagination) {
  bottom: 8px !important;
}
</style>
