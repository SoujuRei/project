import { ref } from 'vue'
import { useAuth } from './useAuth'
import { apiRequest } from '../api/client'

const commentsByLog = ref({})
const hydratedLogIds = new Set()

/**
 * Resets the in-memory comments state and hydration tracking.
 * Must be exported so `useAuth.js` can invoke it directly on logout.
 */
export function clearComments() {
  hasCommented.value = {}
  hydratedLogIds.clear()
}

export function useComments() {
  const { currentUser } = useAuth()

  function commentsFor(logId) {
    return commentsByLog.value[logId] || []
  }

  async function hydrate(logId) {
    if (!logId || hydratedLogIds.has(logId)) return

    hydratedLogIds.add(logId)

    try {
      const res = await apiRequest(
        `/comments.php?log_id=${encodeURIComponent(logId)}`
      )

      commentsByLog.value[logId] = Array.isArray(res.data)
        ? res.data
        : []
    } catch {
      commentsByLog.value[logId] = []
    }
  }

  async function addComment(logId, text) {
    if (!currentUser.value || !text.trim()) return

    await apiRequest('/comments.php', {
      method: 'POST',
      body: JSON.stringify({
        log_id: logId,
        text: text.trim(),
      }),
    })

    /*
     * Force a fresh request after posting.
     */
    hydratedLogIds.delete(logId)
    await hydrate(logId)
  }

  async function deleteComment(commentId, logId) {
    if (!currentUser.value || !commentId) return

    await apiRequest(
      `/comments.php?id=${encodeURIComponent(commentId)}`,
      {
        method: 'DELETE',
      }
    )


    commentsByLog.value[logId] =
      commentsFor(logId).filter(
        (comment) =>
          Number(comment.id) !== Number(commentId)
      )
  }

  function hasCommented(
    logId,
    username = currentUser.value?.username ?? null
  ) {
    if (!username) return false

    return commentsFor(logId).some(
      (comment) => comment.author === username
    )
  }

  return {
    commentsByLog,
    hydrate,
    commentsFor,
    addComment,
    deleteComment,
    hasCommented,
    clearComments,
  }
}