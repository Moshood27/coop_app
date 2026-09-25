import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user')) || null,
    token: localStorage.getItem('token') || null,
    admin: JSON.parse(localStorage.getItem('admin')) || null,
    adminToken: localStorage.getItem('admin_token') || null,
    isAdmin: localStorage.getItem('is_admin') === 'true',
  }),
  getters: {
    isAuthenticated: (state) => !!state.token,
    isAdminAuthenticated: (state) => !!state.adminToken && state.isAdmin,
  },
  actions: {
    setUser(user, token) {
      this.user = user
      this.token = token
      localStorage.setItem('user', JSON.stringify(user))
      localStorage.setItem('token', token)
      if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
      }
    },
    setAdmin(admin, token) {
      this.admin = admin
      this.adminToken = token
      this.isAdmin = true
      localStorage.setItem('admin', JSON.stringify(admin))
      localStorage.setItem('admin_token', token)
      localStorage.setItem('is_admin', 'true')
      if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
      }
    },
    logout() {
      this.user = null
      this.token = null
      localStorage.removeItem('user')
      localStorage.removeItem('token')
      localStorage.removeItem('last_activity_ts')
      delete axios.defaults.headers.common['Authorization']
    },
    adminLogout() {
      this.admin = null
      this.adminToken = null
      this.isAdmin = false
      localStorage.removeItem('admin')
      localStorage.removeItem('admin_token')
      localStorage.removeItem('is_admin')
      localStorage.removeItem('last_activity_ts')
      delete axios.defaults.headers.common['Authorization']
    }
  }
})
