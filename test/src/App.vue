<template>
    <div id="app">
      <router-view :key="$route.fullPath"></router-view>
    </div>
  </template>
  
  <script setup>
  import { RouterLink, RouterView, useRoute } from 'vue-router';
  
  const route = useRoute();
  </script>
  
  <style>
  #app { font-family: Avenir, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; text-align: center; color: #2c3e50; }
  nav { padding: 30px; }
  nav a { font-weight: bold; color: #2c3e50; text-decoration: none; }
  nav a.router-link-exact-active { color: #42b983; }
  </style>