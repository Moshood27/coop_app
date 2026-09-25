<template>
  <div class="space-y-6">
    <!-- P2P Transfer Form -->
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-100 transition-all">
      <h3 class="font-bold text-slate-800 mb-4">Transfer to Member</h3>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <button v-for="type in ['phone', 'membership']" :key="type"
                  @click="$emit('update:toType', type)"
                  :class="toType === type ? 'bg-emerald-700 text-white border-emerald-700' : 'bg-slate-50 text-slate-600 border-slate-100'"
                  class="p-3 rounded-xl border text-[10px] font-black uppercase tracking-wider transition-all">
            {{ type === 'phone' ? 'Phone' : 'Member ID' }}
          </button>
        </div>

        <div>
          <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">
            {{ toType === 'phone' ? 'Phone Number' : 'Membership ID' }}
          </label>
          <div class="flex gap-2">
            <input :value="toValue" @input="$emit('update:toValue', $event.target.value)" type="text"
                   class="flex-1 bg-slate-50 p-4 rounded-2xl border border-slate-100 text-sm outline-none focus:border-emerald-500 transition-all"
                   :placeholder="toType === 'phone' ? 'e.g. 0803...' : 'e.g. MEM123'" />
            <button @click="$emit('verify')" type="button" class="shrink-0 bg-emerald-50 text-emerald-700 px-5 rounded-2xl text-[10px] font-black uppercase tracking-wider hover:bg-emerald-100 transition-colors">
              Verify
            </button>
          </div>
        </div>

        <div v-if="toType === 'membership'" class="space-y-4">
          <div>
            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Branch ID (Optional)</label>
            <input :value="branchId" @input="$emit('update:branchId', $event.target.value)" type="number" min="1"
                   class="w-full bg-slate-50 p-4 rounded-2xl border border-slate-100 text-sm outline-none focus:border-emerald-500 transition-all"
                   placeholder="ID if known" />
          </div>

          <!-- Recipient preview / disambiguation -->
          <div v-if="recipient" class="p-4 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-emerald-700 text-white flex items-center justify-center font-bold">
              {{ recipient.name[0] }}
            </div>
            <div class="min-w-0">
              <p class="text-[10px] font-black text-emerald-800 uppercase tracking-widest leading-none mb-1">Recipient Found</p>
              <p class="text-sm font-bold text-slate-800 truncate">{{ recipient.name }}</p>
              <p class="text-[10px] text-emerald-600 font-medium">{{ recipient.membership_number }} • {{ recipient.branch_name }}</p>
            </div>
          </div>

          <div v-else-if="recipientError" class="p-4 rounded-2xl bg-amber-50 border border-amber-100 space-y-3">
            <p class="text-xs font-bold text-amber-800">{{ recipientError }}</p>
            <div v-if="branchesOptions.length" class="flex flex-wrap gap-2">
              <button v-for="b in branchesOptions" :key="b.id" type="button" @click="$emit('chooseBranch', b)"
                      class="px-3 py-1.5 rounded-lg bg-white border border-amber-200 text-amber-700 text-[10px] font-bold uppercase hover:bg-amber-100 transition-colors">
                {{ b.name }}
              </button>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Amount</label>
          <div class="relative group">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 font-bold">₦</div>
            <input :value="transferAmount" @input="$emit('update:transferAmount', $event.target.value)" type="number" min="1" placeholder="0.00"
                   class="w-full bg-slate-50 pl-11 p-4 rounded-2xl border border-slate-100 text-sm outline-none focus:border-emerald-500 transition-all" />
          </div>
        </div>

        <div>
          <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Note (Optional)</label>
          <input :value="note" @input="$emit('update:note', $event.target.value)" type="text" maxlength="120"
                 class="w-full bg-slate-50 p-4 rounded-2xl border border-slate-100 text-sm outline-none focus:border-emerald-500 transition-all"
                 placeholder="Purpose of transfer" />
        </div>

        <button @click="$emit('transfer')" :disabled="loading || !canSend"
                class="w-full bg-emerald-700 text-white p-4 rounded-2xl font-bold shadow-lg shadow-emerald-700/20 disabled:opacity-50 transition-all active:scale-[0.98]">
          {{ loading ? 'Transferring…' : 'Send Funds' }}
        </button>
        <p class="text-[10px] text-slate-500 text-center">Confirmation with Transaction PIN or Biometrics required.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  toType: String,
  toValue: String,
  branchId: [Number, String],
  recipient: Object,
  recipientError: String,
  branchesOptions: Array,
  transferAmount: [Number, String],
  note: String,
  loading: Boolean,
  canSend: Boolean
})

defineEmits(['update:toType', 'update:toValue', 'update:branchId', 'update:transferAmount', 'update:note', 'verify', 'chooseBranch', 'transfer'])
</script>
