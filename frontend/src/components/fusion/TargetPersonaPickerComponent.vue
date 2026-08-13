<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  options: { type: Array, default: () => [] },
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Type a target Persona' },
  label: { type: String, default: 'Target Persona' },
})

const emit = defineEmits(['update:modelValue'])
const isOpen = ref(false)

function normalizeText(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '')
}

function buildRegex(query) {
  const safe = normalizeText(query)
  if (!safe) return null
  return new RegExp(safe.split('').join('.*'), 'i')
}

const filteredOptions = computed(() => {
  const query = props.modelValue || ''
  if (!query) return props.options
  const regex = buildRegex(query)
  return props.options.filter((option) => (regex ? regex.test(option) : true))
})

function toggleDropdown() {
  isOpen.value = !isOpen.value
}

function handleInput(event) {
  emit('update:modelValue', event.target.value)
  isOpen.value = true
}

function selectOption(option) {
  emit('update:modelValue', option)
  isOpen.value = false
}

function handleBlur() {
  window.setTimeout(() => {
    isOpen.value = false
  }, 120)
}
</script>

<template>
  <div class="w-100 position-relative">
    <label v-if="label" class="form-label d-block text-center">{{ label }}</label>
    <div class="position-relative">
      <input
        class="form-control text-center w-100 pe-5"
        :value="modelValue"
        :placeholder="placeholder"
        @input="handleInput"
        @focus="isOpen = true"
        @blur="handleBlur"
      />
      <button
        type="button"
      class="btn position-absolute top-50 end-0 translate-middle-y me-2 p-0 text-white border-0 shadow-none"
        :aria-expanded="isOpen"
        :aria-label="isOpen ? 'Collapse suggestions' : 'Expand suggestions'"
        @click.stop="toggleDropdown"
      >
        <span class="fs-5">{{ isOpen ? '▲' : '▼' }}</span>
      </button>
    </div>

    <div
      v-if="isOpen"
      class="position-absolute start-0 end-0 mt-1 rounded border shadow-sm bg-body z-3"
      style="max-height: 240px; overflow-y: auto;"
    >
      <button
        v-for="option in filteredOptions"
        :key="option"
        type="button"
        class="btn btn-link d-block text-start w-100 border-0 rounded-0"
        @click.stop="selectOption(option)"
      >
        {{ option }}
      </button>
      <div v-if="!filteredOptions.length" class="px-3 py-2 small text-muted">
        No matches found.
      </div>
    </div>
  </div>
</template>