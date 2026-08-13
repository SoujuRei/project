<script setup>
defineProps({
  item: { type: Object, required: true },
})
</script>

<template>
  <div class="card p-3">
    <div class="d-flex justify-content-between flex-wrap">
      <h2 class="h5 mb-1">{{ item.title }}</h2>
      <span class="badge badge-arcana align-self-start">{{ item.category }}</span>
    </div>
    <p class="text-muted small mb-2">{{ item.date }}</p>
    <p class="mb-0">{{ item.content }}</p>
  </div>
</template>