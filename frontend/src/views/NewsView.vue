<script setup>
import { ref, computed, onMounted } from 'vue'
import NewsCardComponent from '../components/NewsCardComponent.vue'
import PaginationControlsComponent from '../components/PaginationControlsComponent.vue'

const news = ref([])
const searchQuery = ref('')
const categoryFilter = ref('')
const currentPage = ref(1)
const perPage = 6

onMounted(async () => {
  const res = await fetch(`${import.meta.env.BASE_URL}assets/data/news.json`)
  news.value = await res.json()
})

const categories = computed(() => [...new Set(news.value.map((item) => item.category).filter(Boolean))].sort())

const filteredNews = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()
  return [...news.value]
    .filter((item) => (categoryFilter.value ? item.category === categoryFilter.value : true))
    .filter((item) => {
      if (!q) return true
      return [item.date, item.title, item.content, item.category].some((field) =>
        String(field).toLowerCase().includes(q)
      )
    })
    .sort((a, b) => new Date(b.date) - new Date(a.date))
})

const pageCount = computed(() => Math.ceil(filteredNews.value.length / perPage) || 1)

const paginatedNews = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredNews.value.slice(start, start + perPage)
})

function resetPage() {
  currentPage.value = 1
}
</script>

<template>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
      <h1 class="text-gold mb-0">Dark Hour Reports</h1>
    </div>
    <div class="col-12 col-md-3">
      <input
        v-model="searchQuery"
        @input="resetPage"
        type="text"
        class="form-control"
        placeholder="Search reports"
        aria-label="Search news reports"
      />
    </div>
    <div class="col-12 col-md-3">
      <select v-model="categoryFilter" @change="resetPage" class="form-select">
        <option value="">All categories</option>
        <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
      </select>
    </div>
  </div>

  <div class="row g-3">
    <div v-for="item in paginatedNews" :key="item.id" class="col-12">
      <NewsCardComponent :item="item" />
    </div>
    <p v-if="!paginatedNews.length" class="text-muted">No reports match your search.</p>
  </div>

  <PaginationControlsComponent :page-count="pageCount" @page-change="(p) => (currentPage = p)" />
</template>