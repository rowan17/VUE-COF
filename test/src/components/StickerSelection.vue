<script setup>
import { ref, computed, defineProps, defineEmits } from 'vue';

const props = defineProps({
  fetchedStickers: {
    type: Array,
    default: () => []
  },
  isLoadingStickers: {
    type: Boolean,
    default: true
  },
  stickerLoadingError: {
    type: String,
    default: null
  },
  stickerQuantities: {
    type: Object,
    required: true // stickerQuantities object should be passed from parent
  },
  stickerPrice: {
    type: Number,
    default: 3.50
  },
  stickerItemWidth: {
    type: String,
    default: '192px' // Approx w-48 (12rem) for card width
  }
});

const emit = defineEmits(['update:stickerQuantities', 'stickerImageError']);

// Internal reactive copy of stickerQuantities to allow v-model on input
const localStickerQuantities = computed({
  get: () => props.stickerQuantities,
  set: (newValue) => {
    emit('update:stickerQuantities', newValue);
  }
});

function incrementStickerQuantity(sku) {
  const newQuantities = { ...localStickerQuantities.value };
  newQuantities[sku] = (newQuantities[sku] || 0) + 1;
  localStickerQuantities.value = newQuantities;
}

function decrementStickerQuantity(sku) {
  const newQuantities = { ...localStickerQuantities.value };
  if (newQuantities[sku] && newQuantities[sku] > 0) {
    newQuantities[sku]--;
  }
  localStickerQuantities.value = newQuantities;
}

function updateStickerQuantity(sku, event) {
  const value = parseInt(event.target.value, 10);
  const newQuantities = { ...localStickerQuantities.value };
  if (!isNaN(value) && value >= 0) {
    newQuantities[sku] = value;
  } else if (event.target.value === "") {
    newQuantities[sku] = 0;
  }
  localStickerQuantities.value = newQuantities;
}

function onStickerImageError(event) {
  emit('stickerImageError', event);
}
</script>

<template>
  <section class="sticker-section bg-blue-50 p-6 rounded-lg shadow-inner mb-8">
    <h2 class="text-2xl font-bold text-blue-800 mb-4 border-b-2 border-blue-300 pb-2 flex items-center">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
      </svg>
      Stickers
    </h2>

    <div v-if="isLoadingStickers" class="text-center text-blue-600 text-lg py-4">
      Loading stickers...
    </div>
    <div v-else-if="stickerLoadingError" class="text-center text-red-600 font-medium py-4">
      <p>Error loading stickers:</p>
      <p>{{ stickerLoadingError }}</p>
    </div>
    <div v-else-if="fetchedStickers.length === 0" class="text-center text-blue-500 text-lg py-4">
      No stickers available.
    </div>

    <div v-else class="sticker-list-container">
      <div v-for="sticker in fetchedStickers" :key="sticker.sku"
        :style="{ width: stickerItemWidth }"
        class="sticker-card flex-shrink-0 mr-4 bg-white rounded-lg shadow-md p-3 text-center transition-transform hover:scale-105">

        <div class="image-wrapper w-36 h-36 mx-auto mb-2 border border-gray-200 rounded overflow-hidden flex items-center justify-center bg-gray-50">
          <img :src="sticker.img" :alt="`Sticker ${sticker.sku}`"
            class="max-w-full max-h-full object-contain"
            @error="onStickerImageError"/>
        </div>

        <p class="text-xs text-gray-600 overflow-hidden text-ellipsis whitespace-nowrap mb-1" :title="sticker.sku">
          SKU: {{ sticker.sku }}
        </p>
        <p class="text-sm font-semibold text-indigo-600 my-1">
          ${{ stickerPrice.toFixed(2) }}
        </p>

        <div class="quantity-controls flex justify-center items-center mt-2 space-x-1">
          <button @click="decrementStickerQuantity(sticker.sku)" class="qty-btn bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-1 px-3 rounded-full transition-colors">-</button>
          <input type="number"
            :value="localStickerQuantities[sticker.sku] || 0"
            @input="updateStickerQuantity(sticker.sku, $event)"
            min="0" class="qty-input w-12 text-center border border-gray-300 rounded-md py-1"/>
          <button @click="incrementStickerQuantity(sticker.sku)" class="qty-btn bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-1 px-3 rounded-full transition-colors">+</button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* Custom scrollbar for better appearance */
.sticker-list-container {
  display: flex;
  overflow-x: auto;
  padding-bottom: 1rem; /* Added padding for scrollbar visibility */
}

.sticker-list-container::-webkit-scrollbar {
  height: 8px;
}
.sticker-list-container::-webkit-scrollbar-thumb {
  background-color: #94a3b8; /* Tailwind slate-400 */
  border-radius: 10px;
}
.sticker-list-container::-webkit-scrollbar-track {
  background-color: #e2e8f0; /* Tailwind slate-200 */
}

.sticker-card {
  box-sizing: border-box; /* Include padding in the width calculation */
}

/* Remove debug borders as they are no longer needed */
/*
.sticker-item-debug { border: none !important; }
.image-container-debug { border: none !important; }
.custom-scrollbar { border: none !important; }
*/
</style>