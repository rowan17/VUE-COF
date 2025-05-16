// src/main.js
import { createApp } from 'vue';
import App from './App.vue';
import router from './router'; // Import your Vue Router instance

const app = createApp(App);

app.use(router); // Use the Vue Router plugin

app.mount('#app'); 