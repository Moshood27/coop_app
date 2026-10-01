<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/80 backdrop-blur-md flex items-center justify-center z-[100] p-4 sm:p-6">
    <div class="bg-white rounded-3xl sm:rounded-[2.5rem] shadow-2xl w-full max-w-sm max-h-[90vh] overflow-y-auto animate-in zoom-in duration-300 border border-slate-100">
      <div class="p-6 sm:p-8">
         <div class="w-16 h-16 sm:w-20 sm:h-20 bg-emerald-50 rounded-2xl sm:rounded-3xl flex items-center justify-center mx-auto mb-4 sm:mb-6 shadow-sm border border-emerald-100">
           <span class="i-mdi-account w-10 h-10 text-emerald-600"></span>
         </div>
         
         <h3 class="text-xl sm:text-2xl font-black text-slate-800 text-center mb-2 uppercase tracking-tight">Update Gender</h3>
         <p class="text-slate-500 text-center text-[10px] sm:text-xs mb-6 sm:mb-8 leading-relaxed font-medium">To provide you with tailored services and accurate records, please select your gender.</p>
         
         <div class="space-y-3">
           <button 
             @click="selectedGender = 'male'"
             :class="selectedGender === 'male' ? 'bg-emerald-600 text-white border-emerald-600 scale-[1.02] shadow-lg shadow-emerald-100' : 'bg-slate-50 text-slate-600 border-slate-100 hover:bg-slate-100'"
             class="w-full p-4 sm:p-5 rounded-2xl border-2 font-black uppercase tracking-widest text-[10px] sm:text-xs transition-all flex items-center justify-between"
           >
             <span>Male</span>
             <span v-if="selectedGender === 'male'" class="i-mdi-check w-5 h-5"></span>
           </button>
           
           <button 
             @click="selectedGender = 'female'"
             :class="selectedGender === 'female' ? 'bg-emerald-600 text-white border-emerald-600 scale-[1.02] shadow-lg shadow-emerald-100' : 'bg-slate-50 text-slate-600 border-slate-100 hover:bg-slate-100'"
             class="w-full p-4 sm:p-5 rounded-2xl border-2 font-black uppercase tracking-widest text-[10px] sm:text-xs transition-all flex items-center justify-between"
           >
             <span>Female</span>
             <span v-if="selectedGender === 'female'" class="i-mdi-check w-5 h-5"></span>
           </button>
         </div>
      </div>
      
      <div class="p-4 sm:p-6 bg-slate-50 border-t border-slate-100">
        <button 
          @click="$emit('submit', selectedGender)" 
          :disabled="!selectedGender || processing"
          class="w-full bg-emerald-600 text-white font-black py-4 sm:py-5 rounded-2xl shadow-xl shadow-emerald-100 flex items-center justify-center gap-3 uppercase tracking-[0.2em] text-[10px] sm:text-xs active:scale-95 transition-all disabled:opacity-50"
        >
          <span v-if="processing" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
          <span>Save Changes</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  isOpen: Boolean,
  processing: Boolean
})

const emit = defineEmits(['submit'])

const selectedGender = ref('')
</script>
