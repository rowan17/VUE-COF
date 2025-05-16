<template>
  <div class="sticker-carousel-component w-full bg-gray-50 p-4 md:p-6 rounded-lg shadow-inner my-6">
    <h3 class="text-xl md:text-2xl font-semibold text-center text-gray-700 mb-6">
      Add Some Stickers!
    </h3>

    <div v-if="isLoadingStickers" class="text-center text-gray-500 py-8">
      Loading stickers...
    </div>
    <div v-else-if="stickerLoadingError" class="text-center text-red-600 py-8">
      Error loading stickers: {{ stickerLoadingError }}
    </div>
    <div v-else-if="processedStickers.length === 0" class="text-center text-gray-500 py-8">
      No stickers available at the moment.
    </div>

    <div v-else class="relative">
      <div ref="viewport" class="overflow-hidden w-full">
        <div
          ref="carouselTrack"
          class="flex flex-nowrap transition-transform duration-500 ease-in-out"
          :style="{ 
            transform: `translateX(-${currentScrollOffset}px)`
          }"
        >
          <div
            v-for="sticker in processedStickers"
            :key="sticker.uniqueSku"
            class="sticker-card bg-white rounded-lg shadow-md p-3 flex flex-col items-center flex-shrink-0 mx-2"
            :style="{ width: cardWidth + 'px' }"
          >
            <div class="image-wrapper w-32 h-32 md:w-40 md:h-40 mb-3 rounded border border-gray-200 overflow-hidden flex justify-center items-center bg-gray-100">
              <img
                :src="sticker.img"
                :alt="`Sticker ${sticker.sku}`"
                class="object-contain max-w-full max-h-full" 
                @error="onImageError"
              />
            </div>
            <p class="text-xs text-gray-500 font-medium leading-tight text-center h-8 overflow-hidden">
              SKU: {{ sticker.sku }}
            </p>
            <p class="text-md font-bold text-indigo-600 my-1">${{ stickerPrice.toFixed(2) }}</p>

            <div class="flex items-center mt-2 space-x-1.5">
              <button
                @click="decrementQuantity(sticker.uniqueSku)"
                class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-1 px-2.5 rounded-l text-sm transition-colors"
                aria-label="Decrease quantity"
              >
                -
              </button>
              <input
                type="number"
                :value="quantities[sticker.uniqueSku] || 0"
                @input="updateQuantity(sticker.uniqueSku, $event.target.value)"
                min="0"
                class="quantity-input w-10 text-center border-t border-b border-gray-300 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
                :aria-label="`Quantity for ${sticker.sku}`"
              />
              <button
                @click="incrementQuantity(sticker.uniqueSku)"
                class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-1 px-2.5 rounded-r text-sm transition-colors"
                aria-label="Increase quantity"
              >
                +
              </button>
            </div>
          </div>
        </div>
      </div>

      <button
        v-if="processedStickers.length > 0 && totalStickerCount > itemsToDisplay"
        @click="prevSlide"
        :disabled="isPrevDisabled"
        class="nav-button absolute top-1/2 left-0 transform -translate-y-1/2 -translate-x-3 sm:-translate-x-5 bg-indigo-500 hover:bg-indigo-600 text-white p-2 rounded-full shadow-md z-10 disabled:opacity-40 disabled:cursor-not-allowed"
        aria-label="Previous stickers"
      >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 sm:w-5 sm:h-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
        </svg>
      </button>
      <button
        v-if="processedStickers.length > 0 && totalStickerCount > itemsToDisplay"
        @click="nextSlide"
        :disabled="isNextDisabled"
        class="nav-button absolute top-1/2 right-0 transform -translate-y-1/2 translate-x-3 sm:translate-x-5 bg-indigo-500 hover:bg-indigo-600 text-white p-2 rounded-full shadow-md z-10 disabled:opacity-40 disabled:cursor-not-allowed"
        aria-label="Next stickers"
      >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 sm:w-5 sm:h-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
      </button>
    </div>

    <div v-if="!isLoadingStickers && !stickerLoadingError && processedStickers.length > 0" class="mt-6 pt-4 border-t border-gray-200 text-right">
      <p class="text-lg font-semibold text-gray-700">
        Stickers Subtotal: ${{ currentStickerTotal.toFixed(2) }}
      </p>
      <p class="text-sm text-gray-500">
        {{ currentStickerQuantity }} sticker(s) selected
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import axios from 'axios';

const props = defineProps({
  stickersUrl: {
    type: String,
    required: true,
    default: '/stickers.json'
  }
});

const emit = defineEmits(['update-sticker-selection']);

const fetchedStickers = ref([]);
const isLoadingStickers = ref(true);
const stickerLoadingError = ref(null);

const stickerPrice = ref(3.50);
const quantities = ref({});
const currentIndex = ref(0); 
const itemsToDisplay = ref(3); 
const cardWidth = ref(180); 
const cardMargin = ref(8); 

const carouselTrack = ref(null);
const viewport = ref(null);

const processedStickers = computed(() => {
  const skuCounts = {};
  return fetchedStickers.value
    .filter(s => s && typeof s.sku === 'string' && s.img)
    .map(sticker => {
      skuCounts[sticker.sku] = (skuCounts[sticker.sku] || 0) + 1;
      const uniqueSku = skuCounts[sticker.sku] > 1 ? `${sticker.sku}_${skuCounts[sticker.sku]}` : sticker.sku;
      return { ...sticker, uniqueSku };
    });
});

const totalStickerCount = computed(() => processedStickers.value.length);

const currentScrollOffset = computed(() => {
  const effectiveSingleItemWidth = cardWidth.value + (cardMargin.value * 2);
  return currentIndex.value * effectiveSingleItemWidth;
});

const isPrevDisabled = computed(() => currentIndex.value === 0);

const isNextDisabled = computed(() => {
  if (totalStickerCount.value === 0) return true;
  return currentIndex.value >= totalStickerCount.value - itemsToDisplay.value;
});


const currentStickerTotal = computed(() => {
  let total = 0;
  for (const uniqueSku in quantities.value) {
    if (quantities.value[uniqueSku] > 0) {
      total += quantities.value[uniqueSku] * stickerPrice.value;
    }
  }
  return total;
});

const currentStickerQuantity = computed(() => {
  let totalQty = 0;
  for (const uniqueSku in quantities.value) {
    totalQty += Number(quantities.value[uniqueSku] || 0);
  }
  return totalQty;
});

async function loadStickers() {
  isLoadingStickers.value = true;
  stickerLoadingError.value = null;
  try {
    const response = await axios.get(props.stickersUrl);
    if (Array.isArray(response.data)) {
      fetchedStickers.value = response.data;
    } else {
      stickerLoadingError.value = 'Invalid sticker data format received.';
      fetchedStickers.value = [];
    }
  } catch (error) {
    stickerLoadingError.value = `Could not fetch stickers. ${error.message}`;
    fetchedStickers.value = [];
  } finally {
    isLoadingStickers.value = false;
    await nextTick();
    calculateCardLayout(); 
    emitStickerSelection();
  }
}

function calculateCardLayout() {
    console.log("Attempting to calculate card layout...");
    if (typeof window === 'undefined' || !viewport.value) {
        console.warn("Window or viewport ref not available for layout calculation.");
        return;
    }

    const viewportWidth = viewport.value.offsetWidth;
    console.log("Viewport Width:", viewportWidth);

    if (viewportWidth <= 0) {
        console.warn("Carousel viewport has no width (or width is 0). Layout calculation skipped.");
        itemsToDisplay.value = 1; 
        cardWidth.value = 160;    
        return;
    }

    if (viewportWidth < 480) { 
        itemsToDisplay.value = 1;
    } else if (viewportWidth < 768) { 
        itemsToDisplay.value = 2;
    } else { 
        itemsToDisplay.value = 3;
    }
    console.log("Items to Display:", itemsToDisplay.value);

    const calculatedCardWidth = Math.floor((viewportWidth / itemsToDisplay.value) - (cardMargin.value * 2));
    cardWidth.value = Math.max(120, calculatedCardWidth); 
    console.log("Calculated Card Width:", cardWidth.value);


    if (currentIndex.value > totalStickerCount.value - itemsToDisplay.value) {
        currentIndex.value = Math.max(0, totalStickerCount.value - itemsToDisplay.value);
        console.log("Adjusted Current Index:", currentIndex.value);
    }
}

function prevSlide() {
  if (currentIndex.value > 0) {
    currentIndex.value--;
  }
}

function nextSlide() {
  if (currentIndex.value < totalStickerCount.value - itemsToDisplay.value) {
    currentIndex.value++;
  }
}

function incrementQuantity(uniqueSku) {
  quantities.value[uniqueSku] = (quantities.value[uniqueSku] || 0) + 1;
}

function decrementQuantity(uniqueSku) {
  if (quantities.value[uniqueSku] && quantities.value[uniqueSku] > 0) {
    quantities.value[uniqueSku]--;
  }
}

function updateQuantity(uniqueSku, value) {
  const numValue = parseInt(value, 10);
  if (!isNaN(numValue) && numValue >= 0) {
    quantities.value[uniqueSku] = numValue;
  } else if (value === "") {
    quantities.value[uniqueSku] = 0;
  }
}

function onImageError(event) {
  event.target.src = 'https://placehold.co/150x150/cccccc/ffffff?text=No+Image';
}

function emitStickerSelection() {
  const selectedItems = [];
  for (const uniqueSku in quantities.value) {
    if (quantities.value[uniqueSku] > 0) {
      const sticker = processedStickers.value.find(s => s.uniqueSku === uniqueSku);
      if (sticker) {
        selectedItems.push({
          SKU: sticker.sku, Title: `Sticker - ${sticker.sku}`, img: sticker.img,
          quantity: quantities.value[uniqueSku], price: stickerPrice.value, type: 'sticker'
        });
      }
    }
  }
  emit('update-sticker-selection', {
    items: selectedItems, totalPrice: currentStickerTotal.value, totalQuantity: currentStickerQuantity.value
  });
}

watch(quantities, emitStickerSelection, { deep: true });

watch(processedStickers, async () => {
    await nextTick();
    calculateCardLayout(); 
    if (currentIndex.value >= totalStickerCount.value && totalStickerCount.value > 0) {
        currentIndex.value = Math.max(0, totalStickerCount.value - itemsToDisplay.value);
    } else if (totalStickerCount.value === 0) {
        currentIndex.value = 0;
    }
}, { deep: true });

onMounted(async () => {
  await loadStickers(); 
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', calculateCardLayout);
  }
});

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', calculateCardLayout);
  }
});

</script>

<style scoped>
.sticker-carousel-component {
  /* Main container styling */
}

.overflow-hidden {
  /* Tailwind utility for overflow: hidden; */
}

.carousel-track {
  /* `flex flex-nowrap` is applied via class.
     `transform` is applied via inline style.
     It will naturally grow to fit its content horizontally. */
}

.sticker-card {
  /* `width` is dynamic. `mx-2` for L/R margins. `flex-shrink-0` prevents shrinking. */
  transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}
.sticker-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 12px -4px rgba(0, 0, 0, 0.1), 0 3px 5px -2px rgba(0, 0, 0, 0.05);
}

/* New class for the image wrapper */
.image-wrapper {
  /* Dimensions are set by Tailwind classes in the template.
     `overflow-hidden` clips the image.
     `flex justify-center items-center` will center the image if its aspect ratio
     doesn't match the wrapper's aspect ratio (when using object-contain). */
}

.quantity-input::-webkit-outer-spin-button,
.quantity-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.quantity-input[type=number] {
  -moz-appearance: textfield;
}

.nav-button {
  transition: background-color 0.3s ease, transform 0.2s ease, opacity 0.3s ease;
}
.nav-button:hover:not(:disabled) {
  transform: scale(1.1) translateY(-50%);
}

/* Optional: Hide scrollbar if it ever appears on the track itself */
.flex.transition-transform::-webkit-scrollbar { display: none; }
.flex.transition-transform { -ms-overflow-style: none; scrollbar-width: none; }
</style>
