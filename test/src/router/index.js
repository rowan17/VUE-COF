import { createRouter, createWebHistory } from 'vue-router';
import OrderForm from '../components/OrderForm.vue';
import BadIdPage from '../components/BadIdPage.vue';

const routes = [
  {
    path: '/:customerId', // Capture ID without .html
    name: 'OrderForm',
    component: OrderForm,
    props: true // Pass route params as props
  },
  {
    path: '/badid',
    name: 'BadId',
    component: BadIdPage
  },
  {
    path: '/:pathMatch(.*)*', // Catch-all route for 404s
    name: 'NotFound',
    redirect: '/badid'
  }

  // Other routes...
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
});

export default router;