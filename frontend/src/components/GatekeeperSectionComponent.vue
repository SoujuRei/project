<script setup>
defineProps({
  gatekeeper: { type: Object, required: true },
})
</script>

<template>
  <div class="mb-4">
    <div class="form-check mb-2">
      <input id="gkEncountered" v-model="gatekeeper.encountered" type="checkbox" class="form-check-input" />
      <label for="gkEncountered" class="form-check-label h6 text-gold">Gatekeeper Encounter (optional)</label>
    </div>

    <div v-if="gatekeeper.encountered" class="card p-3 row g-2">
      <div class="col-6 col-md-4">
        <label class="form-label small mb-1">Boss Name</label>
        <input v-model="gatekeeper.bossName" class="form-control form-control-sm" placeholder="e.g. The Priestess" />
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Floor</label>
        <input v-model="gatekeeper.floor" type="number" class="form-control form-control-sm" placeholder="e.g. 45" />
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Weakness</label>
        <input v-model="gatekeeper.weakness" class="form-control form-control-sm" placeholder="e.g. Wind" />
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label small mb-1">Recommended Level</label>
        <input v-model="gatekeeper.recommendedLevel" type="number" class="form-control form-control-sm" placeholder="e.g. 44" />
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label small mb-1">Notable Mechanics</label>
        <input v-model="gatekeeper.mechanics" class="form-control form-control-sm" placeholder="e.g. Summons adds at 50% HP" />
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label small mb-1">Strategy</label>
        <input v-model="gatekeeper.strategy" class="form-control form-control-sm" placeholder="e.g. Focus adds first, then burst the boss" />
      </div>
    </div>
  </div>
</template>