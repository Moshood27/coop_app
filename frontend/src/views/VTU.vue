<template>
  <div class="min-h-screen bg-slate-50 font-sans pb-24">
    <!-- Header -->
    <header class="header-fintech sticky top-0 z-50">
      <div class="flex items-center justify-between px-4 h-16">
        <div class="flex items-center gap-3">
          <button @click="$router.back()" class="p-2 -ml-2 text-slate-600 active:scale-90 transition-transform">
            <span class="material-icons text-2xl">arrow_back</span>
          </button>
          <h1 class="text-lg font-bold text-slate-800">Airtime & Bills</h1>
        </div>
        <router-link to="/vtu/history" class="text-emerald-700 text-sm font-bold bg-emerald-50 px-3 py-1.5 rounded-full active:scale-95 transition-all">
          History
        </router-link>
      </div>
    </header>

    <div class="max-w-md mx-auto">
      <!-- Balance Section -->
      <div class="px-4 py-6 bg-white border-b border-slate-100">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-500 text-xs font-semibold uppercase tracking-wider mb-1">Available Balance</p>
            <div class="flex items-center gap-2">
              <h2 class="text-3xl font-black text-slate-800 tracking-tight">₦ {{ formatMoney(balance) }}</h2>
              <button @click="loadWallet" class="p-1 text-slate-300 hover:text-emerald-600 transition-colors">
                <span class="material-icons text-lg">refresh</span>
              </button>
            </div>
          </div>
          <router-link to="/wallet/topup" class="bg-emerald-600 text-white p-3 rounded-2xl shadow-lg shadow-emerald-100 active:scale-95 transition-all">
            <span class="material-icons">add</span>
          </router-link>
        </div>
      </div>

      <!-- Service Selection Grid -->
      <div class="p-4">
        <div class="bg-white rounded-3xl p-4 shadow-sm border border-slate-100 grid grid-cols-4 gap-2">
          <button v-for="s in services" :key="s.id" @click="tab = s.id" 
            class="flex flex-col items-center gap-2 p-2 rounded-2xl transition-all duration-300 relative group"
            :class="tab === s.id ? 'bg-emerald-50' : 'hover:bg-slate-50'">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center transition-all"
              :class="tab === s.id ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200' : 'bg-slate-100 text-slate-500'">
              <span class="material-icons text-2xl">{{ s.icon }}</span>
            </div>
            <span class="text-[10px] font-bold text-center leading-tight" 
              :class="tab === s.id ? 'text-emerald-700' : 'text-slate-500'">{{ s.name }}</span>
            <div v-if="tab === s.id" class="absolute -bottom-1 w-1 h-1 bg-emerald-600 rounded-full"></div>
          </button>
        </div>
      </div>

      <!-- Main Form Area -->
      <div class="px-4 pb-12">
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 p-6 space-y-6 min-h-[400px]">
          
          <!-- AIRTIME FORM -->
          <div v-if="tab==='airtime'" class="animate-fade-in space-y-6">
            <div class="space-y-3">
              <label class="lbl">Select Network</label>
              <div class="flex gap-4 overflow-x-auto pb-2 -mx-1 px-1 hide-scrollbar">
                <button v-for="n in networks" :key="n.id" @click="airtime.network = n.id"
                  class="flex-shrink-0 flex flex-col items-center gap-2 group">
                  <div :class="[
                    airtime.network === n.id ? 'border-emerald-500 bg-emerald-50 shadow-md ring-2 ring-emerald-100' : 'border-slate-100 bg-white',
                    'w-16 h-16 rounded-2xl border-2 flex items-center justify-center transition-all duration-300'
                  ]">
                    <div :style="{ backgroundColor: n.color }" class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-black text-xs shadow-inner">
                      {{ n.short }}
                    </div>
                  </div>
                  <span class="text-[10px] font-bold uppercase tracking-tighter transition-colors" :class="airtime.network === n.id ? 'text-emerald-700' : 'text-slate-700'">{{ n.name }}</span>
                </button>
              </div>
            </div>

            <div class="space-y-2">
              <label class="lbl">Recipient Phone Number</label>
              <div class="relative">
                <input v-model="airtime.phone" type="tel" placeholder="0803 000 0000" class="inp py-4 px-5 text-base font-semibold" />
                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-emerald-600 material-icons">contact_phone</span>
              </div>
            </div>

            <div class="space-y-4">
              <label class="lbl">Amount (₦)</label>
              <input v-model.number="airtime.amount" type="number" placeholder="Enter Amount" class="inp py-4 px-5 text-xl font-black text-emerald-700" />
              
              <div class="grid grid-cols-4 gap-2">
                <button v-for="amt in [100, 200, 500, 1000]" :key="amt" @click="airtime.amount = amt"
                  class="py-2.5 rounded-xl border border-slate-200 text-xs font-bold transition-all active:scale-90"
                  :class="airtime.amount === amt ? 'bg-emerald-600 text-white border-emerald-600 shadow-md' : 'bg-slate-50 text-slate-600'">
                  ₦{{ amt }}
                </button>
              </div>
            </div>

            <button @click="buyAirtime" :disabled="loadingAirtime || !canBuyAirtime" 
              class="w-full bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white py-5 rounded-[1.5rem] font-bold text-lg shadow-xl shadow-emerald-100 transition-all active:scale-95 flex items-center justify-center gap-2">
              <span v-if="loadingAirtime" class="animate-spin material-icons">sync</span>
              <span>{{ loadingAirtime ? 'Processing...' : 'Recharge Now' }}</span>
            </button>
          </div>

          <!-- DATA FORM -->
          <div v-if="tab==='data'" class="animate-fade-in space-y-6">
            <div class="space-y-3">
              <label class="lbl">Select Network</label>
              <div class="flex gap-4 overflow-x-auto pb-2 -mx-1 px-1 hide-scrollbar">
                <button v-for="n in networks" :key="n.id" @click="dataForm.network = n.id"
                  class="flex-shrink-0 flex flex-col items-center gap-2 group">
                  <div :class="[
                    dataForm.network === n.id ? 'border-emerald-500 bg-emerald-50 shadow-md ring-2 ring-emerald-100' : 'border-slate-100 bg-white',
                    'w-16 h-16 rounded-2xl border-2 flex items-center justify-center transition-all duration-300'
                  ]">
                    <div :style="{ backgroundColor: n.color }" class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-black text-xs">
                      {{ n.short }}
                    </div>
                  </div>
                  <span class="text-[10px] font-bold uppercase tracking-tighter transition-colors" :class="dataForm.network === n.id ? 'text-emerald-700' : 'text-slate-700'">{{ n.name }}</span>
                </button>
              </div>
            </div>

            <div class="space-y-2">
              <label class="lbl">Phone Number</label>
              <input v-model="dataForm.phone" type="tel" placeholder="0803 000 0000" class="inp py-4 px-5 text-base font-semibold" />
            </div>

            <div class="space-y-2">
              <label class="lbl">Data Plan</label>
              <select v-model="dataForm.bundleCode" class="inp py-4 px-5 text-sm font-semibold">
                <option value="">Select a plan</option>
                <option v-for="b in bundles" :key="b.code" :value="b.code">
                  {{ b.name || b.code }} (₦{{ formatMoney(b.amount) }})
                </option>
              </select>
              <div v-if="selectedBundle" class="mt-2 p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                <p class="text-[10px] text-emerald-800 font-bold uppercase text-center">
                  Total Charge: ₦{{ formatMoney(selectedBundle.total_debit) }}
                </p>
              </div>
            </div>

            <button @click="buyData" :disabled="loadingData || !canBuyData" 
              class="w-full bg-emerald-700 text-white py-5 rounded-[1.5rem] font-bold text-lg shadow-xl shadow-emerald-100 transition-all active:scale-95 flex items-center justify-center gap-2">
              <span v-if="loadingData" class="animate-spin material-icons">sync</span>
              <span>Buy Data</span>
            </button>
          </div>

          <!-- ELECTRICITY FORM -->
          <div v-if="tab==='electricity'" class="animate-fade-in space-y-6">
            <div class="space-y-4">
              <div class="space-y-2">
                <label class="lbl">Distribution Company (Disco)</label>
                <div class="grid grid-cols-2 gap-2">
                  <button v-for="d in electricityDiscos" :key="d.code" @click="electricity.disco = d.code"
                    class="p-3 rounded-xl border-2 transition-all text-left flex items-center gap-3 group"
                    :class="electricity.disco === d.code ? 'border-emerald-600 bg-emerald-50' : 'border-slate-100 bg-white'">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-[10px] font-black group-hover:bg-slate-200 transition-colors"
                      :class="electricity.disco === d.code ? 'text-emerald-700' : 'text-slate-500'">
                      {{ getShortName(d.name) }}
                    </div>
                    <span class="text-[11px] font-bold leading-tight" :class="electricity.disco === d.code ? 'text-emerald-700' : 'text-slate-800'">
                      {{ d.name || d.code }}
                    </span>
                  </button>
                  <div v-if="!electricityDiscos.length" class="col-span-2 py-4 text-center text-slate-400 text-xs italic">
                    Loading companies...
                  </div>
                </div>
              </div>
              
              <div class="space-y-2">
                <label class="lbl">Meter Type</label>
                <div class="flex gap-3">
                  <button v-for="t in ['prepaid', 'postpaid']" :key="t" 
                    @click="electricity.meterType = t"
                    class="flex-1 py-3 rounded-xl border-2 transition-all font-bold text-xs uppercase tracking-wider"
                    :class="electricity.meterType === t ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500'">
                    {{ t }}
                  </button>
                </div>
              </div>
            </div>

            <div class="space-y-2">
              <label class="lbl">Meter Number</label>
              <div class="flex gap-2">
                <input v-model="electricity.meter" type="text" placeholder="Meter number" class="flex-1 inp font-bold" />
                <button @click="verifyMerchant" :disabled="verification.loading || !electricity.meter" 
                  class="bg-slate-800 text-white px-4 rounded-xl text-[10px] font-bold uppercase active:scale-90 transition-all">
                  {{ verification.loading ? '...' : 'Verify' }}
                </button>
              </div>
              <div v-if="verification.verified" class="mt-2 p-3 bg-emerald-50 rounded-xl border border-emerald-200">
                <p class="text-xs text-emerald-800 font-bold flex items-center gap-2">
                  <span class="material-icons text-sm">check_circle</span>
                  {{ verification.customerName }}
                </p>
              </div>
            </div>

            <div class="space-y-2">
              <label class="lbl">Amount (₦)</label>
              <input v-model.number="electricity.amount" type="number" placeholder="Enter amount" class="inp py-4 text-xl font-black text-emerald-700" />
            </div>

            <button @click="buyElectricity" :disabled="loadingElectricity || !canBuyElectricity" 
              class="w-full bg-slate-900 text-white py-5 rounded-[1.5rem] font-bold text-lg shadow-xl transition-all active:scale-95">
              Pay Bill
            </button>
          </div>

          <!-- CABLE TV FORM -->
          <div v-if="tab==='cable'" class="animate-fade-in space-y-6">
             <div class="space-y-3">
              <label class="lbl">Select Provider</label>
              <div class="flex gap-4">
                <button v-for="p in [{id:'dstv', n:'DSTV', c:'#0067b2'}, {id:'gotv', n:'GOTV', c:'#ec1c24'}, {id:'startimes', n:'Star', c:'#f7941d'}]" 
                  :key="p.id" @click="cable.service = p.id"
                  class="flex-1 flex flex-col items-center gap-2">
                  <div :class="[
                    cable.service === p.id ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-100' : 'border-slate-100 bg-white',
                    'w-full aspect-square rounded-2xl border-2 flex items-center justify-center transition-all'
                  ]">
                    <div :style="{ backgroundColor: p.c }" class="w-10 h-10 rounded-lg flex items-center justify-center text-[10px] text-white font-black uppercase">
                      {{ p.id }}
                    </div>
                  </div>
                  <span class="text-[10px] font-bold uppercase tracking-tighter transition-colors" :class="cable.service === p.id ? 'text-emerald-700' : 'text-slate-700'">{{ p.n }}</span>
                </button>
              </div>
            </div>

            <div class="space-y-2">
              <label class="lbl">Smartcard / IUC Number</label>
              <div class="flex gap-2">
                <input v-model="cable.smartcard" type="text" placeholder="IUC number" class="flex-1 inp font-bold" />
                <button @click="verifyMerchant" :disabled="verification.loading || !cable.smartcard" 
                  class="bg-slate-800 text-white px-4 rounded-xl text-[10px] font-bold uppercase">
                  Verify
                </button>
              </div>
              <div v-if="verification.verified" class="mt-2 p-3 bg-emerald-50 rounded-xl">
                <p class="text-xs text-emerald-800 font-bold">{{ verification.customerName }}</p>
              </div>
            </div>

            <div class="space-y-2">
              <label class="lbl">Package</label>
              <select v-model="cable.bundleCode" class="inp text-sm font-bold">
                <option value="">Choose package</option>
                <option v-for="b in tvBundles" :key="b.code" :value="b.code">{{ b.name || b.code }} (₦{{ formatMoney(b.amount) }})</option>
              </select>
            </div>

            <button @click="buyCable" :disabled="loadingCable || !canBuyCable" 
              class="w-full bg-emerald-700 text-white py-5 rounded-[1.5rem] font-bold text-lg shadow-xl active:scale-95 transition-all">
              Subscribe
            </button>
          </div>

        </div>
      </div>
    </div>

    <!-- Custom Notice Modal (reusable) -->
    <CustomNotice
      v-model="notice.visible"
      :type="notice.type"
      :title="notice.title"
      :message="notice.message"
      @close="closeNotice"
    />

    <!-- PIN Confirmation Modal -->
    <CustomNotice
      v-model="pinModal.visible"
      type="info"
      :title="pinModal.title"
      :message="pinModal.message"
      :prompt="true"
      inputLabel="Transaction PIN (4 digits)"
      confirmText="Confirm"
      cancelText="Cancel"
      inputType="password"
      inputPattern="\\d*"
      :inputMaxlength="4"
      :busy="pinModal.busy"
      @confirm="handlePinConfirm"
      @cancel="handlePinCancel"
      @close="handlePinCancel"
    />

    <AppBottomNav />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import AppBottomNav from '../components/AppBottomNav.vue'
import axios from '../http'
import CustomNotice from '../components/CustomNotice.vue'
import { useNotice } from '../composables/useNotice'
import { useAppStatusStore } from '../stores/appStatus'

// State
const appStatusStore = useAppStatusStore()
const balance = ref(0)
const tab = ref('airtime')

const services = [
  { id: 'airtime', name: 'Airtime', icon: 'phone_android' },
  { id: 'data', name: 'Data', icon: 'wifi' },
  { id: 'electricity', name: 'Electricity', icon: 'bolt' },
  { id: 'cable', name: 'TV Cable', icon: 'tv' },
]

const networks = [
  { id: 'mtn', name: 'MTN', short: 'MTN', color: '#FFCC00' },
  { id: 'airtel', name: 'Airtel', short: 'AIR', color: '#FF0000' },
  { id: 'glo', name: 'Glo', short: 'GLO', color: '#2DB400' },
  { id: '9mobile', name: '9mobile', short: '9M', color: '#00573C' },
]

const bundles = ref([])
const tvBundles = ref([])
const electricityDiscos = ref([])
const loadingAirtime = ref(false)
const loadingData = ref(false)
const loadingElectricity = ref(false)
const loadingCable = ref(false)

const airtime = ref({ network: 'mtn', phone: '', amount: '' })
const dataForm = ref({ network: 'mtn', phone: '', bundleCode: '', vtuProvider: '' })
const electricity = ref({ disco: '', meterType: 'prepaid', meter: '', amount: '', phone: '' })
const cable = ref({ service: 'dstv', smartcard: '', bundleCode: '', phone: '' })

// Verification state
const verification = ref({
  loading: false,
  verified: false,
  customerName: '',
  error: ''
})

// Custom Notice State (shared)
const { notice, showNotice, closeNotice } = useNotice()
// Keep backward-compatible naming inside this file
const showCustomNotice = (title, message, type = 'info') => showNotice(title, message, type)

// PIN Modal state and helpers
const pinModal = ref({
  visible: false,
  title: 'Confirm Purchase',
  message: 'Enter your 4-digit Transaction PIN to proceed.',
  busy: false,
  resolver: null,
  rejecter: null,
})

function promptForPin(message) {
  if (!appStatusStore.transactionPinEnabled) {
    return Promise.resolve('')
  }
  return new Promise((resolve, reject) => {
    pinModal.value.title = 'Confirm Purchase'
    pinModal.value.message = message || 'Enter your 4-digit Transaction PIN to proceed.'
    pinModal.value.busy = false
    pinModal.value.visible = true
    pinModal.value.resolver = resolve
    pinModal.value.rejecter = reject
  })
}

function handlePinConfirm(value) {
  const pin = String(value || '').trim()
  if (!/^\d{4}$/.test(pin)) {
    showCustomNotice('Invalid PIN', 'Please enter a valid 4-digit PIN.', 'error')
    return
  }
  const resolver = pinModal.value.resolver
  pinModal.value.visible = false
  pinModal.value.resolver = null
  pinModal.value.rejecter = null
  if (resolver) resolver(pin)
}

function handlePinCancel() {
  const rejecter = pinModal.value.rejecter
  pinModal.value.visible = false
  pinModal.value.resolver = null
  pinModal.value.rejecter = null
  if (rejecter) rejecter(new Error('cancelled'))
}

// Helpers
const formatMoney = (val) => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
const getShortName = (name) => {
  if (!name) return '?'
  return name.split(/[\s()]/).filter(x => x.length > 2 && x.toUpperCase() === x).join('') || name.substring(0, 3).toUpperCase()
}
const canBuyAirtime = computed(() => !!airtime.value.network && airtime.value.phone?.length >= 10 && Number(airtime.value.amount) >= 50)
const canBuyData = computed(() => !!dataForm.value.network && dataForm.value.phone?.length >= 10 && !!dataForm.value.bundleCode)
const canBuyElectricity = computed(() => !!electricity.value.disco && !!electricity.value.meterType && electricity.value.meter?.length >= 6 && Number(electricity.value.amount) >= 100)
const canBuyCable = computed(() => !!cable.value.service && cable.value.smartcard?.length >= 6 && !!cable.value.bundleCode)
const selectedBundle = computed(() => bundles.value.find(b => b.code === dataForm.value.bundleCode))
const selectedTvBundle = computed(() => tvBundles.value.find(b => b.code === cable.value.bundleCode))

// After a pending response, schedule a one-time status check to finalize UI and refresh wallet
async function scheduleStatusCheck(reference, deliveredMessage = 'Delivered') {
  try {
    // wait ~35s to allow provider callback/reconciliation
    await new Promise(res => setTimeout(res, 35000))
    const { data } = await axios.get(`/api/vtu/status/${reference}`)
    if (data?.status === 'success') {
      await loadWallet()
      const msg = data.message && data.message !== 'Delivered' ? data.message : deliveredMessage
      showCustomNotice('Success', msg, 'success')
    }
  } catch (e) {
    // silent: user can still check History manually
    console.debug('Status check error', e?.response?.data || e?.message)
  }
}

// Data Loading
const loadWallet = async () => {
  try {
    const { data } = await axios.get('/api/wallet')
    balance.value = data.balance || 0
  } catch (e) { console.error('Wallet error', e) }
}

const loadBundles = async () => {
  if (!dataForm.value.network) return
  try {
    const { data } = await axios.get('/api/vtu/data/bundles', { params: { network: dataForm.value.network } })
    bundles.value = data.bundles || []
    dataForm.value.vtuProvider = data.provider || ''
  } catch (e) {
    console.error('Bundles load error', e)
  }
}

const loadTvBundles = async () => {
  if (!cable.value.service) return
  try {
    const { data } = await axios.get('/api/vtu/tv/bundles', { params: { service: cable.value.service } })
    tvBundles.value = data.bundles || []
  } catch (e) {
    console.error('TV bundles load error', e)
  }
}

const loadElectricityDiscos = async () => {
  try {
    const { data } = await axios.get('/api/vtu/electricity/discos')
    electricityDiscos.value = data.discos || []
    if (electricityDiscos.value.length > 0 && !electricity.value.disco) {
      electricity.value.disco = electricityDiscos.value[0].code
    }
  } catch (e) {
    console.error('Discos load error', e)
  }
}

const verifyMerchant = async () => {
  const serviceID = tab.value === 'electricity' ? electricity.value.disco : cable.value.service
  const billersCode = tab.value === 'electricity' ? electricity.value.meter : cable.value.smartcard
  const type = tab.value === 'cable' ? (selectedTvBundle.value?.type || 'renewal') : (tab.value === 'electricity' ? electricity.value.meterType : null)

  if (!serviceID || !billersCode) return

  verification.value.loading = true
  verification.value.verified = false
  verification.value.customerName = ''
  verification.value.error = ''

  try {
    const { data } = await axios.post('/api/vtu/verify-merchant', {
      serviceID,
      billersCode,
      type,
      service_type: tab.value
    })

    const name = data?.customer_name || data?.Customer_Name || data?.customername || data?.content?.Customer_Name || data?.content?.customer_name;
    if (name) {
      verification.value.customerName = name
      verification.value.verified = true
    } else if (data?.content?.error) {
      verification.value.error = data.content.error
    } else {
      verification.value.error = 'Could not verify merchant. Please check the number.'
    }
  } catch (e) {
    verification.value.error = e.response?.data?.message || 'Verification failed. Please try again.'
  } finally {
    verification.value.loading = false
  }
}

// THE FIX: Check if provider data inside an ERROR response actually indicates success
const checkIfActuallySuccess = (errorResponse) => {
  const provider = errorResponse?.provider || errorResponse?.data?.provider
  if (!provider) return false

  const status = String(provider?.status || provider?.data?.status || '').toLowerCase()
  const code = String(provider?.code || provider?.data?.code || '')
  const respDesc = String(provider?.response_description || provider?.data?.response_description || '').toLowerCase()

  return ['delivered', 'success', 'successful', 'completed'].includes(status) ||
      code === '000' ||
      respDesc.includes('success') ||
      respDesc.includes('delivered')
}

// Actions
const buyAirtime = async () => {
  if (!canBuyAirtime.value) return

  loadingAirtime.value = true
  notice.value.visible = false // Reset modal

  try {
    const payload = {
      network: airtime.value.network,
      phone_number: airtime.value.phone,
      amount: Number(airtime.value.amount)
    }
    // Prompt for 4-digit Transaction PIN (custom modal)
    try {
      const pin = await promptForPin('Enter your 4-digit Transaction PIN to confirm purchase.')
      payload.pin = pin
    } catch (e) {
      // User cancelled PIN entry
      loadingAirtime.value = false
      return
    }
    const { data } = await axios.post('/api/vtu/airtime', payload)

    if (data.status === 'success' || data.status === 'pending') {
      showCustomNotice('Success', data.message || 'Transaction processing...', 'success')
      airtime.value.amount = ''
      await loadWallet()
      if (data.status === 'pending' && data.reference) {
        scheduleStatusCheck(data.reference, 'Airtime delivered!')
      }
    } else {
      showCustomNotice('Notice', data.message || 'Check transaction history', 'info')
    }
  } catch (e) {
    // Check for "Success hidden inside error" (common in VTpass Sandbox)
    if (checkIfActuallySuccess(e.response?.data)) {
      showCustomNotice('Success', 'Airtime sent successfully!', 'success')
      airtime.value.amount = ''
      await loadWallet()
    } else {
      const status = e?.response?.status
      const msg = e?.response?.data?.message || 'Transaction could not be completed at this time.'
      if (status === 409) {
        showCustomNotice('Set PIN', 'You need to set your Transaction PIN first. Go to Profile > Transaction PIN.', 'warning')
      } else if (status === 403) {
        showCustomNotice('Invalid PIN', 'Invalid Transaction PIN. Please try again.', 'error')
      } else {
        showCustomNotice('Failed', msg, 'error')
      }
    }
  } finally {
    loadingAirtime.value = false
  }
}

const buyData = async () => {
  if (!canBuyData.value || !selectedBundle.value) return

  loadingData.value = true
  notice.value.visible = false

  try {
    const payload = {
      network: dataForm.value.network,
      phone_number: dataForm.value.phone,
      bundle_code: dataForm.value.bundleCode,
      vtu_provider: dataForm.value.vtuProvider,
      amount: Number(selectedBundle.value?.amount ?? 0)
    }
    // Prompt for 4-digit Transaction PIN (custom modal)
    try {
      const pin = await promptForPin('Enter your 4-digit Transaction PIN to confirm data purchase.')
      payload.pin = pin
    } catch (e) {
      loadingData.value = false
      return
    }
    const { data } = await axios.post('/api/vtu/data', payload)

    if (data.status === 'success' || data.status === 'pending') {
      showCustomNotice('Success', data.message || 'Data purchase processing...', 'success')
      dataForm.value.bundleCode = ''
      await loadWallet()
      if (data.status === 'pending' && data.reference) {
        scheduleStatusCheck(data.reference, 'Data bundle delivered!')
      }
    }
  } catch (e) {
    if (checkIfActuallySuccess(e.response?.data)) {
      showCustomNotice('Success', 'Data bundle purchased successfully!', 'success')
      dataForm.value.bundleCode = ''
      await loadWallet()
    } else {
      const status = e?.response?.status
      const msg = e?.response?.data?.message || 'Data purchase failed'
      if (status === 409) {
        showCustomNotice('Set PIN', 'You need to set your Transaction PIN first. Go to Profile > Transaction PIN.', 'warning')
      } else if (status === 403) {
        showCustomNotice('Invalid PIN', 'Invalid Transaction PIN. Please try again.', 'error')
      } else {
        showCustomNotice('Error', msg, 'error')
      }
    }
  } finally {
    loadingData.value = false
  }
}

const buyElectricity = async () => {
  if (!canBuyElectricity.value) return
  if (!verification.value.verified) {
    showCustomNotice('Verification Required', 'Please verify your meter number first.', 'warning')
    return
  }
  loadingElectricity.value = true
  notice.value.visible = false
  try {
    const payload = {
      disco: electricity.value.disco,
      meter_number: electricity.value.meter,
      meter_type: electricity.value.meterType,
      amount: Number(electricity.value.amount),
    }
    if (electricity.value.phone) payload.phone_number = electricity.value.phone
    // Prompt for 4-digit Transaction PIN (custom modal)
    try {
      const pin = await promptForPin('Enter your 4-digit Transaction PIN to confirm electricity vend.')
      payload.pin = pin
    } catch (e) {
      loadingElectricity.value = false
      return
    }
    const { data } = await axios.post('/api/vtu/electricity', payload)
    if (data.status === 'success' || data.status === 'pending') {
      showCustomNotice('Success', data.message || 'Electricity vend processing...', 'success')
      await loadWallet()
      if (data.status === 'pending' && data.reference) {
        scheduleStatusCheck(data.reference, 'Electricity token vended!')
      }
    }
  } catch (e) {
    if (checkIfActuallySuccess(e.response?.data)) {
      showCustomNotice('Success', 'Electricity token vended successfully!', 'success')
      await loadWallet()
    } else {
      const status = e?.response?.status
      const msg = e?.response?.data?.message || 'Electricity vend failed'
      if (status === 409) {
        showCustomNotice('Set PIN', 'You need to set your Transaction PIN first. Go to Profile > Transaction PIN.', 'warning')
      } else if (status === 403) {
        showCustomNotice('Invalid PIN', 'Invalid Transaction PIN. Please try again.', 'error')
      } else {
        showCustomNotice('Error', msg, 'error')
      }
    }
  } finally {
    loadingElectricity.value = false
  }
}

const buyCable = async () => {
  if (!canBuyCable.value || !selectedTvBundle.value) return
  if (!verification.value.verified) {
    showCustomNotice('Verification Required', 'Please verify your smartcard number first.', 'warning')
    return
  }
  loadingCable.value = true
  notice.value.visible = false
  try {
    const payload = {
      service: cable.value.service,
      smartcard_number: cable.value.smartcard,
      bundle_code: cable.value.bundleCode,
      amount: Number(selectedTvBundle.value?.amount ?? 0),
    }
    if (cable.value.phone) payload.phone_number = cable.value.phone
    // Prompt for 4-digit Transaction PIN (custom modal)
    try {
      const pin = await promptForPin('Enter your 4-digit Transaction PIN to confirm cable subscription.')
      payload.pin = pin
    } catch (e) {
      loadingCable.value = false
      return
    }
    const { data } = await axios.post('/api/vtu/cable', payload)
    if (data.status === 'success' || data.status === 'pending') {
      showCustomNotice('Success', data.message || 'Cable subscription processing...', 'success')
      await loadWallet()
      if (data.status === 'pending' && data.reference) {
        scheduleStatusCheck(data.reference, 'Cable subscription delivered!')
      }
    }
  } catch (e) {
    if (checkIfActuallySuccess(e.response?.data)) {
      showCustomNotice('Success', 'Cable subscription successful!', 'success')
      await loadWallet()
    } else {
      const status = e?.response?.status
      const msg = e?.response?.data?.message || 'Cable subscription failed'
      if (status === 409) {
        showCustomNotice('Set PIN', 'You need to set your Transaction PIN first. Go to Profile > Transaction PIN.', 'warning')
      } else if (status === 403) {
        showCustomNotice('Invalid PIN', 'Invalid Transaction PIN. Please try again.', 'error')
      } else {
        showCustomNotice('Error', msg, 'error')
      }
    }
  } finally {
    loadingCable.value = false
  }
}

// Initial Load
onMounted(() => {
  loadWallet()
  loadBundles()
  loadTvBundles()
  loadElectricityDiscos()
})

// Watchers
watch(() => dataForm.value.network, () => loadBundles())
watch(() => cable.value.service, () => {
  loadTvBundles()
  verification.value.verified = false
  verification.value.customerName = ''
})
watch(() => electricity.value.disco, () => {
  verification.value.verified = false
  verification.value.customerName = ''
})
watch(() => electricity.value.meter, () => {
  verification.value.verified = false
  verification.value.customerName = ''
})
watch(() => cable.value.smartcard, () => {
  verification.value.verified = false
  verification.value.customerName = ''
})
watch(tab, () => {
  verification.value.verified = false
  verification.value.customerName = ''
  verification.value.error = ''
})
</script>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>