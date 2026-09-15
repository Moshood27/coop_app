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
    attendanceBleBeaconEnabled: true,
    attendanceFingerprintEnabled: true,
    markAdminAttendanceEnabled: false,
    adminAttendanceQrEnabled: true,
    adminAttendanceVoiceSearchEnabled: true,
    adminAttendanceIntelligentFilteringEnabled: true,
    adminAttendanceAlphabeticalScrollerEnabled: true,
    adminAttendanceRecentListEnabled: true,
    adminAttendanceSmartSearchEnabled: true,
    openingBalanceVerificationEnabled: true,
    onboardingSwiperEnabled: true,
    onboardingSwiperSlides: [],
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
      this.attendanceBleBeaconEnabled = status.attendance_ble_beacon_enabled ?? true
      this.attendanceFingerprintEnabled = status.attendance_fingerprint_enabled ?? true
      this.markAdminAttendanceEnabled = status.mark_admin_attendance_enabled ?? false
      this.adminAttendanceQrEnabled = status.admin_attendance_qr_enabled ?? true
      this.adminAttendanceVoiceSearchEnabled = status.admin_attendance_voice_search_enabled ?? true
      this.adminAttendanceIntelligentFilteringEnabled = status.admin_attendance_intelligent_filtering_enabled ?? true
      this.adminAttendanceAlphabeticalScrollerEnabled = status.admin_attendance_alphabetical_scroller_enabled ?? true
      this.adminAttendanceRecentListEnabled = status.admin_attendance_recent_list_enabled ?? true
      this.adminAttendanceSmartSearchEnabled = status.admin_attendance_smart_search_enabled ?? true
      this.openingBalanceVerificationEnabled = status.opening_balance_verification_enabled ?? true
      this.onboardingSwiperEnabled = status.onboarding_swiper_enabled ?? true
      this.onboardingSwiperSlides = status.onboarding_swiper_slides ?? []
      if (status.paymentGateways) {
        this.paymentGateways = status.paymentGateways
      }
    }
  }
})
