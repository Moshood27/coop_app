<template>
  <div class="min-h-screen bg-slate-900 flex flex-col items-center justify-center p-6 pb-20 overflow-hidden relative">
    <!-- Background Decor -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none no-print">
       <div class="absolute -top-20 -left-20 w-80 h-80 bg-emerald-500/10 rounded-full blur-3xl"></div>
       <div class="absolute -bottom-20 -right-20 w-80 h-80 bg-emerald-500/10 rounded-full blur-3xl"></div>
    </div>

    <!-- Header / Close -->
    <div class="absolute top-6 left-6 right-6 flex items-center justify-between z-20 no-print">
      <button @click="$router.back()" class="w-10 h-10 bg-white/10 hover:bg-white/20 rounded-2xl flex items-center justify-center text-white backdrop-blur-lg transition-all active:scale-90 shadow-xl border border-white/10">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h1 class="text-emerald-500 font-black tracking-[0.2em] text-xs uppercase">Digital Identity Card</h1>
      <div class="w-10"></div>
    </div>

    <!-- The Card Container with Flip Effect (screen only) -->
    <div class="w-full max-w-[340px] perspective-1000 no-print">
      <div ref="cardRef" class="relative w-full aspect-[2/3] transition-all duration-700 preserve-3d cursor-pointer shadow-2xl rounded-[2.5rem] print-card"
           :class="{ 'rotate-y-180': isFlipped }"
           @click.stop="isFlipped = !isFlipped">
        
        <!-- Front of the Card -->
        <div class="absolute inset-0 backface-hidden bg-gradient-to-br from-emerald-800 to-emerald-950 rounded-[2.5rem] p-8 border border-white/20 flex flex-col items-center justify-between overflow-hidden shadow-2xl">
           <!-- Card Gloss / Texture -->
           <div class="absolute top-0 left-0 w-full h-full bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.1),transparent)] pointer-events-none"></div>
           <div class="absolute -bottom-20 -right-20 w-60 h-60 bg-white/5 rounded-full"></div>
           
           <!-- Logo & Header -->
           <div class="w-full flex flex-col items-center gap-2 relative z-10">
              <div class="w-16 h-16 bg-white/10 backdrop-blur-md rounded-2xl border border-white/20 flex items-center justify-center shadow-inner">
                 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10 text-emerald-300">
                   <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5a2.25 2.25 0 0 0 2.25 2.25Zm.75-12h3.75v3.75H5.25V7.5Z" />
                 </svg>
              </div>
              <div class="text-center">
                <h2 class="text-lg font-black text-white tracking-tight uppercase leading-none">Attaqwa</h2>
                <p class="text-[8px] font-black text-emerald-300/80 tracking-[0.3em] uppercase mt-1">Cooperative Society</p>
              </div>
           </div>

           <!-- Member Photo -->
           <div class="relative z-10">
              <div class="w-32 h-32 rounded-[2.5rem] bg-emerald-900 border-4 border-white/20 p-1 shadow-2xl relative overflow-hidden">
                 <img v-if="user.passport_url" :src="getImageUrl(user.passport_url)" crossorigin="anonymous" class="w-full h-full object-cover rounded-[2rem]" />
                 <div v-else class="w-full h-full flex items-center justify-center text-5xl font-black text-emerald-700 bg-emerald-50">
                   {{ (user.full_name || 'M')[0] }}
                 </div>
              </div>
              <div class="absolute -bottom-2 -right-2 w-10 h-10 bg-emerald-500 rounded-2xl border-4 border-emerald-900 flex items-center justify-center text-white shadow-lg">
                 <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                   <path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.498 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.307 4.49 4.49 0 0 1-1.307-3.497A4.49 4.49 0 0 1 2.25 12a4.49 4.49 0 0 1 1.549-3.397 4.491 4.491 0 0 1 1.307-3.498 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                 </svg>
              </div>
           </div>

           <!-- Member Info -->
           <div class="w-full text-center space-y-2 relative z-10">
              <div class="px-4">
                <h3 class="text-xl font-black text-white uppercase truncate">{{ user.full_name }}</h3>
                <p class="text-xs font-bold text-emerald-400 font-mono tracking-widest mt-1">{{ user.membership_id }}</p>
              </div>
              <div class="flex items-center justify-center gap-3">
                 <div class="px-3 py-1 bg-white/10 rounded-full border border-white/10 text-[9px] font-black text-white uppercase tracking-tighter backdrop-blur-sm">
                   {{ user.branch_name || 'Global Branch' }}
                 </div>
                 <div class="px-3 py-1 bg-emerald-500/20 rounded-full border border-emerald-500/20 text-[9px] font-black text-emerald-400 uppercase tracking-tighter backdrop-blur-sm">
                   Official Member
                 </div>
              </div>
           </div>

           <!-- Card Footer -->
           <div class="w-full flex items-center justify-between border-t border-white/10 pt-4 relative z-10">
              <div class="flex flex-col">
                 <span class="text-[7px] font-black text-emerald-400/60 uppercase tracking-widest">Valid Thru</span>
                 <span class="text-[10px] font-black text-white uppercase tracking-widest">PERMANENT</span>
              </div>
              <div class="flex flex-col items-end">
                 <span class="text-[7px] font-black text-emerald-400/60 uppercase tracking-widest">Verified Since</span>
                 <span class="text-[10px] font-black text-white uppercase tracking-widest">{{ user.date_joined || '2024' }}</span>
              </div>
           </div>
        </div>

        <!-- Back of the Card (QR Code) -->
        <div class="absolute inset-0 backface-hidden rotate-y-180 bg-white rounded-[2.5rem] p-8 border border-slate-200 flex flex-col items-center justify-between shadow-2xl isolate">
           <div class="absolute top-0 left-0 w-full h-8 bg-emerald-800 rounded-t-[2.5rem]"></div>
           
           <div class="mt-4 text-center">
             <h4 class="text-slate-800 font-black text-xs uppercase tracking-widest">Attendance QR Code</h4>
             <p class="text-[9px] text-slate-500 font-medium mt-1">Present this code for instant marking</p>
           </div>

          <!-- Large High-Contrast QR (local generator) -->
          <div v-if="qrDataUrl" class="w-full aspect-square bg-white rounded-[2rem] p-6 border-2 border-slate-200 flex items-center justify-center relative group overflow-hidden">
              <div class="absolute inset-0 bg-emerald-500/5 scale-0 group-hover:scale-100 transition-transform rounded-[2rem]"></div>
              <img :src="qrDataUrl"
                   alt="Member QR"
                   class="w-full h-full rounded-xl relative z-10 shadow-sm" />
          </div>
          <div v-else class="w-full aspect-square bg-slate-50 rounded-[2rem] p-6 border-2 border-slate-100 flex items-center justify-center relative group">
              <div class="animate-pulse flex flex-col items-center">
                 <div class="w-12 h-12 bg-slate-200 rounded-full mb-2"></div>
                 <div class="h-2 w-24 bg-slate-200 rounded"></div>
              </div>
          </div>

           <div class="w-full space-y-4">
              <div class="flex items-center gap-3 p-3 bg-emerald-50 rounded-2xl border border-emerald-100">
                 <div class="w-8 h-8 rounded-xl bg-emerald-600 flex items-center justify-center text-white shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                    </svg>
                 </div>
                 <p class="text-[9px] font-bold text-emerald-800 leading-tight">Increase screen brightness to maximum for better scan reliability.</p>
              </div>

              <div class="text-center">
                 <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest">Property of Attaqwa Cooperative</p>
              </div>
           </div>
        </div>
      </div>
    </div>

    <!-- Flip Instructions -->
    <div class="mt-12 text-center animate-bounce-slow no-print">
       <p class="text-white/40 text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-2">
         <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
           <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
         </svg>
         Tap Card to Flip
       </p>
    </div>

    <!-- Bottom Action: Print only -->
    <div class="fixed left-4 right-4 md:left-6 md:right-6 flex items-center justify-center z-50 no-print pointer-events-auto bottom-actions"
         style="touch-action: manipulation;">
      <button @click="printId" aria-label="Print Digital ID" role="button" class="w-full max-w-[340px] h-16 md:h-14 bg-emerald-600 hover:bg-emerald-500 rounded-2xl flex items-center justify-center gap-2 text-white text-[12px] md:text-[11px] font-black uppercase tracking-widest shadow-xl shadow-emerald-900/40 transition-all active:scale-95 pointer-events-auto drop-shadow-xl">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4.5A1.5 1.5 0 0 1 7.5 3h9A1.5 1.5 0 0 1 18 4.5V9M6 15H5.25A2.25 2.25 0 0 1 3 12.75v-3A2.25 2.25 0 0 1 5.25 7.5h13.5A2.25 2.25 0 0 1 21 9.75v3A2.25 2.25 0 0 1 18.75 15H18m-12 0h12M6 15v4.5A1.5 1.5 0 0 0 7.5 21h9a1.5 1.5 0 0 0 1.5-1.5V15" />
          </svg>
          Print ID Card
      </button>
    </div>

    <!-- Print layout: two sides on paper -->
    <div class="print-area" aria-hidden="true">
      <div class="print-grid">
        <!-- Front (print) -->
        <div class="id-card-print">
          <div class="absolute top-0 left-0 w-full h-full bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.1),transparent)] pointer-events-none"></div>
          <div class="w-full h-full bg-gradient-to-br from-emerald-800 to-emerald-950 rounded-[7mm] p-[6mm] border border-white/20 flex flex-col items-center justify-between overflow-hidden">
            <div class="w-full flex flex-col items-center gap-2">
              <div class="w-16 h-16 bg-white/10 rounded-2xl border border-white/20 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10 text-emerald-300">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5a2.25 2.25 0 0 0 2.25 2.25Zm.75-12h3.75v3.75H5.25V7.5Z" />
                </svg>
              </div>
              <div class="text-center">
                <h2 class="text-lg font-black text-white tracking-tight uppercase leading-none">Attaqwa</h2>
                <p class="text-[8px] font-black text-emerald-300/80 tracking-[0.3em] uppercase mt-1">Cooperative Society</p>
              </div>
            </div>
            <div>
              <div class="w-28 h-28 rounded-[6mm] bg-emerald-900 border-4 border-white/20 p-1 shadow-2xl overflow-hidden">
                <img v-if="user.passport_url" :src="getImageUrl(user.passport_url)" crossorigin="anonymous" class="w-full h-full object-cover rounded-[5mm]" />
                <div v-else class="w-full h-full flex items-center justify-center text-4xl font-black text-emerald-700 bg-emerald-50">
                  {{ (user.full_name || 'M')[0] }}
                </div>
              </div>
            </div>
            <div class="w-full text-center space-y-1">
              <h3 class="text-base font-black text-white uppercase truncate">{{ user.full_name }}</h3>
              <p class="text-[10px] font-bold text-emerald-400 font-mono tracking-widest">{{ user.membership_id }}</p>
              <div class="flex items-center justify-center gap-2">
                <div class="px-2 py-0.5 bg-white/10 rounded-full border border-white/10 text-[8px] font-black text-white uppercase tracking-tighter">{{ user.branch_name || 'Global Branch' }}</div>
                <div class="px-2 py-0.5 bg-emerald-500/20 rounded-full border border-emerald-500/20 text-[8px] font-black text-emerald-400 uppercase tracking-tighter">Official Member</div>
              </div>
              <div class="w-full flex items-center justify-between border-t border-white/10 pt-2">
                <div class="flex flex-col text-left">
                  <span class="text-[7px] font-black text-emerald-400/60 uppercase tracking-widest">Valid Thru</span>
                  <span class="text-[9px] font-black text-white uppercase tracking-widest">PERMANENT</span>
                </div>
                <div class="flex flex-col items-end text-right">
                  <span class="text-[7px] font-black text-emerald-400/60 uppercase tracking-widest">Verified Since</span>
                  <span class="text-[9px] font-black text-white uppercase tracking-widest">{{ user.date_joined || '2024' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- Back (print) -->
        <div class="id-card-print bg-white">
          <div class="w-full h-full bg-white rounded-[7mm] p-[6mm] border border-slate-200 flex flex-col items-center justify-between">
            <div class="mt-1 text-center">
              <h4 class="text-slate-800 font-black text-xs uppercase tracking-widest">Attendance QR Code</h4>
            </div>
            <div class="w-full flex-1 flex items-center justify-center">
              <div class="w-full h-full bg-white rounded-[5mm] p-[4mm] border-2 border-slate-200 flex items-center justify-center overflow-hidden">
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="Member QR" class="w-full h-full rounded-[3mm]" />
              </div>
            </div>
            <div class="text-center">
              <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest">Property of Attaqwa Cooperative</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from '../http'
import { useRouter } from 'vue-router'
import getImageUrl from '../utils/image'
import { Clipboard } from '@capacitor/clipboard'
import QRCode from 'qrcode'

const router = useRouter()
const user = ref({})
const isFlipped = ref(false)
const cardRef = ref(null)
const qrDataUrl = ref('')

const load = async () => {
  try {
    const { data } = await axios.get('/api/dashboard')
    user.value = data
  } catch (err) {
    console.error('Failed to load user info', err)
  }
}

const generateQr = async () => {
  try {
    const id = user.value?.membership_id
    if (!id) {
      qrDataUrl.value = ''
      return
    }
    const data = `attaqwa:member?id=${id}`
    qrDataUrl.value = await QRCode.toDataURL(data, {
      width: 600,
      margin: 2,
      errorCorrectionLevel: 'H',
      color: { dark: '#000000', light: '#ffffff' }
    })
  } catch (e) {
    console.error('QR generation failed', e)
    qrDataUrl.value = ''
  }
}

const shareId = async () => {
  const text = `Attaqwa Digital ID\nName: ${user.value.full_name || ''}\nMembership ID: ${user.value.membership_id || ''}\nLink: ${window.location.href}`
  try {
    if (navigator.share) {
      await navigator.share({
        title: 'Attaqwa Digital ID',
        text
      })
      return
    }
  } catch (err) {
    console.error('Web Share failed', err)
  }
  try {
    await Clipboard.write({ string: text })
    window.alert('ID details copied to clipboard.')
  } catch (err) {
    console.error('Clipboard write failed', err)
  }
}

const downloadCard = async () => {
  try {
    const el = cardRef.value
    if (!el) throw new Error('Card element not found')

    // Try html-to-image first (better with modern CSS like oklch via foreignObject)
    let blob = null
    try {
      const htmlToImage = await import('html-to-image')
      blob = await htmlToImage.toBlob(el, {
        backgroundColor: '#ffffff',
        pixelRatio: 2,
        cacheBust: true,
        // Avoid trying to inline remote webfonts (Google Fonts) which can
        // throw SecurityError when accessing cross-origin CSSStyleSheet.
        skipFonts: true
      })
    } catch (e) {
      console.warn('html-to-image failed, will try html2canvas', e)
    }

    // Fallback to html2canvas if needed
    if (!blob) {
      const { default: html2canvas } = await import('html2canvas')
      const canvas = await html2canvas(el, {
        backgroundColor: '#ffffff',
        scale: 2,
        useCORS: true,
        foreignObjectRendering: true
      })
      blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'))
    }

    if (!blob) throw new Error('Failed to generate image')

    const fileName = `attaqwa-id-${user.value.membership_id || 'member'}.png`
    const file = new File([blob], fileName, { type: 'image/png' })

    if (navigator.canShare && navigator.canShare({ files: [file] })) {
      await navigator.share({ files: [file], title: 'Attaqwa Digital ID' })
      return
    }

    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = fileName
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch (err) {
    console.error('Save Image failed, falling back to print', err)
    window.print()
  }
}

onMounted(() => {
  load()
})

watch(() => user.value?.membership_id, () => {
  generateQr()
})

const printId = async () => {
  try {
    // ensure QR is ready before printing
    if (!qrDataUrl.value) await generateQr()
    setTimeout(() => window.print(), 150)
  } catch (e) {
    window.print()
  }
}
</script>

<style scoped>
.perspective-1000 {
  perspective: 1000px;
}
.preserve-3d {
  transform-style: preserve-3d;
}
.backface-hidden {
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
}
.rotate-y-180 {
  transform: rotateY(180deg);
}
.animate-bounce-slow {
  animation: bounce-slow 3s infinite;
}
@keyframes bounce-slow {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

/* Custom shadow for emerald colors */
.shadow-emerald-900\/40 {
  shadow: 0 10px 15px -3px rgba(6, 78, 59, 0.4), 0 4px 6px -4px rgba(6, 78, 59, 0.4);
}

/* Safe-area aware bottom bar position */
.bottom-actions {
  bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
}

/* Print optimizations */
@media print {
  .no-print { display: none !important; }
  .print-card { box-shadow: none !important; }
  .bg-slate-900 { background-color: #ffffff !important; }
}

/* Print layout */
.print-area { display: none; }
@media print {
  .print-area { display: block !important; margin: 0 auto; }
  .print-grid {
    display: grid;
    grid-template-rows: auto auto;
    gap: 12mm;
    justify-content: center;
    align-content: start;
    padding: 10mm;
  }
  .id-card-print {
    width: 85.6mm; /* CR80 width */
    height: 54mm;  /* CR80 height */
    border-radius: 7mm;
    position: relative;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    box-shadow: none !important;
    overflow: hidden;
  }
}
</style>
