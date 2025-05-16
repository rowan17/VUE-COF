import { createRouter, createWebHistory } from 'vue-router';
import OrderForm from '../components/OrderForm.vue'; // Adjust path as needed

const routes = [
  {
    path: '/order/:customerId',
    name: 'OrderForm',
    component: OrderForm,
    props: true // Pass route params as props
  },
  {
    path: '/order', // Optional: A route without a specific customer ID
    name: 'OrderFormGeneral',
    component: OrderForm
  }

  // Other routes...
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
});

export default router;