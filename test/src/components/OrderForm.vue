<script setup>
import { ref, computed, watch, onMounted, defineProps, nextTick } from 'vue';
import axios from 'axios';

// --- Props ---
const props = defineProps({
  customerId: {
    type: String,
    required: false
  }
});

// --- Reactive State for Main Order Form ---
const customerData = ref(null);
const orderItems = ref([]); 
const notes = ref('');
const isLoading = ref(true); 
const error = ref(null);
const showConfirmationModal = ref(false);
const submissionStatus = ref('');
const showLargeImage = ref(false);
const largeImageUrl = ref('');
const allProductsCatalog = ref(null);
const productCatalogError = ref(null);

// --- Reactive State for Inline Sticker Section ---
const fetchedStickers = ref([]);
const isLoadingStickers = ref(true);
const stickerLoadingError = ref(null);
const stickerQuantities = ref({}); 
const stickerPrice = ref(3.50); 
const stickerItemWidth = ref('192px'); // Approx w-48 (12rem). Card width.

// --- Lifecycle Hooks ---
onMounted(async () => {
  await fetchProductCatalog(); 
  await loadStickers();       

  if (props.customerId) {
    console.log('Order Form will load for customer:', props.customerId);
  } else {
    console.log('General Order Form loaded (no customerId).');
    isLoading.value = false; 
  }
});

// --- Data Fetching for Main Form ---
async function fetchData(id) {
  console.log(`Fetching data for customer: ${id}`);
  isLoading.value = true;
  error.value = null;
  customerData.value = null;
  notes.value = '';

  try {
    const response = await axios.get(`/data/${id}.json`);
    customerData.value = response.data;
    console.log("Customer data fetched successfully:", customerData.value);
    initializeOrderItems(); 
  } catch (err) {
    console.error("Error fetching customer data:", err);
    if (err.response && err.response.status === 404) {
       error.value = `Error: Could not find data file for customer ID: ${id}. Please check the ID.`;
    } else {
       error.value = `An error occurred while loading customer data. Please try again later.`;
    }
    customerData.value = null;
  } finally {
    isLoading.value = false;
  }
}

async function fetchProductCatalog() {
  if (allProductsCatalog.value) return;
  console.log("Fetching product catalog (productdata.json)...");
  productCatalogError.value = null;
  try {
    const response = await axios.get('/productdata.json'); 
    allProductsCatalog.value = response.data;
    console.log("Product catalog fetched successfully.");
  } catch (err) {
    console.error("Error fetching product catalog:", err);
    productCatalogError.value = "Could not load product catalog.";
  }
}

// --- Sticker Data Fetching & Processing ---
async function loadStickers() {
  isLoadingStickers.value = true;
  stickerLoadingError.value = null;
  try {
    const response = await axios.get('/stickers.json'); 
    if (Array.isArray(response.data)) {
      fetchedStickers.value = response.data.filter(s => s && typeof s.sku === 'string' && s.sku.trim() !== '' && s.img);
      fetchedStickers.value.forEach(sticker => {
        if (!(sticker.sku in stickerQuantities.value)) {
          stickerQuantities.value[sticker.sku] = 0;
        }
      });
    } else {
      console.error('Sticker JSON is not an array:', response.data);
      stickerLoadingError.value = 'Invalid sticker data format.';
      fetchedStickers.value = [];
    }
  } catch (error) {
    console.error('Failed to load stickers:', error);
    stickerLoadingError.value = `Could not fetch stickers. ${error.message}`;
    fetchedStickers.value = [];
  } finally {
    isLoadingStickers.value = false;
  }
}

const displayStickers = computed(() => {
  return fetchedStickers.value;
});


// --- Initialize Order State ---
function initializeOrderItems() {
  if (!customerData.value && !props.customerId) { 
      orderItems.value = orderItems.value.filter(item => item.type === 'sticker');
      return;
  }
  if (!customerData.value && props.customerId) return; 

  const nonStickerProducts = [
    ...(customerData.value.data || []).map(item => ({ ...item, type: 'history', currentQuantity: 0 })),
    ...(customerData.value.recommendations || []).map(item => ({ ...item, type: 'recommendation', currentQuantity: 0 })),
  ];

  const uniqueNonStickerProducts = new Map();
  nonStickerProducts.forEach(item => {
    if (item.SKU && !uniqueNonStickerProducts.has(item.SKU)) { 
      uniqueNonStickerProducts.set(item.SKU, item);
    }
  });
  
  const existingStickersInOrder = orderItems.value.filter(item => item.type === 'sticker');
  orderItems.value = [...existingStickersInOrder, ...Array.from(uniqueNonStickerProducts.values())];

  console.log("Initialized non-sticker order items:", orderItems.value);
}


// --- Sticker Quantity Management ---
function incrementStickerQuantity(sku) {
  stickerQuantities.value[sku] = (stickerQuantities.value[sku] || 0) + 1;
}

function decrementStickerQuantity(sku) {
  if (stickerQuantities.value[sku] && stickerQuantities.value[sku] > 0) {
    stickerQuantities.value[sku]--;
  }
}

function updateStickerQuantity(sku, eventValue) {
  const value = parseInt(eventValue, 10);
  if (!isNaN(value) && value >= 0) {
    stickerQuantities.value[sku] = value;
  } else if (eventValue === "") { 
    stickerQuantities.value[sku] = 0;
  }
}

// --- Sync Sticker Selections to Main Order Items ---
function syncStickersToOrder() {
  orderItems.value = orderItems.value.filter(item => item.type !== 'sticker');
  for (const sku in stickerQuantities.value) {
    if (stickerQuantities.value[sku] > 0) {
      const stickerData = fetchedStickers.value.find(s => s.sku === sku);
      if (stickerData) {
        orderItems.value.push({
          SKU: stickerData.sku,
          Title: `Sticker - ${stickerData.sku}`, 
          image_url: stickerData.img,
          product_url: '#', 
          description: `Sticker: ${stickerData.sku}`,
          price: stickerPrice.value,
          currentQuantity: stickerQuantities.value[sku],
          type: 'sticker'
        });
      }
    }
  }
  console.log("Order items after sticker sync:", JSON.parse(JSON.stringify(orderItems.value)));
}

watch(stickerQuantities, () => {
  syncStickersToOrder();
}, { deep: true });


// --- Computed Properties for Totals ---
const totalQuantity = computed(() => {
  return orderItems.value.reduce((sum, item) => sum + Number(item.currentQuantity || 0), 0);
});

const totalPrice = computed(() => {
  const total = orderItems.value.reduce((sum, item) => {
    const price = parseFloat(item.price || 0);
    const quantity = Number(item.currentQuantity || 0);
    return sum + (price * quantity);
  }, 0);
  return total.toFixed(2);
});

const historyItems = computed(() => orderItems.value.filter(item => item.type === 'history'));
const recommendationItems = computed(() => orderItems.value.filter(item => item.type === 'recommendation'));
const selectedStickerItemsForDisplay = computed(() => orderItems.value.filter(item => item.type === 'sticker'));
const manuallyAddedItems = computed(() => orderItems.value.filter(item => item.type === 'manual'));

// --- Methods (Add SKU, Submit, CSV, Print, Image Preview) ---
async function addSkuItem(sku) {
  if (!sku) return;
  const details = fetchProductDetails(sku); 
  if (details) {
    const existingItem = orderItems.value.find(item => item.SKU === details.SKU && item.type === 'manual');
    if (existingItem) {
      alert(`Item ${sku} is already in your manually added list.`);
    } else {
      orderItems.value.push({ ...details, currentQuantity: 1, type: 'manual' });
    }
  } else {
    alert(`Could not find details for SKU: ${sku}`);
  }
}

function fetchProductDetails(sku) {
    if (productCatalogError.value) {
        alert(`Product catalog is unavailable. Cannot add SKU ${sku}.`);
        return null;
    }
    if (!allProductsCatalog.value) {
        alert("Product catalog is still loading. Please try again.");
        return null;
    }
    const product = allProductsCatalog.value.find(p => p.sku && p.sku.toUpperCase() === sku.toUpperCase());
    if (product) {
        return {
            image_url: product.image_url || "https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image",
            product_url: product.product_url || "#", Title: product.Title, SKU: product.sku,
            description: product.description || "No description available.", price: product.WholesalePrice 
        };
    }
    return null;
}

async function submitOrder() {
  isLoading.value = true; 
  submissionStatus.value = ''; error.value = null; 
  const orderData = {
    customerId: props.customerId,
    customerInfo: { 
      name: customerData.value?.Name, company: customerData.value?.Company,
      email: customerData.value?.Email, phone: customerData.value?.Phone,
      address: customerData.value?.Address
    },
    notes: notes.value,
    items: orderItems.value.filter(item => item.currentQuantity > 0) 
      .map(item => ({ 
        SKU: item.SKU, Title: item.Title, quantity: item.currentQuantity,
        price: item.price, rowTotal: (parseFloat(item.price || 0) * Number(item.currentQuantity || 0)).toFixed(2)
      })),
    totalQuantity: totalQuantity.value, totalPrice: totalPrice.value,
    orderTimestamp: new Date().toISOString()
  };
  const formData = new URLSearchParams();
  formData.append('realemail', orderData.customerInfo.email || '');
  formData.append('company_name', orderData.customerInfo.company || 'N/A');
  formData.append('url_link', window.location.href);
  formData.append('order_details', formatOrderDetailsForEmail(orderData));
  try {
    const response = await axios.post('http://localhost:8001/mail.php', formData, {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });
    if (response.data && response.data.success) {
      submissionStatus.value = response.data.message || "Order submitted successfully!";
      showConfirmationModal.value = true; 
    } else {
      submissionStatus.value = response.data.message || "Failed to submit order.";
      error.value = submissionStatus.value; 
    }
  } catch (err) {
    submissionStatus.value = "An error occurred while submitting your order.";
    if (err.response) {
      submissionStatus.value += ` (Server status: ${err.response.status})`;
      if(err.response.data && typeof err.response.data === 'string' && err.response.data.toLowerCase().includes("<!doctype html>")) {
        submissionStatus.value = "Error: PHP script error.";
      }
    } else if (err.request) {
      submissionStatus.value = "Error: No response from server.";
    } else {
      submissionStatus.value = "Error: Could not send request.";
    }
    error.value = submissionStatus.value;
  } finally {
    isLoading.value = false; 
  }
}

function formatOrderDetailsForEmail(orderData) {
  let detailsString = `Customer Name: ${orderData.customerInfo.name || 'N/A'}\n`;
  detailsString += `Company: ${orderData.customerInfo.company || 'N/A'}\n`;
  detailsString += `Email: ${orderData.customerInfo.email || 'N/A'}\n`;
  detailsString += `Phone: ${orderData.customerInfo.phone || 'N/A'}\n`;
  if (orderData.customerInfo.address) {
    detailsString += `Shipping Address: ${orderData.customerInfo.address}\n`;
  }
  detailsString += `\n--- ITEMS ORDERED ---\n`;
  orderData.items.forEach(item => {
    detailsString += `${item.Title} (SKU: ${item.SKU}) - Qty: ${item.quantity}, Price: $${item.price}, Total: $${item.rowTotal}\n`;
  });
  detailsString += `\n--- TOTALS ---\n`;
  detailsString += `Total Quantity: ${orderData.totalQuantity}\n`;
  detailsString += `Total Price: $${orderData.totalPrice}\n`;
  if (orderData.notes) {
    detailsString += `\n--- NOTES ---\n${orderData.notes}\n`;
  }
  detailsString += `\nOrder Timestamp: ${orderData.orderTimestamp}\n`;
  return detailsString;
}

function downloadCSV() {
  if (!props.customerId || !customerData.value || !customerData.value.data) {
      alert("No customer-specific order history available to download.");
      return;
  }
  const headers = ['Title', 'SKU', 'Price', 'Quantity Purchased', 'Times Purchased'];
  const rows = customerData.value.data.map(item => ({
      Title: item.Title, SKU: item.SKU, Price: item.price,
      QuantityPurchased: item.Quantity, TimesPurchased: item.Times
  }));
  const contactInfo = customerData.value;
  const headerRows = [
      ["Paradise Cay Publications /// orders@paracay.com /// (707) 822-9063"],
      [`${contactInfo.Company} /// ${contactInfo.Name} /// ${contactInfo.Phone} /// ${contactInfo.Email}`],
      [`Shipping Address: ${contactInfo.Address.replace(/<br>/g, ' ')}`],
      [], headers
  ];
  const csvData = headerRows.concat(rows.map(row => headers.map(header => row[header.replace(/ /g, '')])));
  const csvContent = "data:text/csv;charset=utf-8,"
      + csvData.map(e => e.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(",")).join("\n");
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", `orderhistory_${props.customerId}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function printOrder() { window.print(); }

function onImageError(event) {
  if (!event.target.src.includes('placehold.co')) {
    event.target.src = 'https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image';
  }
}
function onStickerImageError(event) {
   if (!event.target.src.includes('placehold.co')) {
    event.target.src = 'https://placehold.co/144x144/cccccc/ffffff?text=No+Sticker'; // Adjusted placeholder size
  }
}

function showPreview(url) {
    if (url) { largeImageUrl.value = url; showLargeImage.value = true; }
}
function hidePreview() {
    showLargeImage.value = false; largeImageUrl.value = '';
}

watch(() => props.customerId, (newId, oldId) => {
  if (newId && newId !== oldId) {
    fetchData(newId);
  } else if (!newId) { 
    customerData.value = null;
    initializeOrderItems(); 
  }
}, { immediate: true });

const skuToAdd = ref('');
function handleAddSku() {
    addSkuItem(skuToAdd.value.trim());
    skuToAdd.value = '';
}

</script>

<template>
  <div class="order-form-container">
    <div v-if="isLoading && !customerData && !(!props.customerId && !isLoadingStickers) " class="loading-overlay">Loading data...</div>
    <div v-if="error" class="error-message">
      <p>Error:</p>
      <p>{{ error }}</p>
    </div>

    <div v-if="props.customerId && customerData && !isLoading && !error">
      <header class="form-header">
        <div class="customer-info">
          <p><strong>Contact:</strong> {{ customerData.Name }} ({{ customerData.Email }}, {{ customerData.Phone }})</p>
          <p><strong>Default Shipping Address:</strong></p>
          <div v-html="customerData.Address" class="address"></div>
          <p><small>Order Form Updated: {{ customerData.date }}</small></p>
        </div>
        <button @click="downloadCSV" class="action-button">Download Order History (CSV)</button>
        <hr>
        <p class="info-message">Annual publications or discontinued items may not appear below. Use the Notes field for inquiries.</p>
      </header>
    </div>
    <div v-if="!props.customerId && !isLoading && !error" class="form-header">
        <p class="info-message text-lg">General Order Form. Add items by SKU or select from available stickers.</p>
    </div>

    <section class="sticker-section"> 
      <h2 class="text-xl font-semibold mb-4 text-slate-700">Stickers</h2>
      <div v-if="isLoadingStickers" class="text-center text-slate-500">Loading stickers...</div>
      <div v-else-if="stickerLoadingError" class="text-center text-red-500">{{ stickerLoadingError }}</div>
      <div v-else-if="displayStickers.length === 0" class="text-center text-slate-500">No stickers available.</div>
      
      <div v-else 
           :style="{ 
             display: 'flex !important', 
             overflowX: 'auto !important', 
             paddingBottom: '1rem !important', /* Increased padding for scrollbar visibility */
             border: '2px solid red !important' /* DEBUG BORDER */
           }" 
           class="custom-scrollbar">
        
        <div v-for="sticker in displayStickers" :key="sticker.sku" 
             :style="{ 
                flexShrink: '0 !important', 
                width: stickerItemWidth, /* '192px' by default */
                marginRight: '1rem !important',
                border: '1px solid blue !important', /* DEBUG BORDER */
                padding: '0.75rem !important', /* p-3 */
                textAlign: 'center !important',
                backgroundColor: 'white !important'
             }"
             class="sticker-item-debug rounded-lg shadow-md"> 
          
          <div 
            :style="{
              width: '144px !important', /* w-36 */
              height: '144px !important', /* h-36 */
              margin: '0 auto 0.5rem auto !important', /* mx-auto mb-2 */
              border: '1px solid #e2e8f0 !important', /* border-gray-200 */
              borderRadius: '0.25rem !important', /* rounded */
              overflow: 'hidden !important',
              display: 'flex !important',
              alignItems: 'center !important',
              justifyContent: 'center !important',
              backgroundColor: '#f8fafc !important' /* bg-gray-50 */
            }"
            class="image-container-debug">
            <img :src="sticker.img" :alt="`Sticker ${sticker.sku}`" 
                 :style="{ 
                    maxWidth: '100% !important', 
                    maxHeight: '100% !important', 
                    objectFit: 'contain !important' 
                 }"
                 @error="onStickerImageError"/>
          </div>

          <p 
            :style="{ 
                fontSize: '0.75rem !important', /* text-xs */
                color: '#4b5563 !important', /* text-gray-600 */
                overflow: 'hidden !important',
                textOverflow: 'ellipsis !important',
                whiteSpace: 'nowrap !important', /* Helps with truncate */
                height: '2rem !important', /* h-8, for 2 lines if needed, or adjust */
                lineHeight: '1rem !important', /* leading-tight */
                marginBottom: '0.25rem !important' /* my-1 for spacing around price */
            }"
            :title="sticker.sku">SKU: {{ sticker.sku }}
          </p>
          <p 
            :style="{
                fontSize: '0.875rem !important', /* text-sm */
                fontWeight: '600 !important', /* font-semibold */
                color: '#4f46e5 !important', /* text-indigo-600 */
                margin: '0.25rem 0 !important' /* my-1 */
            }">
            ${{ stickerPrice.toFixed(2) }}
          </p>
          <div 
            :style="{
                display: 'flex !important',
                justifyContent: 'center !important',
                alignItems: 'center !important',
                marginTop: '0.5rem !important' /* mt-2 */
            }"
            class="quantity-controls-debug space-x-1">
            <button @click="decrementStickerQuantity(sticker.sku)" class="qty-btn" :style="{ marginRight: '0.25rem !important' }">-</button>
            <input type="number" 
                   :value="stickerQuantities[sticker.sku] || 0" 
                   @input="updateStickerQuantity(sticker.sku, $event.target.value)"
                   min="0" class="qty-input w-12 text-center"/> 
            <button @click="incrementStickerQuantity(sticker.sku)" class="qty-btn" :style="{ marginLeft: '0.25rem !important' }">+</button>
          </div>
        </div>
      </div>
    </section>

    <section v-if="props.customerId && customerData" class="table-section">
      <h2>Your Order History</h2>
      <table class="order-table">
        <thead>
          <tr>
            <th>Image</th><th>Product</th><th>Price</th>
            <th>Total Qty Purchased</th><th># Times Purchased</th>
            <th>Order QTY</th><th>Total Cost</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="historyItems.length === 0"><td colspan="7">No order history found.</td></tr>
          <tr v-for="item in historyItems" :key="item.SKU">
            <td>
              <img :src="item.image_url || 'https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image'" :alt="item.Title"
                   class="product-image" @error="onImageError" @mouseover="showPreview(item.image_url)" @mouseout="hidePreview">
            </td>
            <td class="product-details">
              <a :href="item.product_url" target="_blank" rel="noopener noreferrer">{{ item.Title }} - {{ item.SKU }}</a>
              <p class="description">{{ item.description }}</p>
            </td>
            <td>${{ item.price }}</td>
            <td class="center-align">{{ item.Quantity }}</td>
            <td class="center-align">{{ item.Times }}</td>
            <td><input type="number" v-model.number="item.currentQuantity" min="0" class="quantity-input"></td>
            <td class="right-align">${{ (parseFloat(item.price || 0) * Number(item.currentQuantity || 0)).toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="props.customerId && customerData" class="table-section">
      <h2>We Think You Might Like</h2>
      <table class="order-table">
        <thead><tr><th>Image</th><th>Product</th><th>Price</th><th>Order QTY</th><th>Total Cost</th></tr></thead>
        <tbody>
          <tr v-if="recommendationItems.length === 0"><td colspan="5">No recommendations available.</td></tr>
          <tr v-for="item in recommendationItems" :key="item.SKU">
            <td>
              <img :src="item.image_url || 'https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image'" :alt="item.Title"
                   class="product-image" @error="onImageError" @mouseover="showPreview(item.image_url)" @mouseout="hidePreview">
            </td>
            <td class="product-details">
              <a :href="item.product_url" target="_blank" rel="noopener noreferrer">{{ item.Title }} - {{ item.SKU }}</a>
              <p class="description">{{ item.description }}</p>
            </td>
            <td>${{ item.price }}</td>
            <td><input type="number" v-model.number="item.currentQuantity" min="0" class="quantity-input"></td>
            <td class="right-align">${{ (parseFloat(item.price || 0) * Number(item.currentQuantity || 0)).toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="add-sku-section">
      <h2>Add Product by SKU</h2>
      <div class="add-sku-controls">
        <input type="text" v-model="skuToAdd" placeholder="Enter SKU" @keyup.enter="handleAddSku">
        <button @click="handleAddSku" class="action-button">Add Item</button>
      </div>
      <div v-if="manuallyAddedItems.length > 0">
        <h3>Manually Added Items</h3>
        <table class="order-table">
          <thead><tr><th>Image</th><th>Product</th><th>Price</th><th>Order QTY</th><th>Total Cost</th></tr></thead>
          <tbody>
            <tr v-for="item in manuallyAddedItems" :key="item.SKU">
              <td>
                <img :src="item.image_url || 'https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image'" :alt="item.Title"
                     class="product-image" @error="onImageError" @mouseover="showPreview(item.image_url)" @mouseout="hidePreview">
              </td>
              <td class="product-details">
                {{ item.Title }} - {{ item.SKU }}
                <p class="description">{{ item.description }}</p>
              </td>
              <td>${{ item.price }}</td>
              <td><input type="number" v-model.number="item.currentQuantity" min="0" class="quantity-input"></td>
              <td class="right-align">${{ (parseFloat(item.price || 0) * Number(item.currentQuantity || 0)).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="notes-section">
      <h2>Notes</h2>
      <textarea v-model="notes" placeholder="Add any notes about your order..." class="notes-textarea"></textarea>
    </section>

    <div class="bottom-bar">
      <span class="summary-item">Total QTY: <strong>{{ totalQuantity }}</strong></span>
      <span class="summary-item">Order Estimate: <strong>${{ totalPrice }}</strong></span>
      <button @click="submitOrder" :disabled="totalQuantity === 0 || isLoading" class="submit-button">
        {{ isLoading ? 'Processing...' : 'Submit Order' }}
      </button>
      <p v-if="submissionStatus" :class="{'success-message': !error, 'error-message': error}" class="submission-status-message">
        {{ submissionStatus }}
      </p>
    </div>

    <div v-if="showLargeImage" class="image-preview-overlay" @click="hidePreview">
      <img :src="largeImageUrl" alt="Large product preview" class="large-image-preview">
    </div>

    <div v-if="showConfirmationModal" class="modal-overlay">
      <div class="modal-container">
        <h2>Thank you!</h2>
        <p>Your order has been submitted successfully.</p>
        <p>You should receive an email confirmation shortly. If not, please contact orders@paracay.com.</p>
        <p><strong>Please print a copy of your order for your records.</strong></p>
        <div class="modal-actions">
          <button @click="printOrder" class="action-button">Print Order</button>
          <button @click="showConfirmationModal = false" class="action-button secondary">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Basic Styling - Enhance with a UI library or more detailed CSS */
.order-form-container {
  font-family: 'Roboto', sans-serif;
  max-width: 1000px;
  margin: 20px auto;
  padding: 20px;
  padding-bottom: 100px; /* Space for fixed bottom bar */
  border: 1px solid #ccc;
  border-radius: 8px;
  background-color: #fff;
  position: relative; /* Needed for absolute positioning of overlays */
}

.loading-overlay {
    position: fixed; /* Changed to fixed to cover whole screen */
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.9); /* Slightly more opaque */
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 1.5em; /* Larger text */
    color: #333;
    z-index: 10000; /* Ensure it's on top of everything */
}

.error-message {
  color: #D8000C;
  background-color: #FFD2D2;
  border: 1px solid #D8000C;
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 4px;
}

.form-header {
  text-align: center;
  margin-bottom: 30px;
}

.customer-info {
  text-align: left;
  margin-bottom: 15px;
  padding: 10px;
  border: 1px dashed #eee;
  background-color: #f9f9f9;
}
.customer-info p { margin: 5px 0; }
.address { margin-left: 10px; font-style: italic; }

.info-message { font-size: 0.9em; color: #555; }
.info-message.text-lg { font-size: 1.1em; margin-bottom: 1rem;} /* For general order form message */


.table-section, .add-sku-section, .notes-section {
  margin-bottom: 30px;
}
/* Sticker Section Specific Styles */
.sticker-section {
    /* Removed Tailwind bg, padding, rounded from here as it's applied inline for testing */
}
.sticker-section h2 {
    /* color: #333; */
}
.custom-scrollbar::-webkit-scrollbar {
    height: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #cbd5e1; /* Tailwind gray-400 */
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background-color: #e2e8f0; /* Tailwind gray-300 */
}

/* Styles for sticker items if needed, but primarily using inline for debugging */
.sticker-item-debug { 
  /* This class is just for semantic grouping if needed, styles are inline */
  display: flex; /* Added to help with internal alignment if text-center on parent is not enough */
  flex-direction: column; /* Ensure content within card stacks vertically */
  justify-content: space-between; /* Push content to top and bottom */
}
.image-container-debug {
  /* This class is just for semantic grouping if needed, styles are inline */
}

.sticker-item-debug .qty-btn { /* Target buttons within the debugged sticker item */
    background-color: #e9ecef;
    border: 1px solid #ced4da;
    color: #495057;
    padding: 0.25rem 0.6rem;
    border-radius: 0.25rem;
    cursor: pointer;
    font-weight: bold;
    line-height: 1;
}
.sticker-item-debug .qty-btn:hover {
    background-color: #dee2e6;
}
.sticker-item-debug .qty-input { /* Target input within the debugged sticker item */
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    padding: 0.3rem;
    font-size: 0.9rem;
}


h2 {
  border-bottom: 2px solid #48A5BA;
  padding-bottom: 5px;
  margin-bottom: 15px;
  color: #333;
}

.order-table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
.order-table th, .order-table td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; vertical-align: middle; }
.order-table th { background-color: #48A5BA; color: white; font-weight: bold; }
.order-table tbody tr:nth-child(odd) { background-color: #f9f9f9; }

.product-image { max-width: 80px; max-height: 80px; display: block; margin: 0 auto; cursor: pointer; }
.product-details a { font-weight: bold; color: #0056b3; text-decoration: none; }
.product-details a:hover { text-decoration: underline; }
.product-details .description { font-size: 0.85em; color: #666; margin-top: 4px; max-height: 4.5em; overflow: hidden; }

.quantity-input { width: 60px; padding: 5px; text-align: center; border: 1px solid #ccc; border-radius: 4px; }
.center-align { text-align: center; }
.right-align { text-align: right; }

.add-sku-controls { display: flex; gap: 10px; margin-bottom: 15px; }
.add-sku-controls input[type="text"] { flex-grow: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }

.notes-textarea { width: 100%; min-height: 100px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit; font-size: 1em; box-sizing: border-box; }

.bottom-bar {
  position: fixed; bottom: 0; left: 0; width: 100%;
  background-color: #48A5BA; color: white; padding: 15px 20px;
  border-top: 1px solid #ccc; display: flex; justify-content: flex-end;
  align-items: center; box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
  z-index: 50; box-sizing: border-box;
}
.summary-item { margin-left: 25px; font-size: 1.1em; }

.action-button, .submit-button {
  padding: 10px 18px; border: none; border-radius: 5px;
  cursor: pointer; font-size: 1em; transition: background-color 0.2s ease;
}
.action-button { background-color: #eee; color: #333; border: 1px solid #ccc; margin: 5px; }
.action-button:hover { background-color: #ddd; }
.action-button.secondary { background-color: #aaa; color: white; }
.action-button.secondary:hover { background-color: #888; }

.submit-button { background-color: #fff; color: #48A5BA; font-weight: bold; margin-left: 25px; }
.submit-button:hover { background-color: #f0f0f0; }
.submit-button:disabled { background-color: #ccc; color: #666; cursor: not-allowed; }

.submission-status-message {
  width: 100%; text-align: right; margin-top: 10px;
  margin-right: 10px; padding: 5px 0; font-size: 0.9em;
}
.success-message { color: #155724; }
.error-message { color: #D8000C; font-weight: bold; } /* Also used for form-level errors */

.modal-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background-color: rgba(0, 0, 0, 0.6); display: flex;
  justify-content: center; align-items: center; z-index: 1000;
}
.modal-container {
  background-color: #fff; padding: 30px; border-radius: 8px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.2); max-width: 500px; width: 90%;
}
.modal-container h2 { margin-top: 0; color: #48A5BA; }
.modal-actions { margin-top: 20px; text-align: right; }

.image-preview-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background-color: rgba(0, 0, 0, 0.7); display: flex;
  justify-content: center; align-items: center; z-index: 1100; cursor: pointer;
}
.large-image-preview {
  max-width: 80%; max-height: 80%; object-fit: contain;
  box-shadow: 0 0 20px rgba(0,0,0,0.5);
}

@media print { /* Print styles ... */ }
</style>
