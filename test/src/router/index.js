import { createRouter, createWebHistory } from 'vue-router';
import OrderForm from '../components/OrderForm.vue';
const routes = [
  {
    path: '/:customerId', // Capture ID without .html
    name: 'OrderForm',
    component: OrderForm,
    props: true // Pass route params as props
  },
  {
    path: '/:pathMatch(.*)*', // Catch-all route for 404s
    name: 'NotFound',
    redirect: '/orderform/' // Redirect to the base path, which OrderForm.vue can handle
  }

  // Other routes...
];

const router = createRouter({
  history: createWebHistory('/orderform/'),
  routes
});

export default router;