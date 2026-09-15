<template>
  <div class="min-h-screen bg-slate-50">
    <AppHeader title="Attendance" :showBack="true" />

    <div class="p-4 pb-32">
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-emerald-600"></div>
        <p class="mt-4 text-slate-500 font-medium">Checking for meetings...</p>
      </div>

      <div v-else-if="!meeting" class="bg-white p-10 rounded-3xl shadow-sm border border-slate-100 text-center">
        <div class="text-5xl mb-4">🗓️</div>
        <h2 class="text-xl font-bold text-slate-800">No active or upcoming meeting</h2>
        <p class="text-slate-500 mt-2 text-sm">There is no meeting currently active or scheduled for your branch.</p>
        <button @click="fetchCurrentMeeting" class="mt-8 w-full bg-emerald-600 text-white font-black py-4 rounded-2xl shadow-lg shadow-emerald-100 uppercase tracking-widest text-xs active:scale-[0.98] transition-all">Refresh</button>
      </div>

      <div v-if="!loading && meeting">
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
        <div v-if="canMarkForOthers && meeting && (meeting.status === 'ongoing' || meeting.status === 'scheduled')" 
             class="bg-gradient-to-br from-amber-50 via-white to-emerald-50 p-6 rounded-[2.5rem] shadow-xl shadow-amber-100/20 border border-amber-100/50 mt-4 relative overflow-hidden transition-all duration-500">
             
             <!-- Decorative background element -->
             <div class="absolute -right-10 -top-10 w-32 h-32 bg-amber-200/20 rounded-full blur-3xl"></div>
             
             <div class="flex items-center justify-between mb-4 relative z-10">
               <div class="flex items-center gap-3">
                 <div class="w-10 h-10 bg-amber-600 rounded-2xl flex items-center justify-center text-xl shadow-lg shadow-amber-200">👥</div>
                 <div>
                   <h3 class="font-black text-slate-800 text-base uppercase tracking-tight leading-none">Admin Control</h3>
                   <p class="text-[9px] text-amber-600 font-black uppercase tracking-widest mt-1">Mark for Member</p>
                 </div>
               </div>
               
               <div class="flex flex-col items-end gap-1">
                 <button v-if="appStatusStore.adminAttendanceStatsEnabled" @click="showStatsDashboard = !showStatsDashboard" class="text-[9px] font-black text-amber-600 uppercase tracking-widest border border-amber-200 px-2 py-1 rounded-lg hover:bg-amber-100 transition-colors mb-1">
                   {{ showStatsDashboard ? 'Hide Stats' : 'Show Stats' }}
                 </button>
                 <label class="relative inline-flex items-center cursor-pointer scale-75 origin-right">
                   <input type="checkbox" v-model="quickMark" class="sr-only peer">
                   <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                   <span class="ml-2 text-[10px] font-black text-slate-500 uppercase">Quick Mark</span>
                 </label>
               </div>
            </div>
            
            <p class="text-[11px] text-slate-500 mb-6 font-medium leading-relaxed relative z-10">
              Select members below to mark them present instantly. Use search to find specific members.
            </p>

            <!-- Admin Stats Dashboard -->
            <transition name="slide-down">
              <div v-if="showStatsDashboard && meetingStats" class="mb-6 relative z-10 space-y-4">
                <div class="grid grid-cols-3 gap-3">
                  <div class="bg-white p-3 rounded-2xl border border-amber-100 shadow-sm">
                    <div class="text-[8px] font-black text-slate-400 uppercase mb-1">Total Members</div>
                    <div class="text-lg font-black text-slate-700">{{ meetingStats.total_members }}</div>
                  </div>
                  <div class="bg-white p-3 rounded-2xl border border-amber-100 shadow-sm">
                    <div class="text-[8px] font-black text-slate-400 uppercase mb-1">Present</div>
                    <div class="text-lg font-black text-emerald-600">{{ meetingStats.total_present }}</div>
                  </div>
                  <div class="bg-white p-3 rounded-2xl border border-amber-100 shadow-sm">
                    <div class="text-[8px] font-black text-slate-400 uppercase mb-1">Rate</div>
                    <div class="text-lg font-black text-amber-600">{{ meetingStats.attendance_rate }}%</div>
                  </div>
                </div>

                <div class="bg-white p-4 rounded-3xl border border-amber-100 shadow-sm overflow-hidden">
                   <h4 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center justify-between">
                     <span>Branch Breakdown</span>
                     <span class="text-amber-600">Present / Total</span>
                   </h4>
                   <div class="space-y-3 max-h-48 overflow-y-auto no-scrollbar pr-1">
                      <div v-for="b in meetingStats.branches" :key="b.name" class="space-y-1">
                        <div class="flex justify-between text-[10px] font-bold text-slate-600">
                          <span>{{ b.name }}</span>
                          <span>{{ b.present }} / {{ b.total }}</span>
                        </div>
                        <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                          <div class="h-full bg-amber-500 rounded-full transition-all duration-1000" :style="{ width: b.percentage + '%' }"></div>
                        </div>
                      </div>
                   </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div class="bg-white p-4 rounded-3xl border border-amber-100 shadow-sm flex items-center justify-between">
                    <div>
                       <div class="text-[8px] font-black text-blue-400 uppercase mb-1">Male</div>
                       <div class="text-xs font-black text-slate-700">{{ meetingStats.gender.male.present }} / {{ meetingStats.gender.male.total }}</div>
                    </div>
                    <div class="text-xl">👨</div>
                  </div>
                  <div class="bg-white p-4 rounded-3xl border border-amber-100 shadow-sm flex items-center justify-between">
                    <div>
                       <div class="text-[8px] font-black text-rose-400 uppercase mb-1">Female</div>
                       <div class="text-xs font-black text-slate-700">{{ meetingStats.gender.female.present }} / {{ meetingStats.gender.female.total }}</div>
                    </div>
                    <div class="text-xl">👩</div>
                  </div>
                </div>
              </div>
            </transition>

            <div v-if="meeting.status === 'scheduled'" class="bg-amber-50/50 p-4 rounded-2xl border border-amber-100 mb-6 relative z-10 flex items-center gap-3">
               <span class="text-xl">⏳</span>
               <p class="text-[10px] font-bold text-amber-700 uppercase tracking-tight">Meeting has not started yet. You will be able to mark attendance once it's ongoing.</p>
            </div>
            
            <div class="flex items-center gap-2 mb-4 relative z-10">
              <div class="relative flex-1">
                <input 
                  v-model="memberSearchQuery" 
                  @input="searchMembers"
                  type="text" 
                  placeholder="Search name, phone or ID"
                  class="w-full bg-white border-2 border-amber-100 rounded-2xl p-4 pr-24 text-xs font-bold focus:ring-1 focus:ring-amber-500 focus:border-amber-500 transition-all placeholder:text-slate-300"
                />
                <div class="absolute right-2 top-1.5 flex items-center gap-1">
                  <button v-if="appStatusStore.adminAttendanceVoiceSearchEnabled" @click="startVoiceSearch" :class="{'text-red-500 animate-pulse': isListening}" class="p-2.5 text-slate-400 hover:text-amber-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                    </svg>
                  </button>
                  <button v-if="appStatusStore.adminAttendanceQrEnabled" @click="adminScanQr" class="p-2.5 text-slate-400 hover:text-amber-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5zM6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" />
                    </svg>
                  </button>
                </div>
                <div v-if="searchingMembers" class="absolute left-1/2 -bottom-6 -translate-x-1/2">
                  <div class="animate-spin rounded-full h-3 w-3 border-2 border-amber-600 border-t-transparent"></div>
                </div>
              </div>
              <button @click="showFilters = !showFilters" :class="{'bg-amber-600 text-white shadow-lg': showFilters, 'bg-white text-slate-400 border-amber-100': !showFilters}" class="w-12 h-12 rounded-2xl border-2 flex items-center justify-center transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 12h16.5m-16.5 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-3 0H3.75m0 6H7.5" />
                </svg>
              </button>
            </div>

            <!-- Intelligent Filters -->
            <transition name="slide-down">
              <div v-if="showFilters && appStatusStore.adminAttendanceIntelligentFilteringEnabled" class="grid grid-cols-2 gap-3 mb-6 relative z-10 bg-amber-50/50 p-4 rounded-3xl border border-amber-100">
                <div class="col-span-2">
                  <label class="block text-[9px] font-black text-amber-600 uppercase tracking-widest mb-2 ml-1">Branch/Zone</label>
                  <select v-model="searchBranchId" @change="searchMembers" class="w-full bg-white border-none rounded-xl p-3 text-[11px] font-bold text-slate-700 shadow-sm focus:ring-1 focus:ring-amber-500">
                    <option value="">All Branches</option>
                    <option v-for="b in availableBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-[9px] font-black text-amber-600 uppercase tracking-widest mb-2 ml-1">Gender</label>
                  <select v-model="searchGender" @change="searchMembers" class="w-full bg-white border-none rounded-xl p-3 text-[11px] font-bold text-slate-700 shadow-sm focus:ring-1 focus:ring-amber-500">
                    <option value="">All</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                  </select>
                </div>
                <div class="flex items-end">
                   <label class="relative flex items-center cursor-pointer p-1">
                      <input type="checkbox" v-model="expectedOnly" @change="searchMembers" class="sr-only peer">
                      <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[6px] after:left-[6px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                      <span class="ml-2 text-[9px] font-black text-slate-500 uppercase leading-none">Expected Only</span>
                    </label>
                </div>
              </div>
            </transition>

            <!-- Recent/Frequent List -->
            <div v-if="recentMarked.length > 0 && appStatusStore.adminAttendanceRecentListEnabled && !memberSearchQuery" class="mb-6 relative z-10">
               <h4 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Recent marked</h4>
               <div class="flex items-center gap-3 overflow-x-auto no-scrollbar pb-2 px-1">
                  <div v-for="m in recentMarked" :key="m.id" @click="memberSearchQuery = m.membership_number; searchMembers()" 
                       class="flex flex-col items-center gap-1.5 flex-shrink-0 cursor-pointer active:scale-90 transition-transform group">
                    <div class="w-12 h-12 bg-white rounded-2xl shadow-sm border border-slate-100 flex items-center justify-center text-lg overflow-hidden group-hover:border-amber-400">
                       <img v-if="m.passport_path" :src="`/storage/${m.passport_path}`" class="w-full h-full object-cover" />
                       <span v-else>👤</span>
                    </div>
                    <span class="text-[8px] font-bold text-slate-500 uppercase truncate w-14 text-center">{{ m.name }}</span>
                  </div>
               </div>
            </div>

            <div v-if="memberSearchResults.length > 0" class="flex items-center gap-2 mt-4 relative z-10 overflow-x-auto no-scrollbar pb-1">
              <button 
                v-for="f in ['all', 'absent', 'present']" 
                :key="f"
                @click="searchFilter = f"
                class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all whitespace-nowrap"
                :class="searchFilter === f ? 'bg-amber-600 text-white shadow-lg shadow-amber-200' : 'bg-white text-slate-400 border border-slate-100 hover:border-amber-200'"
              >
                {{ f === 'all' ? 'All Results' : (f === 'absent' ? 'Not Marked' : 'Already Present') }}
              </button>
            </div>

            <div v-if="memberSearchResults.length > 0" class="mt-6 relative z-10 flex gap-2">
               <div class="flex-1 space-y-2 max-h-[32rem] overflow-y-auto no-scrollbar pb-10">
                 <!-- Bulk Actions Bar -->
                 <div class="flex items-center justify-between bg-white/90 backdrop-blur-sm p-3 rounded-2xl border border-amber-100 mb-3 sticky top-0 z-20 shadow-sm">
                   <div class="flex items-center gap-3">
                     <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="w-5 h-5 rounded-lg border-slate-300 text-amber-600 focus:ring-amber-500 transition-all cursor-pointer" />
                     <span class="text-[11px] font-black text-slate-600 uppercase tracking-tight">
                       {{ isAllSelected ? 'Unselect All' : `Select All (${searchFilter === 'present' ? filteredSearchResults.length : filteredSearchResults.filter(m => !m.is_present).length})` }}
                     </span>
                   </div>
                   <div class="flex items-center gap-2">
                     <button 
                       v-if="selectedCountByStatus.absent > 0"
                       @click="bulkMarkAction"
                       :disabled="bulkMarking"
                       class="bg-emerald-600 text-white text-[10px] font-black px-4 py-2 rounded-xl uppercase tracking-widest shadow-lg shadow-emerald-200 active:scale-95 disabled:opacity-50 transition-all flex items-center gap-2"
                     >
                       <span v-if="bulkMarking" class="animate-spin rounded-full h-3 w-3 border-2 border-white border-t-transparent"></span>
                       <span v-else>Mark ({{ selectedCountByStatus.absent }})</span>
                     </button>
                     <button 
                       v-if="selectedCountByStatus.present > 0"
                       @click="bulkUnmarkAction"
                       :disabled="bulkMarking"
                       class="bg-red-500 text-white text-[10px] font-black px-4 py-2 rounded-xl uppercase tracking-widest shadow-lg shadow-red-200 active:scale-95 disabled:opacity-50 transition-all flex items-center gap-2"
                     >
                       <span v-if="bulkMarking" class="animate-spin rounded-full h-3 w-3 border-2 border-white border-t-transparent"></span>
                       <span v-else>Unmark ({{ selectedCountByStatus.present }})</span>
                     </button>
                   </div>
                 </div>

                 <div v-if="filteredSearchResults.length === 0" class="py-12 text-center bg-white/50 rounded-3xl border-2 border-dashed border-slate-100">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">No members match this filter</p>
                 </div>

                 <div v-for="member in filteredSearchResults" :key="member.id" :id="`member-${member.id}`"
                      class="p-4 bg-white/80 rounded-[1.5rem] flex items-center justify-between gap-4 border border-slate-100 shadow-sm transition-all hover:border-amber-200 active:bg-amber-50/30 group scroll-mt-20"
                      :class="{'ring-2 ring-amber-500/50 bg-amber-50/20': selectedMembers.includes(member.id)}">
                    <div class="flex items-center gap-4 flex-1 min-w-0">
                      <div class="relative">
                        <input 
                          type="checkbox" 
                          :value="member.id" 
                          v-model="selectedMembers"
                          class="w-6 h-6 rounded-lg border-slate-300 text-amber-600 focus:ring-amber-500 transition-all cursor-pointer" 
                        />
                        <div v-if="member.is_present" class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-emerald-500 rounded-full border-2 border-white flex items-center justify-center shadow-sm">
                           <svg xmlns="http://www.w3.org/2000/svg" class="h-2 w-2 text-white" viewBox="0 0 20 20" fill="currentColor">
                             <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                           </svg>
                        </div>
                      </div>
                      
                      <div class="flex-1 min-w-0" @click="selectedMembers.includes(member.id) ? selectedMembers = selectedMembers.filter(id => id !== member.id) : selectedMembers.push(member.id)">
                        <p class="text-sm font-black text-slate-800 truncate">{{ member.surname }} {{ member.name }}</p>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">{{ member.membership_number }} • {{ member.phone }}</p>
                      </div>
                    </div>

                    <div class="flex items-center gap-2">
                      <button 
                        v-if="member.is_present"
                        @click="unmarkForMemberAction(member)"
                        :disabled="unmarkingForMember === member.id || meeting.status !== 'ongoing'"
                        class="w-10 h-10 bg-red-50 text-red-600 rounded-2xl flex items-center justify-center shadow-sm active:scale-90 disabled:opacity-50 transition-all"
                      >
                        <span v-if="unmarkingForMember === member.id" class="animate-spin rounded-full h-4 w-4 border-2 border-red-600 border-t-transparent"></span>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                      <button 
                        v-else
                        @click="markForMemberAction(member)" 
                        :disabled="markingForMember === member.id || meeting.status !== 'ongoing'"
                        class="h-10 px-4 bg-emerald-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-emerald-100 active:scale-95 disabled:opacity-50 transition-all"
                      >
                        <span v-if="markingForMember === member.id" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent inline-block"></span>
                        <span v-else>{{ meeting.status !== 'ongoing' ? 'Wait' : 'Mark' }}</span>
                      </button>
                    </div>
                 </div>
               </div>

               <!-- Alphabetical Scroller Sidebar -->
               <div v-if="memberSearchResults.length > 5 && appStatusStore.adminAttendanceAlphabeticalScrollerEnabled" 
                    class="flex flex-col items-center gap-0.5 px-1 sticky top-0 h-fit bg-amber-50/30 backdrop-blur-sm rounded-full py-3 border border-amber-100/50">
                 <button v-for="char in alphabet" :key="char" @click="scrollToLetter(char)" 
                         class="text-[8px] font-black text-slate-400 hover:text-amber-600 hover:scale-125 transition-all uppercase w-4 h-3.5 flex items-center justify-center">
                   {{ char }}
                 </button>
               </div>
            </div>
            
            <!-- Members I've Marked -->
            <div v-if="markedByMeList.length > 0" class="mt-8 border-t border-amber-100 pt-6 relative z-10">
               <div class="flex items-center justify-between mb-4">
                 <div class="flex flex-col">
                   <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest leading-none">Your Records Today</h4>
                   <span class="text-[9px] font-bold text-amber-600 uppercase mt-1">Total: {{ markedByMeList.length }} members</span>
                 </div>
                 <button @click="fetchMarkedByMe" :disabled="loadingMarkedByMe" 
                         class="bg-white border border-slate-200 text-[10px] font-bold text-slate-500 px-3 py-1.5 rounded-xl active:scale-95 disabled:opacity-50 transition-all shadow-sm">
                   Refresh List
                 </button>
               </div>
               
               <div class="grid grid-cols-1 gap-2">
                 <div v-for="rec in markedByMeList" :key="rec.id" class="flex items-center justify-between p-3 bg-emerald-50/40 rounded-2xl border border-emerald-100/50 group transition-all">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 bg-white rounded-xl flex items-center justify-center text-xs shadow-sm ring-1 ring-emerald-100">👤</div>
                      <div>
                        <p class="text-[11px] font-black text-slate-800">{{ rec.user?.name }} {{ rec.user?.surname }}</p>
                        <div class="flex items-center gap-2">
                          <p class="text-[9px] text-slate-400 font-bold tracking-tight">{{ rec.user?.membership_number }}</p>
                          <span class="w-1 h-1 bg-emerald-300 rounded-full"></span>
                          <span class="text-[9px] font-black text-emerald-600 uppercase">{{ formatTime(rec.attended_at) }}</span>
                        </div>
                      </div>
                    </div>
                    <button @click="unmarkForMemberAction(rec)" :disabled="unmarkingForMember === rec.user_id || meeting.status !== 'ongoing'" 
                            class="w-8 h-8 bg-white text-red-500 rounded-xl flex items-center justify-center text-[10px] shadow-sm border border-red-50 border-t-transparent active:scale-90 disabled:opacity-50 transition-all opacity-40 group-hover:opacity-100 hover:bg-red-50">
                      <span v-if="unmarkingForMember === rec.user_id" class="animate-spin h-3 w-3 border-2 border-red-500 border-t-transparent rounded-full"></span>
                      <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                 </div>
               </div>
            </div>
        </div>
      </div>

      <!-- History (Always visible) -->
      <div v-if="!loading" class="mt-10 mb-6">
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
                
                <!-- View Report Button for Audited Meetings or Admins -->
                <button v-if="item.meeting?.status === 'audited' || canMarkForOthers" 
                        @click="openMeetingReport(item.meeting)"
                        class="mt-2 px-2 py-1 bg-slate-800 text-white text-[8px] font-black uppercase tracking-widest rounded-lg active:scale-[0.98] transition-all">
                  View Report
                </button>
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
import {ref, onMounted, onUnmounted, computed, watch} from 'vue'
import { Geolocation } from '@capacitor/geolocation'
import { Device } from '@capacitor/device'
import { BarcodeScanner } from '@capacitor-mlkit/barcode-scanning'
import { SpeechRecognition } from '@capacitor-community/speech-recognition'
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
const scanMode = ref('member') // 'member' or 'admin'
const hasBiometrics = ref(false)
const scanningBeacon = ref(false)

const memberSearchQuery = ref('')
const memberSearchResults = ref([])
const searchingMembers = ref(false)
const markingForMember = ref(null)
const unmarkingForMember = ref(null)
const currentUser = ref(null)
const markedByMeList = ref([])
const loadingMarkedByMe = ref(false)
const showReportModal = ref(false)
const meetingReportData = ref(null)
const loadingReport = ref(false)

const meetingStats = ref(null)
const loadingStats = ref(false)
const showStatsDashboard = ref(false)

const quickMark = ref(localStorage.getItem('attendance_quick_mark') === 'true')
const selectedMembers = ref([])
const bulkMarking = ref(false)
const searchFilter = ref('all') // 'all', 'absent', 'present'
const searchBranchId = ref('')
const searchGender = ref('')
const expectedOnly = ref(false)
const availableBranches = ref([])
const isListening = ref(false)
const recentMarked = ref(JSON.parse(localStorage.getItem('recent_marked') || '[]'))
const showFilters = ref(false)
const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')
const isNative = Capacitor.isNativePlatform()
const canScan = true // Always true now as we have web fallback

watch(quickMark, (val) => {
  localStorage.setItem('attendance_quick_mark', val)
})

const filteredSearchResults = computed(() => {
  if (searchFilter.value === 'present') {
    return memberSearchResults.value.filter(m => m.is_present)
  }
  if (searchFilter.value === 'absent') {
    return memberSearchResults.value.filter(m => !m.is_present)
  }
  return memberSearchResults.value
})

const isAllSelected = computed(() => {
  let items = []
  if (searchFilter.value === 'present') {
    items = filteredSearchResults.value
  } else {
    items = filteredSearchResults.value.filter(m => !m.is_present)
  }
  
  if (items.length === 0) return false
  return items.every(m => selectedMembers.value.includes(m.id))
})

const toggleSelectAll = () => {
  let targetIds = []
  if (searchFilter.value === 'present') {
    targetIds = filteredSearchResults.value.map(m => m.id)
  } else {
    targetIds = filteredSearchResults.value.filter(m => !m.is_present).map(m => m.id)
  }

  if (isAllSelected.value) {
    // Remove these from selection
    selectedMembers.value = selectedMembers.value.filter(id => !targetIds.includes(id))
  } else {
    // Add these to selection
    const newSelection = new Set([...selectedMembers.value, ...targetIds])
    selectedMembers.value = Array.from(newSelection)
  }
}

const selectedCountByStatus = computed(() => {
  const selected = memberSearchResults.value.filter(m => selectedMembers.value.includes(m.id))
  return {
    absent: selected.filter(m => !m.is_present).length,
    present: selected.filter(m => m.is_present).length
  }
})

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

const fetchMeetingStats = async () => {
  if (!meeting.value) return
  loadingStats.value = true
  try {
    const { data } = await axios.get(`/api/meetings/${meeting.value.id}/stats`)
    meetingStats.value = data
  } catch (err) {
    console.error('Failed to fetch stats:', err)
  } finally {
    loadingStats.value = false
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
  // If search query is empty and no filters are active, clear results
  if (!memberSearchQuery.value && !searchBranchId.value && !searchGender.value && !expectedOnly.value) {
    memberSearchResults.value = []
    return
  }
  
  searchingMembers.value = true
  try {
    const params = new URLSearchParams({
      q: memberSearchQuery.value,
      meeting_id: meeting.value?.id || '',
      branch_id: searchBranchId.value,
      gender: searchGender.value,
      expected_only: expectedOnly.value ? '1' : '0',
      smart: appStatusStore.adminAttendanceSmartSearchEnabled ? '1' : '0'
    })
    const { data } = await axios.get(`/api/attendance/search-members?${params.toString()}`)
    memberSearchResults.value = data
  } catch (err) {
    console.error('Member search failed:', err)
  } finally {
    searchingMembers.value = false
  }
}

const fetchSearchFilters = async () => {
  try {
    const { data } = await axios.get('/api/attendance/search-filters')
    availableBranches.value = data.branches
  } catch (err) {
    console.error('Failed to fetch filters:', err)
  }
}

const startVoiceSearch = async () => {
  if (isListening.value) return

  // Web Speech API fallback
  const WebSpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition
  
  if (!isNative && WebSpeechRecognition) {
    const recognition = new WebSpeechRecognition()
    recognition.lang = 'en-US'
    recognition.interimResults = false
    recognition.maxAlternatives = 1

    recognition.onstart = () => { isListening.value = true }
    recognition.onend = () => { isListening.value = false }
    recognition.onresult = (event) => {
      const text = event.results[0][0].transcript
      memberSearchQuery.value = text
      searchMembers()
    }
    recognition.onerror = (event) => {
      console.error('Web Speech Error:', event.error)
      isListening.value = false
    }
    recognition.start()
    return
  }

  // Capacitor Plugin
  try {
    const available = await SpeechRecognition.available()
    if (!available.available) {
      modal.alert('Speech recognition is not available on this device.')
      return
    }

    const permission = await SpeechRecognition.checkPermissions()
    if (permission.speechRecognition !== 'granted') {
      const req = await SpeechRecognition.requestPermissions()
      if (req.speechRecognition !== 'granted') {
        modal.alert('Speech recognition permission denied.')
        return
      }
    }

    isListening.value = true
    SpeechRecognition.start({
      language: 'en-US',
      maxResults: 1,
      prompt: 'Say member name or number',
      partialResults: false,
      popup: true
    }).then(result => {
      if (result.matches && result.matches.length > 0) {
        memberSearchQuery.value = result.matches[0]
        searchMembers()
      }
    }).finally(() => {
      isListening.value = false
    })
  } catch (err) {
    console.error('Capacitor Speech Error:', err)
    isListening.value = false
  }
}

const processAdminScan = async (code) => {
  if (!code) return

  let memberIdOrNum = code
  if (code.includes('member?id=')) {
    memberIdOrNum = code.split('id=')[1]
  }
  
  memberSearchQuery.value = memberIdOrNum
  searchingMembers.value = true
  try {
    const { data } = await axios.get('/api/attendance/search-members', {
      params: {
        q: memberIdOrNum,
        meeting_id: meeting.value.id,
        smart: true
      }
    })
    memberSearchResults.value = data
    
    // If unique match and quickMark enabled, auto mark
    if (data.length === 1 && !data[0].is_present) {
      if (quickMark.value) {
        await markForMemberAction(data[0])
        // Give a small feedback and allow scanning again
        setTimeout(() => {
           if (isNative) {
             adminScanQr()
           } else {
             scanMode.value = 'admin'
             showWebScanner.value = true
           }
        }, 500)
      }
    }
  } catch (err) {
    console.error("Search failed after scan", err)
  } finally {
    searchingMembers.value = false
  }
}

const adminScanQr = async () => {
  if (!isNative) {
    scanMode.value = 'admin'
    showWebScanner.value = true
    return
  }

  try {
    const perm = await BarcodeScanner.checkPermissions()
    if (perm.camera !== 'granted') {
      const req = await BarcodeScanner.requestPermissions()
      if (req.camera !== 'granted') return
    }
    
    // Support QR and 1D barcodes
    const { barcodes } = await BarcodeScanner.scan({ 
      formats: ['qrCode', 'code128', 'code39', 'ean13', 'upca'],
      lensFacing: 'back'
    })
    
    if (barcodes.length > 0) {
      const code = barcodes[0].rawValue || barcodes[0].displayValue
      processAdminScan(code)
    }
  } catch (err) {
    console.error('Admin Scan Error:', err)
  }
}

const addToRecent = (member) => {
  let recent = [...recentMarked.value]
  // Remove if exists
  recent = recent.filter(m => m.id !== member.id)
  // Add to front
  recent.unshift({
    id: member.id,
    name: member.name,
    surname: member.surname,
    membership_number: member.membership_number,
    passport_path: member.passport_path
  })
  // Limit to 10
  recent = recent.slice(0, 10)
  recentMarked.value = recent
  localStorage.setItem('recent_marked', JSON.stringify(recent))
}

const scrollToLetter = (letter) => {
  const target = memberSearchResults.value.find(m => 
    m.surname?.toUpperCase().startsWith(letter) || m.name?.toUpperCase().startsWith(letter)
  )
  if (target) {
    const el = document.getElementById(`member-${target.id}`)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

const markForMemberAction = async (member) => {
  if (!quickMark.value) {
    const confirm = await modal.confirm(`Mark attendance for ${member.name} ${member.surname}?`)
    if (!confirm) return
  }

  markingForMember.value = member.id
  try {
    const res = await axios.post(`/api/meetings/${meeting.value.id}/mark-member-attendance`, {
      user_id: member.id
    })
    
    if (res.data.success || res.data.record) {
      if (!quickMark.value) modal.alert(res.data.message || "Attendance marked successfully")
      // Update local state to reflect change immediately
      member.is_present = true
      fetchMarkedByMe()
      fetchMeetingStats()
      addToRecent(member)
      
      // Remove from selected if there
      selectedMembers.value = selectedMembers.value.filter(id => id !== member.id)
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

const bulkMarkAction = async () => {
  if (selectedMembers.value.length === 0) return
  
  const count = selectedMembers.value.length
  if (!quickMark.value) {
    const confirm = await modal.confirm(`Mark attendance for ${count} selected members?`)
    if (!confirm) return
  }

  bulkMarking.value = true
  try {
    const res = await axios.post(`/api/meetings/${meeting.value.id}/bulk-mark-attendance`, {
      user_ids: selectedMembers.value
    })
    
    if (res.data.success) {
      modal.alert(res.data.message || `Successfully marked ${count} members.`)
      
      // Update local state for all selected members
      selectedMembers.value.forEach(id => {
        const member = memberSearchResults.value.find(m => m.id === id)
        if (member) {
          member.is_present = true
          addToRecent(member)
        }
      })
      
      // Keep selectedThoseWhoWereAlreadyPresent if any
      selectedMembers.value = selectedMembers.value.filter(id => {
        const m = memberSearchResults.value.find(m => m.id === id)
        return m && m.is_present // Actually we should probably just clear or filter carefully
      })
      // Simpler: just remove those we just marked
      selectedMembers.value = selectedMembers.value.filter(id => {
         const m = memberSearchResults.value.find(item => item.id === id)
         return m ? false : true // if we found it in search results, it was probably marked
      })
      // Actually the backend response might be better. 
      // Let's just clear for now or filter by those who are now present
      selectedMembers.value = []
      fetchMarkedByMe()
      fetchMeetingStats()
    } else {
      modal.alert(res.data.message || "Failed to bulk mark attendance", "Attendance Error")
    }
  } catch (err) {
    modal.alert(err.response?.data?.message || "Error during bulk marking", "Attendance Error")
  } finally {
    bulkMarking.value = false
  }
}

const bulkUnmarkAction = async () => {
  const toUnmark = memberSearchResults.value.filter(m => selectedMembers.value.includes(m.id) && m.is_present)
  if (toUnmark.length === 0) return
  
  const count = toUnmark.length
  if (!quickMark.value) {
    const confirm = await modal.confirm(`Remove attendance for ${count} selected members?`)
    if (!confirm) return
  }

  bulkMarking.value = true
  try {
    const res = await axios.post(`/api/meetings/${meeting.value.id}/bulk-unmark-attendance`, {
      user_ids: toUnmark.map(m => m.id)
    })
    
    if (res.data.success) {
      if (!quickMark.value) modal.alert(res.data.message || `Successfully unmarked ${count} members.`)
      
      // Update local state
      toUnmark.forEach(m => {
        m.is_present = false
      })
      
      selectedMembers.value = selectedMembers.value.filter(id => !toUnmark.map(m => m.id).includes(id))
      fetchMarkedByMe()
      fetchMeetingStats()
    } else {
      modal.alert(res.data.message || "Failed to bulk unmark attendance", "Attendance Error")
    }
  } catch (err) {
    modal.alert(err.response?.data?.message || "Error during bulk unmarking", "Attendance Error")
  } finally {
    bulkMarking.value = false
  }
}

const unmarkForMemberAction = async (memberOrRecord) => {
  const memberId = memberOrRecord.user_id || memberOrRecord.id
  const name = memberOrRecord.user ? `${memberOrRecord.user.name} ${memberOrRecord.user.surname}` : `${memberOrRecord.name} ${memberOrRecord.surname}`
  
  const confirm = await modal.confirm(`Are you sure you want to UNMARK attendance for ${name}? This will remove their presence record.`)
  if (!confirm) return

  unmarkingForMember.value = memberId
  try {
    const res = await axios.post(`/api/meetings/${meeting.value.id}/unmark-member-attendance`, {
      user_id: memberId
    })
    
    if (res.data.success) {
      modal.alert("Attendance unmarked successfully")
      // Update search results if visible
      const foundInSearch = memberSearchResults.value.find(m => m.id === memberId)
      if (foundInSearch) foundInSearch.is_present = false
      
      fetchMarkedByMe()
      fetchMeetingStats()
    } else {
      modal.alert(res.data.message || "Failed to unmark attendance")
    }
  } catch (err) {
    modal.alert(err.response?.data?.message || "Error unmarking attendance")
  } finally {
    unmarkingForMember.value = null
  }
}

const scanQr = async () => {
  if (!location.value) {
    modal.alert('Please capture your location first.')
    return
  }

  scanMode.value = 'member'
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
        fetchMeetingStats()
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
  
  if (scanMode.value === 'admin') {
    processAdminScan(code)
    return
  }

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
  if (canMarkForOthers.value) {
    fetchSearchFilters()
  }

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

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.3s ease-out;
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
