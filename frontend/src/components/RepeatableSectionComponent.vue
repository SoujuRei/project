<script setup>
defineProps({
  title: { type: String, required: true },
  fields: { type: Array, required: true }, 
  rows: { type: Array, required: true },
})
defineEmits(['add', 'remove'])
</script>

<template>
  <div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h3 class="h6 text-gold mb-0">{{ title }}</h3>
      <button type="button" class="btn btn-outline-gold btn-sm" @click="$emit('add')">
        + Add {{ title }}
      </button>
    </div>

    <p v-if="!rows.length" class="text-muted small">None added yet.</p>

    <div v-for="row in rows" :key="row.id" class="card p-3 mb-2">
      <div class="row g-2 align-items-end">
        <div v-for="field in fields" :key="field.key" :class="field.col || 'col-6 col-md-3'">
          <label class="form-label small mb-1">{{ field.label }}</label>
          <select
            v-if="field.type === 'select'"
            v-model="row[field.key]"
            class="form-select form-select-sm"
          >
            <option value="" disabled>Select {{ field.label.toLowerCase() }}</option>
            <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
          </select>
          <input
            v-else
            v-model="row[field.key]"
            :type="field.type || 'text'"
            class="form-control form-control-sm"
            :placeholder="field.placeholder || field.label"
          />
        </div>
        <div class="col-auto">
          <button type="button" class="btn btn-outline-gold btn-sm" @click="$emit('remove', row.id)">Remove</button>
        </div>
      </div>
    </div>
  </div>
</template>