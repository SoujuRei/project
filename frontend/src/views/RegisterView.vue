<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const router = useRouter()
const { register } = useAuth()

const username = ref('')
const email = ref('')
const password = ref('')
const confirmPassword = ref('')
const errorMessage = ref('')

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

async function onSubmit() {
  if (username.value.trim().length < 3) {
    errorMessage.value = 'Username must be at least 3 characters.'
    return
  }
  if (!emailPattern.test(email.value)) {
    errorMessage.value = 'Please enter a valid email address.'
    return
  }
  if (password.value.length < 6) {
    errorMessage.value = 'Password must be at least 6 characters.'
    return
  }
  if (password.value !== confirmPassword.value) {
    errorMessage.value = 'Passwords do not match.'
    return
  }

  const result = await register({ username: username.value, email: email.value, password: password.value })
  if (!result.success) {
    errorMessage.value = result.message
    return
  }
  router.push('/log')
}
</script>

<template>
  <div class="row justify-content-center">
    <div class="col-12 col-md-6">
      <h1 class="text-gold mb-4">Become a SEES Member</h1>

      <form @submit.prevent="onSubmit">
        <label for="username" class="form-label">Username <span class="required-asterisk">*</span></label>
        <input id="username" v-model="username" type="text" class="form-control mb-3" required />

        <label for="email" class="form-label">Email <span class="required-asterisk">*</span></label>
        <input id="email" v-model="email" type="email" class="form-control mb-3" required />

        <label for="password" class="form-label">Password <span class="required-asterisk">*</span></label>
        <input id="password" v-model="password" type="password" class="form-control mb-3" required />

        <label for="confirmPassword" class="form-label">Confirm password <span class="required-asterisk">*</span></label>
        <input
          id="confirmPassword"
          v-model="confirmPassword"
          type="password"
          class="form-control mb-3"
          required
        />

        <p v-if="errorMessage" class="text-danger">{{ errorMessage }}</p>

        <button type="submit" class="btn btn-gold">Register</button>
      </form>

      <p class="mt-3 small">
        Already a member? <router-link to="/login">Log in here</router-link>.
      </p>
    </div>
  </div>
</template>