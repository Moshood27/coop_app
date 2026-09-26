<template>
  <div class="min-h-screen bg-slate-50">
    <AppHeader title="Wallet" :showBack="true" />

    <div class="max-w-5xl mx-auto p-4 pb-32 space-y-6">
      <WalletBalanceCard 
        :net-balance="store.netBalance"
        :total-balance="store.wallet.balance"
        :admin-charge-balance="store.wallet.admin_charge_balance"
        :withdrawable-balance="store.wallet.available_for_withdrawal"
        :refreshing="store.loading"
        :show-admin-charges="appStatusStore.features['display-admin-charge-in-wallet']"
        @refresh="store.fetchWalletData"
        @allocate="$router.push('/wallet/allocate')"
        @fund="activeTab = 'fund'"
      />

      <!-- Tabs Navigation -->
      <div class="flex p-1.5 bg-slate-200/50 rounded-[1.5rem] gap-1 shadow-inner overflow-x-auto no-scrollbar">
        <button 
          v-for="tab in availableTabs" 
          :key="tab"
          @click="activeTab = tab"
          :class="activeTab === tab ? 'bg-white text-emerald-700 shadow-md scale-[1.02]' : 'text-slate-500 hover:bg-white/30'"
          class="flex-1 py-3 px-4 rounded-2xl text-[10px] font-black uppercase tracking-wider transition-all duration-300 ease-out whitespace-nowrap"
        >
          {{ tab }}
        </button>
      </div>

      <div v-if="activeTab === 'overview'" class="space-y-4">
         <div class="grid grid-cols-2 gap-3">
            <button @click="activeTab = 'transfer'" class="bg-white p-4 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50">
               <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-xl">💸</div>
               <span class="text-xs font-bold text-slate-700">Send</span>
            </button>
            <button v-if="appStatusStore.features['withdrawals-enabled']" @click="activeTab = 'withdraw'" class="bg-white p-4 rounded-3xl shadow-sm border border-slate-100 flex flex-col items-center gap-2 active:bg-slate-50">
               <div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center text-xl">🏦</div>
               <span class="text-xs font-bold text-slate-700">Withdraw</span>
            </button>
         </div>
         <TransactionHistory :transactions="store.transactions" :loading="store.loadingTransactions" @load-more="store.fetchTransactions" />
      </div>

      <FundWalletTab 
        v-if="activeTab === 'fund'" 
        :wallet="store.wallet" 
        :gateways="enabledGateways" 
        :loading="processing"
        :maintenance-charge-config="store.wallet.maintenance_charge_config"
        @fund="handleFundWallet"
      />

      <TransferWalletTab 
        v-if="activeTab === 'transfer'"
        :recipient="recipient"
        :loading="processing"
        @verify="verifyRecipient"
        @transfer="handleTransfer"
      />

      <WithdrawWalletTab 
        v-if="activeTab === 'withdraw'"
        :wallet="store.wallet"
        :features="appStatusStore.features"
        :loading="processing"
        @withdraw="handleWithdraw"
      />

      <MerchantTab 
        v-if="activeTab === 'merchant'"
        :features="appStatusStore.features"
        @pay="$router.push('/merchant/pay')"
        @receive="$router.push('/merchant/receive')"
      />

      <TransactionHistory 
        v-if="activeTab === 'transactions'" 
        :transactions="store.transactions" 
        :loading="store.loadingTransactions"
        @load-more="store.fetchTransactions"
      />

      <WithdrawalRequestsTab 
        v-if="activeTab === 'requests'"
        :requests="store.withdrawalRequests"
        :loading="store.loadingRequests"
        @cancel="cancelWithdrawal"
      />
    </div>

    <AppBottomNav />

    <CustomNotice :notice="notice" @close="closeNotice" />

    <div v-if="showPinPrompt" class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-slate-900/60 backdrop-blur-sm">
       <div class="bg-white w-full max-w-sm rounded-[2.5rem] p-8 shadow-2xl animate-scale-up">
          <h3 class="text-xl font-black text-slate-800 text-center mb-2">Security Verification</h3>
          <p class="text-xs text-slate-500 text-center mb-6">Enter your 4-digit transaction PIN to authorize this request.</p>
          <input v-model="pin" type="password" maxlength="4" class="w-full bg-slate-50 border-none rounded-2xl p-4 text-center text-2xl font-black tracking-widest focus:ring-2 focus:ring-emerald-500" placeholder="••••" />
          <div class="flex gap-3 mt-6">
             <button @click="showPinPrompt = false" class="flex-1 py-4 text-xs font-black uppercase text-slate-400">Cancel</button>
             <button @click="confirmActionWithPin" :disabled="pin.length < 4 || processing" class="flex-1 bg-emerald-700 text-white py-4 rounded-2xl text-xs font-black uppercase shadow-lg shadow-emerald-100">Confirm</button>
          </div>
       </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from '../http.js'
import AppHeader from '../components/AppHeader.vue'
import AppBottomNav from '../components/AppBottomNav.vue'
import CustomNotice from '../components/CustomNotice.vue'
import { useAppStatusStore } from '../stores/appStatus'
import { useWalletStore } from '../stores/wallet'
import { useNotice } from '../composables/useNotice'

// Components
import WalletBalanceCard from '../components/wallet/WalletBalanceCard.vue'
import TransactionHistory from '../components/wallet/TransactionHistory.vue'
import FundWalletTab from '../components/wallet/FundWalletTab.vue'
import TransferWalletTab from '../components/wallet/TransferWalletTab.vue'
import WithdrawWalletTab from '../components/wallet/WithdrawWalletTab.vue'
import MerchantTab from '../components/wallet/MerchantTab.vue'
import WithdrawalRequestsTab from '../components/wallet/WithdrawalRequestsTab.vue'

const router = useRouter()
const appStatusStore = useAppStatusStore()
const store = useWalletStore()
const { notice, showNotice, closeNotice } = useNotice()

const activeTab = ref('overview')
const processing = ref(false)
const recipient = ref(null)
const showPinPrompt = ref(false)
const pin = ref('')
const pendingAction = ref(null)

const availableTabs = computed(() => {
  const tabs = ['overview', 'fund', 'transfer', 'withdraw', 'merchant', 'transactions', 'requests']
  return tabs.filter(t => {
    if (t === 'withdraw') return appStatusStore.features['withdrawals-enabled']
    if (t === 'merchant') return appStatusStore.features['merchant-pay-enabled'] || appStatusStore.features['receive-qr-enabled']
    return true
  })
})

const enabledGateways = computed(() => {
  const gws = appStatusStore.paymentGateways || {}
  return Object.keys(gws).filter(k => k !== 'primary' && gws[k])
})

onMounted(async () => {
  await store.fetchWalletData()
  await store.fetchTransactions()
  await store.fetchWithdrawalRequests()
})

const handleFundWallet = async ({ amount, gateway }) => {
    processing.value = true
    try {
        const res = await axios.post('/api/wallet/fund', { amount, gateway })
        if (res.data.authorization_url) {
            window.location.href = res.data.authorization_url
        }
    } catch (err) {
        showNotice(err.response?.data?.message || 'Funding failed', 'error')
    } finally {
        processing.value = false
    }
}

const verifyRecipient = async (query) => {
    processing.value = true
    try {
        const res = await axios.get('/api/wallet/verify-recipient', { params: { query } })
        recipient.value = res.data
    } catch (err) {
        showNotice('Recipient not found', 'error')
        recipient.value = null
    } finally {
        processing.value = false
    }
}

const handleTransfer = (data) => {
    pendingAction.value = { type: 'transfer', data }
    showPinPrompt.value = true
}

const handleWithdraw = (data) => {
    pendingAction.value = { type: 'withdraw', data }
    showPinPrompt.value = true
}

const confirmActionWithPin = async () => {
    processing.value = true
    try {
        if (pendingAction.value.type === 'transfer') {
            await axios.post('/api/wallet/transfer', { ...pendingAction.value.data, pin: pin.value })
            showNotice('Transfer successful', 'success')
        } else if (pendingAction.value.type === 'withdraw') {
            await axios.post('/api/wallet/withdraw', { ...pendingAction.value.data, pin: pin.value })
            showNotice('Withdrawal request submitted', 'success')
        }
        showPinPrompt.value = false
        pin.value = ''
        await store.fetchWalletData()
        await store.fetchTransactions()
        await store.fetchWithdrawalRequests()
        activeTab.value = 'overview'
    } catch (err) {
        showNotice(err.response?.data?.message || 'Action failed', 'error')
    } finally {
        processing.value = false
    }
}

const cancelWithdrawal = async (wr) => {
    if (!confirm('Are you sure you want to cancel this request?')) return
    try {
        await axios.post(`/api/wallet/withdraw/${wr.id}/cancel`)
        showNotice('Request cancelled', 'success')
        await store.fetchWithdrawalRequests()
    } catch (err) {
        showNotice('Failed to cancel request', 'error')
    }
}
</script>