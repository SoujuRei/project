<script setup>
import { ref, computed } from 'vue'

const base = import.meta.env.BASE_URL
const firstName = ref('')
const lastName = ref('')
const role = ref('member') // 'member' | 'support'

const welcomeMessage = computed(() => {
  if (!firstName.value && !lastName.value) return ''
  return `Welcome, ${firstName.value} ${lastName.value}!`.trim()
})

const roleImage = computed(() =>
  role.value === 'member'
    ? `${base}assets/images/About/P3R_key_art.png`
    : `${base}assets/images/About/tartarus.png`
)
</script>

<template>
  <div class="row g-4">
    <div class="col-12">
      <h1 class="text-gold">About Tartarus Log</h1>
      <p>
        Tartarus Log is a fan-made companion app for Persona 3 Reload players — part
        Compendium reference, part community exploration journal. Register as a SEES
        member to log your own runs, or browse freely as a guest.
      </p>
    </div>

    <div class="col-12 col-md-6">
      <label for="firstName" class="form-label">First name</label>
      <input id="firstName" v-model="firstName" type="text" class="form-control mb-3" />

      <label for="lastName" class="form-label">Last name</label>
      <input id="lastName" v-model="lastName" type="text" class="form-control mb-3" />

      <p v-if="welcomeMessage" class="text-gold">{{ welcomeMessage }}</p>

      <fieldset class="mt-3">
        <legend class="fs-6">Which side do you fall on?</legend>
        <div class="form-check">
          <input
            id="roleMember"
            v-model="role"
            class="form-check-input"
            type="radio"
            value="member"
          />
          <label class="form-check-label" for="roleMember">SEES Member</label>
        </div>
        <div class="form-check">
          <input
            id="roleSupport"
            v-model="role"
            class="form-check-input"
            type="radio"
            value="support"
          />
          <label class="form-check-label" for="roleSupport">Support Staff</label>
        </div>
      </fieldset>
    </div>

    <div class="col-12 col-md-6">
      <!-- Swaps based on the radio choice above -->
      <img :src="roleImage" alt="Selected role artwork" class="img-fluid rounded" />
    </div>
  </div>
</template>