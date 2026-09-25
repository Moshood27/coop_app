import { defineStore } from 'pinia'
import axios from 'axios'

export const useWalletStore = defineStore('wallet', {
  state: () => ({
    wallet: null,
    transactions: [],
    withdrawals: [],
    loading: false,
    error: null,
    lastFetched: null
  }),
  actions: {
    async fetchWallet(force = false) {
      if (!force && this.wallet && this.lastFetched && (Date.now() - this.lastFetched < 30000)) {
        return this.wallet
      }
      
      this.loading = true
      try {
        const { data } = await axios.get('/api/wallet')
        this.wallet = data
        this.lastFetched = Date.now()
        return data
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to fetch wallet'
        throw err
      } finally {
        this.loading = false
      }
    },
    async fetchTransactions(page = 1, perPage = 15) {
      this.loading = true
      try {
        const { data } = await axios.get('/api/wallet/transactions', {
          params: { page, per_page: perPage }
        })
        if (page === 1) {
          this.transactions = data.data
        } else {
          // You might want to append or just return for component to handle
          return data
        }
        return data
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to fetch transactions'
        throw err
      } finally {
        this.loading = false
      }
    },
    clearWallet() {
      this.wallet = null
      this.transactions = []
      this.withdrawals = []
      this.lastFetched = null
    }
  }
})
