<script setup>
import { ref, computed, watch, onMounted, defineProps } from 'vue';
import axios from 'axios'; // Using axios for data fetching
// Import PapaParse for CSV generation if you choose to use it
// import Papa from 'papaparse';
// Import a modal component library if desired
// import VueFinalModal from 'vue-final-modal';
// Import Swiper for Vue if needed for stickers
// import { Swiper, SwiperSlide } from 'swiper/vue';
// import 'swiper/css';


// --- Props ---
// Assumes vue-router is configured to pass the customerId from the URL as a prop

const props = defineProps({
  customerId: {
    type: String,
    required: false // Make it not required for the /order route
  }
});

onMounted(() => {
  if (props.customerId) {
    console.log('Order Form loaded for customer:', props.customerId);
    // Fetch customer-specific data
  } else {
    console.log('General Order Form loaded');
    // Perform general order form setup
  }
});
// --- Reactive State ---
const customerData = ref(null); // Holds the fetched customer JSON data
const orderItems = ref([]); // Holds all products (history, recs, added) with currentQuantity
const notes = ref(''); // For the notes textarea
const isLoading = ref(true); // Loading state indicator
const error = ref(null); // Error message holder
const showConfirmationModal = ref(false); // To control the confirmation modal visibility
const submissionStatus = ref(''); // For success/error messages from PHP script
// Add refs for other features like large image preview if needed
const showLargeImage = ref(false);
const largeImageUrl = ref('');

// --- Data Fetching ---
async function fetchData(id) {
  console.log(`Fetching data for customer: ${id}`);
  isLoading.value = true;
  error.value = null;
  customerData.value = null;
  orderItems.value = [];
  notes.value = '';

  try {
    // Fetch the JSON data from the public/data/ directory
    // Adjust the path if your files are located elsewhere
    const response = await axios.get(`/data/${id}.json`);
    customerData.value = response.data;
    console.log("Data fetched successfully:", customerData.value);

    // Initialize the order items based on fetched data
    initializeOrderItems();

  } catch (err) {
    console.error("Error fetching customer data:", err);
    if (err.response && err.response.status === 404) {
       error.value = `Error: Could not find data file for customer ID: ${id}. Please check the ID.`;
    } else {
       error.value = `An error occurred while loading customer data. Please try again later.`;
    }
    customerData.value = null; // Ensure data is null on error
  } finally {
    isLoading.value = false;
  }
}

// --- Initialize Order State ---
function initializeOrderItems() {
  if (!customerData.value) return;

  // Combine history and recommendations, adding a 'currentQuantity' field
  // Ensure unique SKUs if necessary and handle potential overlaps
  const allProducts = [
    ...(customerData.value.data || []).map(item => ({ ...item, type: 'history' })),
    ...(customerData.value.recommendations || []).map(item => ({ ...item, type: 'recommendation' })),
    // Add sticker items here if applicable, mark with type: 'sticker'
    // Manually added SKU items will be added later
  ];

  // Use a Map to handle potential duplicate SKUs if needed (optional)
  const uniqueProducts = new Map();
  allProducts.forEach(item => {
    if (!uniqueProducts.has(item.SKU)) {
      uniqueProducts.set(item.SKU, {
        ...item,
        currentQuantity: 0 // Initialize quantity for the current order
      });
    } else {
      // Optional: Handle duplicates (e.g., merge info, log warning)
      console.warn(`Duplicate SKU found and ignored: ${item.SKU}`);
    }
  });

  orderItems.value = Array.from(uniqueProducts.values());
  console.log("Initialized order items:", orderItems.value);
}

// --- Computed Properties ---
// Calculate total quantity of items being ordered
const totalQuantity = computed(() => {
  return orderItems.value.reduce((sum, item) => sum + Number(item.currentQuantity || 0), 0);
});

// Calculate total price of the order
const totalPrice = computed(() => {
  const total = orderItems.value.reduce((sum, item) => {
    const price = parseFloat(item.price || 0);
    const quantity = Number(item.currentQuantity || 0);
    return sum + (price * quantity);
  }, 0);
  return total.toFixed(2); // Format to 2 decimal places
});

// Filter items for different table sections
const historyItems = computed(() => orderItems.value.filter(item => item.type === 'history'));
const recommendationItems = computed(() => orderItems.value.filter(item => item.type === 'recommendation'));
const stickerItems = computed(() => orderItems.value.filter(item => item.type === 'sticker')); // Example
const manuallyAddedItems = computed(() => orderItems.value.filter(item => item.type === 'manual')); // Example

// --- Methods ---

// Placeholder for adding item by SKU
// This would typically involve calling an API endpoint to get product details
async function addSkuItem(sku) {
  if (!sku) return;
  console.log(`Attempting to add SKU: ${sku}`);
  // --- Replace with actual API call ---
  // Example: const details = await fetchProductDetails(sku);
  const details = MOCK_fetchProductDetails(sku); // Using mock function for demo
  // --- End Replace ---

  if (details) {
    // Check if SKU already exists in orderItems
    const existingItem = orderItems.value.find(item => item.SKU === details.SKU);
    if (existingItem) {
      // Optionally increment quantity or alert user
      alert(`Item ${sku} is already in your list.`);
    } else {
      // Add the new item with type 'manual'
      orderItems.value.push({
        ...details,
        currentQuantity: 1, // Default quantity to 1 when adding
        type: 'manual'
      });
    }
  } else {
    alert(`Could not find details for SKU: ${sku}`);
  }
}

// Mock function for SKU lookup (replace with actual API call)
function MOCK_fetchProductDetails(sku) {
    console.warn("Using MOCK SKU lookup");
    if (sku === 'SKUADD1') {
        return {
            image_url: "https://placehold.co/80x80/aabbcc/ffffff?text=SKUADD1",
            product_url: "#",
            Title: "Manually Added Item One",
            SKU: "SKUADD1",
            description: "This item was added manually via SKU.",
            price: "15.00"
            // Note: No 'Quantity' or 'Times' (past history) for manually added items
        };
    }
    return null;
}


// Handle order submission
async function submitOrder() {
  isLoading.value = true; // Indicate processing for the submit button
  submissionStatus.value = ''; // Clear previous status
  error.value = null; // Clear previous error

  // 1. Prepare the data payload
  const orderData = {
    customerId: props.customerId,
    customerInfo: { // Send only necessary info
      name: customerData.value?.Name,
      company: customerData.value?.Company,
      email: customerData.value?.Email,
      phone: customerData.value?.Phone,
      // Address will be part of the formatted order_details string
      address: customerData.value?.Address
    },
    notes: notes.value,
    items: orderItems.value
      .filter(item => item.currentQuantity > 0) // Only send items with quantity > 0
      .map(item => ({ // Send only relevant fields for the order
        SKU: item.SKU,
        Title: item.Title,
        quantity: item.currentQuantity,
        price: item.price,
        rowTotal: (item.price * item.currentQuantity).toFixed(2)
      })),
    totalQuantity: totalQuantity.value,
    totalPrice: totalPrice.value,
    orderTimestamp: new Date().toISOString()
  };

  console.log("Order Payload:", JSON.stringify(orderData, null, 2));

  // 2. Prepare FormData for mail.php
  const formData = new URLSearchParams();
  formData.append('realemail', orderData.customerInfo.email || '');
  // mail.php expects 'company_name', ensure this matches if you changed mail.php
  formData.append('company_name', orderData.customerInfo.company || 'N/A');
  formData.append('url_link', window.location.href);
  formData.append('order_details', formatOrderDetailsForEmail(orderData));

  // 3. Send data to mail.php
  try {
    // Use the full URL of your PHP server
    const response = await axios.post('http://localhost:8001/mail.php', formData, {
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      }
    });

    console.log('Response from mail.php:', response.data);

    if (response.data && response.data.success) {
      submissionStatus.value = response.data.message || "Order submitted successfully!";
      showConfirmationModal.value = true; // Show your existing confirmation modal
      // Optionally, reset parts of the form here if needed
      // e.g., orderItems.value.forEach(item => item.currentQuantity = 0);
      // notes.value = '';
    } else {
      submissionStatus.value = response.data.message || "Failed to submit order. Please try again.";
      error.value = submissionStatus.value; // Set error to display in error style
    }

  } catch (err) {
    console.error("Order submission failed:", err);
    submissionStatus.value = "An error occurred while submitting your order. Please contact support.";
    if (err.response) {
      // The request was made and the server responded with a status code
      // that falls out of the range of 2xx
      console.error("Error data:", err.response.data);
      console.error("Error status:", err.response.status);
      submissionStatus.value += ` (Server status: ${err.response.status})`;
      // Check if the response is HTML (often indicates a PHP error page)
      if(err.response.data && typeof err.response.data === 'string' && err.response.data.toLowerCase().includes("<!doctype html>")) {
        submissionStatus.value = "Error: Received an HTML error page from the server. Please check the PHP script (mail.php) for errors.";
      }
    } else if (err.request) {
      // The request was made but no response was received
      submissionStatus.value = "Error: No response from the server. Please ensure mail.php is accessible and correctly configured.";
    } else {
      // Something happened in setting up the request that triggered an Error
      submissionStatus.value = "Error: Could not send the request to the server.";
    }
    error.value = submissionStatus.value;
  } finally {
    isLoading.value = false; // Stop indicating processing
  }
}

// Helper function to format order details for the email body
function formatOrderDetailsForEmail(orderData) {
  let detailsString = `Customer Name: ${orderData.customerInfo.name || 'N/A'}\n`;
  detailsString += `Company: ${orderData.customerInfo.company || 'N/A'}\n`;
  detailsString += `Email: ${orderData.customerInfo.email || 'N/A'}\n`;
  detailsString += `Phone: ${orderData.customerInfo.phone || 'N/A'}\n`;
  if (orderData.customerInfo.address) {
    detailsString += `Shipping Address: ${orderData.customerInfo.address}\n`; // Keep <br> if present, mail.php handles it
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

// Generate and download CSV
function downloadCSV() {
  console.log("Generating CSV...");
  if (!customerData.value || !customerData.value.data) {
      alert("No order history data available to download.");
      return;
  }

  // Define CSV headers
  const headers = ['Title', 'SKU', 'Price', 'Quantity Purchased', 'Times Purchased'];
  // Prepare data rows (only history items)
  const rows = customerData.value.data.map(item => ({
      Title: item.Title,
      SKU: item.SKU,
      Price: item.price,
      QuantityPurchased: item.Quantity,
      TimesPurchased: item.Times
  }));

  // Add contact info header rows (similar to original)
  const contactInfo = customerData.value; // Assuming top-level info is needed
  const headerRows = [
      ["Paradise Cay Publications /// orders@paracay.com /// (707) 822-9063"], // Static header
      [`${contactInfo.Company} /// ${contactInfo.Name} /// ${contactInfo.Phone} /// ${contactInfo.Email}`], // Dynamic customer header
      [`Shipping Address: ${contactInfo.Address.replace(/<br>/g, ' ')}`], // Address header
      [], // Blank separator row
      headers // Actual data headers
  ];

  // Combine header rows and data rows
  const csvData = headerRows.concat(rows.map(row => headers.map(header => row[header.replace(/ /g, '')]))); // Map data to header order

  // Convert array of arrays to CSV string (basic implementation)
  // Consider using PapaParse.unparse(csvData) for more robust CSV generation
  const csvContent = "data:text/csv;charset=utf-8,"
      + csvData.map(e => e.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(",")).join("\n");

  // Create a link and trigger download
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", `orderhistory_${props.customerId}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}


// Trigger browser print dialog
function printOrder() {
  window.print();
}

// Handle image loading errors
function onImageError(event) {
  console.warn("Image failed to load:", event.target.src);
  event.target.style.display = 'none'; // Hide broken image icon
  // Optionally display a placeholder
  // event.target.src = '/path/to/placeholder.png';
}

// Handle large image preview
function showPreview(url) {
    if (url) {
        largeImageUrl.value = url;
        showLargeImage.value = true;
    }
}

function hidePreview() {
    showLargeImage.value = false;
    largeImageUrl.value = '';
}


// --- Watchers ---
// Watch for changes in the customerId prop and refetch data
watch(() => props.customerId, (newId, oldId) => {
  if (newId && newId !== oldId) {
    console.log(`Customer ID changed to: ${newId}`);
    fetchData(newId);
  }
}, { immediate: true }); // Use immediate: true to fetch data on initial component load


// --- Lifecycle Hooks ---

// --- Temporary SKU Input State ---
const skuToAdd = ref('');
function handleAddSku() {
    addSkuItem(skuToAdd.value.trim());
    skuToAdd.value = ''; // Clear input after attempting add
}

</script>

<template>
  <div class="order-form-container">
    <div v-if="isLoading" class="loading-overlay">Loading customer data...</div>

    <div v-if="error" class="error-message">
        <p>Error:</p>
        <p>{{ error }}</p>
    </div>

    <div v-if="customerData && !isLoading && !error">

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

      <section class="table-section">
        <h2>Your Order History</h2>
        <table class="order-table">
          <thead>
            <tr>
              <th>Image</th>
              <th>Product</th>
              <th>Price</th>
              <th>Total Qty Purchased</th>
              <th># Times Purchased</th>
              <th>Order QTY</th>
              <th>Total Cost</th>
            </tr>
          </thead>
          <tbody>
             <tr v-if="historyItems.length === 0">
                <td colspan="7">No order history found.</td>
             </tr>
             <tr v-for="item in historyItems" :key="item.SKU">
                 <td>
                    <img
                        :src="item.image_url || 'https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image'"
                        :alt="item.Title"
                        class="product-image"
                        @error="onImageError"
                        @mouseover="showPreview(item.image_url)"
                        @mouseout="hidePreview"
                    >
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

      <section class="table-section">
        <h2>We Think You Might Like</h2>
         <table class="order-table">
           <thead>
            <tr>
              <th>Image</th>
              <th>Product</th>
              <th>Price</th>
              <th>Order QTY</th>
              <th>Total Cost</th>
            </tr>
          </thead>
           <tbody>
             <tr v-if="recommendationItems.length === 0">
                <td colspan="5">No recommendations available at this time.</td>
             </tr>
             <tr v-for="item in recommendationItems" :key="item.SKU">
                 <td>
                    <img
                        :src="item.image_url || 'https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image'"
                        :alt="item.Title"
                        class="product-image"
                        @error="onImageError"
                        @mouseover="showPreview(item.image_url)"
                        @mouseout="hidePreview"
                    >
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

      <section v-if="customerData.bigfootTrue" class="sticker-section">
          <h2>Stickers</h2>
          <p><i>Sticker Carousel Component Placeholder</i></p>
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
                  <thead>
                      <tr>
                          <th>Image</th>
                          <th>Product</th>
                          <th>Price</th>
                          <th>Order QTY</th>
                          <th>Total Cost</th>
                      </tr>
                  </thead>
                   <tbody>
                       <tr v-for="item in manuallyAddedItems" :key="item.SKU">
                           <td>
                                <img
                                    :src="item.image_url || 'https://placehold.co/80x80/eeeeee/aaaaaa?text=No+Image'"
                                    :alt="item.Title"
                                    class="product-image"
                                    @error="onImageError"
                                    @mouseover="showPreview(item.image_url)"
                                    @mouseout="hidePreview"
                                >
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
        <textarea v-model="notes" placeholder="Add any notes about your order, shipping changes, or list additional SKUs here..." class="notes-textarea"></textarea>
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

    </div> </div> </template>

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
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 1.2em;
    z-index: 100;
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

.logo {
  max-width: 300px;
  margin-bottom: 15px;
}

.customer-info {
  text-align: left;
  margin-bottom: 15px;
  padding: 10px;
  border: 1px dashed #eee;
  background-color: #f9f9f9;
}

.customer-info p {
    margin: 5px 0;
}

.address {
    margin-left: 10px;
    font-style: italic;
}

.warning-message {
  color: red;
  font-weight: bold;
}
.info-message {
    font-size: 0.9em;
    color: #555;
}

.table-section, .sticker-section, .add-sku-section, .notes-section {
  margin-bottom: 30px;
}

h2 {
  border-bottom: 2px solid #48A5BA; /* Paradise Cay blue */
  padding-bottom: 5px;
  margin-bottom: 15px;
  color: #333;
}

.order-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9em;
}

.order-table th, .order-table td {
  border: 1px solid #ddd;
  padding: 8px 10px;
  text-align: left;
  vertical-align: middle;
}

.order-table th {
  background-color: #48A5BA; /* Paradise Cay blue */
  color: white;
  font-weight: bold;
}

.order-table tbody tr:nth-child(odd) {
  background-color: #f9f9f9;
}

.product-image {
  max-width: 80px;
  max-height: 80px;
  display: block;
  margin: 0 auto;
  cursor: pointer; /* Indicate preview is available */
}

.product-details a {
    font-weight: bold;
    color: #0056b3;
    text-decoration: none;
}
.product-details a:hover {
    text-decoration: underline;
}
.product-details .description {
    font-size: 0.85em;
    color: #666;
    margin-top: 4px;
    max-height: 4.5em; /* Limit description height */
    overflow: hidden;
}

.quantity-input {
  width: 60px;
  padding: 5px;
  text-align: center;
  border: 1px solid #ccc;
  border-radius: 4px;
}

.center-align { text-align: center; }
.right-align { text-align: right; }

.add-sku-controls {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}
.add-sku-controls input[type="text"] {
    flex-grow: 1;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.notes-textarea {
  width: 100%;
  min-height: 100px;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 4px;
  font-family: inherit;
  font-size: 1em;
  box-sizing: border-box; /* Include padding in width */
}

.bottom-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  width: 100%;
  background-color: #48A5BA; /* Paradise Cay blue */
  color: white;
  padding: 15px 20px;
  border-top: 1px solid #ccc;
  display: flex;
  justify-content: flex-end; /* Align items to the right */
  align-items: center;
  box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
  z-index: 50;
  box-sizing: border-box;
}

.summary-item {
  margin-left: 25px;
  font-size: 1.1em;
}

.action-button, .submit-button {
  padding: 10px 18px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 1em;
  transition: background-color 0.2s ease;
}

.action-button {
    background-color: #eee;
    color: #333;
    border: 1px solid #ccc;
    margin: 5px;
}
.action-button:hover {
    background-color: #ddd;
}

.action-button.secondary {
    background-color: #aaa;
    color: white;
}
.action-button.secondary:hover {
    background-color: #888;
}


.submit-button {
  background-color: #fff;
  color: #48A5BA;
  font-weight: bold;
  margin-left: 25px;
}

.submit-button:hover {
  background-color: #f0f0f0;
}

.submit-button:disabled {
  background-color: #ccc;
  color: #666;
  cursor: not-allowed;
}

.submission-status-message {
  width: 100%; /* Take full width available in the flex container */
  text-align: right; /* Align text to the right, near the button */
  margin-top: 10px;
  margin-right: 10px; /* Give some space from the edge or button */
  padding: 5px 0;
  font-size: 0.9em;
}
.success-message {
  color: #155724; /* Dark green for success text */
}
.error-message {
  color: #D8000C; /* Red for error text, consistent with other error messages */
  font-weight: bold;
}

/* Basic Modal Styling */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-container {
  background-color: #fff;
  padding: 30px;
  border-radius: 8px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.2);
  max-width: 500px;
  width: 90%;
}
.modal-container h2 {
    margin-top: 0;
    color: #48A5BA;
}
.modal-actions {
    margin-top: 20px;
    text-align: right;
}

/* Image Preview Styling */
.image-preview-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1100; /* Above modal */
    cursor: pointer;
}

.large-image-preview {
    max-width: 80%;
    max-height: 80%;
    object-fit: contain;
    box-shadow: 0 0 20px rgba(0,0,0,0.5);
}

/* Print styles */
@media print {
  body * {
    visibility: hidden;
  }
  .order-form-container, .order-form-container * {
    visibility: visible;
  }
  .order-form-container {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    padding: 0;
    border: none;
  }
  .bottom-bar, .action-button, .submit-button, .add-sku-section, .modal-overlay, .image-preview-overlay, .form-header button, .form-header hr, .warning-message, .info-message {
    display: none !important; /* Hide non-essential elements */
  }
  .order-table th, .order-table td {
      border: 1px solid #ccc; /* Ensure borders print */
  }
  .order-table th {
      background-color: #eee !important; /* Lighter background for printing */
      color: #000 !important; /* Ensure text is black */
      -webkit-print-color-adjust: exact; /* Force background color printing in Chrome/Safari */
      print-color-adjust: exact; /* Standard */
  }
   table, tr, td, th, tbody, thead, tfoot {
      page-break-inside: avoid !important;
   }
   h2 {
       page-break-after: avoid;
   }
   .product-image {
       max-width: 60px; /* Smaller images for print */
       max-height: 60px;
       display: block; /* Ensure it doesn't break weirdly */
   }
   .notes-textarea {
       border: 1px solid #ccc;
       background-color: #fff !important; /* Ensure background is white */
       min-height: 50px; /* Reduce height */
   }
   input[type="number"] {
       border: none; /* Hide input borders */
       text-align: right; /* Align numbers */
       width: auto; /* Adjust width */
   }
   /* Add more print-specific styles as needed */
}


</style>
