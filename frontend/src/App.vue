<script setup>
import { onMounted } from 'vue'
// import AppNavbarComponent from './components/AppNavbarComponent.vue'
import { useAuth } from './composables/useAuth'



const { currentUser, checkSession, logout } = useAuth()

// Restores the logged-in user from the PHP session cookie on refresh 
// without this, currentUser resets to null every reload even if the
// session is still valid server-side
onMounted(checkSession)
</script>

<template>
  <nav class="navbar navbar-expand-md navbar-dark mb-4">
    <div class="container">
      <router-link class="navbar-brand text-gold fw-bold" to="/">Tartarus Log</router-link>
      <button
        class="navbar-toggler"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#navMenu"
      >
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMenu">
        <ul class="navbar-nav me-auto">
          <li class="nav-item"><router-link class="nav-link" active-class="active" exact-active-class="active" to="/">Home</router-link></li>
          <li class="nav-item"><router-link class="nav-link" active-class="active" exact-active-class="active" to="/compendium">Compendium</router-link></li>
          <li class="nav-item"><router-link class="nav-link" active-class="active" exact-active-class="active" to="/log">Tartarus Log</router-link></li>
          <li class="nav-item"><router-link class="nav-link" active-class="active" exact-active-class="active" to="/news">News</router-link></li>
          <li class="nav-item"><router-link class="nav-link" active-class="active" exact-active-class="active" to="/about">About</router-link></li>
        </ul>
        <ul class="navbar-nav">
          <template v-if="currentUser">
            <li class="nav-item">
              <span class="nav-link text-gold">{{ currentUser.username }}</span>
            </li>
            <li class="nav-item">
              <button class="btn btn-outline-gold btn-sm mt-1" @click="logout">Log out</button>
            </li>
          </template>
          <template v-else>
            <li class="nav-item"><router-link class="nav-link" to="/login">Log in</router-link></li>
            <li class="nav-item"><router-link class="nav-link" to="/register">Register</router-link></li>
          </template>
        </ul>
      </div>
    </div>
  </nav>

  <main class="container pb-5">
    <router-view />
  </main>
</template>