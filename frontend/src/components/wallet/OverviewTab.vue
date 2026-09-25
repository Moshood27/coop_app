<template>
  <div class="space-y-6">
    <!-- Virtual Account Info (Paystack) -->
    <div v-if="appStatusStore.paymentGateways['paystack']" class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
      <div class="flex justify-between items-center mb-4">
        <h3 class="font-bold text-slate-800">Bank Transfer Account</h3>
      </div>

      <div class="mb-4 bg-rose-50 border border-rose-100 p-3 rounded-2xl">
        <p class="text-xs text-rose-600 font-bold text-center italic">Note: A maintenance charge of {{ wallet?.maintenance_charge_config?.percentage || 1 }}% (max ₦{{ wallet?.maintenance_charge_config?.max_amount || 500 }}) applies.</p>
      </div>
      
      <div v-if="wallet.virtual_account?.account_number" class="space-y-4">
        <div class="relative overflow-hidden bg-gradient-to-br from-emerald-600 to-emerald-800 p-6 rounded-3xl text-white shadow-lg shadow-emerald-200">
           <!-- Subtle pattern overlay -->
           <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white opacity-10 rounded-full blur-2xl"></div>
           <div class="absolute bottom-0 left-0 -mb-8 -ml-8 w-32 h-32 bg-emerald-400 opacity-20 rounded-full blur-3xl"></div>

           <div class="relative">
              <div class="flex justify-between items-start mb-6">
                <div>
                  <p class="text-emerald-100 text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80">Bank Name</p>
                  <p class="font-black text-lg leading-none">{{ wallet.virtual_account.bank_name }}</p>
                </div>
                <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm">
                   <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                   </svg>
                </div>
              </div>

              <div class="mb-6">
                <p class="text-emerald-100 text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80">Account Number</p>
                <div class="flex items-center gap-3">
                   <p class="font-black text-2xl tracking-[0.2em]">{{ wallet.virtual_account.account_number }}</p>
                   <button @click="$emit('copy', wallet.virtual_account.account_number)" class="hover:scale-110 transition-transform active:scale-95">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                     </svg>
                   </button>
                </div>
              </div>

              <div class="flex justify-between items-end">
                <div>
                  <p class="text-emerald-100 text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80">Account Name</p>
                  <p class="font-bold text-sm">{{ wallet.virtual_account.account_name }}</p>
                </div>
                <div class="text-[8px] font-black uppercase tracking-tighter bg-white/10 px-2 py-1 rounded backdrop-blur-md border border-white/10">
                  Virtual Account
                </div>
              </div>
           </div>
        </div>
        <p class="text-[11px] text-slate-500 text-center px-4 leading-relaxed">Transfer funds to this account to top up your wallet instantly.</p>
      </div>

      <div v-else class="space-y-4">
        <div class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100">
          <p class="text-sm text-slate-600 leading-relaxed mb-4">No virtual account yet. Generate one to fund via bank transfer.</p>
          
          <div class="space-y-3">
            <div class="space-y-2">
              <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">BVN (optional)</label>
              <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-emerald-600 text-slate-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm5 3h-3a2 2 0 01-2-2V5" />
                  </svg>
                </div>
                <input :value="bvn" @input="$emit('update:bvn', $event.target.value)" type="tel" inputmode="numeric" maxlength="11" placeholder="11-digit BVN"
                       class="w-full bg-white pl-11 p-4 rounded-2xl border border-slate-200 text-sm outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all" />
              </div>
              <p v-if="bvn && !bvnValid" class="text-rose-600 text-[10px] font-bold">Please enter a valid 11-digit BVN.</p>
              <p class="text-[10px] text-slate-400 leading-tight">Providing your BVN helps us verify your dedicated account faster.</p>
            </div>

            <button @click="$emit('assign')" :disabled="assigning || (!!bvn && !bvnValid)"
                    class="w-full bg-emerald-700 text-white font-bold py-4 rounded-2xl shadow-lg shadow-emerald-200 active:scale-[0.98] transition-all disabled:opacity-50 disabled:shadow-none flex items-center justify-center gap-2">
              <span v-if="!assigning">Generate Virtual Account</span>
              <span v-else>Creating Account...</span>
              <svg v-if="!assigning" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Flutterwave Virtual Account Info -->
    <div v-if="appStatusStore.paymentGateways['flutterwave']" class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
      <div class="flex justify-between items-center mb-4">
        <h3 class="font-bold text-slate-800">Bank Transfer Account (Alt)</h3>
      </div>

      <div class="mb-4 bg-rose-50 border border-rose-100 p-3 rounded-2xl">
        <p class="text-xs text-rose-600 font-bold text-center italic">Note: A maintenance charge of {{ wallet?.maintenance_charge_config?.percentage || 1 }}% (max ₦{{ wallet?.maintenance_charge_config?.max_amount || 500 }}) applies.</p>
      </div>

      <div v-if="wallet.flw_virtual_account?.account_number" class="space-y-4">
        <div class="relative overflow-hidden bg-gradient-to-br from-orange-500 to-orange-700 p-6 rounded-3xl text-white shadow-lg shadow-orange-200">
           <!-- Subtle pattern overlay -->
           <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white opacity-10 rounded-full blur-2xl"></div>
           <div class="absolute bottom-0 left-0 -mb-8 -ml-8 w-32 h-32 bg-orange-300 opacity-20 rounded-full blur-3xl"></div>

           <div class="relative">
              <div class="flex justify-between items-start mb-6">
                <div>
                  <p class="text-orange-100 text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80">Bank Name</p>
                  <p class="font-black text-lg leading-none">{{ wallet.flw_virtual_account.bank_name }}</p>
                </div>
                <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm">
                   <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                   </svg>
                </div>
              </div>

              <div class="mb-6">
                <p class="text-orange-100 text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80">Account Number</p>
                <div class="flex items-center gap-3">
                   <p class="font-black text-2xl tracking-[0.2em]">{{ wallet.flw_virtual_account.account_number }}</p>
                   <button @click="$emit('copy', wallet.flw_virtual_account.account_number)" class="hover:scale-110 transition-transform active:scale-95">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-orange-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                     </svg>
                   </button>
                </div>
              </div>

              <div class="flex justify-between items-end">
                <div>
                  <p class="text-orange-100 text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80">Account Name</p>
                  <p class="font-bold text-sm">{{ wallet.flw_virtual_account.account_name }}</p>
                </div>
                <div class="text-[8px] font-black uppercase tracking-tighter bg-white/10 px-2 py-1 rounded backdrop-blur-md border border-white/10">
                  Alternative Account
                </div>
              </div>
           </div>
        </div>
        <p class="text-[11px] text-slate-500 text-center px-4 leading-relaxed">Transfer funds here to top up your wallet instantly.</p>
      </div>

      <div v-else class="space-y-4">
        <div class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100">
          <p class="text-sm text-slate-600 leading-relaxed mb-4">Generate an alternative virtual account to fund via bank transfer.</p>
          
          <div class="space-y-3">
            <div class="space-y-2">
              <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">BVN (required)</label>
              <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-orange-600 text-slate-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm5 3h-3a2 2 0 01-2-2V5" />
                  </svg>
                </div>
                <input :value="flwBvn" @input="$emit('update:flwBvn', $event.target.value)" type="tel" inputmode="numeric" maxlength="11" placeholder="11-digit BVN"
                       class="w-full bg-white pl-11 p-4 rounded-2xl border border-slate-200 text-sm outline-none focus:ring-4 focus:ring-orange-500/10 focus:border-orange-500 transition-all" />
              </div>
              <p v-if="flwBvn && !flwBvnValid" class="text-rose-600 text-[10px] font-bold">Please enter a valid 11-digit BVN.</p>
              <p class="text-[10px] text-slate-400 leading-tight">BVN is required by Flutterwave to create your dedicated account.</p>
            </div>

            <button @click="$emit('assignFlw')" :disabled="assigningFlw || !flwBvnValid"
                    class="w-full bg-orange-600 text-white font-bold py-4 rounded-2xl shadow-lg shadow-orange-200 active:scale-[0.98] transition-all disabled:opacity-50 disabled:shadow-none flex items-center justify-center gap-2">
              <span v-if="!assigningFlw">Generate Alternative Account</span>
              <span v-else>Creating Account...</span>
              <svg v-if="!assigningFlw" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Monnify Virtual Account Info -->
    <div v-if="appStatusStore.paymentGateways['monnify']" class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
      <div class="flex justify-between items-center mb-4">
        <h3 class="font-bold text-slate-800">Bank Transfer Account (Alt 2)</h3>
      </div>
      <div class="mb-4 bg-rose-50 border border-rose-100 p-3 rounded-2xl">
        <p class="text-xs text-rose-600 font-bold text-center italic">Note: A maintenance charge of {{ wallet?.maintenance_charge_config?.percentage || 1 }}% (max ₦{{ wallet?.maintenance_charge_config?.max_amount || 500 }}) applies.</p>
      </div>
      <div v-if="wallet.monnify_virtual_account?.account_number" class="space-y-4">
        <div class="relative overflow-hidden bg-gradient-to-br from-sky-500 to-sky-700 p-6 rounded-3xl text-white shadow-lg shadow-sky-200">
           <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white opacity-10 rounded-full blur-2xl"></div>
           <div class="absolute bottom-0 left-0 -mb-8 -ml-8 w-32 h-32 bg-sky-300 opacity-20 rounded-full blur-3xl"></div>
           <div class="relative">
              <div class="flex justify-between items-start mb-6">
                <div>
                  <p class="text-sky-100 text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80">Bank Name</p>
                  <p class="font-black text-lg leading-none">{{ wallet.monnify_virtual_account.bank_name }}</p>
                </div>
                <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm">
                   <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                   </svg>
                </div>
              </div>
              <div class="mb-6">
                <p class="text-sky-100 text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80">Account Number</p>
                <div class="flex items-center gap-3">
                   <p class="font-black text-2xl tracking-[0.2em]">{{ wallet.monnify_virtual_account.account_number }}</p>
                   <button @click="$emit('copy', wallet.monnify_virtual_account.account_number)" class="hover:scale-110 transition-transform active:scale-95">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-sky-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                     </svg>
                   </button>
                </div>
              </div>
              <div class="flex justify-between items-end">
                <div>
                  <p class="text-sky-100 text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80">Account Name</p>
                  <p class="font-bold text-sm">{{ wallet.monnify_virtual_account.account_name }}</p>
                </div>
                <div class="text-[8px] font-black uppercase tracking-tighter bg-white/10 px-2 py-1 rounded backdrop-blur-md border border-white/10">
                  Monnify Account
                </div>
              </div>
           </div>
        </div>
        <p class="text-[11px] text-slate-500 text-center px-4 leading-relaxed">Monnify account — transfer funds here to top up your wallet.</p>
      </div>
      <div v-else class="space-y-4">
        <div class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100">
          <p class="text-sm text-slate-600 leading-relaxed mb-4">Generate a Monnify virtual account to fund via bank transfer.</p>
          <button @click="$emit('assignMonnify')" :disabled="assigningMonnify"
                  class="w-full bg-sky-600 text-white font-bold py-4 rounded-2xl shadow-lg shadow-sky-200 active:scale-[0.98] transition-all disabled:opacity-50 disabled:shadow-none flex items-center justify-center gap-2">
            <span v-if="!assigningMonnify">Generate Monnify Account</span>
            <span v-else>Creating Account...</span>
            <svg v-if="!assigningMonnify" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Opay Virtual Account Info -->
    <div v-if="appStatusStore.paymentGateways['opay']" class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100">
      <div class="flex justify-between items-center mb-4">
        <h3 class="font-bold text-slate-800">Bank Transfer Account (Alt 3)</h3>
      </div>
      <div class="mb-4 bg-rose-50 border border-rose-100 p-3 rounded-2xl">
        <p class="text-xs text-rose-600 font-bold text-center italic">Note: A maintenance charge of {{ wallet?.maintenance_charge_config?.percentage || 1 }}% (max ₦{{ wallet?.maintenance_charge_config?.max_amount || 500 }}) applies.</p>
      </div>
      <div v-if="wallet.opay_virtual_account?.account_number" class="space-y-4">
        <div class="relative overflow-hidden bg-gradient-to-br from-teal-500 to-teal-700 p-6 rounded-3xl text-white shadow-lg shadow-teal-200">
           <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white opacity-10 rounded-full blur-2xl"></div>
           <div class="absolute bottom-0 left-0 -mb-8 -ml-8 w-32 h-32 bg-teal-300 opacity-20 rounded-full blur-3xl"></div>
           <div class="relative">
              <div class="flex justify-between items-start mb-6">
                <div>
                  <p class="text-teal-100 text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80">Bank Name</p>
                  <p class="font-black text-lg leading-none">{{ wallet.opay_virtual_account.bank_name }}</p>
                </div>
                <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm">
                   <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                   </svg>
                </div>
              </div>
              <div class="mb-6">
                <p class="text-teal-100 text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80">Account Number</p>
                <div class="flex items-center gap-3">
                   <p class="font-black text-2xl tracking-[0.2em]">{{ wallet.opay_virtual_account.account_number }}</p>
                   <button @click="$emit('copy', wallet.opay_virtual_account.account_number)" class="hover:scale-110 transition-transform active:scale-95">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-teal-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                     </svg>
                   </button>
                </div>
              </div>
              <div class="flex justify-between items-end">
                <div>
                  <p class="text-teal-100 text-[10px] font-bold uppercase tracking-widest mb-1 opacity-80">Account Name</p>
                  <p class="font-bold text-sm">{{ wallet.opay_virtual_account.account_name }}</p>
                </div>
                <div class="text-[8px] font-black uppercase tracking-tighter bg-white/10 px-2 py-1 rounded backdrop-blur-md border border-white/10">
                  Opay Account
                </div>
              </div>
           </div>
        </div>
        <p class="text-[11px] text-slate-500 text-center px-4 leading-relaxed">Opay account — transfer funds here to top up your wallet.</p>
      </div>
      <div v-else class="space-y-4">
        <div class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100">
          <p class="text-sm text-slate-600 leading-relaxed mb-4">Generate an Opay virtual account to fund via bank transfer.</p>
          <button @click="$emit('assignOpay')" :disabled="assigningOpay"
                  class="w-full bg-teal-600 text-white font-bold py-4 rounded-2xl shadow-lg shadow-teal-200 active:scale-[0.98] transition-all disabled:opacity-50 disabled:shadow-none flex items-center justify-center gap-2">
            <span v-if="!assigningOpay">Generate Opay Account</span>
            <span v-else>Creating Account...</span>
            <svg v-if="!assigningOpay" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Adding Admin Charge notice if applicable -->
    <div v-if="wallet.admin_charge_balance > 0" class="bg-rose-50 p-6 rounded-[2rem] border border-rose-100 shadow-sm">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-rose-100 rounded-2xl flex items-center justify-center text-2xl shrink-0">
          ⚠️
        </div>
        <div>
          <h4 class="font-bold text-rose-800">Outstanding Charges</h4>
          <p class="text-xs text-rose-600 mt-1">You have ₦{{ formatMoney(wallet.admin_charge_balance) }} in unpaid administrative charges.</p>
        </div>
      </div>
      <button @click="$emit('payAdminCharge')" :disabled="payingAdminCharge"
              class="w-full mt-4 bg-rose-600 text-white font-bold py-3 rounded-xl active:scale-95 transition-all disabled:opacity-50">
        {{ payingAdminCharge ? 'Processing...' : 'Pay Now' }}
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  wallet: Object,
  appStatusStore: Object,
  bvn: String,
  bvnValid: Boolean,
  assigning: Boolean,
  flwBvn: String,
  flwBvnValid: Boolean,
  assigningFlw: Boolean,
  assigningMonnify: Boolean,
  assigningOpay: Boolean,
  payingAdminCharge: Boolean
})

defineEmits(['copy', 'assign', 'assignFlw', 'assignMonnify', 'assignOpay', 'payAdminCharge', 'update:bvn', 'update:flwBvn'])

const formatMoney = (val) => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
</script>
