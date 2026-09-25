<template>
  <div class="space-y-6">
    <!-- Merchant Pay (QR) Tab Content -->
    <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 text-center relative overflow-hidden">
      <div class="absolute right-0 top-0 w-32 h-32 bg-emerald-50 rounded-full -mr-16 -mt-16 opacity-50"></div>
      <div class="relative z-10 space-y-6">
        <div class="w-20 h-20 bg-emerald-100 rounded-[2.5rem] flex items-center justify-center mx-auto text-4xl shadow-inner">
          📱
        </div>
        <div>
          <h3 class="text-xl font-black text-slate-800">Merchant Pay (QR)</h3>
          <p class="text-sm text-slate-500 mt-2 leading-relaxed px-4">Scan to pay at any Attaqwa Merchant or generate your own QR to receive payments instantly.</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <button v-if="features['merchant-pay-enabled']" @click="$router.push('/merchant/pay')" class="bg-emerald-700 text-white p-4 rounded-2xl font-bold shadow-lg shadow-emerald-700/20 active:scale-95 transition-all flex flex-col items-center gap-2">
            <span class="text-xl">🔍</span>
            <span class="text-xs uppercase tracking-widest">Scan & Pay</span>
          </button>
          <button v-if="features['receive-qr-enabled']" @click="$router.push('/merchant/receive')" class="bg-white text-emerald-700 border-2 border-emerald-100 p-4 rounded-2xl font-bold active:scale-95 transition-all flex flex-col items-center gap-2">
            <span class="text-xl">📥</span>
            <span class="text-xs uppercase tracking-widest">Receive</span>
          </button>
        </div>
      </div>
    </div>
    
    <div class="bg-emerald-900 p-6 rounded-[2rem] text-white shadow-xl relative overflow-hidden">
       <div class="absolute left-0 bottom-0 w-24 h-24 bg-white/5 rounded-full -ml-12 -mb-12"></div>
       <h4 class="font-bold text-emerald-200 text-[10px] uppercase tracking-widest mb-2">How it works</h4>
       <ul class="space-y-3">
         <li class="flex gap-3 text-xs">
           <span class="font-black text-emerald-400">01.</span>
           <span class="opacity-90">Open "Scan & Pay" to scan a merchant's Attaqwa QR code.</span>
         </li>
         <li class="flex gap-3 text-xs">
           <span class="font-black text-emerald-400">02.</span>
           <span class="opacity-90">Confirm the amount and merchant details.</span>
         </li>
         <li class="flex gap-3 text-xs">
           <span class="font-black text-emerald-400">03.</span>
           <span class="opacity-90">Funds are transferred instantly from your wallet.</span>
         </li>
       </ul>
    </div>
  </div>
</template>

<script setup>
defineProps({
  features: Object
})
</script>
