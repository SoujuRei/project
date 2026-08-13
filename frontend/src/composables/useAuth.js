import { ref } from 'vue'
import { apiRequest } from '../api/client'
import { clearVotes } from './useLogs'
import { clearComments } from './useComments'

const currentUser = ref(null)

export function useAuth() {
  async function checkSession() {
    try {
      const res = await apiRequest('/auth.php?action=me')
      currentUser.value = res.user
    } catch {
      currentUser.value = null
    }
  }

  // Accepts strictly: { username, password }
  async function login(credentials) {
    const res = await apiRequest('/auth.php?action=login', {
      method: 'POST',
      body: credentials,
    })
    currentUser.value = res.user
    return res
  }

  // Accepts strictly: { username, email, password }
  async function register(userData) {
    const res = await apiRequest('/auth.php?action=register', {
      method: 'POST',
      body: userData,
    })
    currentUser.value = res.user
    return res
  }

  async function logout() {
    try {
      await apiRequest('/auth.php?action=logout', { method: 'POST' })
    } catch {
      // Clean local state even if logout network request fails
    } finally {
      currentUser.value = null

      if (typeof clearVotes === 'function') {
        clearVotes()
      }
      if (typeof clearComments === 'function') {
        clearComments()
      }
    }
  }

  return { currentUser, checkSession, login, register, logout }
}