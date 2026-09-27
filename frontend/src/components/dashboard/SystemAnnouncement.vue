<template>
  <div v-if="message" 
       class="mt-4 bg-emerald-600 text-white px-4 py-3 rounded-2xl text-center text-xs font-bold flex items-center justify-center gap-3 shadow-md animate-in fade-in slide-in-from-top duration-500 mb-6">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 shrink-0">
      <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.007.51.011.77.011h3.39c.8 0 1.545-.466 1.89-1.159L18.42 12l-1.12-2.25c-.345-.693-1.09-1.159-1.89-1.159h-3.39c-.26 0-.517.004-.77.011m0 9.18c.612.016 1.221.031 1.83.042m-1.83-9.222c.61-.011 1.218-.026 1.83-.042m-1.83 9.222v-9.18m1.83 9.138A17.944 17.944 0 0 1 12 18c-1.353 0-2.65-.148-3.903-.432m10.343-9.43A17.944 17.944 0 0 0 12 6c-1.353 0-2.65.148-3.903.432" />
    </svg>
    <p class="leading-tight">{{ message }}</p>
  </div>
</template>

<script setup>
defineProps({
  message: String
})
</script>
