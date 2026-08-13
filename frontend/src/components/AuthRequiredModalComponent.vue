<script setup>
defineProps({
  message: { type: String, default: 'You need to be a SEES member to do that.' },
})
defineEmits(['close'])
</script>

<template>
  <div class="p3r-modal-backdrop" @click.self="$emit('close')">
    <div class="p3r-modal">
      <button class="p3r-modal-close" @click="$emit('close')">×</button>
      <p class="mb-3">{{ message }}</p>
      <div class="d-flex gap-2">
        <router-link to="/login" class="btn btn-gold btn-sm" @click="$emit('close')">Log in</router-link>
        <router-link to="/register" class="btn btn-outline-gold btn-sm" @click="$emit('close')">Register</router-link>
      </div>
    </div>
  </div>
</template>