<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/80 backdrop-blur-md flex items-center justify-center z-[101] p-4 sm:p-6">
    <div class="bg-white rounded-3xl sm:rounded-[2.5rem] shadow-2xl w-full max-w-sm max-h-[90vh] overflow-y-auto animate-in zoom-in duration-300 border border-slate-100">
      <div class="p-6 sm:p-8">
         <div class="w-16 h-16 sm:w-20 sm:h-20 bg-amber-50 rounded-2xl sm:rounded-3xl flex items-center justify-center mx-auto mb-4 sm:mb-6 shadow-sm border border-amber-100">
           <span class="i-mdi-lock-outline w-10 h-10 text-amber-600"></span>
         </div>
         
         <h3 class="text-xl sm:text-2xl font-black text-slate-800 text-center mb-2 uppercase tracking-tight">Set Security PIN</h3>
         <p class="text-slate-500 text-center text-[10px] sm:text-xs mb-6 sm:mb-8 leading-relaxed font-medium">Please set a 4-digit transaction PIN to secure your withdrawals and transfers.</p>
         
         <div class="space-y-4">
           <div>
             <label class="block text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 sm:mb-2 ml-1">New 4-Digit PIN</label>
             <input 
               v-model="form.new_pin" 
               type="password" 
               inputmode="numeric"
               maxlength="4"
               placeholder="••••"
               class="w-full p-3.5 sm:p-4 rounded-2xl bg-slate-50 border-2 border-slate-100 focus:border-amber-500 focus:bg-white outline-none transition-all font-bold text-slate-700 text-center text-xl sm:text-2xl tracking-[0.5em]"
             />
             <p v-if="errors.new_pin" class="text-[9px] sm:text-[10px] text-rose-500 mt-1 ml-1 font-bold">{{ Array.isArray(errors.new_pin) ? errors.new_pin[0] : errors.new_pin }}</p>
           </div>

           <div>
             <label class="block text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 sm:mb-2 ml-1">Confirm PIN</label>
             <input 
               v-model="form.confirm_pin" 
               type="password" 
               inputmode="numeric"
               maxlength="4"
               placeholder="••••"
               class="w-full p-3.5 sm:p-4 rounded-2xl bg-slate-50 border-2 border-slate-100 focus:border-amber-500 focus:bg-white outline-none transition-all font-bold text-slate-700 text-center text-xl sm:text-2xl tracking-[0.5em]"
             />
             <p v-if="errors.confirm_pin" class="text-[9px] sm:text-[10px] text-rose-500 mt-1 ml-1 font-bold">{{ Array.isArray(errors.confirm_pin) ? errors.confirm_pin[0] : errors.confirm_pin }}</p>
           </div>

           <div>
             <label class="block text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 sm:mb-2 ml-1">Account Password</label>
             <input 
               v-model="form.current_password" 
               type="password" 
               placeholder="••••••••"
               class="w-full p-3.5 sm:p-4 rounded-2xl bg-slate-50 border-2 border-slate-100 focus:border-amber-500 focus:bg-white outline-none transition-all font-bold text-slate-700 text-sm sm:text-base"
             />
             <p v-if="errors.current_password" class="text-[9px] sm:text-[10px] text-rose-500 mt-1 ml-1 font-bold">{{ Array.isArray(errors.current_password) ? errors.current_password[0] : errors.current_password }}</p>
           </div>
         </div>
      </div>
      
      <div class="p-4 sm:p-6 bg-slate-50 border-t border-slate-100">
        <button 
          @click="$emit('submit', form)" 
          :disabled="saving"
          class="w-full bg-slate-800 text-white font-black py-4 sm:py-5 rounded-2xl shadow-xl shadow-slate-200 flex items-center justify-center gap-3 uppercase tracking-[0.2em] text-[9px] sm:text-[10px] disabled:opacity-50 active:scale-95 transition-all"
        >
          <span v-if="saving" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
          <span v-else>Set Transaction PIN</span>
        </button>
        
        <p class="text-[8px] sm:text-[9px] text-slate-400 text-center mt-3 sm:mt-4 font-bold uppercase tracking-widest opacity-60">This is required for account security</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  isOpen: Boolean,
  saving: Boolean,
  errors: { type: Object, default: () => ({}) }
})

const emit = defineEmits(['submit'])

const form = ref({ current_password: '', new_pin: '', confirm_pin: '' })
</script>
