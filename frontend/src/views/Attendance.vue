<template>
  <div class="min-h-screen bg-slate-50">
    <AppHeader title="Attendance" :showBack="true" />

    <div class="p-4 pb-32">
      <div v-if="store.loading" class="flex flex-col items-center justify-center py-20">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-600"></div>
        <p class="mt-4 text-slate-500 font-medium">Checking for meetings...</p>
      </div>

      <div v-else-if="!store.meeting" class="bg-white p-10 rounded-3xl shadow-sm border border-slate-100 text-center">
        <div class="text-5xl mb-4">🗓️</div>
        <h2 class="text-xl font-bold text-slate-800">No active or upcoming meeting</h2>
        <p class="text-slate-500 mt-2 text-sm">There is no meeting currently active or scheduled for your branch.</p>
        <button @click="store.fetchCurrentMeeting" class="mt-8 w-full bg-emerald-600 text-white font-black py-4 rounded-2xl shadow-lg shadow-emerald-100 uppercase tracking-widest text-xs active:scale-[0.98] transition-all">Refresh</button>
      </div>

      <div v-if="!store.loading && store.meeting">
        <MeetingStatusCard 
          :meeting="store.meeting"
          :time-remaining="timeRemaining"
          :lateness-countdown="latenessCountdown"
          :is-currently-late="store.isCurrentlyLate"
          :has-record="!!store.record"
          :fine-amount="store.fineAmount"
        />

        <AttendanceResultAlert :record="store.record" />

        <div v-if="!store.record" class="space-y-4">
          <MarkAttendanceForm 
            :meeting="store.meeting"
            :app-status="appStatusStore"
            :location="location"
            :locating="locating"
            :submitting="submitting"
            :has-biometrics="hasBiometrics"
            :scanning-beacon="scanningBeacon"
            @scan-qr="scanQr"
            @mark-biometric="markWithBiometrics"
            @mark-beacon="markWithBeacon"
            @submit-pin="handlePinSubmit"
          />

          <ApologyForm 
            v-if="appStatusStore.attendanceApologyEnabled && !store.inGracePeriod && (!store.record || store.record.status === 'absent')"
            :meeting-id="store.meeting.id"
            @success="handleApologySuccess"
          />

          <div v-if="store.inGracePeriod && !store.record" class="bg-emerald-50 p-6 rounded-3xl border border-emerald-100 flex items-start gap-4">
            <div class="text-2xl">🍼</div>
            <div>
              <h4 class="text-xs font-black text-emerald-800 uppercase tracking-tight">Automatic Grace Period</h4>
              <p class="text-[10px] text-emerald-600 font-medium mt-1">You are currently in the nursing mother grace period. You will not be charged for absence or lateness in this meeting.</p>
            </div>
          </div>
        </div>

        <AdminAttendanceControls 
          v-if="canMarkForOthers"
          :meeting="store.meeting"
          :stats="store.meetingStats"
          :results="memberSearchResults"
          :loading="searchingMembers"
          :marking="markingForMember"
          :can-mark-for-others="canMarkForOthers"
          :app-status="appStatusStore"
          @search="searchMembers"
          @mark="markForMember"
          @update:quickMark="val => quickMark = val"
        />
      </div>

      <AttendanceHistory 
        v-if="!store.loading"
        :history="store.history"
        :loading="store.loadingHistory"
        :can-mark-for-others="canMarkForOthers"
        @view-report="openMeetingReport"
      />
    </div>

    <AppBottomNav />
    
    <WebQrScanner 
      v-if="showWebScanner" 
      @scan="handleScan" 
      @close="showWebScanner = false"
      @error="(e) => modal.alert(e?.message || 'Camera error')" 
    />

    <MeetingReportModal 
      :show="showReportModal"
      :loading="loadingReport"
      :data="meetingReportData"
      @close="showReportModal = false"
    />
  </div>
</template>

<script setup>
import {ref, onMounted, onUnmounted, computed, watch} from 'vue'
import { Geolocation } from '@capacitor/geolocation'
import { Capacitor } from '@capacitor/core'
import { BarcodeScanner } from '@capacitor-mlkit/barcode-scanning'
import AppHeader from '../components/AppHeader.vue'
import AppBottomNav from '../components/AppBottomNav.vue'
import WebQrScanner from '../components/WebQrScanner.vue'
import axios from '../http'
import { parseOptions, publicKeyCredentialToJSON } from '../utils/webauthn'
import { getBiometricAvailability } from '../services/biometric'
import BeaconService from '../services/BeaconService.js'
import { useAppStatusStore } from '../stores/appStatus'
import { useAttendanceStore } from '../stores/attendance'
import { useRouter } from 'vue-router'
import { useModal } from '../composables/useModal'

// Components
import MeetingStatusCard from '../components/attendance/MeetingStatusCard.vue'
import AttendanceResultAlert from '../components/attendance/AttendanceResultAlert.vue'
import MarkAttendanceForm from '../components/attendance/MarkAttendanceForm.vue'
import ApologyForm from '../components/attendance/ApologyForm.vue'
import AttendanceHistory from '../components/attendance/AttendanceHistory.vue'
import AdminAttendanceControls from '../components/attendance/AdminAttendanceControls.vue'
import MeetingReportModal from '../components/attendance/MeetingReportModal.vue'

const router = useRouter()
const modal = useModal()
const appStatusStore = useAppStatusStore()
const store = useAttendanceStore()

const location = ref(null)
const locating = ref(false)
const submitting = ref(false)
const timeRemaining = ref('')
const countdownInterval = ref(null)
const latenessCountdown = ref('')
const showWebScanner = ref(false)
const hasBiometrics = ref(false)
const scanningBeacon = ref(false)

const memberSearchResults = ref([])
const searchingMembers = ref(false)
const markingForMember = ref(null)
const currentUser = ref(null)
const quickMark = ref(localStorage.getItem('attendance_quick_mark') === 'true')

const showReportModal = ref(false)
const meetingReportData = ref(null)
const loadingReport = ref(false)

const canMarkForOthers = computed(() => {
  if (!currentUser.value) return false
  return currentUser.value.permission_names?.includes('mark_attendance') || currentUser.value.is_admin
})

onMounted(async () => {
  try {
    const userRes = await axios.get('/api/user')
    currentUser.value = userRes.data
  } catch (err) {}

  await store.fetchCurrentMeeting()
  await store.fetchHistory()
  if (canMarkForOthers.value && store.meeting) {
    store.fetchMeetingStats()
  }
  hasBiometrics.value = await getBiometricAvailability()
})

onUnmounted(() => {
  if (countdownInterval.value) clearInterval(countdownInterval.value)
})

const updateCountdown = () => {
  if (!store.meeting) return
  const now = new Date()
  
  if (store.meeting.status === 'ongoing' && store.lateAt) {
    const lateTime = new Date(store.lateAt)
    const lateDiff = lateTime - now
    if (lateDiff > 0) {
      latenessCountdown.value = formatDuration(lateDiff)
    } else {
      latenessCountdown.value = ''
    }
  }

  const targetIso = store.meeting.status === 'scheduled' ? store.meeting.start_at : store.meeting.end_at
  const target = new Date(targetIso)
  const diff = target - now
  
  if (diff <= 0) {
    timeRemaining.value = '00:00:00'
    return
  }
  
  timeRemaining.value = formatDuration(diff)
}

const formatDuration = (ms) => {
  const seconds = Math.floor((ms / 1000) % 60)
  const minutes = Math.floor((ms / (1000 * 60)) % 60)
  const hours = Math.floor((ms / (1000 * 60 * 60)) % 24)
  return [hours, minutes, seconds].map(v => v < 10 ? '0' + v : v).join(':')
}

const startCountdown = () => {
  if (countdownInterval.value) clearInterval(countdownInterval.value)
  updateCountdown()
  countdownInterval.value = setInterval(updateCountdown, 1000)
}

watch(() => store.meeting, (newVal) => {
  if (newVal) startCountdown()
}, { immediate: true })

const handlePinSubmit = async (pin) => {
  await markAttendance({ pin })
}

const handleApologySuccess = (newRecord) => {
  store.updateRecord(newRecord)
  store.fetchHistory()
}

const markAttendance = async (data) => {
  submitting.value = true
  try {
    const res = await axios.post(`/api/meetings/${store.meeting.id}/mark`, {
      ...data,
      lat: location.value?.lat,
      lng: location.value?.lng
    })
    modal.alert(res.data.message)
    store.updateRecord(res.data.record)
    store.fetchHistory()
  } catch (err) {
    modal.alert(err.response?.data?.message || 'Failed to mark attendance')
  } finally {
    submitting.value = false
  }
}

const scanQr = async () => {
  if (Capacitor.isNativePlatform()) {
    try {
      const result = await BarcodeScanner.scan()
      if (result.barcode) handleScan(result.barcode.displayValue)
    } catch (err) {}
  } else {
    showWebScanner.value = true
  }
}

const handleScan = async (code) => {
  showWebScanner.value = false
  if (code.startsWith('attaqwa:attendance?')) {
    const params = new URLSearchParams(code.split('?')[1])
    await markAttendance({
      token: params.get('token'),
      meeting_id: params.get('meeting_id')
    })
  } else {
    modal.alert("Invalid QR Code")
  }
}

const markWithBiometrics = async () => {
  try {
    const res = await axios.post('/api/biometric/login-options')
    const options = parseOptions(res.data)
    const credential = await navigator.credentials.get(options)
    const attestation = publicKeyCredentialToJSON(credential)
    await markAttendance({ biometric: attestation })
  } catch (err) {
    modal.alert("Biometric verification failed")
  }
}

const markWithBeacon = async () => {
  scanningBeacon.value = true
  try {
    const found = await BeaconService.scanForMeeting(store.meeting)
    if (found) {
      await markAttendance({ beacon_verified: true })
    } else {
      modal.alert("Meeting beacon not found. Please ensure you are in the meeting room.")
    }
  } catch (err) {
    modal.alert("Beacon scanning failed")
  } finally {
    scanningBeacon.value = false
  }
}

const searchMembers = async (query) => {
  if (!query) {
    memberSearchResults.value = []
    return
  }
  searchingMembers.value = true
  try {
    const res = await axios.get('/api/attendance/search-members', { params: { q: query, meeting_id: store.meeting.id } })
    memberSearchResults.value = res.data
  } catch (err) {
  } finally {
    searchingMembers.value = false
  }
}

const markForMember = async (member) => {
  markingForMember.value = member.id
  try {
    const res = await axios.post(`/api/meetings/${store.meeting.id}/mark-for-member`, { user_id: member.id })
    member.is_present = true
    store.fetchMeetingStats()
  } catch (err) {
    modal.alert(err.response?.data?.message || "Failed to mark member")
  } finally {
    markingForMember.value = null
  }
}

const openMeetingReport = async (meeting) => {
  showReportModal.value = true
  loadingReport.value = true
  try {
    const res = await axios.get(`/api/meetings/${meeting.id}/report`)
    meetingReportData.value = res.data
  } catch (err) {
    modal.alert("Failed to load report")
  } finally {
    loadingReport.value = false
  }
}

const getLocation = async () => {
  locating.value = true
  try {
    const position = await Geolocation.getCurrentPosition({ enableHighAccuracy: true })
    location.value = { lat: position.coords.latitude, lng: position.coords.longitude }
  } catch (error) {
    modal.alert("Location permission is required to verify your presence.")
  } finally {
    locating.value = false
  }
}

watch(() => store.meeting, (m) => {
  if (m && m.status === 'ongoing' && (!store.record || store.record.status !== 'present')) {
    getLocation()
  }
})
</script>