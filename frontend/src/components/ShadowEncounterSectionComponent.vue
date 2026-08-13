<script setup>
const props = defineProps({
  rows: { type: Array, required: true },
  personas: { type: Array, required: true },
})
defineEmits(['add', 'remove'])

function onNameInput(row) {
  const match = props.personas.find((p) => p.name.toLowerCase() === row.name.toLowerCase())
  if (match) {
    row.arcana = match.arcana
    row.weakness = Array.isArray(match.weak) ? match.weak.join(', ') : match.weak || row.weakness
  }
}
</script>

<template>
  <div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h3 class="h6 text-gold mb-0">Shadow Encounters</h3>
      <button type="button" class="btn btn-outline-gold btn-sm" @click="$emit('add')">
        + Add Shadow Encounter
      </button>
    </div>

    <p v-if="!rows.length" class="text-muted small">None added yet.</p>

    <div v-for="(row, i) in rows" :key="row.id" class="card p-3 mb-2">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small text-muted">Encounter #{{ i + 1 }}</span>
        <button type="button" class="btn btn-outline-gold btn-sm" @click="$emit('remove', row.id)">Remove</button>
      </div>

      <div class="row g-2">
        <div class="col-6 col-md-4">
          <label class="form-label small mb-1">Shadow Name</label>
          <!-- Datalist checks against the Compendium; custom bosses just won't match -->
          <input
            v-model="row.name"
            list="shadow-name-options"
            class="form-control form-control-sm"
            placeholder="e.g. Dancing Hand"
            @input="onNameInput(row)"
          />
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small mb-1">Floor Encountered</label>
          <input v-model="row.floorEncountered" type="number" class="form-control form-control-sm" placeholder="e.g. 42" />
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label small mb-1">Arcana</label>
          <input v-model="row.arcana" class="form-control form-control-sm" placeholder="Auto-fills if a known Shadow" />
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label small mb-1">Weakness</label>
          <input v-model="row.weakness" class="form-control form-control-sm" placeholder="e.g. Fire" />
        </div>
        <div class="col-6 col-md-6">
          <label class="form-label small mb-1">Resist / Null / Repel / Drain</label>
          <input v-model="row.resistances" class="form-control form-control-sm" placeholder="e.g. Nullifies Physical" />
        </div>
        <div class="col-6 col-md-6">
          <label class="form-label small mb-1">Dangerous Skills</label>
          <input v-model="row.dangerousSkills" class="form-control form-control-sm" placeholder="e.g. Mazio (party-wide Electric)" />
        </div>
        <div class="col-12">
          <label class="form-label small mb-1">Strategy Notes</label>
          <input v-model="row.strategyNotes" class="form-control form-control-sm" placeholder="e.g. Knock down immediately before it buffs" />
        </div>
      </div>
    </div>

    <datalist id="shadow-name-options">
      <option v-for="p in personas" :key="p.id" :value="p.name" />
    </datalist>
  </div>
</template>