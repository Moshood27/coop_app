<template>
  <div class="min-h-screen bg-[#F8FAFC]">
    <!-- Professional Header -->
    <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-slate-100">
      <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
        <div class="flex items-center gap-2">
          <button @click="$router.push('/dashboard')" class="p-2 -ml-2 hover:bg-slate-100 rounded-full transition-colors">
            <span class="i-mdi-arrow-left text-xl text-slate-600"></span>
          </button>
          <h1 class="text-xl font-bold bg-gradient-to-r from-emerald-600 to-emerald-800 bg-clip-text text-transparent">CoopStore</h1>
        </div>

        <div class="hidden md:flex flex-1 max-w-xl relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 i-mdi-magnify text-slate-400 text-xl"></span>
          <input 
            v-model="q" 
            @keyup.enter="load(1)" 
            type="search" 
            placeholder="Search products, categories..." 
            class="w-full bg-slate-100 border-none rounded-full py-2 pl-10 pr-4 focus:ring-2 focus:ring-emerald-500/20 transition-all text-sm"
          />
        </div>

        <div class="flex items-center gap-1">
          <button 
            class="relative p-2 hover:bg-slate-100 rounded-full transition-colors"
            @click="showCart = true"
          >
            <span class="i-mdi-cart-outline text-2xl text-slate-700"></span>
            <span v-if="totalQty" class="absolute top-1 right-1 w-5 h-5 bg-emerald-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-white animate-bounce-short">
              {{ totalQty }}
            </span>
          </button>
          
          <button 
            class="p-2 hover:bg-slate-100 rounded-full transition-colors"
            @click="$router.push('/store/orders')"
            title="My Orders"
          >
            <span class="i-mdi-package-variant-closed text-2xl text-slate-700"></span>
          </button>

          <div class="w-px h-6 bg-slate-200 mx-1"></div>

          <button 
            v-if="vendor && vendor.id" 
            class="flex items-center gap-2 px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-full hover:bg-emerald-100 transition-colors"
            @click="$router.push('/vendor/dashboard')"
          >
            <span class="i-mdi-store text-lg"></span>
            <span class="text-xs font-bold hidden sm:inline">Vendor Portal</span>
          </button>
          <button 
            v-else 
            class="px-4 py-1.5 bg-slate-900 text-white rounded-full text-xs font-bold hover:bg-slate-800 transition-colors"
            @click="$router.push('/vendor/apply')"
          >
            Sell on Coop
          </button>
        </div>
      </div>
      
      <!-- Mobile Search Bar -->
      <div class="md:hidden px-4 pb-3">
        <div class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 i-mdi-magnify text-slate-400 text-lg"></span>
          <input 
            v-model="q" 
            @keyup.enter="load(1)" 
            type="search" 
            placeholder="Search products..." 
            class="w-full bg-slate-100 border-none rounded-full py-2.5 pl-10 pr-4 focus:ring-2 focus:ring-emerald-500/20 text-sm"
          />
        </div>
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6 pb-32 md:flex md:gap-8">
      <!-- Sidebar Filters (Desktop) -->
      <aside class="hidden md:block w-64 shrink-0 space-y-8">
        <div>
          <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4">Categories</h3>
          <nav class="space-y-1">
            <button 
              @click="selectedCategory = 0; load(1)"
              :class="[selectedCategory === 0 ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50']"
              class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-3"
            >
              <span class="i-mdi-apps text-lg"></span>
              All Products
            </button>
            <button 
              v-for="c in categories" 
              :key="c.id"
              @click="selectedCategory = c.id; load(1)"
              :class="[selectedCategory === c.id ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-50']"
              class="w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-3"
            >
              <span :class="c.icon || 'i-mdi-tag-outline'" class="text-lg opacity-70"></span>
              {{ c.name }}
            </button>
          </nav>
        </div>

        <div>
          <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4">Price Range</h3>
          <div class="space-y-3">
            <div class="flex items-center gap-2">
              <input v-model="minPrice" type="number" placeholder="Min" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs focus:ring-emerald-500/20 outline-none" />
              <span class="text-slate-400 text-xs">—</span>
              <input v-model="maxPrice" type="number" placeholder="Max" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs focus:ring-emerald-500/20 outline-none" />
            </div>
            <button @click="load(1)" class="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition-colors">Apply Filter</button>
          </div>
        </div>

        <div v-if="isAdmin" class="p-4 bg-amber-50 rounded-2xl border border-amber-100">
          <h4 class="text-[10px] font-black text-amber-600 uppercase tracking-widest mb-2">Admin Tools</h4>
          <p class="text-xs text-amber-700 mb-3 leading-relaxed">You can moderate products directly from the storefront.</p>
          <div class="w-full h-px bg-amber-200/50 mb-3"></div>
          <button @click="$router.push('/admin/store')" class="text-xs font-bold text-amber-800 hover:underline">Manage All Inventory →</button>
        </div>
      </aside>

      <!-- Main Content -->
      <div class="flex-1 min-w-0">
        <!-- Horizontal Categories (Mobile) -->
        <div class="md:hidden flex gap-2 overflow-x-auto pb-4 hide-scrollbar -mx-4 px-4 mb-4">
          <button 
            @click="selectedCategory = 0; load(1)"
            :class="[selectedCategory === 0 ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-900/20' : 'bg-white text-slate-600 border border-slate-200']"
            class="whitespace-nowrap px-4 py-2 rounded-full text-xs font-bold transition-all"
          >
            All
          </button>
          <button 
            v-for="c in categories" 
            :key="c.id"
            @click="selectedCategory = c.id; load(1)"
            :class="[selectedCategory === c.id ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-900/20' : 'bg-white text-slate-600 border border-slate-200']"
            class="whitespace-nowrap px-4 py-2 rounded-full text-xs font-bold transition-all flex items-center gap-2"
          >
            <span v-if="c.icon" :class="c.icon"></span>
            {{ c.name }}
          </button>
        </div>

        <!-- Toolbar -->
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-bold text-slate-900">
            {{ selectedCategory ? categories.find(c => c.id === selectedCategory)?.name : 'All Products' }}
            <span class="ml-2 text-sm font-normal text-slate-400">({{ totalItems || 0 }})</span>
          </h2>
          
          <select v-model="sortBy" @change="load(1)" class="bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs font-bold text-slate-600 outline-none focus:ring-2 focus:ring-emerald-500/20">
            <option value="newest">Newest First</option>
            <option value="price_asc">Price: Low to High</option>
            <option value="price_desc">Price: High to Low</option>
            <option value="name_asc">Alphabetical</option>
          </select>
        </div>

        <!-- Products Grid -->
        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="i in 6" :key="i" class="bg-white rounded-3xl p-4 border border-slate-100 animate-pulse">
            <div class="aspect-square bg-slate-100 rounded-2xl mb-4"></div>
            <div class="h-4 bg-slate-100 rounded w-2/3 mb-2"></div>
            <div class="h-3 bg-slate-100 rounded w-full mb-4"></div>
            <div class="h-8 bg-slate-100 rounded w-full"></div>
          </div>
        </div>

        <div v-else-if="!items.length" class="bg-white rounded-[2rem] p-16 text-center border border-dashed border-slate-200">
          <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <span class="i-mdi-magnify-close text-4xl text-slate-300"></span>
          </div>
          <h3 class="text-xl font-bold text-slate-800 mb-2">No matching products</h3>
          <p class="text-slate-500 mb-6">We couldn't find what you're looking for. Try a different search term or category.</p>
          <button @click="q = ''; selectedCategory = 0; load(1)" class="px-6 py-2 bg-emerald-600 text-white rounded-full font-bold text-sm">Clear all filters</button>
        </div>

        <div v-else>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="p in items" :key="p.id" class="group bg-white rounded-3xl border border-slate-100 hover:border-emerald-200 transition-all duration-300 flex flex-col overflow-hidden hover:shadow-xl hover:shadow-emerald-900/5">
              <!-- Image -->
              <div class="aspect-square relative overflow-hidden bg-slate-50 cursor-pointer" @click="openQuick(p)">
                <img 
                  v-if="p.image_url" 
                  :src="getImageUrl(p.image_url)" 
                  class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" 
                />
                <div v-else class="w-full h-full flex items-center justify-center text-slate-200 bg-slate-50">
                   <span class="i-mdi-package-variant-closed text-6xl opacity-20"></span>
                </div>
                
                <!-- Overlay Badges -->
                <div class="absolute top-3 left-3 flex flex-col gap-2">
                  <span v-if="isNew(p.created_at)" class="bg-emerald-600 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded shadow-sm">New</span>
                  <span v-if="!p.is_approved" class="bg-amber-500 text-white text-[9px] font-black uppercase px-2 py-0.5 rounded shadow-sm">Pending</span>
                </div>

                <div v-if="p.track_stock && p.stock_quantity <= 0" class="absolute inset-0 bg-white/60 backdrop-blur-[2px] flex items-center justify-center">
                  <span class="bg-slate-900 text-white text-[10px] font-bold uppercase tracking-widest px-4 py-2 rounded-full">Out of Stock</span>
                </div>
              </div>

              <!-- Content -->
              <div class="p-5 flex-1 flex flex-col">
                <div class="flex items-center justify-between gap-2 mb-2">
                  <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest truncate">
                    {{ p.category?.name || 'General' }}
                  </span>
                  <span v-if="p.vendor" class="text-[9px] text-slate-400 font-medium italic truncate">
                    by {{ p.vendor.name }}
                  </span>
                </div>
                
                <h3 class="text-sm font-bold text-slate-800 mb-1 line-clamp-2 hover:text-emerald-700 transition-colors cursor-pointer" @click="openQuick(p)">
                  {{ p.name }}
                </h3>
                
                <div class="mt-auto pt-4 flex items-center justify-between border-t border-slate-50">
                  <div class="flex flex-col">
                    <span class="text-[10px] text-slate-400 font-medium">Price</span>
                    <span class="text-lg font-black text-slate-900">₦ {{ money(p.selling_price) }}</span>
                  </div>

                  <div class="flex items-center gap-1.5">
                    <template v-if="cart[p.id]">
                      <div class="flex items-center bg-slate-100 rounded-full p-1 border border-slate-200 shadow-inner">
                        <button @click="decQty(p.id)" class="w-7 h-7 flex items-center justify-center text-slate-600 hover:text-rose-600 transition-colors">
                          <span class="i-mdi-minus text-sm"></span>
                        </button>
                        <span class="w-6 text-center text-xs font-bold">{{ cart[p.id].qty }}</span>
                        <button @click="incQty(p.id)" class="w-7 h-7 flex items-center justify-center text-slate-600 hover:text-emerald-600 transition-colors" :disabled="p.track_stock && cart[p.id].qty >= p.stock_quantity">
                          <span class="i-mdi-plus text-sm"></span>
                        </button>
                      </div>
                    </template>
                    <button 
                      v-else-if="!p.track_stock || p.stock_quantity > 0" 
                      @click="addToCart(p)"
                      class="w-10 h-10 bg-emerald-600 hover:bg-emerald-700 text-white rounded-full flex items-center justify-center transition-all shadow-lg shadow-emerald-900/10 active:scale-90"
                    >
                      <span class="i-mdi-cart-plus text-xl"></span>
                    </button>
                  </div>
                </div>

                <!-- Admin Inline Actions -->
                <div v-if="isAdmin && !p.is_approved" class="mt-3 pt-3 border-t border-slate-50 flex gap-2">
                  <button @click="approveProduct(p)" class="flex-1 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-[10px] font-black uppercase tracking-wider hover:bg-emerald-600 hover:text-white transition-all">Approve</button>
                  <button @click="rejectProduct(p)" class="flex-1 py-1.5 bg-rose-50 text-rose-700 rounded-lg text-[10px] font-black uppercase tracking-wider hover:bg-rose-600 hover:text-white transition-all">Reject</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div class="flex items-center justify-between mt-12 py-6 border-t border-slate-100">
            <button 
              class="px-5 py-2 rounded-full border border-slate-200 bg-white text-sm font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-30 transition-all flex items-center gap-2" 
              :disabled="page <= 1 || loading" 
              @click="load(page - 1)"
            >
              <span class="i-mdi-chevron-left text-lg"></span>
              Previous
            </button>
            <div class="text-slate-400 text-xs font-bold uppercase tracking-widest">
              Page {{ page }} of {{ lastPage }}
            </div>
            <button 
              class="px-5 py-2 rounded-full border border-slate-200 bg-white text-sm font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-30 transition-all flex items-center gap-2" 
              :disabled="page >= lastPage || loading" 
              @click="load(page + 1)"
            >
              Next
              <span class="i-mdi-chevron-right text-lg"></span>
            </button>
          </div>
        </div>
      </div>
    </main>

    <!-- Side Cart Drawer -->
    <Transition name="drawer">
      <div v-if="showCart" class="fixed inset-0 z-50 overflow-hidden">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showCart = false"></div>
        <div class="absolute inset-y-0 right-0 max-w-full flex pl-10">
          <div class="w-screen max-w-md">
            <div class="h-full flex flex-col bg-white shadow-2xl">
              <div class="px-6 py-6 border-b border-slate-100 flex items-center justify-between bg-emerald-900 text-white">
                <div class="flex items-center gap-3">
                  <span class="i-mdi-cart text-2xl"></span>
                  <h2 class="text-lg font-bold">Shopping Cart</h2>
                </div>
                <button @click="showCart = false" class="p-2 -mr-2 hover:bg-white/10 rounded-full transition-colors">
                  <span class="i-mdi-close text-2xl"></span>
                </button>
              </div>

              <div class="flex-1 overflow-y-auto py-6 px-6 hide-scrollbar">
                <div v-if="!cartList.length" class="h-full flex flex-col items-center justify-center text-center">
                  <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mb-6">
                    <span class="i-mdi-cart-off text-5xl text-slate-200"></span>
                  </div>
                  <h3 class="text-xl font-bold text-slate-800 mb-2">Your cart is empty</h3>
                  <p class="text-slate-500 mb-8 max-w-[200px]">Looks like you haven't added anything to your cart yet.</p>
                  <button @click="showCart = false" class="px-8 py-3 bg-emerald-600 text-white rounded-full font-bold shadow-lg shadow-emerald-900/20 active:scale-95 transition-all">Start Shopping</button>
                </div>

                <div v-else class="space-y-6">
                  <div v-for="item in cartList" :key="item.id" class="flex gap-4">
                    <div class="w-20 h-20 bg-slate-50 rounded-2xl overflow-hidden border border-slate-100 shrink-0">
                      <img v-if="item.image_url" :src="getImageUrl(item.image_url)" class="w-full h-full object-cover" />
                      <div v-else class="w-full h-full flex items-center justify-center text-slate-200">
                        <span class="i-mdi-package-variant-closed text-2xl"></span>
                      </div>
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex items-start justify-between gap-2 mb-1">
                        <h4 class="text-sm font-bold text-slate-900 truncate">{{ item.name }}</h4>
                        <button @click="remove(item.id)" class="text-slate-400 hover:text-rose-500">
                          <span class="i-mdi-trash-can-outline text-lg"></span>
                        </button>
                      </div>
                      <p class="text-xs text-emerald-700 font-black mb-3">₦ {{ money(item.selling_price) }}</p>
                      
                      <div class="flex items-center justify-between">
                        <div class="flex items-center bg-slate-50 rounded-lg p-1 border border-slate-200">
                          <button @click="decQty(item.id)" class="w-6 h-6 flex items-center justify-center text-slate-500 hover:text-rose-600">
                            <span class="i-mdi-minus text-xs"></span>
                          </button>
                          <span class="w-8 text-center text-xs font-bold">{{ item.qty }}</span>
                          <button @click="incQty(item.id)" class="w-6 h-6 flex items-center justify-center text-slate-500 hover:text-emerald-600" :disabled="item.track_stock && item.qty >= item.stock_quantity">
                            <span class="i-mdi-plus text-xs"></span>
                          </button>
                        </div>
                        <span class="text-sm font-bold text-slate-900">₦ {{ money(item.selling_price * item.qty) }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="cartList.length" class="border-t border-slate-100 p-6 space-y-4">
                <div class="space-y-2">
                  <div class="flex items-center justify-between text-slate-500">
                    <span class="text-sm">Subtotal</span>
                    <span class="text-sm font-bold">₦ {{ money(subtotal) }}</span>
                  </div>
                  <div class="flex items-center justify-between text-slate-900">
                    <span class="text-base font-bold uppercase tracking-wider">Total</span>
                    <span class="text-xl font-black">₦ {{ money(subtotal) }}</span>
                  </div>
                </div>

                <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100">
                  <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                      <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Wallet Balance</span>
                      <button @click="loadWallet" :disabled="refreshingBalance" class="p-1 opacity-60 hover:opacity-100 transition-opacity disabled:opacity-30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" :class="{'animate-spin': refreshingBalance}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                      </button>
                    </div>
                    <span :class="hasInsufficient ? 'text-rose-600' : 'text-emerald-700'" class="text-xs font-bold">₦ {{ money(walletBalance) }}</span>
                  </div>
                  
                  <div v-if="hasInsufficient" class="p-3 bg-amber-50 rounded-xl border border-amber-100 space-y-3">
                     <div class="flex items-center gap-2">
                        <span class="i-mdi-alert-circle text-amber-500 text-lg"></span>
                        <span class="text-[10px] font-bold text-amber-800 uppercase tracking-wider">Insufficient Funds</span>
                     </div>
                     <p class="text-[10px] text-amber-700 leading-tight">Apply for <b>Murabaha Credit</b> to complete this purchase.</p>
                     
                     <div v-if="canUseFinancing" class="space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                          <div>
                            <label class="block text-[8px] font-bold text-slate-400 uppercase mb-1">Tenor</label>
                            <select v-model.number="creditMonths" class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1 text-[10px] font-bold">
                              <option v-for="m in [6,12]" :key="m" :value="m">{{ m }} Months</option>
                            </select>
                          </div>
                          <div>
                            <label class="block text-[8px] font-bold text-slate-400 uppercase mb-1">Profit</label>
                            <select v-model.number="creditProfit" class="w-full bg-white border border-slate-200 rounded-lg px-2 py-1 text-[10px] font-bold">
                              <option :value="0.10">10%</option>
                              <option :value="0.12">12%</option>
                              <option :value="0.15">15%</option>
                            </select>
                          </div>
                        </div>
                        <div class="flex items-start gap-2">
                          <input type="checkbox" v-model="agreedToTerms" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                          <label class="text-[9px] text-slate-500 leading-tight">I agree to the Murabaha Financing terms.</label>
                        </div>
                        <button 
                          @click="creditCheckout()" 
                          :disabled="placing || exceedsLimit || !agreedToTerms"
                          class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all disabled:opacity-50"
                        >
                          Buy on Credit (₦{{ money(creditMonthly) }}/mo)
                        </button>
                        <p v-if="exceedsLimit" class="text-[8px] text-center text-rose-500 font-bold uppercase mt-1">Limit Exceeded (Max: ₦{{ money(eligData?.limit) }})</p>
                     </div>
                     <div v-else class="text-[9px] text-rose-600 font-bold bg-rose-50 p-2 rounded-lg">
                       {{ financingReason }}
                     </div>
                  </div>
                </div>

                <div class="space-y-3">
                  <textarea 
                    v-model="orderNote" 
                    placeholder="Order notes (optional)" 
                    class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-emerald-500/20 outline-none transition-all"
                    rows="2"
                  ></textarea>
                  
                  <button 
                    @click="checkout()" 
                    :disabled="placing || hasInsufficient || !totalQty"
                    class="w-full h-14 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-black uppercase tracking-widest transition-all shadow-lg shadow-emerald-900/20 flex items-center justify-center gap-3 active:scale-95 disabled:opacity-30 disabled:grayscale"
                  >
                    <span v-if="placing" class="i-mdi-loading animate-spin text-xl"></span>
                    <template v-else>
                      <span class="i-mdi-lock-outline text-xl"></span>
                      Confirm & Pay
                    </template>
                  </button>
                </div>
                
                <p v-if="placeError" class="text-center text-rose-600 text-[10px] font-bold">{{ placeError }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Quick View Modal -->
    <Transition name="modal">
      <div v-if="selectedProduct" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeQuick()"></div>
        <div class="relative bg-white w-full max-w-4xl rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col md:flex-row max-h-[90vh]">
          <button @click="closeQuick()" class="absolute top-4 right-4 z-10 w-10 h-10 bg-white/80 backdrop-blur rounded-full flex items-center justify-center text-slate-500 hover:bg-white transition-all shadow-sm">
            <span class="i-mdi-close text-xl"></span>
          </button>
          
          <!-- Image Section -->
          <div class="md:w-1/2 bg-slate-50 p-8 flex items-center justify-center">
            <div class="w-full aspect-square rounded-3xl overflow-hidden shadow-2xl border border-white">
              <img v-if="selectedProduct.image_url" :src="getImageUrl(selectedProduct.image_url)" class="w-full h-full object-cover" />
              <div v-else class="w-full h-full flex items-center justify-center text-slate-200">
                <span class="i-mdi-package-variant-closed text-9xl opacity-20"></span>
              </div>
            </div>
          </div>
          
          <!-- Info Section -->
          <div class="md:w-1/2 p-8 md:p-12 overflow-y-auto hide-scrollbar flex flex-col">
            <div class="mb-8">
              <div class="flex items-center gap-3 mb-3">
                <span class="px-3 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase tracking-widest rounded-full">
                  {{ selectedProduct.category?.name || 'General' }}
                </span>
                <span v-if="selectedProduct.track_stock && selectedProduct.stock_quantity > 0" class="text-[10px] font-bold text-emerald-600">
                  <span class="i-mdi-check-circle text-sm mr-1"></span> In Stock
                </span>
                <span v-else-if="selectedProduct.track_stock" class="text-[10px] font-bold text-rose-500">
                  <span class="i-mdi-close-circle text-sm mr-1"></span> Out of Stock
                </span>
              </div>
              <h2 class="text-3xl font-black text-slate-800 leading-tight mb-2">{{ selectedProduct.name }}</h2>
              <p v-if="selectedProduct.vendor" class="text-sm text-slate-400">Sold by <span class="text-slate-600 font-bold">{{ selectedProduct.vendor.name }}</span></p>
            </div>
            
            <div class="mb-8">
              <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">Description</span>
              <p class="text-slate-600 text-sm leading-relaxed">{{ selectedProduct.description || 'No detailed description available.' }}</p>
            </div>
            
            <div class="mt-auto space-y-8">
              <div class="flex items-end justify-between">
                <div>
                  <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-1">Unit Price</span>
                  <div class="text-3xl font-black text-slate-900">₦ {{ money(selectedProduct.selling_price) }}</div>
                </div>
                
                <div class="flex items-center bg-slate-50 rounded-2xl p-2 border border-slate-100">
                  <button @click="quickQty = Math.max(1, (Number(quickQty)||1)-1)" class="w-10 h-10 flex items-center justify-center bg-white rounded-xl text-slate-500 shadow-sm hover:text-rose-600 active:scale-90 transition-all">
                    <span class="i-mdi-minus"></span>
                  </button>
                  <input v-model.number="quickQty" type="number" class="w-14 text-center bg-transparent text-lg font-black text-slate-800 border-none focus:ring-0" />
                  <button @click="quickQty = Math.min((selectedProduct.track_stock ? selectedProduct.stock_quantity : 999), (Number(quickQty)||1)+1)" class="w-10 h-10 flex items-center justify-center bg-white rounded-xl text-slate-500 shadow-sm hover:text-emerald-600 active:scale-90 transition-all">
                    <span class="i-mdi-plus"></span>
                  </button>
                </div>
              </div>

              <div class="flex gap-4">
                <button 
                  @click="addQuickToCart()" 
                  :disabled="selectedProduct.track_stock && selectedProduct.stock_quantity <= 0"
                  class="flex-1 h-16 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl text-base font-black uppercase tracking-widest transition-all shadow-xl shadow-emerald-900/20 flex items-center justify-center gap-3 active:scale-95 disabled:opacity-30 disabled:grayscale"
                >
                  <span class="i-mdi-cart-plus text-2xl"></span>
                  Add to Cart
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- PIN Prompt Modal -->
    <CustomNotice
      v-model="pinPrompt.visible"
      :type="'info'"
      :title="'Confirm Purchase'"
      :message="'Enter your 4-digit Transaction PIN to confirm checkout.'"
      :prompt="true"
      inputLabel="Transaction PIN"
      confirmText="Confirm"
      cancelText="Cancel"
      :busy="placing"
      @confirm="handlePinConfirm"
      @cancel="handlePinCancel"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import AppHeader from '../components/AppHeader.vue'
import axios from '../http'
import getImageUrl from '../utils/image'
import { useRouter } from 'vue-router'
import CustomNotice from '../components/CustomNotice.vue'

const items = ref([])
const loading = ref(false)
const error = ref('')
const page = ref(1)
const lastPage = ref(1)
const q = ref('')
const selectedCategory = ref(0)
const categories = ref([])
const sortBy = ref('newest')
const minPrice = ref('')
const maxPrice = ref('')
const totalItems = ref(0)

const vendor = ref(null)
const isAdmin = ref(false)
const walletBalance = ref(0)
const refreshingBalance = ref(false)
const eligData = ref(null)

const showCart = ref(false)
const cart = ref({}) // { [id]: { id, name, selling_price, qty } }
const placing = ref(false)
const placeError = ref('')
const placeSuccess = ref('')
// Purchase mode: 'cash' or 'credit'
const purchaseMode = ref('cash')
// PIN prompt modal state
const pinPrompt = ref({ visible: false })
const agreedToTerms = ref(false)

// Quick view modal state
const selectedProduct = ref(null)
const quickQty = ref(1)

// Optional order note
const orderNote = ref('')

const money = (val) => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const isNew = (dt) => {
  if (!dt) return false
  const d = new Date(dt)
  if (isNaN(d)) return false
  const now = Date.now()
  return (now - d.getTime()) <= (14 * 24 * 60 * 60 * 1000) // 14 days
}

const load = async (p = 1) => {
  loading.value = true
  error.value = ''
  try {
    page.value = p
    const { data } = await axios.get('/api/products', {
      params: { 
        page: p, 
        q: q.value || '', 
        category_id: selectedCategory.value, 
        sort: sortBy.value,
        min_price: minPrice.value,
        max_price: maxPrice.value
      }
    })
    const list = Array.isArray(data) ? data : (data?.data || [])
    items.value = list
    totalItems.value = Number(data?.total || list.length)
    lastPage.value = Number(data?.last_page || 1)
  } catch (e) {
    error.value = e?.response?.data?.message || e.message
  } finally {
    loading.value = false
  }
}

const loadCategories = async () => {
  try {
    const { data } = await axios.get('/api/products/categories')
    categories.value = data
  } catch (_) {}
}

const loadWallet = async () => {
  try {
    refreshingBalance.value = true
    const { data } = await axios.get('/api/wallet')
    walletBalance.value = Number(data?.balance || 0)
  } catch (_) {
  } finally {
    refreshingBalance.value = false
  }
}

const loadStoreEligibility = async () => {
  try {
    const { data } = await axios.get('/api/store/eligibility')
    eligData.value = data
  } catch (_) {}
}

const loadVendor = async () => {
  try {
    const { data } = await axios.get('/api/vendor/profile')
    if (data && data.id) {
      vendor.value = data
    }
  } catch (_) {}
}

const loadAdminStatus = async () => {
  try {
    const { data } = await axios.get('/api/dashboard')
    isAdmin.value = !!data.is_admin
  } catch (_) {}
}

const approveProduct = async (p) => {
  if (!confirm(`Approve "${p.name}"?`)) return
  try {
    await axios.post(`/api/admin/products/${p.id}/approve`)
    p.is_approved = true
    alert('Product approved successfully')
  } catch (e) {
    alert(e?.response?.data?.message || 'Failed to approve product')
  }
}

const rejectProduct = async (p) => {
  if (!confirm(`Mark "${p.name}" as pending?`)) return
  try {
    await axios.post(`/api/admin/products/${p.id}/reject`)
    p.is_approved = false
    alert('Product marked as pending')
  } catch (e) {
    alert(e?.response?.data?.message || 'Failed to update status')
  }
}

const CART_KEY = 'coop_store_cart_v1'
const persistCart = () => {
  try { localStorage.setItem(CART_KEY, JSON.stringify(cart.value)) } catch (_) {}
}
const restoreCart = () => {
  try {
    const raw = localStorage.getItem(CART_KEY)
    if (!raw) return
    const obj = JSON.parse(raw)
    if (obj && typeof obj === 'object') {
      cart.value = obj
    }
  } catch (_) {}
}

const toggleCart = () => { showCart.value = !showCart.value }
const addToCart = (p) => {
  const existing = cart.value[p.id]
  if (existing) {
    if (!p.track_stock || existing.qty < p.stock_quantity) {
      existing.qty += 1
    }
  } else {
    cart.value[p.id] = { 
      id: p.id, 
      name: p.name, 
      selling_price: Number(p.selling_price), 
      qty: 1,
      image_url: p.image_url,
      track_stock: p.track_stock,
      stock_quantity: p.stock_quantity
    }
  }
  showCart.value = true
}
const incQty = (id) => { if (cart.value[id]) cart.value[id].qty += 1 }
const decQty = (id) => {
  if (!cart.value[id]) return
  cart.value[id].qty -= 1
  if (cart.value[id].qty <= 0) delete cart.value[id]
}
const remove = (id) => { if (cart.value[id]) delete cart.value[id] }
const clearCart = () => { cart.value = {} }

watch(cart, persistCart, { deep: true })

const cartList = computed(() => Object.values(cart.value))
const totalQty = computed(() => cartList.value.reduce((s, it) => s + (it.qty || 0), 0))
const subtotal = computed(() => cartList.value.reduce((s, it) => s + (Number(it.selling_price || 0) * (it.qty || 0)), 0))
const shortfall = computed(() => Math.max(0, Number(subtotal.value) - Number(walletBalance.value)))
const hasInsufficient = computed(() => shortfall.value > 0)

const canUseFinancing = computed(() => {
  if (!eligData.value) return true
  return !eligData.value.has_active_financing && !eligData.value.has_active_loan
})

const financingReason = computed(() => {
  if (!eligData.value) return ''
  if (eligData.value.has_active_financing) return 'You have an active store financing order.'
  if (eligData.value.has_active_loan) return 'You have an active loan (Qard Hasan).'
  return ''
})

const exceedsLimit = computed(() => {
  if (!eligData.value) return false
  return Number(subtotal.value) > Number(eligData.value.limit)
})

// Murabaha (credit) controls
const creditMonths = ref(12)
const creditProfit = ref(0.12) // 12% default within 10–15%
const creditEstimateTotal = computed(() => {
  const rate = Number(creditProfit.value || 0)
  const base = Number(subtotal.value || 0)
  return Math.max(0, Math.round(base * (1 + rate) * 100) / 100)
})
const creditMonthly = computed(() => {
  const months = Math.max(1, Number(creditMonths.value || 1))
  return Math.round((Number(creditEstimateTotal.value || 0) / months) * 100) / 100
})
const creditValid = computed(() => {
  const m = Number(creditMonths.value)
  const r = Number(creditProfit.value)
  return m >= 6 && m <= 12 && r >= 0.10 && r <= 0.15
})

const openQuick = (p) => {
  selectedProduct.value = p
  quickQty.value = 1
}
const closeQuick = () => { selectedProduct.value = null }
const addQuickToCart = () => {
  const p = selectedProduct.value
  if (!p) return
  const qty = Math.max(1, Number(quickQty.value) || 1)
  const existing = cart.value[p.id]
  if (existing) {
    existing.qty += qty
    if (p.track_stock && existing.qty > p.stock_quantity) {
      existing.qty = p.stock_quantity
    }
  } else {
    cart.value[p.id] = { 
      id: p.id, 
      name: p.name, 
      selling_price: Number(p.selling_price), 
      qty: qty,
      image_url: p.image_url,
      track_stock: p.track_stock,
      stock_quantity: p.stock_quantity
    }
  }
  closeQuick()
  showCart.value = true
}

const checkout = () => {
  placeError.value = ''
  placeSuccess.value = ''
  purchaseMode.value = 'cash'
  if (!totalQty.value) return
  // Open custom PIN prompt modal
  pinPrompt.value.visible = true
}

const creditCheckout = () => {
  placeError.value = ''
  placeSuccess.value = ''
  purchaseMode.value = 'credit'
  if (!totalQty.value || !creditValid.value) return
  pinPrompt.value.visible = true
}

const handlePinConfirm = async (val) => {
  let pin = String(val || '').trim()
  if (!/^\d{4}$/.test(pin)) {
    alert('Please enter a valid 4-digit PIN')
    return
  }
  placing.value = true
  try {
    const payload = {
      items: cartList.value.map(it => ({ product_id: it.id, quantity: it.qty })),
      note: (orderNote.value || '').trim() || undefined,
      pin,
    }
    if (purchaseMode.value === 'credit') {
      payload.financing = {
        enabled: true,
        months: Number(creditMonths.value),
        profit_rate: Number(creditProfit.value),
      }
    }
    const { data } = await axios.post('/api/store/orders', payload)
    placeSuccess.value = data?.message || (purchaseMode.value === 'credit' ? 'Application submitted successfully' : 'Order placed successfully')
    const orderId = data?.order?.id
    clearCart()
    // Refresh wallet balance (may be unchanged for credit orders)
    try { await loadWallet() } catch (_) {}
    pinPrompt.value.visible = false
    if (orderId) {
      // slight delay for UX
      setTimeout(() => {
        // Navigate to receipt
        try { window?.navigator?.vibrate?.(30) } catch (_) {}
        routerPush(`/store/orders/${orderId}`)
      }, 300)
    }
  } catch (e) {
    pinPrompt.value.visible = false
    const status = e?.response?.status
    const msg = e?.response?.data?.message || e.message
    if (status === 409) {
      placeError.value = 'You need to set your Transaction PIN before making purchases. Go to Profile > Transaction PIN.'
    } else if (status === 403) {
      placeError.value = 'Invalid Transaction PIN. Please try again.'
    } else {
      placeError.value = msg
    }
  } finally {
    placing.value = false
    // reset mode back to cash for next action
    purchaseMode.value = 'cash'
  }
}

const handlePinCancel = () => {
  pinPrompt.value.visible = false
}

// Small helper to navigate without importing router explicitly in SFC setup
const routerPush = (path) => {
  try { window.location.href = `${import.meta.env.BASE_URL || '/'}${path.replace(/^\//,'')}` } catch (_) {}
}

onMounted(() => { restoreCart(); load(1); loadWallet(); loadCategories(); loadStoreEligibility(); loadVendor(); loadAdminStatus() })
</script>

<style scoped>
.hide-scrollbar::-webkit-scrollbar {
  display: none;
}
.hide-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.animate-bounce-short {
  animation: bounce-short 0.5s ease-in-out infinite alternate;
}

@keyframes bounce-short {
  from { transform: translateY(0); }
  to { transform: translateY(-4px); }
}

/* Drawer Transitions */
.drawer-enter-active,
.drawer-leave-active {
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;
}

.drawer-enter-from .absolute.inset-y-0.right-0,
.drawer-leave-to .absolute.inset-y-0.right-0 {
  transform: translateX(100%);
}

.drawer-enter-to .absolute.inset-y-0.right-0,
.drawer-leave-from .absolute.inset-y-0.right-0 {
  transform: translateX(0);
}

/* Modal Transitions */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .relative.bg-white,
.modal-leave-active .relative.bg-white {
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modal-enter-from .relative.bg-white {
  transform: scale(0.9) translateY(20px);
}

.modal-leave-to .relative.bg-white {
  transform: scale(0.95);
}
</style>
