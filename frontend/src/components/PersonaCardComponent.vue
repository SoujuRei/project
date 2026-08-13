<script setup>
import { computed } from 'vue'

const props = defineProps({
  persona: { type: Object, required: true },
})

const base = import.meta.env.BASE_URL

function asList(value) {
  if (!value) return []
  if (Array.isArray(value)) return value
  return String(value).split(',').map((v) => v.trim()).filter(Boolean)
}

const arcanaImage = computed(() => `${base}assets/images/Arcana/${props.persona.arcana || 'Fool'}-0.png`)
</script>

<template>
  <div class="card h-100 p-3 d-flex flex-column">
    <div class="mb-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <h2 class="h3 mb-0 fw-bold text-gold">{{ persona.name }}</h2>
        <img :src="arcanaImage" :alt="persona.arcana" width="28" height="28" class="rounded" />
      </div>
      <span class="badge badge-arcana">{{ persona.arcana }} · Lv.{{ persona.level }}</span>
    </div>

    <p class="small mb-3">{{ persona.description }}</p>

    <table class="table table-sm table-borderless mb-2" aria-label="Base stats for persona">
      <caption class="visually-hidden">Base stats for {{ persona.name }}</caption>
      <tbody>
        <tr>
          <th scope="row">STR</th><td>{{ persona.strength }}</td>
          <th scope="row">MAG</th><td>{{ persona.magic }}</td>
        </tr>
        <tr>
          <th scope="row">END</th><td>{{ persona.endurance }}</td>
          <th scope="row">AGI</th><td>{{ persona.agility }}</td>
        </tr>
        <tr>
          <th scope="row">LUCK</th><td colspan="3">{{ persona.luck }}</td>
        </tr>
      </tbody>
    </table>

    <div class="mt-auto">
      <div v-if="asList(persona.weak).length" class="small mb-1">
        <strong>Weak:</strong>
        <span v-for="item in asList(persona.weak)" :key="item" class="badge badge-arcana ms-1">{{ item }}</span>
      </div>
      <div v-if="asList(persona.resists).length" class="small mb-1">
        <strong>Resists:</strong>
        <span v-for="item in asList(persona.resists)" :key="item" class="badge badge-arcana ms-1">{{ item }}</span>
      </div>
      <div v-if="asList(persona.reflects).length" class="small mb-1">
        <strong>Reflects:</strong>
        <span v-for="item in asList(persona.reflects)" :key="item" class="badge badge-arcana ms-1">{{ item }}</span>
      </div>
      <div v-if="asList(persona.absorbs).length" class="small mb-1">
        <strong>Absorbs:</strong>
        <span v-for="item in asList(persona.absorbs)" :key="item" class="badge badge-arcana ms-1">{{ item }}</span>
      </div>
      <div v-if="asList(persona.nullifies).length" class="small">
        <strong>Nullifies:</strong>
        <span v-for="item in asList(persona.nullifies)" :key="item" class="badge badge-arcana ms-1">{{ item }}</span>
      </div>
    </div>
  </div>
</template>