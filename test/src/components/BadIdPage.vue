<template>
  <div class="bad-id-page">
    <h1>Customer ID Not Found</h1>
    <p>The customer ID entered in the URL was not found.</p>
    <p>Please contact <a href="mailto:order@paracay.com">order@paracay.com</a> to confirm your ID.</p>
  </div>
</template>

<script>
export default {
  name: 'BadIdPage'
};
</script>

<style scoped>
.bad-id-page {
  text-align: center;
  padding: 50px;
  color: #333;
}

.bad-id-page h1 {
  color: #e44d26; /* A distinct color for error messages */
  margin-bottom: 20px;
}

.bad-id-page p {
  font-size: 1.1em;
  margin-bottom: 10px;
}

.bad-id-page a {
  color: #007bff;
  text-decoration: none;
}

.bad-id-page a:hover {
  text-decoration: underline;
}
</style>