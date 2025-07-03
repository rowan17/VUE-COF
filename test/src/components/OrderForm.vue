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
const orderItems = ref([]); // This array will hold ALL orderable items with their currentQuantity
const notes = ref('');
const isLoading = ref(true); // Tracks customerData fetch
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
const stickerQuantities = ref({}); // Manages quantities for stickers specifically
const stickerPrice = ref(3.50);
const stickerItemWidth = ref('192px'); // Approx w-48 (12rem). Card width.

// --- Reactive State for Pinned Bigfoot Recommendations ---
const pinnedBigfootRecs = ref([]);
const isLoadingPinnedRecs = ref(true);
const pinnedRecsError = ref(null);

// --- Computed Properties ---

// Computed property for current date and time for printing
const currentDateTime = computed(() => {
  const now = new Date();
  return new Intl.DateTimeFormat('en-US', { dateStyle: 'full', timeStyle: 'long' }).format(now);
});

// Computed property to get the customer ID without the .html extension
const cleanedCustomerId = computed(() => {
  return props.customerId ? props.customerId.replace(/\.html$/, '') : null;
});

// Overall loading indicator for the entire form
const overallLoading = computed(() => {
  // If customerId is present, we are loading until customerData and product catalog are fetched
  if (cleanedCustomerId.value) {
    return isLoading.value || !allProductsCatalog.value;
  } else {
    // If no customerId (general form), we are loading if stickers or product catalog are still loading
    return isLoadingStickers.value || !allProductsCatalog.value;
  }
});

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

// Filter orderItems to display only history items
const historyItems = computed(() => orderItems.value.filter(item => item.type === 'history'));
// Filter orderItems to display only manually added items
const manuallyAddedItems = computed(() => orderItems.value.filter(item => item.type === 'manual'));
// Filter orderItems to display only recommendation items (which now include pinned ones)
const recommendationItems = computed(() => orderItems.value.filter(item => item.type === 'recommendation'));
// Filter orderItems to display only sticker items (for internal use, main display is different)
const selectedStickerItemsForDisplay = computed(() => orderItems.value.filter(item => item.type === 'sticker'));


// --- Lifecycle Hooks ---
onMounted(async () => {
  // Fetch static data first (catalog, stickers, pinned recs)
  await fetchProductCatalog();
  await loadStickers();
  await fetchPinnedBigfootRecommendations();

  // For general form (no customerId), mark main loading as false immediately
  if (!cleanedCustomerId.value) {
    console.log('General Order Form loaded (no customerId).');
    isLoading.value = false;
  }
  // Initial data fetch for customerId is handled by the watch effect with immediate: true
});

// --- Data Fetching Functions ---
async function fetchData(id) {
  console.log(`Fetching data for customer: ${id}`);
  isLoading.value = true;
  error.value = null;
  customerData.value = null;
  notes.value = '';

  try {
    console.log(`Attempting to fetch data for ID: ${id} from URL: ${import.meta.env.BASE_URL}data/${id}.json`);
    const response = await axios.get(`${import.meta.env.BASE_URL}data/${id}.json`);
    console.log("Raw response data:", response.data); // Log raw response
    customerData.value = response.data;
    // Set BigfootCustomer based on the fetched data, default to false if not present
    // Ensure response.data is an object before trying to access properties
    if (typeof response.data === 'object' && response.data !== null) {
        customerData.value.BigfootCustomer = response.data.BigfootCustomer || false;
    } else {
        console.error("Received non-object data, cannot set BigfootCustomer:", response.data);
        // Handle this case, perhaps by setting a default or throwing an error
        customerData.value = { BigfootCustomer: false }; // Provide a default structure
    }
    console.log("Customer data fetched successfully:", customerData.value);
    // initializeOrderItems will be called by the watch effect on overallLoading
  } catch (err) {
    console.error("Error fetching customer data:", err);
    if (err.response && err.response.status === 404) {
        error.value = `Customer ID Not Found: The customer ID entered in the URL was not found. Please contact order@paracay.com to confirm your ID.`;
        customerData.value = null; // Ensure customerData is null to hide the form
    } else {
        error.value = `An error occurred while loading customer data. Please try again later.`;
    }
    customerData.value = null;
  } finally {
    isLoading.value = false;
  }
}

async function fetchProductCatalog() {
  if (allProductsCatalog.value) return; // Prevent refetching if already loaded
  console.log("Fetching product catalog (productdata.json)...");
  productCatalogError.value = null;
  try {
    const response = await axios.get(`${import.meta.env.BASE_URL}productdata.json`);
    if (Array.isArray(response.data)) {
      allProductsCatalog.value = response.data;
    } else {
      console.error('Product catalog JSON is not an array:', response.data);
      productCatalogError.value = 'Invalid product catalog data format.';
      allProductsCatalog.value = []; // Ensure it's an empty array if malformed
    }
    console.log("Product catalog fetched successfully.");
  } catch (err) {
    console.error("Error fetching product catalog:", err);
    productCatalogError.value = "Could not load product catalog.";
  }
}

async function loadStickers() {
  isLoadingStickers.value = true;
  stickerLoadingError.value = null;
  try {
    const response = await axios.get(`${import.meta.env.BASE_URL}stickers.json`);
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

async function fetchPinnedBigfootRecommendations() {
  isLoadingPinnedRecs.value = true;
  pinnedRecsError.value = null;
  try {
    const response = await axios.get(`${import.meta.env.BASE_URL}bigfootPinnedRecommendations.json`);
    if (Array.isArray(response.data)) {
      pinnedBigfootRecs.value = response.data;
      console.log("Pinned Bigfoot recommendations fetched successfully.");
    } else {
      console.error('Pinned recommendations JSON is not an array:', response.data);
      pinnedRecsError.value = 'Invalid pinned recommendation data format.';
      pinnedBigfootRecs.value = [];
    }
  } catch (error) {
    console.error('Failed to load pinned Bigfoot recommendations:', error);
    pinnedRecsError.value = `Could not fetch pinned recommendations. ${error.message}`;
    pinnedBigfootRecs.value = [];
  } finally {
    isLoadingPinnedRecs.value = false;
  }
}

// --- Initialize Order State: Centralized logic for populating orderItems ---
function initializeOrderItems() {
  // Create a temporary map to build up unique items and manage their currentQuantity
  const tempOrderMap = new Map(); // Key: SKU, Value: item object

  // 1. Add existing manually added items (if any, to preserve user input)
  orderItems.value.filter(item => item.type === 'manual').forEach(item => {
    tempOrderMap.set(item.Sku, { ...item }); // Clone to avoid direct mutation issues during reinitialization
  });

  // 2. Add existing sticker items (to preserve user input)
  orderItems.value.filter(item => item.type === 'sticker').forEach(item => {
    tempOrderMap.set(item.Sku, { ...item });
  });

  // 3. Add history items if customerData is available
  if (customerData.value?.data) {
    (customerData.value.data || []).forEach(item => {
      if (!tempOrderMap.has(item.Sku)) { // Only add if not already present (e.g., manually added)
        const details = fetchProductDetails(item.Sku);
        // Check if details were found and if the image URL is not the placeholder
        if (details && details.image_url !== "https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image") {
          tempOrderMap.set(item.Sku, { ...details, ...item, type: 'history', currentQuantity: 0 });
        } else {
          console.warn(`Skipping history item with no loaded image or details found for SKU: ${item.Sku}`);
        }
      }
    });
  }

  // 4. Add recommendations (both pinned and standard) if customerData is available AND product catalog is loaded
  if (customerData.value && allProductsCatalog.value) { // Added check for allProductsCatalog.value
    const potentialRecs = [];

    // Add pinned Bigfoot recommendations first if BigfootCustomer
    if (customerData.value.BigfootCustomer && pinnedBigfootRecs.value.length > 0) {
      pinnedBigfootRecs.value.forEach(pinnedItem => {
        const details = fetchProductDetails(pinnedItem.Sku);
        // Check if details were found and if the image URL is not the placeholder
        if (details && details.image_url !== "https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image") {
          potentialRecs.push({ ...details, type: 'recommendation' });
        } else {
          console.warn(`Skipping pinned recommendation with no loaded image or details found for SKU: ${pinnedItem.Sku}`);
        }
      });
    }

    // Add standard recommendations from customerData
    if (customerData.value.recommendations) {
      customerData.value.recommendations.forEach(recItem => {
        const details = fetchProductDetails(recItem.Sku);
        // Check if details were found and if the image URL is not the placeholder
        if (details && details.image_url !== "https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image") {
          potentialRecs.push({ ...details, type: 'recommendation' });
        } else {
          console.warn(`Skipping recommendation with no loaded image or details found for SKU: ${recItem.Sku}`);
        }
      });
    }

    // Now add these potential recommendations to the map, avoiding duplicates and history items
    potentialRecs.forEach(recItem => {
      // Ensure it's not already in history or manually added, and not already added as another rec
      if (!tempOrderMap.has(recItem.Sku) || tempOrderMap.get(recItem.Sku).type === 'history') {
        // If it's a history item that's also a recommendation, we'll keep it as history.
        // Otherwise, add as a recommendation.
        if (tempOrderMap.has(recItem.Sku) && tempOrderMap.get(recItem.Sku).type === 'history') {
            // Do nothing, history takes precedence for display type
        } else {
            tempOrderMap.set(recItem.Sku, { ...recItem, currentQuantity: 0 });
        }
      }
    });
  }

  // Convert the map values back to an array for orderItems
  orderItems.value = Array.from(tempOrderMap.values());

  console.log("Initialized order items:", JSON.parse(JSON.stringify(orderItems.value)));
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
  // Remove all existing sticker items from orderItems to re-add them based on current quantities
  const nonStickerOrderItems = orderItems.value.filter(item => item.type !== 'sticker');
  let updatedStickerItems = [];

  for (const sku in stickerQuantities.value) {
    if (stickerQuantities.value[sku] > 0) {
      const stickerData = fetchedStickers.value.find(s => s.sku === sku);
      if (stickerData) {
        updatedStickerItems.push({
          Sku: stickerData.sku,
          ItemTitle: `Sticker - ${stickerData.sku}`,
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
  orderItems.value = [...nonStickerOrderItems, ...updatedStickerItems];
  console.log("Order items after sticker sync:", JSON.parse(JSON.stringify(orderItems.value)));
}

// Watch stickerQuantities for changes and sync to main orderItems
watch(stickerQuantities, () => {
  syncStickersToOrder();
}, { deep: true });


// --- Methods (Add SKU, Submit, CSV, Print, Image Preview) ---
function fetchProductDetails(sku) {
    if (productCatalogError.value) {
        console.error(`Product catalog is unavailable. Cannot fetch details for SKU ${sku}.`);
        return null;
    }
    if (!allProductsCatalog.value) {
        console.warn("Product catalog is still loading when trying to fetch details for", sku);
        return null;
    }
    const product = allProductsCatalog.value.find(p => p.sku && p.sku.toUpperCase() === sku.toUpperCase());
    if (product) {
        return {
            image_url: product.image_url || "https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image",
            product_url: product.product_url || "#", ItemTitle: product.Title, Sku: product.sku,
            description: stripHtml(product.description || "No description available."), price: product.WholesalePrice
        };
    }
    return null; // Product not found in catalog
}

async function addSkuItem(sku) {
  if (!sku) return;

  if (productCatalogError.value) {
      alert(`Product catalog is unavailable. Cannot add SKU ${sku}.`);
      return;
  }
  if (!allProductsCatalog.value) {
      alert("Product catalog is still loading. Please try again.");
      return;
  }

  const details = fetchProductDetails(sku);
  // Check if details were found and if the image URL is not the placeholder
  if (details && details.image_url !== "https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image") {
    // Check if the item already exists in orderItems (regardless of type)
    const existingItemIndex = orderItems.value.findIndex(item => item.Sku === details.Sku);

    if (existingItemIndex > -1) {
      // If it exists, update its type to 'manual' and set quantity to 1 if it was 0
      const existingItem = orderItems.value[existingItemIndex];
      if (existingItem.type !== 'manual') { // Only change type if not already manual
          existingItem.type = 'manual';
      }
      if (existingItem.currentQuantity === 0) {
          existingItem.currentQuantity = 1;
      }
      alert(`Item ${sku} is already in your list. Quantity updated to 1 if it was 0.`);
    } else {
      // If it doesn't exist, add it as a new manual item
      orderItems.value.push({ ...details, currentQuantity: 1, type: 'manual' });
    }
  } else {
    console.warn(`Skipping manually added item with no loaded image or details found for SKU: ${sku}`);
    alert(`Could not find details or image for SKU: ${sku}`);
  }
}

async function submitOrder() {
  isLoading.value = true;
  submissionStatus.value = ''; error.value = null;
  const orderData = {
    customerId: cleanedCustomerId.value,
    customerInfo: {
      contactName: customerData.value?.ContactName, companyName: customerData.value?.CompanyName,
      email: customerData.value?.Email, phone: customerData.value?.Phone,
      address: customerData.value?.Address
    },
    notes: notes.value,
    items: orderItems.value.filter(item => item.currentQuantity > 0)
      .map(item => ({
        Sku: item.Sku, ItemTitle: item.ItemTitle, quantity: item.currentQuantity,
        price: item.price, rowTotal: (parseFloat(item.price || 0) * Number(item.currentQuantity || 0)).toFixed(2)
      })),
    totalQuantity: totalQuantity.value, totalPrice: totalPrice.value,
    orderTimestamp: new Date().toISOString(),
    topRecommendations: recommendationItems.value.slice(0, 5).map(item => ({ Sku: item.Sku, ItemTitle: item.ItemTitle }))
  };
  const formData = new URLSearchParams();
  formData.append('realemail', orderData.customerInfo.email || '');
  formData.append('company_name', orderData.customerInfo.companyName || 'N/A');
  formData.append('url_link', window.location.href);
  formData.append('order_details', formatOrderDetailsForEmail(orderData));
  try {
    let mailEndpoint = import.meta.env.VITE_MAIL_ENDPOINT;
    if (mailEndpoint && !mailEndpoint.startsWith('http')) {
      // For production, VITE_MAIL_ENDPOINT is 'mail.php', prepend BASE_URL
      // Ensure BASE_URL ends with a slash and mailEndpoint doesn't start with one if combining.
      // Or, more simply, ensure BASE_URL is correctly set (e.g. /orderform/new_cof/)
      // and mail.php is just the filename.
      mailEndpoint = `${import.meta.env.BASE_URL}${mailEndpoint}`;
    }
    const response = await axios.post(mailEndpoint, formData, {
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
    error.value = error.value || submissionStatus.value; // Set error value as well
  } finally {
    isLoading.value = false;
  }
}

function formatOrderDetailsForEmail(orderData) {
  let detailsString = `Customer Name: ${orderData.customerInfo.contactName || 'N/A'}\n`;
  detailsString += `Company: ${orderData.customerInfo.companyName || 'N/A'}\n`;
  detailsString += `Email: ${orderData.customerInfo.email || 'N/A'}\n`;
  detailsString += `Phone: ${orderData.customerInfo.phone || 'N/A'}\n`;
  if (orderData.customerInfo.address) {
    detailsString += `Shipping Address: ${orderData.customerInfo.address}\n`;
  }
  detailsString += `\n--- ITEMS ORDERED ---\n`;
  orderData.items.forEach(item => {
    // Format each item as: SKU - quantity x $price
    detailsString += `${item.Sku} - ${item.quantity} x $${item.price}\n`;
  });
  detailsString += `\n--- TOTALS ---\n`;
  detailsString += `Total Quantity: ${orderData.totalQuantity}\n`;
  detailsString += `Total Price: $${orderData.totalPrice}\n`;

  if (orderData.topRecommendations && orderData.topRecommendations.length > 0) {
    detailsString += `\n--- TOP 5 RECOMMENDED ITEMS ---\n`;
    orderData.topRecommendations.forEach(rec => {
      detailsString += `${rec.ItemTitle} (Sku: ${rec.Sku})\n`;
    });
  }

  if (orderData.notes) {
    detailsString += `\n--- NOTES ---\n${orderData.notes}\n`;
  }
  detailsString += `\nOrder Timestamp: ${orderData.orderTimestamp}\n`;
  return detailsString;
}

function downloadCSV() {
  if (!cleanedCustomerId.value || !customerData.value || !customerData.value.data) {
       alert("No customer-specific order history available to download.");
       return;
   }
  const headers = ['ItemTitle', 'Sku', 'Price', 'Quantity', 'TimesPurchased'];
  const rows = customerData.value.data.map(item => {
    const productDetails = fetchProductDetails(item.Sku);
    return {
      ItemTitle: item.ItemTitle || (productDetails ? productDetails.ItemTitle : 'N/A'), // Fallback for ItemTitle
      Sku: item.Sku,
      Price: productDetails ? productDetails.price : 'N/A', // Get price from catalog
      Quantity: item.Quantity,
      TimesPurchased: item.TimesPurchased
    };
  });
  const contactInfo = customerData.value;
  const headerRows = [
      ["Paradise Cay Publications /// orders@paracay.com /// (707) 822-9063"],
      [`${contactInfo.CompanyName} /// ${contactInfo.ContactName} /// ${contactInfo.Phone} /// ${contactInfo.Email}`],
      [`Shipping Address: ${contactInfo.Address.replace(/<br>/g, ' ')}`],
      [], headers
  ];
  const csvData = headerRows.concat(rows.map(row => headers.map(header => row[header])));
  const csvContent = "data:text/csv;charset=utf-8,"
      + csvData.map(e => e.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(",")).join("\n");
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", `orderhistory_${cleanedCustomerId.value}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

async function printOrder() {
  // Ensure the DOM is updated before printing
  await nextTick();
  window.print();
}

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

// Watch for changes in cleanedCustomerId to fetch customer-specific data
watch(cleanedCustomerId, (newCleanId, oldCleanId) => {
  if (newCleanId && newCleanId !== oldCleanId) {
    fetchData(newCleanId);
  } else if (!newCleanId && oldCleanId) { // If cleaned customerId becomes null from a previous value
    customerData.value = null;
  }
}, { immediate: true }); // Fetch data immediately if customerId is available on mount

// Watch for overall loading to complete before initializing order items
watch(overallLoading, (newOverallLoading, oldOverallLoading) => {
  // Initialize order items only when overall loading changes from true to false
  // This ensures all necessary data (customer, catalog, stickers, pinned recs, customerData) is available
  if (!newOverallLoading && oldOverallLoading) {
    console.log("Overall loading complete, initializing order items.");
    initializeOrderItems();
  }
});

const skuToAdd = ref('');
function handleAddSku() {
    addSkuItem(skuToAdd.value.trim());
    skuToAdd.value = '';
}

// Utility function to strip HTML tags
function stripHtml(html) {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  return doc.body.textContent || "";
}

</script>

<template>
  <div class="order-form-container">
    <div class="logo-container">
      <img src="https://paracay.com/orderform/logo.png" alt="Paradise Cay Publications Logo" class="logo">
    </div>

    <div v-if="overallLoading" class="loading-overlay">Loading data...</div>
    <div v-if="error" class="error-message">
      <p>Error:</p>
      <p>{{ error }}</p>
    </div>

    <div v-if="cleanedCustomerId && customerData && !overallLoading && !error">
      <header class="form-header">
        <div class="customer-info">
          <p><strong>Contact:</strong> {{ customerData.ContactName }} ({{ customerData.Email }}, {{ customerData.Phone }})</p>
          <p><strong>Company:</strong> {{ customerData.CompanyName }}</p>
          <p><strong>Default Shipping Address:</strong></p>
          <div v-html="customerData.Address" class="address"></div>
          <p><small>Order Form Updated: {{ customerData.date }}</small></p>
        </div>
        <button @click="downloadCSV" class="action-button">Download Order History (CSV)</button>
        <hr>
        <p class="info-message">Annual publications or discontinued items may not appear below. Use the Notes field for inquiries.</p>
        <p class="print-timestamp" style="display: none;">Printed On: {{ currentDateTime }}</p>
      </header>
    </div>
    <div v-if="!cleanedCustomerId && !overallLoading && !error" class="form-header">
        <p class="info-message text-lg">General Order Form. Add items by SKU or select from available stickers.</p>
        <p class="print-timestamp" style="display: none;">Printed On: {{ currentDateTime }}</p>
    </div>

    <!-- Conditional rendering for Sticker Section based on customerData.BigfootCustomer -->
    <section v-if="customerData && customerData.BigfootCustomer" class="sticker-section-container">
      <h2 class="sticker-section-header">
        <svg xmlns="http://www.w3.org/2000/svg" class="header-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
        </svg>
        Stickers
      </h2>
      <div v-if="isLoadingStickers" class="message-text">Loading stickers...</div>
      <div v-else-if="stickerLoadingError" class="message-text error-message">{{ stickerLoadingError }}</div>
      <div v-else-if="fetchedStickers.length === 0" class="message-text">No stickers available.</div>

      <div v-else class="sticker-list-wrapper custom-scrollbar">
        <div v-for="sticker in fetchedStickers" :key="sticker.sku"
              class="sticker-card"
              :style="{ minWidth: stickerItemWidth, maxWidth: stickerItemWidth }">

          <div class="image-wrapper">
            <img :src="sticker.img" :alt="`Sticker ${sticker.sku}`"
                  @error="onStickerImageError"/>
          </div>

          <p class="sticker-sku" :title="sticker.sku">SKU: {{ sticker.sku }}</p>
          <p class="sticker-price">${{ stickerPrice.toFixed(2) }}</p>

          <div class="quantity-controls">
            <button @click="decrementStickerQuantity(sticker.sku)" class="qty-btn">-</button>
            <input type="number"
                  :value="stickerQuantities[sticker.sku] || 0"
                  @input="updateStickerQuantity(sticker.sku, $event.target.value)"
                  min="0" class="qty-input"/>
            <button @click="incrementStickerQuantity(sticker.sku)" class="qty-btn">+</button>
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
          <tr v-for="item in historyItems" :key="item.Sku">
            <td>
              <img :src="item.image_url || 'https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image'" :alt="item.ItemTitle"
                   class="product-image" @error="onImageError" @mouseover="showPreview(item.image_url)" @mouseout="hidePreview">
            </td>
            <td class="product-details">
              <a :href="item.product_url" target="_blank" rel="noopener noreferrer">{{ item.ItemTitle }} - {{ item.Sku }}</a>
              <p class="description">{{ item.description }}</p>
            </td>
            <td>${{ item.price }}</td>
            <td class="center-align">{{ item.Quantity }}</td>
            <td class="center-align">{{ item.TimesPurchased }}</td>
            <td><input type="number" v-model.number="item.currentQuantity" min="0" class="quantity-input"></td>
            <td class="right-align">${{ (parseFloat(item.price || 0) * Number(item.currentQuantity || 0)).toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section v-if="cleanedCustomerId && customerData" class="table-section">
      <h2>We Think You Might Like</h2>
      <div v-if="isLoadingPinnedRecs" class="message-text">Loading special recommendations...</div>
      <div v-else-if="pinnedRecsError" class="message-text error-message">{{ pinnedRecsError }}</div>
      <table class="order-table">
        <thead><tr><th>Image</th><th>Product</th><th>Price</th><th>Order QTY</th><th>Total Cost</th></tr></thead>
        <tbody>
          <tr v-if="recommendationItems.length === 0"><td colspan="5">No recommendations available.</td></tr>
          <tr v-for="item in recommendationItems" :key="item.Sku">
            <td>
              <img :src="item.image_url || 'https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image'" :alt="item.ItemTitle"
                   class="product-image" @error="onImageError" @mouseover="showPreview(item.image_url)" @mouseout="hidePreview">
            </td>
            <td class="product-details">
              <a :href="item.product_url" target="_blank" rel="noopener noreferrer">{{ item.ItemTitle }} - {{ item.Sku }}</a>
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
            <tr v-for="item in manuallyAddedItems" :key="item.Sku">
              <td>
                <img :src="item.image_url || 'https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image'" :alt="item.ItemTitle"
                      class="product-image" @error="onImageError" @mouseover="showPreview(item.image_url)" @mouseout="hidePreview">
              </td>
              <td class="product-details">
                {{ item.ItemTitle }} - {{ item.Sku }}
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
      <button @click="submitOrder" :disabled="totalQuantity === 0 || overallLoading" class="submit-button">
        {{ overallLoading ? 'Processing...' : 'Submit Order' }}
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

  <!-- Print-only details section -->
  <div class="print-only-details" style="display: none;">
    <div class="print-header">
      <h2>Order Details</h2>
      <p>Printed On: {{ currentDateTime }}</p>
    </div>

    <div class="print-customer-info">
      <h3>Customer Information</h3>
      <p><strong>Contact:</strong> {{ customerData?.ContactName }} ({{ customerData?.Email }}, {{ customerData?.Phone }})</p>
      <p><strong>Company:</strong> {{ customerData?.CompanyName }}</p>
      <p><strong>Shipping Address:</strong></p>
      <div v-html="customerData?.Address"></div>
    </div>

    <div class="print-items">
      <h3>Items Ordered</h3>
      <div v-for="item in orderItems.filter(item => item.currentQuantity > 0)" :key="item.Sku" class="print-item">
        {{ item.Sku }} - {{ item.currentQuantity }} x ${{ item.price }} - {{ item.ItemTitle }}
      </div>
    </div>

    <div class="print-totals">
      <h3>Totals</h3>
      <p>Total Quantity: {{ totalQuantity }}</p>
      <p>Total Price: ${{ totalPrice }}</p>
    </div>

    <div v-if="notes" class="print-notes">
      <h3>Notes</h3>
      <p>{{ notes }}</p>
    </div>

    <div v-if="recommendationItems.length > 0" class="print-recommendations">
      <h3>Top 5 Recommended Items</h3>
      <div v-for="rec in recommendationItems.slice(0, 5)" :key="rec.Sku" class="print-rec-item">
        {{ rec.ItemTitle }} (Sku: {{ rec.Sku }})
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Basic Styling - Enhance with a UI library or more detailed CSS */
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

.logo-container {
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
  padding: 15px 0; /* Increased padding for better visual spacing */
  background-color: #48A5BA; /* Changed background to blue */
  border-radius: 8px 8px 0 0; /* Rounded top corners to match container */
  box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Subtle shadow */
}

.logo {
  max-width: 300px; /* Adjust as needed */
  height: auto;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 1.5em;
    color: #333;
    z-index: 10000;
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
.info-message.text-lg { font-size: 1.1em; margin-bottom: 1rem;}


.table-section, .add-sku-section, .notes-section {
  margin-bottom: 30px;
}

/* --- Sticker Section Styling --- */
.sticker-section-container {
  background-color: #e3f2fd; /* A light blue background */
  padding: 1.5rem; /* Good internal spacing */
  border-radius: 0.5rem; /* Slightly rounded corners */
  box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06); /* Subtle inner shadow */
  margin-bottom: 2rem; /* Space below the section */
}

.sticker-section-header {
  font-size: 1.5rem; /* Larger header text */
  font-weight: bold;
  color: #1565c0; /* A darker blue for the header */
  margin-bottom: 1rem;
  border-bottom: 2px solid #90caf9; /* A lighter blue underline */
  padding-bottom: 0.5rem;
  display: flex;
  align-items: center;
}

.header-icon {
  height: 1.5rem;
  width: 1.5rem;
  margin-right: 0.5rem;
  color: #2196f3; /* Medium blue for the icon */
}

/* Message Text (Loading, Error, No Stickers) */
.message-text {
  text-align: center;
  font-size: 1.125rem;
  padding-top: 1rem;
  padding-bottom: 1rem;
  color: #1976d2; /* Blue for loading/no stickers messages */
}

.message-text.error-message {
  color: #d32f2f; /* Red for error messages */
  font-weight: 500;
}

/* --- Sticker List Wrapper (The Flexbox Parent) --- */
.sticker-list-wrapper {
  display: flex;
  flex-wrap: nowrap; /* Prevents wrapping to multiple rows */
  overflow-x: auto; /* Enables horizontal scrolling */
  padding-bottom: 1rem; /* Space for the scrollbar */
  gap: 1rem; /* Space between sticker cards */
  -webkit-overflow-scrolling: touch; /* Improves scrolling on iOS */
}

/* Custom scrollbar for better appearance */
.custom-scrollbar::-webkit-scrollbar {
  height: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #94a3b8; /* Tailwind slate-400 */
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background-color: #e2e8f0; /* Tailwind slate-200 */
}

/* --- Individual Sticker Card Styling --- */
.sticker-card {
  box-sizing: border-box; /* Include padding/border in width calculation */
  flex-shrink: 0; /* Prevent cards from shrinking */
  flex-grow: 0; /* Prevent cards from growing */
  background-color: #ffffff; /* White background for cards */
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* Standard shadow */
  padding: 0.75rem;
  text-align: center;
  transition: transform 0.2s ease-in-out; /* Smooth hover effect */
  /* min-width and max-width are set inline from stickerItemWidth */
}

.sticker-card:hover {
  transform: scale(1.03); /* Slightly less aggressive scale on hover */
}

/* Image Wrapper and Image */
.image-wrapper {
  width: 9rem; /* 144px */
  height: 9rem; /* 144px */
  margin-left: auto;
  margin-right: auto;
  margin-bottom: 0.5rem;
  border: 1px solid #e2e8f0; /* Light gray border */
  border-radius: 0.25rem;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #f8fafc; /* Very light gray background for image area */
}

.image-wrapper img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

/* Sticker SKU text */
.sticker-sku {
  font-size: 0.75rem;
  color: #4a5568; /* Darker gray for text */
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap; /* Keep SKU on one line */
  margin-bottom: 0.25rem;
  height: 1.5rem; /* Fixed height to prevent layout shifts */
  line-height: 1.5rem;
}

/* Sticker Price text */
.sticker-price {
  font-size: 0.875rem;
  font-weight: 600;
  color: #6366f1; /* Indigo for price */
  margin-top: 0.25rem;
  margin-bottom: 0.25rem;
}

/* Quantity Controls */
.quantity-controls {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-top: 0.5rem;
  gap: 0.25rem; /* Space between buttons and input */
}

.qty-btn {
  background-color: #e2e8f0;
  color: #4a5568;
  font-weight: bold;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px; /* Pill shape */
  transition: background-color 0.2s ease-in-out;
  border: none;
  cursor: pointer;
}

.qty-btn:hover {
  background-color: #cbd5e1;
}

.qty-input {
  width: 3rem;
  text-align: center;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  padding: 0.25rem 0.5rem;
  -moz-appearance: textfield; /* Hide arrows on Firefox */
}
.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button {
  -webkit-appearance: none; /* Hide arrows on Chrome, Safari, Edge */
  margin: 0;
}

/* --- Rest of your existing styles --- */

h2 {
  border-bottom: 2px solid #48A5BA;
  padding-bottom: 5px;
  margin-bottom: 15px;
  color: #333;
}

.order-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9em;
    table-layout: fixed;
}
.order-table th, .order-table td {
    border: 1px solid #ddd;
    padding: 8px 10px;
    text-align: left;
    vertical-align: middle;
    word-wrap: break-word;
}
.order-table th { background-color: #48A5BA; color: white; font-weight: bold; }
.order-table tbody tr:nth-child(odd) { background-color: #f9f9f9; }

/* Column Width Definitions */
.order-table th:nth-child(1), .order-table td:nth-child(1) {
    width: 100px;
    text-align: center;
}

.order-table th:nth-child(2), .order-table td:nth-child(2) {
    width: 35%;
    min-width: 250px;
}

.order-table th:nth-child(3), .order-table td:nth-child(3) {
    width: 80px;
    text-align: right;
}

.order-table th:nth-child(4), .order-table td:nth-child(4) {
    width: 100px;
    text-align: center;
}

.order-table th:nth-child(5), .order-table td:nth-child(5) {
    width: 100px;
    text-align: center;
}

.order-table th:nth-child(6), .order-table td:nth-child(6) {
    width: 100px;
    text-align: center;
}

.order-table th:nth-child(7), .order-table td:nth-child(7) {
    width: 120px;
    text-align: right;
}

.product-image { max-width: 80px; max-height: 80px; display: block; margin: 0 auto; cursor: pointer; }
.product-details a { font-weight: bold; color: #0056b3; text-decoration: none; }
.product-details a:hover { text-decoration: underline; }
.product-details .description {
    font-size: 0.85em;
    color: #666;
    margin-top: 4px;
    max-height: 4.5em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: normal;
}

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
.error-message { color: #D8000C; font-weight: bold; }

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
    justify-content: center; align-items: center; z-index: 1100;
    cursor: pointer;
}
.large-image-preview {
    max-width: 80%; max-height: 80%; object-fit: contain;
    box-shadow: 0 0 20px rgba(0,0,0,0.5);
}

@media print {
  body * {
    visibility: hidden;
  }
  .order-form-container {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    padding: 20px; /* Add some padding for print */
    border: none;
    box-shadow: none;
    background-color: transparent;
    visibility: visible; /* Make the container visible */
  }

  /* Hide main form elements */
  .logo-container,
  .loading-overlay,
  .error-message,
  .form-header, /* Hide the main header */
  .sticker-section-container,
  .table-section,
  .add-sku-section,
  .notes-section,
  .bottom-bar,
  .image-preview-overlay,
  .modal-overlay {
    display: none !important;
  }

  /* Show the print-only details section */
  .print-only-details {
    display: block !important;
    visibility: visible !important;
    width: 100%;
  }

  /* Basic styling for the print-only section */
  .print-header, .print-customer-info, .print-items, .print-totals, .print-notes, .print-recommendations {
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 1px solid #eee;
  }

  .print-header h2, .print-customer-info h3, .print-items h3, .print-totals h3, .print-notes h3, .print-recommendations h3 {
      margin-top: 0;
      margin-bottom: 5px;
      font-size: 1.1em;
      color: #333;
  }

  .print-item, .print-rec-item {
      margin-bottom: 5px;
      font-size: 0.9em;
      line-height: 1.4;
  }

  .print-customer-info p, .print-totals p, .print-notes p {
      margin: 5px 0;
      font-size: 0.9em;
  }

  .print-customer-info div {
      margin-left: 10px;
      font-style: italic;
      font-size: 0.9em;
  }
}
</style>
