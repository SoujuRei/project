<script setup>
import { computed, onMounted, onUnmounted, ref, nextTick, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import { useLogs } from '../composables/useLogs'
import { apiRequest } from '../api/client'
import LogEntryCardComponent from '../components/LogEntryCardComponent.vue'
import PaginationControlsComponent from '../components/PaginationControlsComponent.vue'
import { DynamicScroller, DynamicScrollerItem } from 'vue-virtual-scroller'

const route = useRoute()
const { currentUser } = useAuth()
const { logs, total, fetchLogs, deleteLog, upvote, hasVoted } = useLogs()

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




// Dropdown option lists that can't be derived from `logs.value` anymore —
// only one page of logs is ever loaded client-side under real pagination
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

const pageCount = computed(() => Math.ceil(total.value / limit) || 1)

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

async function refetch() {
  try {
    errorMessage.value = ''
    await fetchLogs(buildParams())
    scrollToHighlight()
  } catch (e) {
    errorMessage.value = e.message || 'Could not load log entries.'
  }
}


function resetAndRefetch() {
  page.value = 1
  refetch()
}

let searchDebounce = null
function onSearchInput() {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(resetAndRefetch, 300)
}

onMounted(async () => {
  try {
    const meta = await apiRequest('/logs.php?meta=1')
    authors.value = meta.authors
    goals.value = meta.goals
    shadowNames.value = meta.shadowNames
  } catch {
   
  }
  await refetch()
})
// onUnmounted(() => {
//   if (observer) observer.disconnect()
// })


function scrollToHighlight() {
  if (!highlightId.value) return

  nextTick(() => {
    const target = document.getElementById(`entry-${highlightId.value}`)
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'center' })
      return
    }
    setTimeout(() => {
      document.getElementById(`entry-${highlightId.value}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }, 150)
  })
}

async function onDelete(id) {
  try {
    await deleteLog(id)
    await refetch() // total count changed, keep pagination accurate
  } catch (e) {
    errorMessage.value = e.message || 'Could not delete entry.'
  }
}

async function onUpvote(id) {
  try {
    await upvote(id)
  } catch (e) {
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

  <div class="row g-3">
    <div v-for="entry in logs" :key="entry.id" class="col-12">
      <LogEntryCardComponent
        :entry="entry"
        :current-username="currentUser?.username ?? null"
        :is-admin="currentUser?.role === 'admin'"
        :has-voted="hasVoted(entry.id)"
        :is-highlighted="highlightId === String(entry.id)"
        :user-run-number="entry.runNumber"
        @upvote="onUpvote"
        @delete="onDelete"
      />
    </div>
    <p v-if="!logs.length" class="text-muted">No log entries yet.</p>
  </div>

  <PaginationControlsComponent
    :page-count="pageCount"
    @page-change="(p) => { page = p; refetch() }"
  />
</template>