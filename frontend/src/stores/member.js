import { defineStore } from 'pinia'
import axios from 'axios'

export const useMemberStore = defineStore('member', {
  state: () => ({
    profile: null,
    loading: false,
    error: null,
    lastFetched: null
  }),
  actions: {
    async fetchProfile(force = false) {
      if (!force && this.profile && this.lastFetched && (Date.now() - this.lastFetched < 60000)) {
        return this.profile
      }
      
      this.loading = true
      try {
        const { data } = await axios.get('/api/profile')
        this.profile = data
        this.lastFetched = Date.now()
        return data
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to fetch profile'
        throw err
      } finally {
        this.loading = false
      }
    },
    async updateProfile(payload) {
      this.loading = true
      try {
        const { data } = await axios.post('/api/profile', payload)
        this.profile = { ...this.profile, ...data.user }
        return data
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to update profile'
        throw err
      } finally {
        this.loading = false
      }
    },
    clearProfile() {
      this.profile = null
      this.lastFetched = null
    }
  }
})
