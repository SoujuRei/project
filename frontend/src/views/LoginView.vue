<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const router = useRouter()
const { login } = useAuth()

const username = ref('')
const password = ref('')
const errorMessage = ref('')

async function onSubmit() {
  errorMessage.value = ''

  try {
    await login({
      username: username.value,
      password: password.value,
    })
    router.push('/log')
  } catch (err) {
    errorMessage.value = err.message || 'Login failed. Please try again.'
  }
}
</script>

<template>
  <div class="row justify-content-center">
    <div class="col-12 col-md-6">
      <h1 class="text-gold mb-4">SEES Member Login</h1>

      <form @submit.prevent="onSubmit">
        <label for="username" class="form-label">Username</label>
        <input id="username" v-model="username" type="text" class="form-control mb-3" required />

        <label for="password" class="form-label">Password</label>
        <input id="password" v-model="password" type="password" class="form-control mb-3" required />

        <p v-if="errorMessage" class="text-danger">{{ errorMessage }}</p>

        <button type="submit" class="btn btn-gold">Log in</button>
      </form>

      <p class="mt-3 small">
        Not a member yet? <router-link to="/register">Register here</router-link>.
      </p>
    </div>
  </div>
</template>