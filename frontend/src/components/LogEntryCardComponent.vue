<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import { highlightFlash as vHighlightFlash } from '../directives/highlightFlash'
import { useComments } from '../composables/useComments'
import { API_BASE } from '../api/client'
import CommentSectionComponent from './CommentSectionComponent.vue'
import AuthRequiredModalComponent from './AuthRequiredModalComponent.vue'

const props = defineProps({
  entry: { type: Object, required: true },
  currentUsername: { type: String, default: null },
  isAdmin: { type: Boolean, default: false },
  hasVoted: { type: Boolean, default: false },
  isHighlighted: { type: Boolean, default: false },
  userRunNumber: { type: Number, default: null },
})

const emit = defineEmits(['upvote', 'delete'])
const showAuthModal = ref(false)
const showFullReport = ref(false)

const { commentsFor, hydrate: hydrateComments, hasCommented: checkHasCommented } = useComments()

// Derive backend base origin from API_BASE (e.g. http://localhost/project)
const backendOrigin = API_BASE.replace(/\/api$/, '')

function resolveMediaUrl(url) {
  if (!url) return ''
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url
  }
  return `${backendOrigin}${url.startsWith('/') ? '' : '/'}${url}`
}

onMounted(() => {
  // idempotent per log id — CommentSectionComponent also calls this for the same id
  void hydrateComments(props.entry.id)
})

function onVoteClick() {
  if (!props.currentUsername) {
    showAuthModal.value = true
    return
  }
  emit('upvote', props.entry.id)
}

const validCoAuthors = computed(() =>
  (props.entry.coAuthors || []).filter((item) => item?.name?.trim())
)
const validTreasure = computed(() =>
  (props.entry.treasure || []).filter((item) => item?.itemName?.trim() || item?.source?.trim())
)
const validShuffleTime = computed(() =>
  (props.entry.shuffleTime || []).filter(
    (item) => item?.arcana?.trim() || item?.cardEffect?.trim() || item?.notes?.trim()
  )
)
const validDiscoveries = computed(() =>
  (props.entry.discoveries || []).filter((item) => item?.discovery?.trim() || item?.details?.trim())
)
const validCustomInfo = computed(() =>
  (props.entry.customInfo || []).filter((item) => item?.label?.trim() || item?.value?.trim())
)
const validMedia = computed(() => (props.entry.media || []).filter((item) => item?.url?.trim()))

const hasExtraDetails = computed(() => {
  const e = props.entry
  return (
    e.gatekeeper?.encountered ||
    validTreasure.value.length ||
    validShuffleTime.value.length ||
    validDiscoveries.value.length ||
    validCustomInfo.value.length ||
    e.overallNotes?.trim()
  )
})

const displayTitle = computed(() => {
  const title = props.entry.title?.trim()
  if (title) return title

  const author = props.entry.author || 'User'
  const runNumber = props.userRunNumber ?? props.entry.runNumber
  if (runNumber != null) return `${author}'s Run #${runNumber}`
  return `${author}'s Run`
})

const hasCommented = computed(() => checkHasCommented(props.entry.id, props.currentUsername))

const lightboxItem = ref(null)
const lightboxRef = ref(null)
let lastFocusedEl = null

function openLightbox(item) {
  lastFocusedEl = document.activeElement
  lightboxItem.value = item
  nextTick(() => lightboxRef.value?.focus())
}

function closeLightbox() {
  lightboxItem.value = null
  lastFocusedEl?.focus?.()
}
</script>

<template>
  <div :id="`entry-${entry.id}`" v-highlight-flash="isHighlighted" class="card log-entry-card p-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <span v-if="entry.block || entry.blocksCovered?.length || entry.startFloor || entry.endFloor" class="badge badge-arcana">
          <span v-if="entry.blocksCovered?.length">{{ entry.blocksCovered.join(', ') }}</span>
          <span v-else-if="entry.block">{{ entry.block }}</span>
          <span v-if="entry.startFloor || entry.endFloor"> — Floors {{ entry.startFloor ?? entry.floor ?? '—' }}-{{ entry.endFloor ?? entry.floor ?? '—' }}</span>
          <span v-else-if="entry.floor"> — Floor {{ entry.floor }}</span>
        </span>
        <span v-if="entry.outcome" class="badge badge-arcana">{{ entry.outcome }}</span>
        <span v-if="entry.difficulty" class="badge badge-arcana">{{ entry.difficulty }}</span>
      </div>
      <span class="text-muted small">{{ entry.date }}</span>
    </div>

    <h2 class="h6 mt-2 mb-1">{{ displayTitle }}</h2>
    <p v-if="validCoAuthors.length" class="small text-muted mb-2">Co-authors: {{ validCoAuthors.map((author) => author.name).join(', ') }}</p>
    <p v-if="entry.explorationGoal" class="small text-muted mb-2">Goal: {{ entry.explorationGoal }}</p>

    <div v-if="entry.partyMembers?.length" class="mb-2">
      <span v-for="member in entry.partyMembers" :key="member" class="badge bg-secondary me-1">{{ member }}</span>
    </div>

    <p v-if="entry.strategyNotes || entry.strategyNote" class="mb-2">{{ entry.strategyNotes || entry.strategyNote }}</p>

    <div
      v-if="validMedia.length"
      class="log-media-grid mb-2"
      :class="{ 'log-media-grid--single': validMedia.length === 1 }"
    >
      <figure v-for="item in validMedia" :key="item.id || item.url" class="log-media-item m-0">
        <img
          v-if="item.type === 'image'"
          :src="resolveMediaUrl(item.url)"
          :alt="item.caption || `Screenshot from ${displayTitle}`"
          loading="lazy"
          class="log-media-thumb"
          role="button"
          tabindex="0"
          :aria-label="`Open larger view: ${item.caption || 'log image'}`"
          @click="openLightbox(item)"
          @keydown.enter="openLightbox(item)"
          @keydown.space.prevent="openLightbox(item)"
        />
        <video
          v-else-if="item.type === 'video'"
          :src="resolveMediaUrl(item.url)"
          controls
          preload="metadata"
          class="log-media-thumb"
          :aria-label="item.caption || `Video from ${displayTitle}`"
        >
          Your browser does not support embedded video.
        </video>
        <figcaption v-if="item.caption" class="small text-muted mt-1">{{ item.caption }}</figcaption>
      </figure>
    </div>

    <ul v-if="entry.shadows?.length" class="list-unstyled small mb-2 log-shadow-list">
      <li v-for="shadow in entry.shadows" :key="shadow.id" class="mb-1">
        <strong>{{ shadow.name }}</strong>
        <span v-if="shadow.weakness"> — weak to {{ shadow.weakness }}</span>
        <span v-if="shadow.strategyNotes" class="text-muted"> ({{ shadow.strategyNotes }})</span>
      </li>
    </ul>

    <button
      v-if="hasExtraDetails"
      class="btn btn-outline-gold btn-sm mb-2"
      :aria-expanded="showFullReport"
      @click="showFullReport = !showFullReport"
    >
      {{ showFullReport ? 'Hide Full Report' : 'Show Full Report' }}
    </button>

    <div v-if="showFullReport" class="log-shadow-list mb-2">
      <p v-if="entry.overallNotes" class="small mb-2"><strong>Overall Notes:</strong> {{ entry.overallNotes }}</p>

      <div v-if="entry.gatekeeper?.encountered" class="small mb-2">
        <strong>Gatekeeper:</strong> {{ entry.gatekeeper.bossName }} (Floor {{ entry.gatekeeper.floor }})
        <span v-if="entry.gatekeeper.weakness"> — weak to {{ entry.gatekeeper.weakness }}</span>
        <p v-if="entry.gatekeeper.strategy" class="mb-0">Strategy: {{ entry.gatekeeper.strategy }}</p>
      </div>

      <div v-if="validTreasure.length" class="small mb-2">
        <strong>Treasure:</strong>
        <span v-for="(t, i) in validTreasure" :key="t.id">
          {{ t.itemName }}<span v-if="t.source"> ({{ t.source }})</span>{{ i < validTreasure.length - 1 ? ', ' : '' }}
        </span>
      </div>

      <div v-if="validShuffleTime.length" class="small mb-2">
        <strong>Shuffle Time:</strong>
        <span v-for="(s, i) in validShuffleTime" :key="s.id">
          {{ [s.arcana, s.cardEffect, s.notes].filter(Boolean).join(' — ') }}{{ i < validShuffleTime.length - 1 ? '; ' : '' }}
        </span>
      </div>

      <div v-if="validDiscoveries.length" class="small mb-2">
        <strong>Discoveries:</strong>
        <div v-for="d in validDiscoveries" :key="d.id">
          {{ [d.discovery, d.details].filter(Boolean).join(' — ') }}
        </div>
      </div>

      <div v-if="validCustomInfo.length" class="small">
        <strong>Additional Info:</strong>
        <span v-for="(c, i) in validCustomInfo" :key="c.id">
          {{ [c.label, c.value].filter(Boolean).join(': ') }}{{ i < validCustomInfo.length - 1 ? ', ' : '' }}
        </span>
      </div>
    </div>

    <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
      <button
        class="btn btn-sm btn-vote"
        :class="hasVoted ? 'btn-gold active-voted' : 'btn-outline-gold'"
        :aria-pressed="hasVoted"
        :aria-label="hasVoted ? `Remove upvote, currently ${entry.votes} votes` : `Upvote this run, currently ${entry.votes} votes`"
        @click="onVoteClick"
      >
        <span class="vote-icon">{{ hasVoted ? '▲' : '△' }}</span>
        <span class="ms-1">{{ entry.votes }}</span>
        <span v-if="hasVoted" class="badge bg-dark ms-1">✓ Voted</span>
      </button>

      <template v-if="currentUsername === entry.author || isAdmin">
        <router-link
          v-if="currentUsername === entry.author"
          :to="`/log/edit/${entry.id}`"
          class="btn btn-outline-gold btn-sm"
        >
          Edit
        </router-link>

        <button
          class="btn btn-outline-gold btn-sm"
          @click="$emit('delete', entry.id)"
        >
          Delete{{ isAdmin && currentUsername !== entry.author ? ' (admin)' : '' }}
        </button>
      </template>
    </div>

    <CommentSectionComponent
      :id="`comments-panel-${entry.id}`"
      :log-id="entry.id"
      :current-username="currentUsername"
    />

    <AuthRequiredModalComponent
      v-if="showAuthModal"
      message="Only registered SEES members can vote."
      @close="showAuthModal = false"
    />
<Teleport to="body">
    <div
      v-if="lightboxItem"
      class="log-lightbox-overlay"
      role="dialog"
      aria-modal="true"
      :aria-label="lightboxItem.caption || 'Expanded log image'"
      tabindex="-1"
      ref="lightboxRef"
      @click.self="closeLightbox"
      @keydown.esc="closeLightbox"
    >
      <button class="log-lightbox-close" aria-label="Close image preview" @click="closeLightbox">×</button>
      <img :src="resolveMediaUrl(lightboxItem.url)" :alt="lightboxItem.caption || 'Expanded log image'" class="log-lightbox-img" />
      <p v-if="lightboxItem.caption" class="log-lightbox-caption small">{{ lightboxItem.caption }}</p>
    </div>
</Teleport>  
  </div>
</template>

<style scoped>
.log-media-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.5rem;
}

@media (min-width: 576px) {
  .log-media-grid:not(.log-media-grid--single) {
    grid-template-columns: repeat(2, 1fr);
  }
}

.log-media-thumb {
  width: 100%;
  max-height: 320px;
  object-fit: cover;
  border-radius: 0.375rem;
  display: block;
}

img.log-media-thumb {
  cursor: pointer;
}

img.log-media-thumb:focus-visible {
  outline: 2px solid var(--bs-warning, #ffc107);
  outline-offset: 2px;
}

.log-lightbox-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  padding: 1rem;
}

.log-lightbox-img {
  max-width: 100%;
  max-height: 85vh;
  border-radius: 0.375rem;
}

.log-lightbox-caption {
  color: #f1f1f1;
  margin-top: 0.5rem;
}

.log-lightbox-close {
  position: absolute;
  top: 1rem;
  right: 1rem;
  background: transparent;
  border: none;
  color: #fff;
  font-size: 2rem;
  line-height: 1;
  cursor: pointer;
}

.log-lightbox-close:focus-visible {
  outline: 2px solid var(--bs-warning, #ffc107);
  outline-offset: 2px;
}

.btn-vote {
  transition: all 0.2s ease-in-out;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

.btn-vote:hover {
  transform: translateY(-1px);
}

.active-voted {
  box-shadow: 0 0 8px rgba(255, 215, 0, 0.4);
  font-weight: 600;
}

.vote-icon {
  display: inline-block;
  font-size: 0.85em;
  line-height: 1;
}
</style>