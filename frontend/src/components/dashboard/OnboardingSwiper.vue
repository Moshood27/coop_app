<template>
  <div v-if="visible && slides.length > 0" class="mt-4 relative group">
    <Swiper
        :modules="[Pagination, Autoplay]"
        :pagination="{ clickable: true }"
        :autoplay="{ delay: 5000, disableOnInteraction: false }"
        class="rounded-[2.5rem] overflow-hidden shadow-sm border border-slate-100 bg-white"
    >
      <SwiperSlide v-for="(s, i) in slides" :key="i">
        <div class="p-6 flex items-center gap-4">
          <div class="w-14 h-14 flex-shrink-0 flex items-center justify-center bg-emerald-50 rounded-2xl" v-html="s.icon"></div>
          <div class="flex-1 pr-4">
            <h3 class="font-bold text-slate-800 text-sm">{{ s.title }}</h3>
            <p class="text-[10px] text-slate-500 leading-tight mt-0.5">{{ s.description || s.desc }}</p>
          </div>
        </div>
      </SwiperSlide>
    </Swiper>
    <button @click="$emit('dismiss')" class="absolute top-3 right-3 z-10 p-1 bg-slate-50 hover:bg-slate-100 rounded-full text-slate-400 transition-colors shadow-sm">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>

<script setup>
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Pagination, Autoplay } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/pagination'

defineProps({
  visible: Boolean,
  slides: {
    type: Array,
    default: () => []
  }
})

defineEmits(['dismiss'])
</script>
