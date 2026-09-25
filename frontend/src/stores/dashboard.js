import { defineStore } from 'pinia'
import axios from 'axios'

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    data: null,
    loading: false,
    error: null,
    lastFetched: null
  }),
  actions: {
    async fetchDashboard(force = false) {
      if (!force && this.data && this.lastFetched && (Date.now() - this.lastFetched < 30000)) {
        return this.data
      }
      this.loading = true
      try {
        const { data } = await axios.get('/api/dashboard')
        this.data = data
        this.lastFetched = Date.now()
        return data
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to fetch dashboard'
        throw err
      } finally {
        this.loading = false
      }
    },
    clearDashboard() {
      this.data = null
      this.lastFetched = null
    }
  }
})
