<script setup>
import { ref, onMounted } from 'vue'

const base = import.meta.env.BASE_URL
const velvetMessage = ref('')

onMounted(async () => {
  try {
    const res = await fetch('https://api.quotable.io/random')
    const data = await res.json()
    velvetMessage.value = data.content

    
  } catch {
    velvetMessage.value = 'The Velvet Room is quiet tonight.'
  }
})
</script>

<template>
  <div class="row align-items-center g-4">
    <div class="col-12">
      <h1 class="text-gold">Welcome to Tartarus Log</h1>
      <p class="lead">
        A companion site for Persona 3 Reload players. Browse the full Compendium of
        Personas and Shadows, and follow or contribute to the Tartarus Log kept
        by fellow SEES members documenting their floor-by-floor exploration.
      </p>
    </div>
    <div class="col-12" v-if="velvetMessage">
      <p class="text-gold fst-italic">"{{ velvetMessage }}" — a voice from the Velvet Room</p>
    </div>
    <div class="col-12 col-md-6">
      <img
        :src="`${base}assets/images/Home/SeesSquad.png`"
        alt="SEES party members"
        class="img-fluid rounded"
      />
    </div>
    <div class="col-12 col-md-6">
      <img
        :src="`${base}assets/images/Home/sees .png`"
        alt="SEES armband insignia"
        class="img-fluid rounded"
      />
    </div>
  </div>
</template>