<script setup>
import { ref } from 'vue'
import { apiUpload, MEDIA_BASE } from '../api/client'

const props = defineProps({
  media: { type: Array, required: true },
})
const emit = defineEmits(['add', 'remove'])

const uploading = ref(false)
const uploadError = ref('')



// Safely formats the media URL whether it is relative or already absolute
function resolveMediaUrl(url) {
  if (!url) return ''
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url
  }
  return `${MEDIA_BASE}${url}`
}

async function onFilesSelected(event) {
  const files = Array.from(event.target.files || [])
  uploadError.value = ''
  uploading.value = true

  for (const file of files) {
    const formData = new FormData()
    formData.append('file', file)
    try {
      const res = await apiUpload('/uploads.php', formData)
      emit('add', { id: crypto.randomUUID(), type: res.type, url: res.url, caption: '' })
    } catch (e) {
      uploadError.value = e.message || 'Upload failed.'
    }
  }

  uploading.value = false
  event.target.value = ''
}
</script>

<template>
  <div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h3 class="h6 text-gold mb-0">Media (Screenshots / Videos)</h3>
      <label class="btn btn-outline-gold btn-sm mb-0">
        {{ uploading ? 'Uploading...' : '+ Upload Media' }}
        <input
          type="file"
          accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"
          multiple
          hidden
          :disabled="uploading"
          @change="onFilesSelected"
        />
      </label>
    </div>

    <p v-if="uploadError" class="text-danger small">{{ uploadError }}</p>
    <p v-if="!media.length" class="text-muted small">None added yet.</p>

    <div class="row g-2">
      <div v-for="item in media" :key="item.id" class="col-6 col-md-4">
        <div class="card p-2">
          <img
            v-if="item.type === 'image'"
            :src="resolveMediaUrl(item.url)"
            alt="Uploaded log media"
            class="img-fluid rounded mb-2"
            style="max-height: 120px; object-fit: cover;"
          />
          <video
            v-else
            :src="resolveMediaUrl(item.url)"
            controls
            preload="metadata"
            class="w-100 mb-2"
            style="max-height: 120px;"
          ></video>
          <input
            v-model="item.caption"
            type="text"
            class="form-control form-control-sm mb-2"
            placeholder="Caption (optional)"
          />
          <button type="button" class="btn btn-outline-gold btn-sm" @click="$emit('remove', item.id)">Remove</button>
        </div>
      </div>
    </div>
  </div>
</template>