<template>
  <div class="flex p-1.5 bg-slate-200/50 rounded-[1.5rem] gap-1 shadow-inner overflow-x-auto no-scrollbar">
    <button 
      v-for="tab in availableTabs" 
      :key="tab"
      @click="$emit('update:modelValue', tab)"
      :class="modelValue === tab ? 'bg-white text-emerald-700 shadow-md scale-[1.02]' : 'text-slate-500 hover:bg-white/30'"
      class="flex-1 py-3 px-4 rounded-2xl text-[10px] font-black uppercase tracking-wider transition-all duration-300 ease-out whitespace-nowrap"
    >
      {{ tab }}
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: String,
  features: Object
})

defineEmits(['update:modelValue'])

const availableTabs = computed(() => {
  return ['overview', 'fund', 'transfer', 'withdraw', 'merchant', 'transactions', 'requests'].filter(t => {
    if (t === 'withdraw') return props.features['withdrawals-enabled'];
    if (t === 'merchant') return props.features['merchant-pay-enabled'] || props.features['receive-qr-enabled'];
    return true;
  })
})
</script>
