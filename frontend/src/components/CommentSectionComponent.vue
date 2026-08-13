<script setup>
import { onMounted, ref, computed } from 'vue'
import { useComments } from '../composables/useComments'
import AuthRequiredModalComponent from './AuthRequiredModalComponent.vue'

const props = defineProps({
  logId: {
    type: [String, Number],
    required: true,
  },
  currentUsername: {
    type: String,
    default: null,
  },
  isAdmin: {
    type: Boolean,
    default: false,
  },
})

const {
  commentsFor,
  addComment,
  deleteComment,
  hydrate,
  hasCommented,
} = useComments()

const isOpen = ref(false)
const draft = ref('')
const showAuthModal = ref(false)
const errorMessage = ref('')
const deletingId = ref(null)

onMounted(() => {
  void hydrate(props.logId)
})

const commentCount = computed(() => {
  return commentsFor(props.logId).length
})

const alreadyCommented = computed(() => {
  return hasCommented(props.logId, props.currentUsername)
})

const canPost = computed(() => {
  return Boolean(
    props.currentUsername &&
    draft.value.trim().length > 0
  )
})

function formatCommentDate(value) {
  if (!value) return ''

  const date = new Date(String(value).replace(' ', 'T'))

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return date.toLocaleString()
}

function canDelete(comment) {
  return (
    props.isAdmin ||
    comment.author === props.currentUsername
  )
}

async function onPost() {
  if (!props.currentUsername) {
    showAuthModal.value = true
    return
  }

  const text = draft.value.trim()

  if (!text) return

  errorMessage.value = ''

  try {
    await addComment(props.logId, text)
    draft.value = ''
  } catch (error) {
    errorMessage.value =
      error?.message || 'Unable to post comment.'
  }
}

async function onDelete(comment) {
  if (!canDelete(comment)) return

  if (!window.confirm('Delete this comment?')) {
    return
  }

  errorMessage.value = ''
  deletingId.value = comment.id

  try {
    await deleteComment(comment.id, props.logId)
  } catch (error) {
    errorMessage.value =
      error?.message || 'Unable to delete comment.'
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div>
    <button
      class="btn btn-outline-gold btn-sm mt-2"
      :aria-expanded="isOpen"
      :aria-controls="`comments-panel-${logId}`"
      @click="isOpen = !isOpen"
    >
      {{ isOpen ? 'Hide comments' : `Comments (${commentCount})` }}
      <span v-if="alreadyCommented" class="badge bg-secondary ms-1">✓ You commented</span>
    </button>


    <div
      v-if="isOpen"
      :id="`comments-panel-${logId}`"
      class="position-relative mt-2"
      role="region"
      aria-label="Comments on this log entry"
    >
      <button
        type="button"
        class="p3r-modal-close"
        style="position: static; float: right;"
        aria-label="Close comments"
        @click="isOpen = false"
      >
        ×
      </button>

      <ul class="comment-scroll list-unstyled">
        <li
          v-if="!commentCount"
          class="text-muted small"
        >
          No comments yet.
        </li>

        <li
          v-for="comment in commentsFor(logId)"
          :key="comment.id"
          class="small mb-3"
        >
          <div class="d-flex align-items-center gap-2">
            <strong>{{ comment.author }}</strong>

            <span
              v-if="comment.created_at"
              class="text-muted"
            >
              {{ formatCommentDate(comment.created_at) }}
            </span>

            <button
              v-if="canDelete(comment)"
              type="button"
              class="btn btn-link btn-sm text-danger p-0 ms-auto"
              :disabled="deletingId === comment.id"
              @click="onDelete(comment)"
            >
              {{ deletingId === comment.id ? 'Deleting…' : 'Delete' }}
            </button>
          </div>

          <p class="mb-0 mt-1">
            {{ comment.text }}
          </p>
        </li>
      </ul>

      <div
        v-if="errorMessage"
        class="alert alert-danger small py-2"
        role="alert"
      >
        {{ errorMessage }}
      </div>

      <form
        class="d-flex gap-2 mt-2"
        @submit.prevent="onPost"
      >
        <label
          :for="`comment-input-${logId}`"
          class="visually-hidden"
        >
          Add a comment
        </label>

        <input
          :id="`comment-input-${logId}`"
          v-model="draft"
          type="text"
          class="form-control form-control-sm"
          placeholder="Add a comment"
          maxlength="500"
          
        />

        <button
          type="submit"
          class="btn btn-gold btn-sm"
          
        >
          Post
        </button>
      </form>
    </div>

    <AuthRequiredModalComponent
      v-if="showAuthModal"
      message="Only registered SEES members can comment."
      @close="showAuthModal = false"
    />
  </div>
</template>

