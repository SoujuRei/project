<script setup>
defineProps({
  mode: { type: String, required: true },
  // 'dyad' (normal, or fusion with 2 personas) | 'special'
  dyadResults: { type: Array, default: () => [] },
  specialResult: { type: Object, default: null },
})
</script>

<template>
  <div v-if="mode === 'dyad'">
    <p v-if="!dyadResults.length" class="text-muted">No valid input pairs found for the selected target Persona.</p>
    <ul v-else class="list-unstyled">
      <li v-for="(pair, i) in dyadResults" :key="i" class="card p-2 mb-2">
        <div class="fw-bold">{{ pair.inputA.name }} + {{ pair.inputB.name }}</div>
        <div class="small text-muted">{{ pair.inputA.arcana }} + {{ pair.inputB.arcana }}</div>
      </li>
    </ul>
  </div>

  <div v-else-if="mode === 'special'">
    <p v-if="!specialResult" class="text-muted">This Persona has no Special Fusion recipe.</p>
    <div v-else class="card p-3">
      <p class="mb-2"><strong>Ingredients:</strong> {{ specialResult.ingredients.join(', ') }}</p>
      <p v-if="specialResult.unlockCondition" class="mb-0 small text-muted">
        Unlock condition: {{ specialResult.unlockCondition }}
      </p>
    </div>
  </div>
</template>