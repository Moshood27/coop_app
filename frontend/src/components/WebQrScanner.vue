<template>
  <div class="fixed inset-0 z-[110] flex flex-col bg-black">
    <div class="p-4 flex items-center justify-between text-white z-10 bg-black/50 backdrop-blur-md">
      <h3 class="text-lg font-bold">Scan QR Code</h3>
      <button @click="$emit('close')" class="p-2 bg-white/10 rounded-full hover:bg-white/20 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
    
    <div class="flex-1 flex items-center justify-center relative overflow-hidden">
      <div id="qr-reader" class="w-full max-w-md h-full sm:h-auto"></div>
      
      <!-- Scan Overlay -->
      <div class="absolute inset-0 pointer-events-none flex flex-col items-center justify-center">
        <div class="w-64 h-64 border-2 border-emerald-500 rounded-3xl relative">
          <div class="absolute inset-0 border-[40px] border-black/30 -m-[42px] rounded-[3rem]"></div>
          <div class="absolute -top-1 -left-1 w-8 h-8 border-t-4 border-l-4 border-emerald-500 rounded-tl-xl"></div>
          <div class="absolute -top-1 -right-1 w-8 h-8 border-t-4 border-r-4 border-emerald-500 rounded-tr-xl"></div>
          <div class="absolute -bottom-1 -left-1 w-8 h-8 border-b-4 border-l-4 border-emerald-500 rounded-bl-xl"></div>
          <div class="absolute -bottom-1 -right-1 w-8 h-8 border-b-4 border-r-4 border-emerald-500 rounded-br-xl"></div>
          
          <!-- Scanning Line -->
          <div class="absolute top-0 left-0 w-full h-1 bg-emerald-500/50 shadow-[0_0_15px_rgba(16,185,129,0.8)] animate-scan"></div>
        </div>
        <p class="mt-8 text-white/70 text-sm font-medium animate-pulse">Align QR code within the frame</p>
      </div>
    </div>
    
    <div class="p-6 bg-black/50 backdrop-blur-md flex justify-center">
       <p class="text-white/50 text-[10px] uppercase tracking-widest font-black">Powered by Attaqwa</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import { Html5QrcodeScanner, Html5Qrcode } from 'html5-qrcode'

const props = defineProps({
  fps: { type: Number, default: 10 },
  qrbox: { type: Number, default: 250 }
})

const emit = defineEmits(['scan', 'close', 'error'])

let html5QrCode = null

onMounted(async () => {
  // Give a small delay to ensure DOM is ready
  setTimeout(async () => {
    try {
      html5QrCode = new Html5Qrcode("qr-reader")
      const config = { 
        fps: props.fps, 
        qrbox: { width: props.qrbox, height: props.qrbox },
        aspectRatio: 1.0
      }

      await html5QrCode.start(
        { facingMode: "environment" },
        config,
        (decodedText, decodedResult) => {
          emit('scan', decodedText)
          stopScanner()
        },
        (errorMessage) => {
          // ignore common "no code found" errors as they are frequent during scanning
        }
      )
    } catch (err) {
      console.error("Scanner start error:", err)
      emit('error', err)
    }
  }, 300)
})

const stopScanner = async () => {
  if (html5QrCode && html5QrCode.isScanning) {
    try {
      await html5QrCode.stop()
    } catch (err) {
      console.error("Failed to stop scanner", err)
    }
  }
}

onUnmounted(async () => {
  await stopScanner()
})
</script>

<style scoped>
@keyframes scan {
  0%, 100% { top: 0; }
  50% { top: 100%; }
}
.animate-scan {
  animation: scan 2s infinite ease-in-out;
}

#qr-reader :deep(video) {
  width: 100% !important;
  height: 100% !important;
  object-fit: cover !important;
}

#qr-reader :deep(button),
#qr-reader :deep(select),
#qr-reader :deep(input) {
  display: none !important;
}

#qr-reader :deep(img) {
  display: none !important;
}

#qr-reader {
    border: none !important;
}
</style>
