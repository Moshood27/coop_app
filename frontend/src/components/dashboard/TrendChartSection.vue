<template>
  <FinCard class="mt-6" :padded="true" :elevated="true">
    <template #title>
      Activity Trend
    </template>
    <TrendChart :series="series" :categories="categories" :currency="currency" />
  </FinCard>
</template>

<script setup>
import FinCard from '../FinCard.vue'
import TrendChart from '../TrendChart.vue'

defineProps({
  series: Array,
  categories: Array,
  currency: {
    type: String,
    default: '₦'
  }
})
</script>
