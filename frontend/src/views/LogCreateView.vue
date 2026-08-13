<script setup>
import { reactive, ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import { useLogs } from '../composables/useLogs'
import ShadowEncounterSectionComponent from '../components/ShadowEncounterSectionComponent.vue'
import GatekeeperSectionComponent from '../components/GatekeeperSectionComponent.vue'
import RepeatableSectionComponent from '../components/RepeatableSectionComponent.vue'
import MediaUploadSectionComponent from '../components/MediaUploadSectionComponent.vue'

const route = useRoute()
const router = useRouter()
const { currentUser } = useAuth()
const { fetchLogById, addLog, updateLog } = useLogs()

const isEditMode = !!route.params.id
const errors = ref({})
const submitError = ref('')
const personas = ref([])

const cancelTarget = computed(() => {
  if (!isEditMode) return { path: '/log' }
  return { path: '/log', query: { highlight: route.params.id } }
})

const BLOCK_FLOOR_RULES = [
  { id: 'THEBEL', name: 'Thebel', min: 1, max: 22 },
  { id: 'ARQA_1', name: 'Arqa (Part 1)', min: 23, max: 42 },
  { id: 'ARQA_2', name: 'Arqa (Part 2)', min: 43, max: 69 },
  { id: 'YABBASHAH_1', name: 'Yabbashah (Part 1)', min: 70, max: 92 },
  { id: 'YABBASHAH_2', name: 'Yabbashah (Part 2)', min: 93, max: 118 },
  { id: 'TZYY_1', name: 'Tzyy (Part 1)', min: 119, max: 144 },
  { id: 'TZYY_2', name: 'Tzyy (Part 2)', min: 145, max: 172 },
  { id: 'HARABAH_1', name: 'Harabah (Part 1)', min: 173, max: 198 },
  { id: 'HARABAH_2', name: 'Harabah (Part 2)', min: 199, max: 226 },
  { id: 'ADAMAH', name: 'Adamah', min: 227, max: 264 },
]

const partyOptions = ['Yukari', 'Junpei', 'Akihiko', 'Mitsuru', 'Ken', 'Koromaru', 'Aigis']
const difficultyOptions = ['Easy', 'Normal', 'Hard', 'Merciless']
const outcomeOptions = ['Reached checkpoint', 'Defeated Gatekeeper', 'Rescued target', 'Retreat', 'Game Over']
const treasureSourceOptions = ['Chest', 'Golden Chest', 'Shuffle Time', 'Shadow Drop']

function blankShadow() {
  return { id: crypto.randomUUID(), name: '', floorEncountered: '', arcana: '', weakness: '', resistances: '', dangerousSkills: '', strategyNotes: '' }
}
function blankTreasure() { return { id: crypto.randomUUID(), itemName: '', source: '' } }
function blankShuffle() { return { id: crypto.randomUUID(), arcana: '', cardEffect: '', notes: '' } }
function blankDiscovery() { return { id: crypto.randomUUID(), discovery: '', details: '' } }
function blankCustom() { return { id: crypto.randomUUID(), label: '', value: '' } }
function blankCoAuthor() { return { id: crypto.randomUUID(), name: '' } }

function isFilledRow(row, keys) {
  return keys.some((key) => String(row?.[key] ?? '').trim())
}
function filterFilledRows(rows, keys) {
  return (rows || []).filter((row) => isFilledRow(row, keys))
}

function normalizeBlockId(value) {
  if (!value) return ''
  const match = BLOCK_FLOOR_RULES.find((rule) => rule.name === value || rule.id === value)
  return match?.id || value
}

function onCancel() {
  router.push(cancelTarget.value)
}

function getBlockNameById(id) {
  return BLOCK_FLOOR_RULES.find((rule) => rule.id === id)?.name || id
}

function getBlocksForRange(startFloor, endFloor) {
  const start = Number(startFloor)
  const end = Number(endFloor)
  if (!Number.isFinite(start) || !Number.isFinite(end) || start > end) return []
  return BLOCK_FLOOR_RULES.filter((rule) => !(end < rule.min || start > rule.max)).map((rule) => rule.id)
}

function getRangeForBlocks(blockIds) {
  const selectedRules = BLOCK_FLOOR_RULES.filter((rule) => blockIds.includes(rule.id))
  if (!selectedRules.length) return { startFloor: null, endFloor: null }
  return {
    startFloor: Math.min(...selectedRules.map((rule) => rule.min)),
    endFloor: Math.max(...selectedRules.map((rule) => rule.max)),
  }
}

const form = reactive({
  title: '',
  author: currentUser.value?.username ?? '',
  date: '',
  block: '',
  floor: null,
  startFloor: null,
  endFloor: null,
  blocksCovered: [],
  coAuthors: [],
  partyMembers: [],
  difficulty: '',
  explorationGoal: '',
  outcome: '',
  strategyNotes: '',
  overallNotes: '',
  shadows: [blankShadow()],
  gatekeeper: { encountered: false, bossName: '', floor: null, weakness: '', mechanics: '', recommendedLevel: null, strategy: '' },
  treasure: [],
  shuffleTime: [],
  discoveries: [],
  customInfo: [],
  media: [],
})

const floorBounds = computed(() => {
  if (!form.blocksCovered.length) return { min: 1, max: 264 }
  const selectedRules = BLOCK_FLOOR_RULES.filter((rule) => form.blocksCovered.includes(rule.id))
  return {
    min: Math.min(...selectedRules.map((rule) => rule.min)),
    max: Math.max(...selectedRules.map((rule) => rule.max)),
  }
})

onMounted(async () => {
  const res = await fetch(`${import.meta.env.BASE_URL}assets/data/personas.json`)
  personas.value = await res.json()

  if (isEditMode) {
    // Fetched directly rather than searched out of a shared list 
    try {
      const entry = await fetchLogById(route.params.id)
      if (!entry.startFloor && !entry.endFloor && entry.floor != null) {
        entry.startFloor = entry.floor
        entry.endFloor = entry.floor
      }
      if (!entry.blocksCovered?.length && entry.block) {
        entry.blocksCovered = [normalizeBlockId(entry.block)]
      }
      if (!entry.coAuthors) entry.coAuthors = []
      Object.assign(form, entry)
    } catch (e) {
      submitError.value = e.message || 'Could not load this log entry.'
    }
  }
})

watch(
  () => form.blocksCovered,
  (value) => {
    const blockIds = value || []
    if (!blockIds.length) return
    const range = getRangeForBlocks(blockIds)
    if (range.startFloor != null) form.startFloor = range.startFloor
    if (range.endFloor != null) form.endFloor = range.endFloor
  },
  { deep: true }
)

watch(
  [() => form.startFloor, () => form.endFloor],
  ([startFloor, endFloor]) => {
    const start = Number(startFloor)
    const end = Number(endFloor)
    if (!Number.isFinite(start) || !Number.isFinite(end) || start > end) return
    const nextBlocks = getBlocksForRange(start, end)
    if (JSON.stringify(nextBlocks) !== JSON.stringify(form.blocksCovered)) {
      form.blocksCovered = nextBlocks
    }
  }
)

function togglePartyMember(name) {
  const i = form.partyMembers.indexOf(name)
  if (i === -1) form.partyMembers.push(name)
  else form.partyMembers.splice(i, 1)
}

function toggleBlock(id) {
  const selected = form.blocksCovered.includes(id)
  form.blocksCovered = selected
    ? form.blocksCovered.filter((item) => item !== id)
    : [...form.blocksCovered, id]
}

function validate() {
  const e = {}
  if (!form.date) e.date = 'Exploration date is required.'
  if (!form.blocksCovered.length) e.blocksCovered = 'Select at least one block.'
  if (!form.startFloor || !form.endFloor) e.floor = 'Starting and ending floors are required.'
  if (form.startFloor != null && form.endFloor != null && form.startFloor > form.endFloor) {
    e.floor = 'Starting floor cannot be greater than the ending floor.'
  }
  if (!form.partyMembers.length) e.partyMembers = 'Select at least one party member.'
  if (!form.outcome) e.outcome = 'Outcome is required.'
  if (!form.strategyNotes.trim()) e.strategyNotes = 'Strategy notes are required.'
  errors.value = e
  return Object.keys(e).length === 0
}

async function onSubmit() {
  submitError.value = ''
  if (!validate()) return

  const payload = {
    ...form,
    block: form.blocksCovered.length ? getBlockNameById(form.blocksCovered[0]) : form.block || '',
    floor: form.startFloor ?? form.floor ?? null,
    coAuthors: (form.coAuthors || []).filter((author) => author.name?.trim()),
    treasure: filterFilledRows(form.treasure, ['itemName', 'source']),
    shuffleTime: filterFilledRows(form.shuffleTime, ['arcana', 'cardEffect', 'notes']),
    discoveries: filterFilledRows(form.discoveries, ['discovery', 'details']),
    customInfo: filterFilledRows(form.customInfo, ['label', 'value']),
    shadows: filterFilledRows(form.shadows, ['name', 'floorEncountered', 'arcana', 'weakness', 'resistances', 'dangerousSkills', 'strategyNotes']),
    media: form.media,
  }

  try {
    if (isEditMode) {
      await updateLog(route.params.id, payload)
      router.push({ path: '/log', query: { highlight: route.params.id } })
    } else {
      // Server assigns the real id now — use its response, not a client guess
      const newId = await addLog(payload)
      router.push({ path: '/log', query: { highlight: String(newId) } })
    }
  } catch (e) {
    submitError.value = e.message || 'Could not save this log entry.'
  }
}
</script>

<template>
  <div v-if="!currentUser" class="row justify-content-center mt-5">
    <div class="col-12 col-md-6 text-center">
      <h1 class="text-gold">Members Only</h1>
      <p class="my-3">You need to be a registered SEES member to log a Tartarus run.</p>
      <div class="d-flex justify-content-center gap-2">
        <router-link to="/login" class="btn btn-gold">Log in</router-link>
        <router-link to="/register" class="btn btn-outline-gold">Register</router-link>
        <router-link to="/" class="btn btn-outline-gold">Back to Home</router-link>
      </div>
    </div>
  </div>

  <form v-else @submit.prevent="onSubmit">
    <h1 class="text-gold mb-4">{{ isEditMode ? 'Edit Log Entry' : 'New Log Entry' }}</h1>
    <p v-if="submitError" class="text-danger">{{ submitError }}</p>

    <h2 class="h5 text-gold mb-3">Exploration Information</h2>
    <div class="row g-3 mb-4">
      <div class="col-12 col-md-4">
        <label class="form-label" for="title-field">Title</label>
        <input id="title-field" v-model="form.title" type="text" class="form-control" placeholder="e.g. Arqa farm run" />
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label" for="author-field">Author</label>
        <input id="author-field" v-model="form.author" type="text" class="form-control" readonly />
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label" for="date-field">Exploration Date <span class="required-asterisk">*</span></label>
        <input id="date-field" v-model="form.date" type="date" class="form-control" />
        <p v-if="errors.date" class="text-danger small mt-1">{{ errors.date }}</p>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Starting Floor <span class="required-asterisk">*</span></label>
        <input v-model.number="form.startFloor" type="number" class="form-control" :min="floorBounds.min" :max="floorBounds.max" placeholder="e.g. 36" />
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Ending Floor <span class="required-asterisk">*</span></label>
        <input v-model.number="form.endFloor" type="number" class="form-control" :min="floorBounds.min" :max="floorBounds.max" placeholder="e.g. 54" />
      </div>
      <div class="col-12">
        <label class="form-label">Blocks Covered <span class="required-asterisk">*</span></label>
        <div class="d-flex flex-wrap gap-2">
          <div v-for="rule in BLOCK_FLOOR_RULES" :key="rule.id" class="form-check form-check-inline">
            <input :id="rule.id" type="checkbox" class="form-check-input" :checked="form.blocksCovered.includes(rule.id)" @change="toggleBlock(rule.id)" />
            <label :for="rule.id" class="form-check-label">{{ rule.name }}</label>
          </div>
        </div>
        <div v-if="form.blocksCovered.length" class="mt-2 d-flex flex-wrap gap-2">
          <span v-for="id in form.blocksCovered" :key="id" class="badge badge-arcana">{{ getBlockNameById(id) }}</span>
        </div>
        <p v-if="errors.blocksCovered" class="text-danger small mt-1">{{ errors.blocksCovered }}</p>
        <p v-if="errors.floor" class="text-danger small mt-1">{{ errors.floor }}</p>
      </div>

      <div class="col-12">
        <label class="form-label">Party Members <span class="required-asterisk">*</span></label>
        <div class="d-flex flex-wrap gap-2">
          <div v-for="member in partyOptions" :key="member" class="form-check form-check-inline">
            <input :id="`party-${member}`" type="checkbox" class="form-check-input" :checked="form.partyMembers.includes(member)" @change="togglePartyMember(member)" />
            <label :for="`party-${member}`" class="form-check-label">{{ member }}</label>
          </div>
        </div>
        <p v-if="errors.partyMembers" class="text-danger small mt-1">{{ errors.partyMembers }}</p>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label" for="difficulty-field">Difficulty</label>
        <select id="difficulty-field" v-model="form.difficulty" class="form-select">
          <option value="">Not specified</option>
          <option v-for="d in difficultyOptions" :key="d" :value="d">{{ d }}</option>
        </select>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label" for="goal-field">Exploration Goal</label>
        <input id="goal-field" v-model="form.explorationGoal" type="text" class="form-control" placeholder="e.g. Farm materials, Complete Elizabeth's request" />
      </div>
    </div>

    <RepeatableSectionComponent
      title="Co-authors"
      :rows="form.coAuthors"
      :fields="[{ key: 'name', label: 'Name', placeholder: 'e.g. Mitsuru', col: 'col-12 col-md-6' }]"
      @add="form.coAuthors.push(blankCoAuthor())"
      @remove="(id) => (form.coAuthors = form.coAuthors.filter((author) => author.id !== id))"
    />

    <h2 class="h5 text-gold mb-3">Exploration Summary</h2>
    <div class="row g-3 mb-4">
      <div class="col-12 col-md-6">
        <label class="form-label" for="outcome-field">Outcome <span class="required-asterisk">*</span></label>
        <select id="outcome-field" v-model="form.outcome" class="form-select">
          <option value="" disabled>Select an outcome</option>
          <option v-for="o in outcomeOptions" :key="o" :value="o">{{ o }}</option>
        </select>
        <p v-if="errors.outcome" class="text-danger small mt-1">{{ errors.outcome }}</p>
      </div>
      <div class="col-12">
        <label class="form-label" for="strategy-notes-field">Strategy Notes <span class="required-asterisk">*</span></label>
        <textarea id="strategy-notes-field" v-model="form.strategyNotes" class="form-control" rows="3" placeholder="e.g. Buffed before engaging, focused the healer Shadow first"></textarea>
        <p v-if="errors.strategyNotes" class="text-danger small mt-1">{{ errors.strategyNotes }}</p>
      </div>
      <div class="col-12">
        <label class="form-label" for="overall-notes-field">Overall Notes</label>
        <textarea id="overall-notes-field" v-model="form.overallNotes" class="form-control" rows="2" placeholder="e.g. Good run overall, low SP by the end"></textarea>
      </div>
    </div>

    <MediaUploadSectionComponent
      :media="form.media"
      @add="(item) => form.media.push(item)"
      @remove="(id) => (form.media = form.media.filter((m) => m.id !== id))"
    />

    <ShadowEncounterSectionComponent :rows="form.shadows" :personas="personas" @add="form.shadows.push(blankShadow())" @remove="(id) => (form.shadows = form.shadows.filter((s) => s.id !== id))" />

    <GatekeeperSectionComponent :gatekeeper="form.gatekeeper" />

    <RepeatableSectionComponent
      title="Treasure / Loot"
      :rows="form.treasure"
      :fields="[
        { key: 'itemName', label: 'Item Name', placeholder: 'e.g. Life Stone', col: 'col-6 col-md-5' },
        { key: 'source', label: 'Source', type: 'select', options: treasureSourceOptions, col: 'col-6 col-md-5' },
      ]"
      @add="form.treasure.push(blankTreasure())"
      @remove="(id) => (form.treasure = form.treasure.filter((r) => r.id !== id))"
    />

    <RepeatableSectionComponent
      title="Shuffle Time"
      :rows="form.shuffleTime"
      :fields="[
        { key: 'arcana', label: 'Major Arcana', placeholder: 'e.g. The Star', col: 'col-6 col-md-3' },
        { key: 'cardEffect', label: 'Card Effect', placeholder: 'e.g. Full HP/SP restore', col: 'col-6 col-md-4' },
        { key: 'notes', label: 'Notes', placeholder: 'e.g. Took it to save on items', col: 'col-12 col-md-4' },
      ]"
      @add="form.shuffleTime.push(blankShuffle())"
      @remove="(id) => (form.shuffleTime = form.shuffleTime.filter((r) => r.id !== id))"
    />

    <RepeatableSectionComponent
      title="Valuable Discoveries"
      :rows="form.discoveries"
      :fields="[
        { key: 'discovery', label: 'Discovery', placeholder: 'e.g. Rare Shadow Spawn', col: 'col-6 col-md-4' },
        { key: 'details', label: 'Details', placeholder: 'e.g. Appears frequently between floors 73-76 after the Reload patch', col: 'col-12 col-md-7' },
      ]"
      @add="form.discoveries.push(blankDiscovery())"
      @remove="(id) => (form.discoveries = form.discoveries.filter((r) => r.id !== id))"
    />

    <RepeatableSectionComponent
      title="Custom Information"
      :rows="form.customInfo"
      :fields="[
        { key: 'label', label: 'Label', placeholder: 'e.g. Arcana Burst', col: 'col-6 col-md-4' },
        { key: 'value', label: 'Value', placeholder: 'e.g. Active', col: 'col-6 col-md-4' },
      ]"
      @add="form.customInfo.push(blankCustom())"
      @remove="(id) => (form.customInfo = form.customInfo.filter((r) => r.id !== id))"
    />

    <div class="d-flex gap-2 mt-4">
      <button type="submit" class="btn btn-gold">{{ isEditMode ? 'Save Changes' : 'Submit Entry' }}</button>
      <button type="button" class="btn btn-outline-gold" @click="onCancel">Cancel</button>
    </div>
  </form>
</template>