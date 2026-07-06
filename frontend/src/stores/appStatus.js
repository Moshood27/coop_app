import { defineStore } from 'pinia'

export const useAppStatusStore = defineStore('appStatus', {
  state: () => ({
    maintenanceMode: false,
    maintenanceMessage: '',
    maintenanceUntil: '',
    systemAnnouncement: '',
    isOutdated: false,
    isUpdateAvailable: false,
    currentVersion: '',
    playStoreUrl: '',
    transactionPinEnabled: true,
    appPinLoginEnabled: false,
    setTransactionPinEnabled: true,
    attendancePinEnabled: true,
    attendanceQrEnabled: true,
    attendanceApologyEnabled: true,
    isPinVerified: false,
    paymentGateways: {
      paystack: true,
      flutterwave: true,
      monnify: true,
      opay: true,
      primary: 'paystack'
    },
    features: {}
  }),
  actions: {
    setFeatures(features) {
      this.features = features || {}
    },
    setStatus(status) {
      this.maintenanceMode = status.maintenanceMode
      this.maintenanceMessage = status.maintenanceMessage
      this.maintenanceUntil = status.maintenanceUntil
      this.systemAnnouncement = status.systemAnnouncement
      this.isOutdated = status.isOutdated
      this.isUpdateAvailable = status.isUpdateAvailable
      this.currentVersion = status.currentVersion
      this.playStoreUrl = status.playStoreUrl
      this.transactionPinEnabled = status.transaction_pin_enabled ?? true
      this.appPinLoginEnabled = status.app_pin_login_enabled ?? false
      this.setTransactionPinEnabled = status.set_transaction_pin_enabled ?? true
      this.attendancePinEnabled = status.attendance_pin_enabled ?? true
      this.attendanceQrEnabled = status.attendance_qr_enabled ?? true
      this.attendanceApologyEnabled = status.attendance_apology_enabled ?? true
      if (status.paymentGateways) {
        this.paymentGateways = status.paymentGateways
      }
    }
  }
})
