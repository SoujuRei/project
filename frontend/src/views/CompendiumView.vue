<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PersonaCardComponent from '../components/PersonaCardComponent.vue'
import PaginationControlsComponent from '../components/PaginationControlsComponent.vue'
import FusionCalculatorComponent from '../components/fusion/FusionCalculatorComponent.vue'

const route = useRoute()
const router = useRouter()
const base = import.meta.env.BASE_URL
const personas = ref([])
const searchQuery = ref('')
const arcanaFilter = ref('')
const sortByLevel = ref(false)
const currentPage = ref(1)
const perPage = 8

// Tab lives in the URL query so refresh/deep-link keeps you on the same tab
const activeSection = computed(() => (route.query.tab === 'fusion' ? 'fusion' : 'browse'))

function setSection(section) {
  router.replace({ query: { ...route.query, tab: section } })
}

onMounted(async () => {
  const res = await fetch(`${base}assets/data/personas.json`)
  personas.value = await res.json()
})

const arcanaList = computed(() => [...new Set(personas.value.map((p) => p.arcana))].sort())

const filteredPersonas = computed(() => {
  let list = personas.value

  if (arcanaFilter.value) {
    list = list.filter((p) => p.arcana === arcanaFilter.value)
  }

  const q = searchQuery.value.toLowerCase().trim()
  if (q) {
    list = list.filter((p) => p.name.toLowerCase().includes(q))
  }

  if (sortByLevel.value) {
    list = [...list].sort((a, b) => a.level - b.level)
  }

  return list
})

const pageCount = computed(() => Math.ceil(filteredPersonas.value.length / perPage) || 1)

const paginatedPersonas = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredPersonas.value.slice(start, start + perPage)
})

function resetPage() {
  currentPage.value = 1
}
</script>

<template>
  <h1 class="text-gold mb-4">Compendium</h1>

  <ul class="nav nav-tabs mb-4">
    <li class="nav-item">
      <button class="nav-link" :class="{ active: activeSection === 'browse' }" @click="setSection('browse')">
        Browse
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" :class="{ active: activeSection === 'fusion' }" @click="setSection('fusion')">
        Fusion Calculator
      </button>
    </li>
  </ul>

  <div v-if="activeSection === 'browse'">
    <div class="row g-3 mb-4">
      <div class="col-12 col-md-6">
        <input
          v-model="searchQuery"
          @input="resetPage"
          type="text"
          class="form-control"
          placeholder="Search by name"
          aria-label="Search personas by name"
        />
      </div>
      <div class="col-6 col-md-3">
        <select v-model="arcanaFilter" @change="resetPage" class="form-select">
          <option value="">All Arcana</option>
          <option v-for="arcana in arcanaList" :key="arcana" :value="arcana">{{ arcana }}</option>
        </select>
      </div>
      <div class="col-6 col-md-3 form-check pt-2">
        <input id="sortLevel" v-model="sortByLevel" type="checkbox" class="form-check-input" />
        <label for="sortLevel" class="form-check-label">Sort by level</label>
      </div>
    </div>

    <div class="row g-3">
      <div v-for="persona in paginatedPersonas" :key="persona.id" class="col-12 col-md-6 col-lg-4">
        <PersonaCardComponent :persona="persona" />
      </div>
      <p v-if="!paginatedPersonas.length" class="text-muted">No Personas match your search.</p>
    </div>

    <PaginationControlsComponent :page-count="pageCount" @page-change="(p) => (currentPage = p)" />
  </div>

  <FusionCalculatorComponent v-else :personas="personas" />
</template>