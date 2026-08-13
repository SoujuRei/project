<script setup>
import { computed, onMounted, onUnmounted, ref, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useLogs } from '../composables/useLogs'
import { apiRequest } from '../api/client'
import LogEntryCardComponent from '../components/LogEntryCardComponent.vue'
import { useAuth } from '../composables/useAuth'
// New: Replace Pagination => DynamicScroller
import { DynamicScroller, DynamicScrollerItem } from 'vue-virtual-scroller'


const route = useRoute()
const { currentUser } = useAuth()


// New: change logs to logs: fetchLogs to allow item accumulation
// New: add new constants for usage, such as array, bool load condition, logging watcher/sentinel, observer for mounted/unmounted states
const { logs: fetchedLogs, total, fetchLogs, deleteLog, upvote, hasVoted } = useLogs()
// This reactive array holds the full concatenated list of logs loaded so far.
const allLogs = ref([])
// A boolean lock to prevent duplicate API calls while a page fetch is already in flight
const isLoadingMore = ref(false)
// Template refs: 
// one to control the Virtual Scroller component programmatically
// one for bottom scroll detector
const scrollerRef = ref(null)
const sentinelRef = ref(null)
// Holds our native JavaScript IntersectionObserver instance
let observer = null

//New: Replace pageCount => hasMore 
// Computation condition to see if there's more to load compared to total
const hasMore = computed(() => allLogs.value.length < total.value)


// const { logs: fetchedLogs, total, fetchLogs, deleteLog, upvote, hasVoted } = useLogs()
// const allLogs = ref([])
// const isLoadingMore = ref(false)
// const scrollerRef = ref(null)
// const sentinelRef = ref(null)
// let observer = null

const showFilters = ref(false)
const errorMessage = ref('')

const blockFilter = ref('')
const difficultyFilter = ref('')
const outcomeFilter = ref('')
const authorFilter = ref('')
const goalFilter = ref('')
const partyFilter = ref('')
const shadowFilter = ref('')
const gatekeeperFilter = ref('')
const treasureFilter = ref('')
const shuffleFilter = ref('')
const discoveryFilter = ref('')

const searchQuery = ref('')
const sortByVotes = ref(false)

const page = ref(1)
const limit = 10

const authors = ref([])
const goals = ref([])
const shadowNames = ref([])


const blocks = ['Thebel', 'Arqa', 'Yabbashah', 'Tziah', 'Harabah', 'Adamah']
const difficulties = ['Beginner', 'Easy', 'Normal', 'Hard', 'Merciless']
const outcomes = ['Reached checkpoint', 'Retreat', 'Defeated Gatekeeper', 'Rescued target', 'Game Over']
const partyMembers = ['Yukari', 'Junpei', 'Akihiko', 'Mitsuru', 'Fuuka', 'Aigis', 'Koromaru', 'Ken', 'Shinjiro']

const highlightId = computed(() => {
  const queryValue = route.query.highlight
  const hashValue = route.hash?.toString().replace(/^#/, '')
  return String(queryValue || hashValue || '')
})


function buildParams() {
  return {
    page: page.value,
    limit,
    block: blockFilter.value,
    difficulty: difficultyFilter.value,
    outcome: outcomeFilter.value,
    author: authorFilter.value,
    goal: goalFilter.value,
    party: partyFilter.value,
    shadow: shadowFilter.value,
    gatekeeper: gatekeeperFilter.value,
    treasure: treasureFilter.value,
    shuffle: shuffleFilter.value,
    discovery: discoveryFilter.value,
    search: searchQuery.value.trim(),
    sort: sortByVotes.value ? 'votes' : 'date',
  }
}

// New: function refetch => function loadPage
async function loadPage(pageNum, isReset = false) {
  if (isLoadingMore.value) return
  try {
    isLoadingMore.value = true
    errorMessage.value = ''

    await fetchLogs({ ...buildParams(), page: pageNum })

    if (isReset) {
      allLogs.value = Array.isArray(fetchedLogs.value) 
      ? [...fetchedLogs.value] : []
    } else {
      const existingIds = new Set(allLogs.value.map(
        (item) => item.id))
      const uniqueNewItems = (fetchedLogs.value || []).filter(
        (item) => !existingIds.has(item.id))
      allLogs.value = [...allLogs.value, ...uniqueNewItems]
    }

    await nextTick()

    if (highlightId.value) {
      scrollToHighlight()
    }
  } catch (e) {
    errorMessage.value = e.message || 'Could not load log entries.'
  } finally {
    isLoadingMore.value = false
  }
}


function resetAndRefetch() {
  page.value = 1
  loadPage(1, true)
}

let searchDebounce = null
function onSearchInput() {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(resetAndRefetch, 300)
}


// New: Modified onMounted
onMounted(async () => {
  try {
    const meta = await apiRequest('/logs.php?meta=1')
    authors.value = meta.authors || []
    goals.value = meta.goals || []
    shadowNames.value = meta.shadowNames || []
  } catch {

  }

  // Start from here
  await resetAndRefetch()

  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting && hasMore.value && !isLoadingMore.value) {
        page.value++
        loadPage(page.value, false)
      }
    },
    { threshold: 0.1 }
  )

  if (sentinelRef.value) observer.observe(sentinelRef.value)
})

// New: Add onUnmounted
onUnmounted(() => {
  if (observer) observer.disconnect()
})


// New: modified  scrollToHighlight
function scrollToHighlight() {
  if (!highlightId.value) return

  // start from here 
  const targetIndex = allLogs.value.findIndex(
    (item) => String(item.id) === highlightId.value)
  if (targetIndex !== -1 && scrollerRef.value) {
    scrollerRef.value.scrollToItem(targetIndex)
  }
}


// New: Modified Optimistic Update for Delete
async function onDelete(id) {
  try {
    // 1. Optimistically remove from local view immediately
    allLogs.value = allLogs.value.filter((item) => item.id !== id)

    // 2. Call backend API
    await deleteLog(id)
  } catch (e) {
    // If it failed, show error and reload state to revert
    errorMessage.value = e.message || 'Could not delete entry.'
    resetAndRefetch()
  }
}

// New: Modified Optimistic Update for Upvote
async function onUpvote(id) {
  // Find item in allLogs
  const item = allLogs.value.find((e) => e.id === id)
  if (!item) return

  const alreadyVoted = hasVoted(id)

  // 1. Optimistically toggle vote count locally
  if (alreadyVoted) {
    if (item.upvotes > 0) item.upvotes--
  } else {
    item.upvotes = (item.upvotes || 0) + 1
  }

  try {
    // 2. Call composable upvote API
    await upvote(id)
  } catch (e) {
    // Revert if request fails
    if (alreadyVoted) {
      item.upvotes++
    } else {
      if (item.upvotes > 0) item.upvotes--
    }
    errorMessage.value = e.message || 'Could not register vote.'
  }
}

function clearFilters() {
  blockFilter.value = ''
  difficultyFilter.value = ''
  outcomeFilter.value = ''
  authorFilter.value = ''
  goalFilter.value = ''
  partyFilter.value = ''
  shadowFilter.value = ''
  gatekeeperFilter.value = ''
  treasureFilter.value = ''
  shuffleFilter.value = ''
  discoveryFilter.value = ''
  resetAndRefetch()
}
</script>

<template>
  <div class="d-flex justify-content-between align-items-center flex-wrap mb-4 gap-2">
    <h1 class="text-gold mb-0">Tartarus Log</h1>

    <router-link v-if="currentUser" to="/log/create" class="btn btn-gold">
      + New Log Entry
    </router-link>
    <router-link v-else to="/login" class="btn btn-outline-gold">
      Log in to contribute
    </router-link>
  </div>

  <p v-if="errorMessage" class="text-danger">{{ errorMessage }}</p>

  <div class="mb-3">
    <input
      v-model="searchQuery"
      type="text"
      class="form-control"
      placeholder="Search by author, shadow, note, or block"
      aria-label="Search log entries"
      @input="onSearchInput"
    />
  </div>

  <button type="button" class="btn btn-outline-gold mb-3" @click="showFilters = !showFilters">
    {{ showFilters ? 'Hide Filters ▲' : 'Show Filters ▼' }}
  </button>

  <div v-show="showFilters" class="card p-3 mb-4">
    <div class="row g-3">
      <div class="col-12 col-md-3">
        <label class="form-label">Block</label>
        <select v-model="blockFilter" class="form-select" @change="resetAndRefetch">
          <option value="">All blocks</option>
          <option v-for="block in blocks" :key="block" :value="block">{{ block }}</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Difficulty</label>
        <select v-model="difficultyFilter" class="form-select" @change="resetAndRefetch">
          <option value="">Any difficulty</option>
          <option v-for="difficulty in difficulties" :key="difficulty" :value="difficulty">{{ difficulty }}</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Outcome</label>
        <select v-model="outcomeFilter" class="form-select" @change="resetAndRefetch">
          <option value="">Any outcome</option>
          <option v-for="outcome in outcomes" :key="outcome" :value="outcome">{{ outcome }}</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Author</label>
        <select v-model="authorFilter" class="form-select" @change="resetAndRefetch">
          <option value="">Any author</option>
          <option v-for="author in authors" :key="author" :value="author">{{ author }}</option>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label">Exploration Goal</label>
        <select v-model="goalFilter" class="form-select" @change="resetAndRefetch">
          <option value="">Any goal</option>
          <option v-for="goal in goals" :key="goal" :value="goal">{{ goal }}</option>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Party Member</label>
        <select v-model="partyFilter" class="form-select" @change="resetAndRefetch">
          <option value="">Any party member</option>
          <option v-for="member in partyMembers" :key="member" :value="member">{{ member }}</option>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Shadow Encountered</label>
        <select v-model="shadowFilter" class="form-select" @change="resetAndRefetch">
          <option value="">Any shadow</option>
          <option v-for="shadow in shadowNames" :key="shadow" :value="shadow">{{ shadow }}</option>
        </select>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label">Gatekeeper</label>
        <select v-model="gatekeeperFilter" class="form-select" @change="resetAndRefetch">
          <option value="">Any</option>
          <option value="yes">Encountered</option>
          <option value="no">Not encountered</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Treasure</label>
        <select v-model="treasureFilter" class="form-select" @change="resetAndRefetch">
          <option value="">Any</option>
          <option value="yes">Found</option>
          <option value="no">None</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Shuffle Time</label>
        <select v-model="shuffleFilter" class="form-select" @change="resetAndRefetch">
          <option value="">Any</option>
          <option value="yes">Found</option>
          <option value="no">None</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Discoveries</label>
        <select v-model="discoveryFilter" class="form-select" @change="resetAndRefetch">
          <option value="">Any</option>
          <option value="yes">Found</option>
          <option value="no">None</option>
        </select>
      </div>

      <div class="col-12 d-flex justify-content-between align-items-center mt-3">
        <div class="form-check">
          <input id="sortVotes" v-model="sortByVotes" type="checkbox" class="form-check-input" @change="resetAndRefetch" />
          <label for="sortVotes" class="form-check-label">Sort by votes</label>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" @click="clearFilters">Clear Filters</button>
      </div>
    </div>
  </div>

<!-- Dynamic Scroller Viewport -->
<!-- New: Replace old version containing LogEntryCardComponent -->
  <DynamicScroller
    v-if="allLogs.length"
    ref="scrollerRef"
    :items="allLogs"
    :min-item-size="300"
    key-field="id"
    class="scroller mb-4"
  >
    <template #default="{ item, index, active }">
      <DynamicScrollerItem
        :item="item"
        :active="active"
        :data-index="index"
        :data-active="active"
        class="pb-3"
      > 
        <LogEntryCardComponent
          :entry="item"
          :current-username="currentUser?.username ?? null"
          :is-admin="currentUser?.role === 'admin'"
          :has-voted="hasVoted(item.id)"
          :is-highlighted="highlightId === String(item.id)"
          :user-run-number="item.runNumber"
          @upvote="onUpvote"
          @delete="onDelete"
        />
      </DynamicScrollerItem>
    </template>
  </DynamicScroller>

<!-- New: Status update - Log no entries if none can be found -->
  <p v-if="!allLogs.length && !isLoadingMore" class="text-muted">No log entries yet.</p>

<!-- New: Status update - Log other statuses for loading in infinite scroll-->
  <!-- Infinite Scroll Sentinel -->
  <div ref="sentinelRef" class="text-center py-4">
    <div v-if="isLoadingMore" class="spinner-border text-gold" role="status">
      <span class="visually-hidden">Loading more entries...</span>
    </div>
    <p v-else-if="!hasMore && allLogs.length" class="text-muted small">
      All log entries loaded.
    </p>
  </div>
</template>

<style scoped>
.scroller {
  height: 100%;
}

</style>