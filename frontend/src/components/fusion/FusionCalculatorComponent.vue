<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import TargetPersonaPickerComponent from './TargetPersonaPickerComponent.vue'
import FusionResultListComponent from './FusionResultListComponent.vue'
import { findDyadInputs, getSpecialFusionInputs } from '../../composables/useFusionCalculator'

const props = defineProps({
  personas: { type: Array, required: true },
})

const activeTab = ref('dyad') // 'dyad' | 'special'
const targetName = ref('')
const fusionChart = ref(null)
const specialRecipes = ref({})
const fusionUnlocks = ref([])

onMounted(async () => {
  const base = import.meta.env.BASE_URL
  const [chartRes, recipesRes, unlocksRes] = await Promise.all([
    fetch(`${base}assets/data/fusionChart.json`),
    fetch(`${base}assets/data/special-fusion-recipes.json`),
    fetch(`${base}assets/data/fusionUnlocks.json`),
  ])
  fusionChart.value = await chartRes.json()
  specialRecipes.value = await recipesRes.json()
  fusionUnlocks.value = await unlocksRes.json()
})

function normalizeName(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '')
}

const targetPersona = computed(() => {
  if (!targetName.value) return null
  return props.personas.find((persona) =>
    normalizeName(persona.name) === normalizeName(targetName.value) ||
    normalizeName(persona.query) === normalizeName(targetName.value)
  ) || null
})

const dyadResults = computed(() => {
  if (!targetPersona.value || !fusionChart.value) return []
  return findDyadInputs(targetPersona.value, props.personas, fusionChart.value)
})

const specialResult = computed(() => {
  if (!targetName.value) return null
  return getSpecialFusionInputs(targetName.value, specialRecipes.value, fusionUnlocks.value, props.personas)
})

const specialPoolOptions = computed(() => Object.keys(specialRecipes.value).sort())
const personaPoolOptions = computed(() => props.personas.map((persona) => persona.name).sort())

watch(activeTab, () => {
  targetName.value = ''
})
</script>

<template>
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <button
        class="nav-link"
        :class="{ active: activeTab === 'dyad' }"
        @click="activeTab = 'dyad'"
      >
        Dyad Fusion
      </button>
    </li>
    <li class="nav-item">
      <button
        class="nav-link"
        :class="{ active: activeTab === 'special' }"
        @click="activeTab = 'special'"
      >
        Special Fusion
      </button>
    </li>
  </ul>

  <div class="row g-3 mb-3">
    <div class="col-12">
      <TargetPersonaPickerComponent
        v-model="targetName"
        :options="activeTab === 'special' ? specialPoolOptions : personaPoolOptions"
        :placeholder="activeTab === 'special' ? 'Type a special fusion target' : 'Type a target Persona'"
        :label="activeTab === 'special' ? 'Special fusion target' : 'Target Persona'"
      />
    </div>
  </div>

  <FusionResultListComponent
    v-if="activeTab === 'dyad'"
    mode="dyad"
    :dyad-results="dyadResults"
  />
  <FusionResultListComponent
    v-else
    mode="special"
    :special-result="specialResult"
  />
</template>