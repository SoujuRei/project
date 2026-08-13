<script setup>
import { useAuth } from '../composables/useAuth'

const { currentUser, logout } = useAuth()
</script>

<template>
  <nav class="navbar navbar-expand-md navbar-dark mb-4">
    <div class="container">
      <router-link class="navbar-brand text-gold fw-bold" to="/">Tartarus Log</router-link>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMenu">
        <ul class="navbar-nav me-auto">
          <li class="nav-item"><router-link class="nav-link" to="/">Home</router-link></li>
          <li class="nav-item"><router-link class="nav-link" to="/compendium">Compendium</router-link></li>
          <li class="nav-item"><router-link class="nav-link" to="/log">Tartarus Log</router-link></li>
          <li class="nav-item"><router-link class="nav-link" to="/news">News</router-link></li>
          <li class="nav-item"><router-link class="nav-link" to="/about">About</router-link></li>
        </ul>
        <ul class="navbar-nav">
          <template v-if="currentUser">
            <li class="nav-item"><span class="nav-link text-gold">{{ currentUser.username }}</span></li>
            <li class="nav-item"><button class="btn btn-outline-gold btn-sm mt-1" @click="logout">Log out</button></li>
          </template>
          <template v-else>
            <li class="nav-item"><router-link class="nav-link" to="/login">Log in</router-link></li>
            <li class="nav-item"><router-link class="nav-link" to="/register">Register</router-link></li>
          </template>
        </ul>
      </div>
    </div>
  </nav>
</template>