<template>
  <div v-if="isOpen && request" class="fixed inset-0 bg-slate-900/80 backdrop-blur-md flex items-center justify-center z-[110] p-4 sm:p-6">
    <div class="bg-white rounded-3xl sm:rounded-[2.5rem] shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto animate-in zoom-in duration-300 border border-slate-100">
      <div class="p-6 sm:p-8">
         <div class="w-16 h-16 sm:w-20 sm:h-20 bg-emerald-50 rounded-2xl sm:rounded-3xl flex items-center justify-center text-3xl sm:text-4xl mx-auto mb-4 sm:mb-6 shadow-sm border border-emerald-100">🤝</div>
         
         <h3 class="text-xl sm:text-2xl font-black text-slate-800 text-center mb-2 uppercase tracking-tight">Guarantor Request</h3>
         <p class="text-slate-500 text-center text-[10px] sm:text-xs mb-6 sm:mb-8 leading-relaxed font-medium">
           <strong>{{ request.member_name }}</strong> has requested you to be their guarantor for Cooperative registration.
         </p>

         <div class="bg-slate-50 p-4 sm:p-6 rounded-2xl border border-slate-100 mb-6 sm:mb-8">
           <h4 class="text-[9px] sm:text-[10px] font-black text-emerald-800 uppercase tracking-widest mb-2 sm:mb-3">Islamic Testimony</h4>
           <p class="text-[11px] sm:text-xs text-slate-600 italic leading-relaxed">
             "{{ request.testimony }}"
           </p>
         </div>

         <div v-if="!processing" class="mb-6 sm:mb-8">
           <SignaturePad 
             v-model="signature" 
             label="Your Signature" 
             hint="Please sign above to testify"
           />
         </div>
         
         <div class="flex flex-col gap-3">
           <button 
             @click="$emit('action', { action: 'accept', signature })" 
             :disabled="processing || !signature"
             class="w-full bg-emerald-600 text-white font-black py-4 sm:py-5 rounded-2xl shadow-xl shadow-emerald-100 flex items-center justify-center gap-3 uppercase tracking-[0.2em] text-[9px] sm:text-[10px] active:scale-95 transition-all disabled:opacity-50"
           >
             <span v-if="processing" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
             <span>Accept & Testify</span>
           </button>
           
           <button 
             @click="$emit('action', { action: 'decline' })" 
             :disabled="processing"
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
</template>

<script setup>
import { ref, watch } from 'vue'
import SignaturePad from '../SignaturePad.vue'

const props = defineProps({
  isOpen: Boolean,
  request: Object,
  processing: Boolean
})

const emit = defineEmits(['action'])

const signature = ref('')

watch(() => props.isOpen, (newVal) => {
  if (!newVal) signature.value = ''
})
</script>
