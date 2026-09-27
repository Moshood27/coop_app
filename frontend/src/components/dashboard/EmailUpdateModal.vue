<template>
  <div v-if="isOpen" class="fixed inset-0 bg-slate-900/80 backdrop-blur-md flex items-center justify-center z-[101] p-4 sm:p-6">
    <div class="bg-white rounded-3xl sm:rounded-[2.5rem] shadow-2xl w-full max-w-sm max-h-[90vh] overflow-y-auto animate-in zoom-in duration-300 border border-slate-100">
      <div class="p-6 sm:p-8">
         <div class="w-16 h-16 sm:w-20 sm:h-20 bg-emerald-50 rounded-2xl sm:rounded-3xl flex items-center justify-center text-3xl sm:text-4xl mx-auto mb-4 sm:mb-6 shadow-sm border border-emerald-100">📧</div>
         
         <h3 class="text-xl sm:text-2xl font-black text-slate-800 text-center mb-2 uppercase tracking-tight">Update Email</h3>
         <p class="text-slate-500 text-center text-[10px] sm:text-xs mb-6 sm:mb-8 leading-relaxed font-medium">Your current email address is invalid. Please provide a valid email to receive notifications and secure your account.</p>
         
         <div class="space-y-4">
           <div>
             <label class="block text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 sm:mb-2 ml-1">New Email Address</label>
             <input 
               v-model="form.email" 
               type="email" 
               placeholder="yourname@example.com"
               class="w-full p-3.5 sm:p-4 rounded-2xl bg-slate-50 border-2 border-slate-100 focus:border-emerald-500 focus:bg-white outline-none transition-all font-bold text-slate-700 text-sm sm:text-base"
             />
             <p v-if="errors.email" class="text-[9px] sm:text-[10px] text-rose-500 mt-1 ml-1 font-bold">{{ errors.email[0] }}</p>
           </div>

           <div>
             <label class="block text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5 sm:mb-2 ml-1">Confirm Password</label>
             <input 
               v-model="form.password" 
               type="password" 
               placeholder="••••••••"
               class="w-full p-3.5 sm:p-4 rounded-2xl bg-slate-50 border-2 border-slate-100 focus:border-emerald-500 focus:bg-white outline-none transition-all font-bold text-slate-700 text-sm sm:text-base"
             />
             <p v-if="errors.password" class="text-[9px] sm:text-[10px] text-rose-500 mt-1 ml-1 font-bold">{{ errors.password[0] }}</p>
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
          <span v-else>Update Email Address</span>
        </button>
        
        <p class="text-[8px] sm:text-[9px] text-slate-400 text-center mt-3 sm:mt-4 font-bold uppercase tracking-widest opacity-60">This is required to proceed to your dashboard</p>
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

const form = ref({ email: '', password: '' })
</script>
