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
          <h1 class="text-base font-black text-slate-800 tracking-tight leading-none">Wallet Allocation</h1>
          <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-[0.2em] mt-1">{{ user?.full_name }}</p>
        </div>
      </div>
    </header>

    <div v-if="loading" class="flex flex-col items-center py-20 space-y-4">
      <div class="w-12 h-12 border-4 border-emerald-100 border-t-emerald-600 rounded-full animate-spin"></div>
      <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Loading Wallet...</p>
    </div>

    <div v-else class="p-6 space-y-6 max-w-lg mx-auto">
      <div class="bg-amber-600 p-8 rounded-[2.5rem] text-white shadow-xl shadow-amber-200 text-center relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
        <p class="text-[10px] font-black uppercase tracking-[0.3em] opacity-60 mb-1">Available Wallet Balance</p>
        <p class="text-3xl font-black">₦{{ formatMoney(balance) }}</p>
      </div>

      <!-- Quick Actions for Admin -->
      <div v-if="adminSettings.admin_member_funding_enabled || adminSettings.admin_allocation_enabled" class="grid grid-cols-2 gap-4">
        <button 
          v-if="adminSettings.admin_member_funding_enabled"
          @click="showFundModal = true" 
          class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex flex-col items-center gap-3 active:scale-95 transition-all"
        >
          <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl">
            <span class="i-mdi-plus-circle"></span>
          </div>
          <span class="text-[10px] font-black text-slate-800 uppercase tracking-widest text-center">Topup Wallet</span>
        </button>

        <button 
          v-if="adminSettings.admin_member_funding_enabled"
          @click="showDvaModal = true" 
          class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex flex-col items-center gap-3 active:scale-95 transition-all"
        >
          <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl">
            <span class="i-mdi-bank-check"></span>
          </div>
          <span class="text-[10px] font-black text-slate-800 uppercase tracking-widest text-center">Manage DVA</span>
        </button>

        <button 
          v-if="adminSettings.admin_allocation_enabled"
          @click="showAdminAllocModal = true" 
          class="col-span-2 bg-emerald-600 p-6 rounded-[2rem] text-white shadow-lg shadow-emerald-100 flex items-center justify-between active:scale-95 transition-all"
          :class="{ 'col-span-2': true }"
        >
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-2xl">
              <span class="i-mdi-arrow-up-down-bold"></span>
            </div>
            <div class="text-left">
              <p class="text-xs font-black uppercase tracking-widest">Admin Allocation</p>
              <p class="text-[9px] font-bold text-white/70 uppercase tracking-widest">Fund member from your wallet</p>
            </div>
          </div>
          <span class="i-mdi-chevron-right text-white/50"></span>
        </button>
      </div>

      <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm space-y-6">
        <div class="text-center">
          <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">Distribution</h3>
          <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Allocate funds to schemes</p>
        </div>

        <div class="space-y-4">
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Note (Optional)</label>
            <textarea v-model="notes" rows="2" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-medium outline-none focus:ring-2 focus:ring-amber-500 transition-all"></textarea>
          </div>

          <div v-for="(alloc, index) in allocations" :key="index" class="p-4 bg-slate-50 rounded-3xl relative">
            <button v-if="allocations.length > 1" @click="removeAllocation(index)" class="absolute -top-2 -right-2 w-6 h-6 bg-rose-500 text-white rounded-full flex items-center justify-center text-xs shadow-md">
              <span class="i-mdi-close"></span>
            </button>
            
            <div class="space-y-3">
              <select v-model="alloc.scheme_id" class="w-full bg-white border-none rounded-2xl px-4 py-3 text-xs font-black outline-none focus:ring-2 focus:ring-amber-500 transition-all">
                <option v-if="hasSharesAndSavings" value="combined">Shares & Savings (50/50 Split)</option>
                <option v-for="scheme in filteredSchemes" :key="scheme.id" :value="scheme.id">{{ scheme.name }}</option>
              </select>
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-black text-xs">₦</span>
                <input v-model="alloc.amount" type="number" step="0.01" class="w-full pl-8 pr-4 py-3 bg-white border-none rounded-2xl text-xs font-black outline-none focus:ring-2 focus:ring-amber-500 transition-all" placeholder="Amount" />
              </div>
            </div>
          </div>

          <button @click="addAllocation" class="w-full py-4 border-2 border-dashed border-slate-200 rounded-3xl text-[10px] font-black text-slate-400 uppercase tracking-widest hover:border-amber-300 hover:text-amber-500 transition-all">
            + Add Another Scheme
          </button>
        </div>

        <div class="pt-6 border-t border-slate-50 space-y-4">
          <div class="flex items-center justify-between px-4">
            <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Total to Allocate</p>
            <p class="text-lg font-black" :class="totalAllocated > balance ? 'text-rose-500' : 'text-slate-800'">₦{{ formatMoney(totalAllocated) }}</p>
          </div>

          <div class="grid gap-3" :class="{ 'grid-cols-2': adminSettings.admin_member_funding_enabled }">
            <button 
              @click="submitAllocation" 
              :disabled="submitting || totalAllocated <= 0 || totalAllocated > balance"
              class="w-full bg-amber-600 py-5 rounded-[2rem] text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-amber-200 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
            >
              <span v-if="!submitting" class="i-mdi-check-bold text-lg"></span>
              {{ submitting ? 'Processing...' : 'Confirm Allocation' }}
            </button>
            <button 
              v-if="adminSettings.admin_member_funding_enabled"
              @click="initializeSchemePayment" 
              :disabled="submitting || totalAllocated <= 0"
              class="w-full bg-slate-800 py-5 rounded-[2rem] text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-slate-200 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
            >
              <span v-if="!submitting" class="i-mdi-credit-card-outline text-lg"></span>
              {{ submitting ? 'Processing...' : 'Pay via Gateway' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Recent Transactions -->
      <div class="space-y-4 pt-4">
        <div class="flex items-center justify-between px-4">
          <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Transaction History</h3>
          <button @click="fetchTransactions" class="text-[10px] font-black text-amber-600 uppercase tracking-widest">Refresh</button>
        </div>
        
        <div class="space-y-3">
          <div v-for="tx in transactions" :key="tx.id" class="bg-white p-4 rounded-[2rem] border border-slate-100 shadow-sm flex items-center justify-between group">
            <div class="flex items-center gap-4">
              <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="tx.type === 'credit' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'">
                <span :class="tx.type === 'credit' ? 'i-mdi-arrow-down-bold' : 'i-mdi-arrow-up-bold'" class="text-xl"></span>
              </div>
              <div>
                <p class="text-xs font-black text-slate-800">{{ tx.meta?.notes || tx.meta?.description || tx.description || 'Wallet Transaction' }}</p>
                <p class="text-[9px] font-bold text-slate-400 uppercase">{{ new Date(tx.created_at).toLocaleDateString() }} • {{ tx.status || tx.source }}</p>
              </div>
            </div>
            <div class="text-right flex items-center gap-4">
              <div>
                <p class="text-sm font-black" :class="tx.type === 'credit' ? 'text-emerald-600' : 'text-slate-800'">
                  {{ tx.type === 'credit' ? '+' : '-' }}₦{{ formatMoney(tx.amount) }}
                </p>
              </div>
              <div class="flex gap-1">
                <button @click="editTransaction(tx)" class="w-8 h-8 bg-slate-50 text-slate-400 rounded-lg flex items-center justify-center hover:bg-amber-50 hover:text-amber-600">
                  <span class="i-mdi-pencil text-sm"></span>
                </button>
                <button @click="confirmDeleteTransaction(tx)" class="w-8 h-8 bg-slate-50 text-rose-400 rounded-lg flex items-center justify-center hover:bg-rose-50">
                  <span class="i-mdi-trash-can text-sm"></span>
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <div v-if="pagination.next_page_url" class="text-center pt-2">
          <button @click="loadMoreTransactions" class="text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-amber-600 transition-colors">Load More History</button>
        </div>
      </div>
    </div>

    <!-- Edit Transaction Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center p-4">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showEditModal = false"></div>
      <div class="relative bg-white w-full max-w-md rounded-[2.5rem] p-8 space-y-6 animate-in slide-in-from-bottom duration-300">
        <div class="text-center">
          <h3 class="text-xl font-black text-slate-800 tracking-tight">Edit Transaction</h3>
          <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Ref: #{{ editingTx?.id }}</p>
        </div>

        <div class="space-y-4">
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Amount (₦)</label>
            <input v-model="editForm.amount" type="number" step="0.01" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-black outline-none focus:ring-2 focus:ring-amber-500 transition-all" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Type</label>
              <select v-model="editForm.type" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-xs font-black outline-none focus:ring-2 focus:ring-amber-500 transition-all">
                <option value="credit">Credit</option>
                <option value="debit">Debit</option>
              </select>
            </div>
            <div>
              <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Status</label>
              <select v-model="editForm.status" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-xs font-black outline-none focus:ring-2 focus:ring-amber-500 transition-all">
                <option value="pending">Pending</option>
                <option value="success">Success</option>
                <option value="failed">Failed</option>
              </select>
            </div>
          </div>

          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Description</label>
            <textarea v-model="editForm.description" rows="2" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-medium outline-none focus:ring-2 focus:ring-amber-500 transition-all"></textarea>
          </div>
        </div>

        <div class="flex gap-3 pt-4">
          <button @click="showEditModal = false" class="flex-1 py-4 text-sm font-black text-slate-400 uppercase tracking-widest hover:bg-slate-50 rounded-2xl transition-all">Cancel</button>
          <button @click="submitEditTransaction" :disabled="submitting" class="flex-1 bg-amber-600 py-4 rounded-2xl text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-amber-200 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50">
            <span v-if="!submitting" class="i-mdi-content-save text-lg"></span>
            {{ submitting ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Fund Wallet Modal -->
    <div v-if="showFundModal" class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center p-4">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showFundModal = false"></div>
      <div class="relative bg-white w-full max-w-md rounded-[2.5rem] p-8 space-y-6 animate-in slide-in-from-bottom duration-300">
        <div class="text-center">
          <h3 class="text-xl font-black text-slate-800 tracking-tight">Topup Member Wallet</h3>
          <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Direct Paystack Checkout</p>
        </div>
        <div>
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Amount (₦)</label>
          <input v-model="fundAmount" type="number" step="0.01" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all" placeholder="Enter amount to topup" />
        </div>
        <button 
          @click="initializeFunding" 
          :disabled="submitting || fundAmount < 100"
          class="w-full bg-emerald-600 py-5 rounded-[2rem] text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-emerald-200 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
        >
          <span v-if="!submitting" class="i-mdi-credit-card-outline text-lg"></span>
          {{ submitting ? 'Initializing...' : 'Pay with Paystack' }}
        </button>
      </div>
    </div>

    <!-- DVA Management Modal -->
    <div v-if="showDvaModal" class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center p-4">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showDvaModal = false"></div>
      <div class="relative bg-white w-full max-w-md rounded-[2.5rem] p-8 space-y-6 animate-in slide-in-from-bottom duration-300">
        <div class="text-center">
          <h3 class="text-xl font-black text-slate-800 tracking-tight">Virtual Account</h3>
          <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Manage Member's DVA</p>
        </div>

        <div v-if="user?.virtual_account?.dva_account_number" class="bg-slate-50 p-6 rounded-3xl space-y-2">
          <div class="flex justify-between">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Bank</span>
            <span class="text-xs font-black text-slate-800 uppercase">{{ user.virtual_account.dva_bank_name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Account Number</span>
            <span class="text-xs font-black text-slate-800">{{ user.virtual_account.dva_account_number }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Account Name</span>
            <span class="text-xs font-black text-slate-800 uppercase">{{ user.virtual_account.dva_account_name }}</span>
          </div>
        </div>

        <div v-else class="text-center py-4 text-slate-400">
          <p class="text-[10px] font-bold uppercase tracking-widest">No Virtual Account Assigned</p>
        </div>

        <div class="space-y-4 pt-2">
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Preferred Bank</label>
            <select v-model="dvaForm.preferred_bank" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-xs font-black outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
              <option value="wema-bank">Wema Bank</option>
              <option value="titan-paystack">Titan Trust Bank</option>
            </select>
          </div>
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">BVN (11 Digits)</label>
            <input v-model="dvaForm.bvn" type="text" maxlength="11" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-black outline-none focus:ring-2 focus:ring-indigo-500 transition-all" placeholder="Enter Member's BVN" />
          </div>
        </div>

        <button 
          @click="assignDva" 
          :disabled="submitting || !dvaForm.bvn"
          class="w-full bg-indigo-600 py-5 rounded-[2rem] text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-indigo-200 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
        >
          <span v-if="!submitting" class="i-mdi-bank-plus text-lg"></span>
          {{ submitting ? 'Processing...' : (user?.virtual_account?.dva_account_number ? 'Regenerate Account' : 'Assign Virtual Account') }}
        </button>
      </div>
    </div>

    <!-- Admin Allocation Modal -->
    <div v-if="showAdminAllocModal" class="fixed inset-0 z-[100] flex items-end justify-center sm:items-center p-4">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showAdminAllocModal = false"></div>
      <div class="relative bg-white w-full max-w-md rounded-[2.5rem] p-8 space-y-6 animate-in slide-in-from-bottom duration-300 max-h-[90vh] overflow-y-auto">
        <div class="text-center">
          <h3 class="text-xl font-black text-slate-800 tracking-tight">Admin Allocation</h3>
          <div class="mt-2 flex items-center justify-center gap-2">
            <p class="text-[10px] text-emerald-600 font-bold uppercase tracking-widest">Your Balance: ₦{{ formatMoney(adminBalance) }}</p>
            <button @click="showAdminFundModal = true" class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-[8px] font-black uppercase rounded-md hover:bg-emerald-100 transition-colors flex items-center gap-1">
              <span class="i-mdi-plus"></span>
              Topup
            </button>
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Note (Optional)</label>
            <textarea v-model="adminNotes" rows="2" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-medium outline-none focus:ring-2 focus:ring-emerald-500 transition-all"></textarea>
          </div>

          <div v-for="(alloc, index) in adminAllocations" :key="index" class="p-4 bg-slate-50 rounded-3xl relative">
            <button v-if="adminAllocations.length > 1" @click="adminAllocations.splice(index, 1)" class="absolute -top-2 -right-2 w-6 h-6 bg-rose-500 text-white rounded-full flex items-center justify-center text-xs shadow-md">
              <span class="i-mdi-close"></span>
            </button>
            
            <div class="space-y-3">
              <select v-model="alloc.scheme_id" class="w-full bg-white border-none rounded-2xl px-4 py-3 text-xs font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                <option v-if="hasSharesAndSavings" value="combined">Shares & Savings (50/50 Split)</option>
                <option v-for="scheme in filteredSchemes" :key="scheme.id" :value="scheme.id">{{ scheme.name }}</option>
              </select>
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-black text-xs">₦</span>
                <input v-model="alloc.amount" type="number" step="0.01" class="w-full pl-8 pr-4 py-3 bg-white border-none rounded-2xl text-xs font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all" placeholder="Amount" />
              </div>
            </div>
          </div>

          <button @click="adminAllocations.push({ scheme_id: hasSharesAndSavings ? 'combined' : schemes[0]?.id, amount: 0 })" class="w-full py-4 border-2 border-dashed border-slate-200 rounded-3xl text-[10px] font-black text-slate-400 uppercase tracking-widest hover:border-emerald-300 hover:text-emerald-500 transition-all">
            + Add Another Scheme
          </button>
        </div>

        <div class="pt-6 border-t border-slate-50 space-y-4">
          <div class="flex items-center justify-between px-4">
            <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Total to Allocate</p>
            <p class="text-lg font-black" :class="totalAdminAllocated > adminBalance ? 'text-rose-500' : 'text-slate-800'">₦{{ formatMoney(totalAdminAllocated) }}</p>
          </div>

          <button 
            @click="submitAdminAllocation" 
            :disabled="submitting || totalAdminAllocated <= 0 || totalAdminAllocated > adminBalance"
            class="w-full bg-emerald-600 py-5 rounded-[2rem] text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-emerald-200 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
          >
            <span v-if="!submitting" class="i-mdi-account-arrow-right text-lg"></span>
            {{ submitting ? 'Processing...' : 'Confirm Admin Allocation' }}
          </button>
        </div>
      </div>
    </div>
    <!-- Admin Topup Modal -->
    <div v-if="showAdminFundModal" class="fixed inset-0 z-[110] flex items-end justify-center sm:items-center p-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showAdminFundModal = false"></div>
      <div class="relative bg-white w-full max-w-sm rounded-[2.5rem] p-8 space-y-6 animate-in zoom-in-95 duration-200">
        <div class="text-center">
          <h3 class="text-lg font-black text-slate-800 tracking-tight">Topup Admin Wallet</h3>
          <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Fund your account to allocate to members</p>
        </div>
        <div>
          <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-4 mb-2 block">Amount (₦)</label>
          <input v-model="adminFundAmount" type="number" step="0.01" class="w-full bg-slate-50 border-none rounded-2xl px-6 py-4 text-sm font-black outline-none focus:ring-2 focus:ring-emerald-500 transition-all" placeholder="Enter amount" />
        </div>
        <button 
          @click="initializeAdminFunding" 
          :disabled="submitting || adminFundAmount < 100"
          class="w-full bg-emerald-600 py-5 rounded-[2rem] text-sm font-black text-white uppercase tracking-widest shadow-lg shadow-emerald-200 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
        >
          <span v-if="!submitting" class="i-mdi-wallet-plus text-lg"></span>
          {{ submitting ? 'Initializing...' : 'Pay Now' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '../../http'
import { useModal } from '../../composables/useModal'
import getImageUrl from '../../utils/image'

const route = useRoute()
const router = useRouter()
const { confirm, alert } = useModal()
const user = ref(null)
const balance = ref(0)
const loading = ref(true)
const schemes = ref([])
const allocations = ref([{ scheme_id: null, amount: 0 }])
const transactions = ref([])
const pagination = ref({ current_page: 1, next_page_url: null })
const submitting = ref(false)
const notes = ref('')

const hasSharesAndSavings = computed(() => {
  const shares = schemes.value.find(s => s.name === 'Shares') || schemes.value.find(s => s.name.toLowerCase().includes('share'))
  const savings = schemes.value.find(s => s.name === 'Savings') || schemes.value.find(s => s.name.toLowerCase().includes('saving'))
  return !!(shares && savings)
})

const sharesScheme = computed(() => schemes.value.find(s => s.name === 'Shares') || schemes.value.find(s => s.name.toLowerCase().includes('share')))
const savingsScheme = computed(() => schemes.value.find(s => s.name === 'Savings') || schemes.value.find(s => s.name.toLowerCase().includes('saving')))

const filteredSchemes = computed(() => {
  if (!hasSharesAndSavings.value) return schemes.value
  return schemes.value.filter(s => {
    const isShares = s.id === sharesScheme.value?.id
    const isSavings = s.id === savingsScheme.value?.id
    return !isShares && !isSavings
  })
})

const showFundModal = ref(false)
const fundAmount = ref(0)

const showDvaModal = ref(false)
const dvaForm = ref({
  preferred_bank: 'wema-bank',
  bvn: ''
})

const showAdminAllocModal = ref(false)
const showAdminFundModal = ref(false)
const adminFundAmount = ref(0)
const adminBalance = ref(0)
const adminSettings = ref({
  admin_allocation_enabled: true,
  admin_member_funding_enabled: true
})
const adminAllocations = ref([{ scheme_id: null, amount: 0 }])
const adminNotes = ref('')

const showEditModal = ref(false)
const editingTx = ref(null)
const editForm = ref({
  amount: 0,
  type: 'credit',
  status: 'success',
  description: ''
})

const formatMoney = (val) => new Intl.NumberFormat().format(val || 0)

const totalAllocated = computed(() => {
  return allocations.value.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0)
})

const totalAdminAllocated = computed(() => {
  return adminAllocations.value.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0)
})

const fetchData = async () => {
  loading.value = true
  try {
    const [userRes, schemeRes] = await Promise.all([
      axios.get(`/api/admin/members/${route.params.id}`),
      axios.get('/api/schemes')
    ])
    user.value = userRes.data.user
    balance.value = userRes.data.balance
    schemes.value = schemeRes.data
    if (hasSharesAndSavings.value) {
      allocations.value[0].scheme_id = 'combined'
      adminAllocations.value[0].scheme_id = 'combined'
    } else if (schemes.value.length > 0) {
      allocations.value[0].scheme_id = schemes.value[0].id
      adminAllocations.value[0].scheme_id = schemes.value[0].id
    }

    dvaForm.value.bvn = user.value.bvn || ''
    
    // Fetch Admin Balance & Settings
    const adminRes = await axios.get('/api/admin/profile')
    adminBalance.value = adminRes.data.balance
    if (adminRes.data.settings) {
      adminSettings.value = adminRes.data.settings
    }
    
    fetchTransactions()
  } catch (e) {
    console.error('Failed to fetch data', e)
  } finally {
    loading.value = false
  }
}

const fetchTransactions = async (page = 1) => {
  try {
    const { data } = await axios.get(`/api/admin/members/${route.params.id}/wallet-transactions?page=${page}`)
    if (page === 1) {
      transactions.value = data.data
    } else {
      transactions.value = [...transactions.value, ...data.data]
    }
    pagination.value = {
      current_page: data.current_page,
      next_page_url: data.next_page_url
    }
  } catch (e) {
    console.error('Failed to fetch transactions', e)
  }
}

const loadMoreTransactions = () => {
  if (pagination.value.next_page_url) {
    fetchTransactions(pagination.value.current_page + 1)
  }
}

const editTransaction = (tx) => {
  editingTx.value = tx
  editForm.value = {
    amount: tx.amount,
    type: tx.type,
    status: tx.meta?.status || tx.status || 'success',
    description: tx.meta?.notes || tx.meta?.description || tx.description || ''
  }
  showEditModal.value = true
}

const confirmDeleteTransaction = async (tx) => {
  const ok = await confirm('Are you sure you want to delete this transaction? This will NOT automatically revert balance changes.', {
    title: 'Delete Transaction',
    confirmText: 'Delete',
    cancelText: 'Cancel'
  })
  if (!ok) return
  try {
    await axios.delete(`/api/admin/members/wallet-transactions/${tx.id}`)
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to delete transaction', 'Error')
  }
}

const submitEditTransaction = async () => {
  submitting.value = true
  try {
    await axios.patch(`/api/admin/members/wallet-transactions/${editingTx.value.id}`, editForm.value)
    alert('Transaction updated successfully', 'Success')
    showEditModal.value = false
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to update transaction', 'Error')
  } finally {
    submitting.value = false
  }
}

const addAllocation = () => {
  const defaultScheme = hasSharesAndSavings.value ? 'combined' : (schemes.value.length > 0 ? schemes.value[0].id : null)
  allocations.value.push({ 
    scheme_id: defaultScheme, 
    amount: 0 
  })
}

const removeAllocation = (index) => {
  allocations.value.splice(index, 1)
}

const submitAllocation = async () => {
  submitting.value = true
  try {
    const expanded = []
    allocations.value.forEach(alloc => {
      if (alloc.scheme_id === 'combined') {
        const half = (parseFloat(alloc.amount) || 0) / 2
        if (sharesScheme.value) expanded.push({ scheme_id: sharesScheme.value.id, amount: half })
        if (savingsScheme.value) expanded.push({ scheme_id: savingsScheme.value.id, amount: half })
      } else {
        expanded.push(alloc)
      }
    })

    await axios.post(`/api/admin/members/${route.params.id}/allocate-wallet`, {
      allocations: expanded,
      notes: notes.value
    })
    alert('Wallet funds allocated successfully', 'Success')
    router.push(`/admin/members/${route.params.id}`)
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to allocate wallet funds', 'Error')
  } finally {
    submitting.value = false
  }
}

const initializeSchemePayment = async () => {
  submitting.value = true
  try {
    const expanded = []
    allocations.value.forEach(alloc => {
      if (alloc.scheme_id === 'combined') {
        const half = (parseFloat(alloc.amount) || 0) / 2
        if (sharesScheme.value) expanded.push({ scheme_id: sharesScheme.value.id, amount: half })
        if (savingsScheme.value) expanded.push({ scheme_id: savingsScheme.value.id, amount: half })
      } else {
        expanded.push(alloc)
      }
    })

    if (expanded.length === 0) {
      alert('Please add at least one scheme allocation', 'Warning')
      return
    }

    const { data } = await axios.post(`/api/admin/members/${route.params.id}/initialize-scheme-funding`, {
      items: expanded,
      callback_url: window.location.href
    })
    if (data.authorization_url) {
      window.location.href = data.authorization_url
    }
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to initialize payment', 'Error')
  } finally {
    submitting.value = false
  }
}

const initializeFunding = async () => {
  submitting.value = true
  try {
    const { data } = await axios.post(`/api/admin/members/${route.params.id}/initialize-funding`, {
      amount: fundAmount.value,
      callback_url: window.location.href
    })
    if (data.authorization_url) {
      window.location.href = data.authorization_url
    }
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to initialize funding', 'Error')
  } finally {
    submitting.value = false
  }
}

const initializeAdminFunding = async () => {
  submitting.value = true
  try {
    const { data } = await axios.post('/api/wallet/topup/initiate', {
      amount: adminFundAmount.value,
      provider: 'paystack',
      callback_url: window.location.href
    })
    if (data.authorization_url) {
      window.location.href = data.authorization_url
    }
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to initialize admin funding', 'Error')
  } finally {
    submitting.value = false
  }
}

const assignDva = async () => {
  submitting.value = true
  try {
    await axios.post(`/api/admin/members/${route.params.id}/assign-virtual-account`, {
      ...dvaForm.value,
      phone: user.value.phone
    })
    alert('Virtual account assigned successfully', 'Success')
    showDvaModal.value = false
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to assign virtual account', 'Error')
  } finally {
    submitting.value = false
  }
}

const submitAdminAllocation = async () => {
  submitting.value = true
  try {
    const expanded = []
    adminAllocations.value.forEach(alloc => {
      if (alloc.scheme_id === 'combined') {
        const half = (parseFloat(alloc.amount) || 0) / 2
        if (sharesScheme.value) expanded.push({ scheme_id: sharesScheme.value.id, amount: half })
        if (savingsScheme.value) expanded.push({ scheme_id: savingsScheme.value.id, amount: half })
      } else {
        expanded.push(alloc)
      }
    })

    await axios.post(`/api/admin/members/${route.params.id}/allocate-from-admin`, {
      allocations: expanded,
      notes: adminNotes.value
    })
    alert('Funds allocated from admin wallet successfully', 'Success')
    showAdminAllocModal.value = false
    fetchData()
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to allocate from admin wallet', 'Error')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>
