<template>
  <div class="min-h-screen bg-slate-50 pb-24">
    <AppHeader title="Attendance" :showBack="true" />

    <div class="p-4">
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-600"></div>
        <p class="mt-4 text-slate-500 font-medium">Checking for meetings...</p>
      </div>

      <div v-else-if="!meeting" class="bg-white p-10 rounded-3xl shadow-sm border border-slate-100 text-center">
        <div class="text-5xl mb-4">🗓️</div>
        <h2 class="text-xl font-bold text-slate-800">No active or upcoming meeting</h2>
        <p class="text-slate-500 mt-2 text-sm">There is no meeting currently active or scheduled for your branch.</p>
        <button @click="fetchCurrentMeeting" class="mt-8 w-full bg-emerald-600 text-white font-black py-4 rounded-2xl shadow-lg shadow-emerald-100 uppercase tracking-widest text-xs active:scale-95 transition-all">Refresh</button>
      </div>

      <div v-else>
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 mb-4 overflow-hidden relative">
           <div class="absolute -right-6 -top-6 w-20 h-20 bg-emerald-50 rounded-full opacity-50" />
          <div class="flex items-center justify-between mb-4 relative z-10">
            <span v-if="meeting.status === 'ongoing'" class="px-3 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase tracking-widest rounded-full">Ongoing</span>
            <span v-else class="px-3 py-1 bg-blue-100 text-blue-700 text-[10px] font-black uppercase tracking-widest rounded-full">Upcoming</span>
            <span class="text-slate-400 text-[10px] font-bold uppercase">{{ formatDate(meeting.date) }}</span>
          </div>
          <h2 class="text-xl font-black text-slate-800 relative z-10">{{ meeting.name }}</h2>
          <p class="text-slate-500 text-xs mt-1 relative z-10 leading-relaxed">{{ meeting.description }}</p>
          
          <div class="mt-4 flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-widest relative z-10">
            <span>🕒 {{ meeting.start_time }} - {{ meeting.end_time }}</span>
          </div>
        </div>

        <!-- Timer -->
        <div v-if="meeting.status === 'scheduled' || meeting.status === 'ongoing'" class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 mb-4 flex items-center justify-between relative overflow-hidden">
           <div class="absolute -right-4 -bottom-4 w-16 h-16 bg-slate-50 rounded-full opacity-50" />
           <div class="relative z-10">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">
              {{ meeting.status === 'scheduled' ? 'Starts In' : (latenessCountdown ? 'On Time For' : 'Ends In') }}
            </p>
            <p class="text-3xl font-black text-slate-800 tabular-nums tracking-tight">
              {{ (meeting.status === 'ongoing' && latenessCountdown) ? latenessCountdown : (timeRemaining || '--:--:--') }}
            </p>
            <div v-if="meeting.status === 'ongoing' && isCurrentlyLate && !record" class="mt-1 flex items-center gap-1">
              <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
              <span class="text-[9px] font-black text-red-600 uppercase">Late (Fine: ₦{{ formatMoney(fineAmount) }})</span>
            </div>
            <div v-else-if="meeting.status === 'ongoing' && latenessCountdown && !record" class="mt-1 flex items-center gap-1">
              <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
              <span class="text-[9px] font-black text-emerald-600 uppercase">On Time Grace</span>
            </div>
          </div>
          <div class="h-14 w-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-3xl relative z-10">⏳</div>
        </div>

        <!-- Already Marked -->
        <div v-if="record && record.status === 'present'" class="bg-emerald-600 p-8 rounded-[2.5rem] text-center shadow-xl shadow-emerald-100 text-white">
          <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center text-3xl mx-auto mb-4 backdrop-blur-md">✅</div>
          <h3 class="text-xl font-black uppercase tracking-tight">Attendance Marked</h3>
          <p class="text-emerald-50 text-xs mt-2 font-medium">You successfully marked your attendance at {{ formatTime(record.attended_at) }}.</p>
        </div>

        <!-- Excused -->
        <div v-else-if="record && (record.status === 'excused' || record.status === 'pending_excuse')" :class="[
          'p-8 rounded-[2.5rem] text-center shadow-xl text-white',
          record.status === 'excused' ? 'bg-blue-600 shadow-blue-100' : 'bg-slate-600 shadow-slate-100'
        ]">
          <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center text-3xl mx-auto mb-4 backdrop-blur-md">
            {{ record.status === 'excused' ? '🙏' : '⏳' }}
          </div>
          <h3 class="text-xl font-black uppercase tracking-tight">
            {{ record.status === 'excused' ? 'Apology Approved' : 'Apology Pending' }}
          </h3>
          <p class="text-white/80 text-xs mt-2 font-medium">
            <template v-if="record.status === 'excused'">
              Your apology for this meeting was approved on {{ formatTime(record.excused_at) }}. You will not be charged.
            </template>
            <template v-else>
              Your apology has been submitted and is awaiting admin approval. You will not be charged automatically for now.
            </template>
          </p>
        </div>


        <!-- Mark Attendance Form -->
        <div v-else class="space-y-4">
          <div v-if="meeting.status === 'ongoing'" class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <div class="flex items-center gap-2 mb-6">
               <div class="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center text-lg">🔑</div>
               <h3 class="font-black text-slate-800 text-sm uppercase tracking-tight">Verify Presence</h3>
            </div>
            
            <div class="space-y-6">
              <div class="grid grid-cols-1 gap-4">
                <div v-if="appStatusStore.attendancePinEnabled">
                  <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">{{ appStatusStore.attendanceQrEnabled ? 'Option 1: Enter Meeting PIN' : 'Enter Meeting PIN' }}</label>
                  <input v-model="pin" type="text" maxlength="10" placeholder="••••••" 
                         class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl p-5 text-center text-3xl font-black tracking-[0.4em] focus:bg-white focus:border-emerald-500 focus:ring-0 transition-all placeholder:tracking-normal placeholder:text-slate-200" />
                  <p class="text-[9px] text-slate-400 mt-2 text-center font-bold uppercase">The PIN is announced by the Imam or Chairman</p>
                </div>

                <div v-if="appStatusStore.attendancePinEnabled && appStatusStore.attendanceQrEnabled" class="relative py-2 flex items-center">
                  <div class="flex-grow border-t border-slate-200"></div>
                  <span class="flex-shrink mx-4 text-[10px] font-black text-slate-400 uppercase">OR</span>
                  <div class="flex-grow border-t border-slate-200"></div>
                </div>

                <div v-if="appStatusStore.attendanceQrEnabled">
                  <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">{{ appStatusStore.attendancePinEnabled ? 'Option 2: Scan Admin QR Code' : 'Scan Admin QR Code' }}</label>
                  <button @click="scanQr" :disabled="submitting || !location"
                          class="w-full bg-white border-2 border-emerald-600 text-emerald-600 font-black py-4 rounded-2xl flex items-center justify-center gap-3 uppercase tracking-widest text-xs active:scale-[0.98] transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5zM6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" />
                    </svg>
                    Scan Attendance QR
                  </button>
                </div>
              </div>

              <div class="p-5 bg-slate-50 rounded-2xl flex items-center gap-4 border border-slate-100">
                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center text-2xl shadow-sm">📍</div>
                <div class="flex-1 min-w-0">
                  <p class="text-[11px] font-black text-slate-800 uppercase tracking-tight">GPS Location</p>
                  <p class="text-[10px] text-slate-500 font-medium">Required radius: {{ meeting.radius_meters }}m</p>
                </div>
                <div v-if="locating" class="animate-spin rounded-full h-5 w-5 border-2 border-emerald-600 border-t-transparent"></div>
                <div v-else-if="location" class="flex flex-col items-end">
                   <span class="text-emerald-600 text-[10px] font-black uppercase tracking-widest">Captured</span>
                   <button @click="getLocation" class="text-[9px] text-slate-400 font-bold underline mt-0.5 uppercase">Reset</button>
                </div>
                <button v-else @click="getLocation" class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-md shadow-emerald-100 active:scale-95 transition-all">Get</button>
              </div>

              <button @click="submitAttendance()" :disabled="submitting || (!appStatusStore.attendancePinEnabled ? !location : (!pin || !location))" 
                      class="w-full bg-emerald-600 text-white font-black py-5 rounded-2xl shadow-xl shadow-emerald-100 flex items-center justify-center gap-3 uppercase tracking-widest text-xs disabled:opacity-50 disabled:shadow-none active:scale-[0.98] transition-all mt-4">
                <span v-if="submitting" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
                <span v-else>📍 Mark Attendance</span>
              </button>

              <div v-if="(hasBiometrics && appStatusStore.attendanceFingerprintEnabled) || (meeting.beacon_uuid && appStatusStore.attendanceBleBeaconEnabled)" class="relative py-4 flex items-center">
                <div class="flex-grow border-t border-slate-200"></div>
                <span class="flex-shrink mx-4 text-[10px] font-black text-slate-400 uppercase">OR</span>
                <div class="flex-grow border-t border-slate-200"></div>
              </div>

              <!-- Biometric Option (Fintech Style) -->
              <div v-if="hasBiometrics && appStatusStore.attendanceFingerprintEnabled">
                 <button @click="markWithBiometrics" :disabled="submitting || !location"
                         class="w-full h-24 bg-emerald-700 text-white rounded-3xl shadow-xl shadow-emerald-100 flex flex-col items-center justify-center gap-2 uppercase tracking-[0.2em] text-[10px] font-black active:scale-[0.98] transition-all relative overflow-hidden group">
                    <div class="absolute inset-0 bg-white/10 translate-y-full group-active:translate-y-0 transition-transform duration-300"></div>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
                    </svg>
                    Mark with Biometrics
                 </button>
              </div>

              <!-- Beacon Option -->
              <div v-if="meeting.beacon_uuid && appStatusStore.attendanceBleBeaconEnabled">
                 <button @click="markWithBeacon" :disabled="submitting || scanningBeacon"
                         class="w-full h-20 bg-blue-700 text-white rounded-3xl shadow-xl shadow-blue-100 flex flex-col items-center justify-center gap-1 uppercase tracking-[0.2em] text-[10px] font-black active:scale-[0.98] transition-all relative overflow-hidden group">
                    <div class="absolute inset-0 bg-white/10 translate-y-full group-active:translate-y-0 transition-transform duration-300"></div>
                    <div v-if="scanningBeacon" class="animate-ping absolute top-4 right-4 w-2 h-2 bg-blue-300 rounded-full"></div>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                    </svg>
                    {{ scanningBeacon ? 'Searching for Beacon...' : 'Mark via Room Beacon' }}
                 </button>
              </div>
            </div>
          </div>

        <!-- Apology Form -->
        <div v-if="canSubmitApology && !inGracePeriod && (!record || record.status === 'absent')" class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
             <div class="flex items-center gap-2 mb-4">
               <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center text-lg">📝</div>
               <h3 class="font-black text-slate-800 text-sm uppercase tracking-tight">Submit Apology</h3>
            </div>
            <p class="text-[11px] text-slate-500 mb-4">If you cannot attend or will be late, provide a reason here before the meeting starts to avoid fines.</p>
            
            <div class="space-y-4">
              <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Excuse Type</label>
                <select v-model="excuse_type" class="w-full bg-slate-50 border-none rounded-2xl p-4 text-xs font-bold text-slate-800 focus:ring-1 focus:ring-blue-500 transition-all">
                   <option value="medical">Medical</option>
                   <option value="nursing_mother">Nursing Mother</option>
                   <option value="travel">Official Travel</option>
                   <option value="official">Official Duty</option>
                   <option value="other">Other</option>
                </select>
              </div>

              <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Reason</label>
                <textarea v-model="reason" placeholder="Explain your reason..." 
                          class="w-full bg-slate-50 border-none rounded-2xl p-4 text-xs font-medium focus:ring-1 focus:ring-blue-500 min-h-[80px]"></textarea>
              </div>

              <div v-if="excuse_type === 'medical' || excuse_type === 'nursing_mother' || excuse_type === 'travel'">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Attach Proof (Required for {{ excuse_type }})</label>
                <input type="file" @change="e => excuse_proof = e.target.files[0]" accept="image/*,application/pdf"
                       class="w-full bg-slate-50 border-2 border-dashed border-slate-200 rounded-2xl p-4 text-xs font-bold text-slate-800 focus:ring-1 focus:ring-blue-500" />
              </div>

              <button @click="submitApology" :disabled="submittingApology || !reason || ((excuse_type === 'medical' || excuse_type === 'nursing_mother' || excuse_type === 'travel') && !excuse_proof)" 
                      class="w-full bg-slate-800 text-white font-black py-4 rounded-2xl flex items-center justify-center gap-3 uppercase tracking-widest text-[10px] disabled:opacity-50 active:scale-95 transition-all">
                <span v-if="submittingApology" class="animate-spin rounded-full h-3 w-3 border-2 border-white border-t-transparent"></span>
                <span v-else>Submit Apology</span>
              </button>
            </div>
          </div>

          <!-- Grace Period Info -->
          <div v-if="inGracePeriod && !record" class="bg-emerald-50 p-6 rounded-3xl border border-emerald-100 flex items-start gap-4">
            <div class="text-2xl">🍼</div>
            <div>
              <h4 class="text-xs font-black text-emerald-800 uppercase tracking-tight">Automatic Grace Period</h4>
              <p class="text-[10px] text-emerald-600 font-medium mt-1">You are currently in the nursing mother grace period. You will not be charged for absence or lateness in this meeting.</p>
            </div>
          </div>
        </div>
        
        <!-- Mark for Member (Delegated Admin) -->
        <div v-if="canMarkForOthers && meeting && (meeting.status === 'ongoing' || meeting.status === 'scheduled')" class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 mt-4">
             <div class="flex items-center gap-2 mb-4">
               <div class="w-8 h-8 bg-amber-50 rounded-lg flex items-center justify-center text-lg">👥</div>
               <h3 class="font-black text-slate-800 text-sm uppercase tracking-tight">Mark for Member</h3>
            </div>
            <p class="text-[11px] text-slate-500 mb-4">You have privilege to mark attendance for other members who may not have their devices or are unable to mark.</p>
            
            <div class="relative">
              <input 
                v-model="memberSearchQuery" 
                @input="searchMembers"
                type="text" 
                placeholder="Search name, phone or membership #"
                class="w-full bg-slate-50 border-none rounded-2xl p-4 text-xs font-bold focus:ring-1 focus:ring-amber-500"
              />
              <div v-if="searchingMembers" class="absolute right-4 top-4">
                <div class="animate-spin rounded-full h-4 w-4 border-2 border-amber-600 border-t-transparent"></div>
              </div>
            </div>

            <div v-if="memberSearchResults.length > 0" class="mt-4 space-y-2 max-h-60 overflow-y-auto no-scrollbar">
               <div v-for="member in memberSearchResults" :key="member.id" 
                    class="p-3 bg-slate-50 rounded-2xl flex items-center justify-between gap-3 border border-slate-100">
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-black text-slate-800 truncate">{{ member.surname }} {{ member.name }}</p>
                    <p class="text-[9px] text-slate-400 font-bold uppercase">{{ member.membership_number }} • {{ member.phone }}</p>
                  </div>
                  <button 
                    @click="markForMemberAction(member)" 
                    :disabled="markingForMember === member.id || member.is_present"
                    class="px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest shadow-sm active:scale-95 disabled:opacity-50 transition-colors"
                    :class="member.is_present ? 'bg-slate-100 text-slate-400 border border-slate-200' : 'bg-emerald-600 text-white'"
                  >
                    <span v-if="markingForMember === member.id" class="animate-spin rounded-full h-3 w-3 border-2 border-white border-t-transparent inline-block"></span>
                    <span v-else-if="member.is_present" class="flex items-center gap-1">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                      </svg>
                      Present
                    </span>
                    <span v-else>Mark Present</span>
                  </button>
               </div>
            </div>
            
            <!-- Members I've Marked -->
            <div v-if="markedByMeList.length > 0" class="mt-6 border-t border-slate-100 pt-6">
               <div class="flex items-center justify-between mb-3">
                 <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Marked by me ({{ markedByMeList.length }})</h4>
                 <button @click="fetchMarkedByMe" :disabled="loadingMarkedByMe" class="text-[9px] font-bold text-emerald-600 uppercase">Refresh</button>
               </div>
               <div class="space-y-2">
                 <div v-for="rec in markedByMeList" :key="rec.id" class="flex items-center justify-between p-2 bg-emerald-50/50 rounded-xl border border-emerald-100/50">
                    <div class="flex items-center gap-2">
                      <div class="w-6 h-6 bg-white rounded-full flex items-center justify-center text-[10px] shadow-sm">👤</div>
                      <div>
                        <p class="text-[10px] font-black text-slate-800">{{ rec.user?.name }} {{ rec.user?.surname }}</p>
                        <p class="text-[8px] text-slate-400 font-bold">{{ rec.user?.membership_number }}</p>
                      </div>
                    </div>
                    <span class="text-[8px] font-black text-emerald-600 uppercase">{{ formatTime(rec.attended_at) }}</span>
                 </div>
               </div>
            </div>
        </div>

        <!-- History -->
        <div class="mt-10 mb-6">
          <h3 class="px-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Recent History</h3>
          
          <div v-if="loadingHistory && history.length === 0" class="space-y-3">
             <div v-for="i in 3" :key="i" class="h-20 bg-slate-100 rounded-3xl animate-pulse"></div>
          </div>
          
          <div v-else-if="history.length === 0" class="bg-white p-8 rounded-3xl border border-dashed border-slate-200 text-center">
             <p class="text-xs text-slate-400 font-bold uppercase">No history records yet</p>
          </div>

          <div v-else class="space-y-3">
            <div v-for="item in history" :key="item.id" class="bg-white p-4 rounded-3xl shadow-sm border border-slate-100 flex items-center gap-4">
               <div :class="[
                 'w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm',
                 item.status === 'present' ? 'bg-emerald-50 text-emerald-600' : 
                 item.status === 'fine_paid' ? 'bg-orange-50 text-orange-600' :
                 item.status === 'excused' ? 'bg-blue-50 text-blue-600' :
                 item.status === 'pending_excuse' ? 'bg-slate-50 text-slate-600' :
                 item.status === 'fine_pending' ? 'bg-red-50 text-red-600' : 'bg-slate-50 text-slate-400'
               ]">
                 {{ item.status === 'present' ? '✅' : item.status === 'fine_paid' ? '💰' : 
                    item.status === 'excused' ? '🙏' : item.status === 'pending_excuse' ? '⏳' : '❌' }}
               </div>
               
               <div class="flex-1 min-w-0">
                 <h4 class="text-sm font-black text-slate-800 truncate">{{ item.meeting?.name || 'Unknown Meeting' }}</h4>
                 <div class="flex items-center gap-2 mt-0.5">
                    <span class="text-[9px] font-bold text-slate-400 uppercase">{{ formatDate(item.created_at) }}</span>
                    <span v-if="item.status === 'fine_pending'" class="text-[8px] font-black bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full uppercase">Fine Pending</span>
                    <span v-if="item.status === 'fine_paid'" class="text-[8px] font-black bg-emerald-100 text-emerald-600 px-1.5 py-0.5 rounded-full uppercase">Fine Paid</span>
                 </div>
               </div>
               
               <div class="text-right">
                  <p :class="[
                    'text-[10px] font-black uppercase tracking-tight',
                    item.status === 'present' ? 'text-emerald-600' : 
                    item.status === 'fine_paid' ? 'text-orange-600' : 
                    item.status === 'excused' ? 'text-blue-600' :
                    item.status === 'pending_excuse' ? 'text-slate-600' : 'text-red-600'
                  ]">
                    {{ item.status.replace('_', ' ') }}
                  </p>
                  <p v-if="item.status === 'fine_pending' || item.status === 'fine_paid'" class="text-[9px] text-slate-400 font-bold mt-0.5">
                    ₦{{ formatMoney(item.meeting?.fine_amount) }}
                  </p>
                  
                  <!-- View Report Button for Audited Meetings -->
                  <button v-if="item.meeting?.status === 'audited'" 
                          @click="openMeetingReport(item.meeting)"
                          class="mt-2 px-2 py-1 bg-slate-800 text-white text-[8px] font-black uppercase tracking-widest rounded-lg active:scale-95 transition-all">
                    View Report
                  </button>
               </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <AppBottomNav />
    
    <WebQrScanner 
      v-if="showWebScanner" 
      @scan="handleScan" 
      @close="showWebScanner = false"
      @error="(e) => modal.alert(e?.message || 'Camera error')" 
    />

    <!-- Meeting Report Modal -->
    <div v-if="showReportModal" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-4">
       <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showReportModal = false"></div>
       <div class="bg-white w-full max-w-lg rounded-[2.5rem] shadow-2xl relative z-10 overflow-hidden flex flex-col max-h-[90vh]">
          <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
             <div>
               <h3 class="font-black text-slate-800 text-sm uppercase tracking-tight">Attendance Report</h3>
               <p v-if="meetingReportData" class="text-[10px] text-slate-500 font-bold uppercase">{{ meetingReportData.meeting.name }} • {{ formatDate(meetingReportData.meeting.date) }}</p>
             </div>
             <button @click="showReportModal = false" class="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-sm text-slate-400">✕</button>
          </div>

          <div class="flex-1 overflow-y-auto p-4 no-scrollbar">
             <div v-if="loadingReport" class="flex flex-col items-center justify-center py-20">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-800"></div>
             </div>
             <div v-else-if="meetingReportData">
                <div class="grid grid-cols-4 px-2 py-3 border-b border-slate-100 text-[9px] font-black text-slate-400 uppercase tracking-widest">
                   <div class="col-span-2">Member</div>
                   <div>Status</div>
                   <div class="text-right">Time</div>
                </div>
                <div class="divide-y divide-slate-50">
                   <div v-for="rec in meetingReportData.records" :key="rec.id" class="grid grid-cols-4 px-2 py-4 items-center gap-2">
                      <div class="col-span-2 flex items-center gap-2">
                         <div class="w-7 h-7 bg-slate-100 rounded-full flex items-center justify-center text-[10px]">👤</div>
                         <div class="min-w-0">
                            <p class="text-[10px] font-black text-slate-800 truncate">{{ rec.user_name }}</p>
                            <p class="text-[8px] text-slate-400 font-bold uppercase truncate">{{ rec.membership_number }} • {{ rec.branch }}</p>
                         </div>
                      </div>
                      <div>
                         <span :class="[
                           'text-[8px] font-black px-1.5 py-0.5 rounded-full uppercase',
                           rec.status === 'present' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'
                         ]">{{ rec.status }}</span>
                      </div>
                      <div class="text-right text-[9px] font-black text-slate-500">
                         {{ rec.attended_at || '--:--' }}
                      </div>
                   </div>
                </div>
                <div v-if="meetingReportData.records.length === 0" class="py-20 text-center">
                   <p class="text-xs text-slate-400 font-bold uppercase">No records found</p>
                </div>
             </div>
          </div>
          
          <div class="p-6 bg-slate-50 border-t border-slate-100">
             <button @click="showReportModal = false" class="w-full bg-slate-800 text-white font-black py-4 rounded-2xl uppercase tracking-widest text-[10px]">Close</button>
          </div>
       </div>
    </div>
  </div>
</template>

<script setup>
import {ref, onMounted, onUnmounted, computed} from 'vue'
import { Geolocation } from '@capacitor/geolocation'
import { Device } from '@capacitor/device'
import { BarcodeScanner } from '@capacitor-mlkit/barcode-scanning'
import { Capacitor } from '@capacitor/core'
import AppHeader from '../components/AppHeader.vue'
import AppBottomNav from '../components/AppBottomNav.vue'
import WebQrScanner from '../components/WebQrScanner.vue'
import axios from '../http'
import { parseOptions, publicKeyCredentialToJSON } from '../utils/webauthn'
import { getBiometricAvailability } from '../services/biometric'
import BeaconService from '../services/BeaconService.js'
import {useAppStatusStore} from '../stores/appStatus'
import { useRouter } from 'vue-router'
import { useModal } from '../composables/useModal'
import { getEcho } from '../realtime/echo.js'

const router = useRouter()
const modal = useModal()
const appStatusStore = useAppStatusStore()

const loading = ref(true)
const meeting = ref(null)
const record = ref(null)
const inGracePeriod = ref(false)
const serverTime = ref(null)
const lateAt = ref(null)
const isCurrentlyLate = ref(false)
const fineAmount = ref(0)
const history = ref([])
const loadingHistory = ref(false)
const pin = ref('')
const reason = ref('')
const excuse_type = ref('other')
const excuse_proof = ref(null)
const location = ref(null)
const locating = ref(false)
const submitting = ref(false)
const submittingApology = ref(false)
const timeRemaining = ref('')
const countdownInterval = ref(null)
const refreshingStatus = ref(false)
const showWebScanner = ref(false)
const hasBiometrics = ref(false)
const scanningBeacon = ref(false)

const memberSearchQuery = ref('')
const memberSearchResults = ref([])
const searchingMembers = ref(false)
const markingForMember = ref(null)
const currentUser = ref(null)
const markedByMeList = ref([])
const loadingMarkedByMe = ref(false)
const showReportModal = ref(false)
const meetingReportData = ref(null)
const loadingReport = ref(false)

const canMarkForOthers = computed(() => {
  if (!currentUser.value) return false
  return currentUser.value.permission_names?.includes('mark_attendance') || currentUser.value.is_admin
})

const fetchMarkedByMe = async () => {
  if (!meeting.value || !canMarkForOthers.value) return
  loadingMarkedByMe.value = true
  try {
    const { data } = await axios.get(`/api/meetings/${meeting.value.id}/marked-by-me`)
    markedByMeList.value = data
  } catch (err) {
    console.error('Failed to fetch marked members:', err)
  } finally {
    loadingMarkedByMe.value = false
  }
}

const openMeetingReport = async (m = null) => {
  const targetMeeting = m || meeting.value
  if (!targetMeeting) return
  showReportModal.value = true
  loadingReport.value = true
  try {
    const { data } = await axios.get(`/api/meetings/${targetMeeting.id}/report`)
    meetingReportData.value = data
  } catch (err) {
    modal.alert(err.response?.data?.message || "Failed to load report")
    showReportModal.value = false
  } finally {
    loadingReport.value = false
  }
}

const searchMembers = async () => {
  if (memberSearchQuery.value.length < 2) {
    memberSearchResults.value = []
    return
  }
  searchingMembers.value = true
  try {
    const { data } = await axios.get(`/api/attendance/search-members?q=${memberSearchQuery.value}&meeting_id=${meeting.value?.id}`)
    memberSearchResults.value = data
  } catch (err) {
    console.error('Member search failed:', err)
  } finally {
    searchingMembers.value = false
  }
}

const markForMemberAction = async (member) => {
  const confirm = await modal.confirm(`Mark attendance for ${member.name} ${member.surname}?`)
  if (!confirm) return

  markingForMember.value = member.id
  try {
    const res = await axios.post(`/api/meetings/${meeting.value.id}/mark-member-attendance`, {
      user_id: member.id
    })
    
    if (res.data.success || res.data.record) {
      modal.alert(res.data.message || "Attendance marked successfully")
      // Update local state to reflect change immediately
      member.is_present = true
      fetchMarkedByMe()
    } else {
      modal.alert(res.data.message || "Failed to mark attendance", "Attendance Error")
    }
  } catch (err) {
    const errorMsg = err.response?.data?.message || "Failed to mark attendance for member"
    modal.alert(errorMsg, "Attendance Error")
  } finally {
    markingForMember.value = null
  }
}

const isNative = Capacitor.isNativePlatform()
const canScan = true // Always true now as we have web fallback

const scanQr = async () => {
  if (!location.value) {
    modal.alert('Please capture your location first.')
    return
  }

  if (!isNative) {
    showWebScanner.value = true
    return
  }

  try {
    const perm = await BarcodeScanner.checkPermissions()
    if (perm.camera !== 'granted') {
      const req = await BarcodeScanner.requestPermissions()
      if (req.camera !== 'granted') {
        modal.alert('Camera permission denied')
        return
      }
    }
    const { barcodes } = await BarcodeScanner.scan({ formats: ['qrCode'], lensFacing: 'back' })
    const code = Array.isArray(barcodes) && barcodes[0]
      ? (barcodes[0].rawValue || barcodes[0].displayValue || barcodes[0].content || '')
      : ''
    
    if (code && code.startsWith('attaqwa:attendance?')) {
      const urlStr = code.replace('attaqwa:attendance', 'http://localhost')
      const url = new URL(urlStr)
      const qrToken = url.searchParams.get('token')
      if (qrToken) {
        await submitAttendance(qrToken)
      } else {
        modal.alert('Invalid Attendance QR code: Token missing')
      }
    } else {
      modal.alert('Invalid QR code format. Please scan a valid Attendance QR.')
    }
  } catch (e) {
    modal.alert(e?.message || 'Failed to scan QR')
  }
}

const canSubmitApology = computed(() => {
  if (!appStatusStore.attendanceApologyEnabled) return false
  if (!meeting.value) return false
  if (meeting.value.status !== 'scheduled' && meeting.value.status !== 'ongoing') return false
  
  // Strict block: Only allow if now is before meeting start_time
  const now = new Date()
  const start = new Date(meeting.value.start_at)
  return now < start
})

const formatMoney = (val) => {
  return new Intl.NumberFormat('en-NG', { minimumFractionDigits: 2 }).format(val || 0)
}

const formatDate = (dateStr) => {
  if (!dateStr) return 'N/A'
  return new Date(dateStr).toLocaleDateString('en-GB', { 
    day: 'numeric', 
    month: 'short', 
    year: 'numeric' 
  })
}

const formatTime = (dateStr) => {
  if (!dateStr || dateStr === 'N/A') return '--:--'
  try {
     return new Date(dateStr).toLocaleTimeString('en-GB', {
       hour: '2-digit',
       minute: '2-digit'
     })
  } catch (e) {
     return dateStr
  }
}

const formatDuration = (ms) => {
  const s = Math.floor(ms / 1000)
  const m = Math.floor(s / 60)
  const h = Math.floor(m / 60)
  const d = Math.floor(h / 24)

  const ss = (s % 60).toString().padStart(2, '0')
  const mm = (m % 60).toString().padStart(2, '0')
  const hh = (h % 24).toString().padStart(2, '0')

  if (d > 0) return `${d}d ${hh}:${mm}:${ss}`
  return `${hh}:${mm}:${ss}`
}

const updateCountdown = () => {
  if (!meeting.value) return
  
  const now = new Date()
  
  // Lateness Countdown
  if (meeting.value.status === 'ongoing' && lateAt.value) {
    const lateTarget = new Date(lateAt.value)
    const lateDiff = lateTarget - now
    if (lateDiff > 0) {
      latenessCountdown.value = formatDuration(lateDiff)
      isCurrentlyLate.value = false
    } else {
      latenessCountdown.value = ''
      isCurrentlyLate.value = true
    }
  }

  const targetIso = meeting.value.status === 'scheduled' ? meeting.value.start_at : meeting.value.end_at
  const target = new Date(targetIso)
  const diff = target - now
  
  if (diff <= 0) {
    timeRemaining.value = '00:00:00'
    if (meeting.value.status !== 'completed' && meeting.value.status !== 'audited') {
       if (!refreshingStatus.value) {
         refreshingStatus.value = true
         setTimeout(() => {
           fetchCurrentMeeting().then(() => {
             refreshingStatus.value = false
           })
         }, 10000) // Wait 10s before next refresh if status didn't change
       }
    }
    return
  }
  
  timeRemaining.value = formatDuration(diff)
}

const startCountdown = () => {
  if (countdownInterval.value) clearInterval(countdownInterval.value)
  updateCountdown()
  countdownInterval.value = setInterval(updateCountdown, 1000)
}

const latenessCountdown = ref('')

const fetchCurrentMeeting = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/attendance/current')
    meeting.value = res.data.meeting
    record.value = res.data.attendance_record
    inGracePeriod.value = res.data.in_grace_period
    serverTime.value = res.data.server_time
    lateAt.value = res.data.late_at
    isCurrentlyLate.value = res.data.is_currently_late
    fineAmount.value = res.data.fine_amount
    
    // Auto-request location if meeting is ongoing and attendance not marked
    if (meeting.value && meeting.value.status === 'ongoing' && (!record.value || record.value.status !== 'present')) {
      getLocation()
    }

    if (meeting.value) {
      startCountdown()
      if (canMarkForOthers.value) {
        fetchMarkedByMe()
      }
    }
  } catch (err) {
    console.error('Attendance Check:', err)
  } finally {
    loading.value = false
    fetchHistory()
  }
}

const fetchHistory = async () => {
  loadingHistory.value = true
  try {
    const res = await axios.get('/api/attendance/history')
    history.value = res.data.data
  } catch (err) {
    console.error('Attendance History:', err)
  } finally {
    loadingHistory.value = false
  }
}

const getLocation = async () => {
  locating.value = true
  try {
    const position = await Geolocation.getCurrentPosition({
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0
    })
    
    location.value = {
      lat: position.coords.latitude,
      lng: position.coords.longitude
    }
  } catch (error) {
    console.error('Location Error:', error)
    const perms = await Geolocation.checkPermissions()
    if (perms.location !== 'granted') {
      const title = "Permission Required"
      const msg = "Location permission is required to verify your presence and mark attendance during a meeting. Please allow access in your device settings."
      const retry = await modal.confirm(msg, { confirmText: 'Try Again', title })
      if (retry) {
        await getLocation()
      }
    } else {
      await modal.alert("Could not get your location. Please check your GPS and try again.", "Location Error")
    }
  } finally {
    locating.value = false
  }
}

const handleScan = async (code) => {
  showWebScanner.value = false
  if (code && code.startsWith('attaqwa:attendance?')) {
    const urlStr = code.replace('attaqwa:attendance', 'http://localhost')
    const url = new URL(urlStr)
    const qrToken = url.searchParams.get('token')
    if (qrToken) {
      await submitAttendance(qrToken)
    } else {
      modal.alert('Invalid Attendance QR code: Token missing')
    }
  } else {
    modal.alert('Invalid QR code format. Please scan a valid Attendance QR.')
  }
}

const submitAttendance = async (qrToken = null) => {
  // If qrToken is an Event object (from @click="submitAttendance"), treat it as null
  if (qrToken && typeof qrToken === 'object' && qrToken.constructor.name.includes('Event')) {
    qrToken = null
  }

  if (!qrToken && appStatusStore.attendancePinEnabled && !pin.value) return
  submitting.value = true
  try {
    const info = await Device.getId()
    const payload = {
      lat: location.value.lat,
      lng: location.value.lng,
      device_uuid: info.identifier
    }
    if (qrToken) {
      payload.qr_token = qrToken
    } else {
      payload.pin = pin.value
    }

    const res = await axios.post(`/api/meetings/${meeting.value.id}/mark-attendance`, payload)
    record.value = res.data.record
    modal.alert(res.data.message || "Attendance marked successfully!")
    fetchHistory()
  } catch (err) {
    if (!navigator.onLine || err.message === 'Network Error') {
      // Save for offline sync
      const offlineRecords = JSON.parse(localStorage.getItem('offline_attendance') || '[]')
      offlineRecords.push({
        ...payload,
        meeting_id: meeting.value.id,
        attended_at: new Date().toISOString(),
        verification_type: qrToken ? 'qr' : 'pin'
      })
      localStorage.setItem('offline_attendance', JSON.stringify(offlineRecords))
      modal.alert("You are offline. Your attendance has been saved locally and will sync when you are back online.")
    } else {
      modal.alert(err.response?.data?.message || "Failed to mark attendance")
    }
  } finally {
    submitting.value = false
  }
}

const syncOfflineRecords = async () => {
  const records = JSON.parse(localStorage.getItem('offline_attendance') || '[]')
  if (records.length === 0) return

  try {
    const res = await axios.post('/api/attendance/sync-offline', { records })
    localStorage.removeItem('offline_attendance')
    modal.alert(res.data.message || "Offline records synced successfully")
    fetchCurrentMeeting()
  } catch (err) {
    console.error('Offline sync failed:', err)
  }
}

const markWithBiometrics = async () => {
  const bio = await getBiometricAvailability()
  if (!bio.isAvailable || bio.platform !== 'webauthn') {
    let msg = "Biometrics (WebAuthn) not supported on this device/browser."
    if (bio.reason === 'insecure_context') {
      msg = "Biometrics require a secure HTTPS connection. Please access the site via HTTPS."
    }
    modal.alert(msg)
    return
  }

  if (!location.value) {
    modal.alert('Please capture your location first.')
    return
  }
  
  submitting.value = true
  try {
    const { data: options } = await axios.get(`/api/meetings/${meeting.value.id}/biometric-options`)
    const publicKey = parseOptions(options)
    const assertion = await navigator.credentials.get({ publicKey })
    
    const info = await Device.getId()
    const payload = {
      ...publicKeyCredentialToJSON(assertion),
      lat: location.value.lat,
      lng: location.value.lng,
      device_uuid: info.identifier
    }
    
    const res = await axios.post(`/api/meetings/${meeting.value.id}/mark-biometric`, payload)
    record.value = res.data.record
    modal.alert(res.data.message || "Attendance marked successfully!")
    fetchHistory()
  } catch (err) {
    console.error(err)
    modal.alert(err.response?.data?.message || "Biometric verification failed. Please try PIN or QR.")
  } finally {
    submitting.value = false
  }
}

const markWithBeacon = async () => {
  if (!isNative) {
    modal.alert("Beacon attendance is only available on the mobile app.")
    return
  }

  scanningBeacon.value = true
  try {
    const isNearby = await BeaconService.checkProximity(meeting.value)
    
    if (!isNearby) {
      modal.alert(`Could not detect meeting beacon. Please ensure you are inside the venue and Bluetooth is enabled.`)
      return
    }

    const info = await Device.getId()
    if (!location.value) {
      await getLocation()
    }

    const payload = {
      beacon_uuid: meeting.value.beacon_uuid,
      beacon_major: meeting.value.beacon_major,
      beacon_minor: meeting.value.beacon_minor,
      device_uuid: info.identifier,
      lat: location.value?.lat,
      lng: location.value?.lng
    }
    
    const res = await axios.post(`/api/meetings/${meeting.value.id}/mark-beacon`, payload)
    record.value = res.data.record
    modal.alert(res.data.message || "Attendance marked successfully via Beacon!")
    fetchHistory()
  } catch (err) {
    console.error('Beacon Error:', err)
    modal.alert(err.response?.data?.message || err.message || "Beacon verification failed.")
  } finally {
    scanningBeacon.value = false
  }
}

const submitApology = async () => {
  if (!reason.value) return
  
  submittingApology.value = true
  const formData = new FormData()
  formData.append('reason', reason.value)
  formData.append('excuse_type', excuse_type.value)
  if (excuse_proof.value) {
    formData.append('proof', excuse_proof.value)
  }

  try {
    const res = await axios.post(`/api/meetings/${meeting.value.id}/apology`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    
    modal.alert(res.data.message || "Apology submitted successfully!")
    record.value = res.data.record
    reason.value = ''
    excuse_proof.value = null
    fetchHistory()
  } catch (err) {
    modal.alert(err.response?.data?.message || "Failed to submit apology")
  } finally {
    submittingApology.value = false
  }
}

onMounted(async () => {
  const token = localStorage.getItem('token')
  if (token) {
    try {
      const { data: userData } = await axios.get('/api/profile', { headers: { Authorization: `Bearer ${token}` } })
      currentUser.value = userData
      if (meeting.value && canMarkForOthers.value) {
        fetchMarkedByMe()
      }
    } catch (err) {
      console.error('Failed to fetch user profile:', err)
    }
  }

  await fetchCurrentMeeting()
  await syncOfflineRecords()

  try {
    const { data } = await axios.get('/api/biometrics/status')
    hasBiometrics.value = data.has_biometrics
  } catch (e) {}

  // Real-time listener
  try {
    const echo = getEcho()
    if (!echo) {
      console.warn('Echo not initialized in Attendance')
      return
    }
    
    const token = localStorage.getItem('token')
    if (token && currentUser.value) {
      const userId = currentUser.value.id

      if (userId) {
        echo.private(`user.${userId}`)
          .listen('UserAccountUpdated', (e) => {
            console.log('Real-time update received in Attendance:', e)
            fetchCurrentMeeting()
          })
      }
    }
  } catch (err) {
    console.error('Failed to initialize real-time listener in Attendance:', err)
  }
})
onUnmounted(() => {
  if (countdownInterval.value) clearInterval(countdownInterval.value)
  
  // Cleanup Echo listener
  try {
    const echo = getEcho()
    const userId = localStorage.getItem('user_id')
    if (echo && userId) {
      echo.leave(`user.${userId}`)
    }
  } catch (_) {}
})
</script>
