const API_BASE =
  import.meta.env.VITE_API_BASE_URL ||
  'http://localhost/project/backend/api'

export async function apiRequest(path, options = {}) {
  let body = options.body

  // Convert objects automatically to JSON strings if not already stringified
  if (body && typeof body === 'object' && !(body instanceof FormData)) {
    body = JSON.stringify(body)
  }

  const res = await fetch(`${API_BASE}${path}`, {
    credentials: 'include',
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...options.headers,
    },
    body,
  })

  const data = await res.json().catch(() => ({}))

  if (!res.ok) {
    throw new Error(
      data.error || data.message || `Request failed (${res.status})`
    )
  }

  return data
}

export async function apiUpload(path, formData) {
  const res = await fetch(`${API_BASE}${path}`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  })

  const data = await res.json().catch(() => ({}))

  if (!res.ok) {
    throw new Error(
      data.error || `Upload failed (${res.status})`
    )
  }

  return data
}

export { API_BASE }
export const MEDIA_BASE =
  import.meta.env.VITE_MEDIA_BASE_URL ||
  'http://localhost/project/backend'