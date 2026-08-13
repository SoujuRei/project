import { createRouter, createWebHashHistory } from 'vue-router'
import Home from '../views/HomeView.vue'
import About from '../views/AboutView.vue'
import News from '../views/NewsView.vue'
import Compendium from '../views/CompendiumView.vue'
import TartarusLog from '../views/TartarusLogView.vue'
import LogCreate from '../views/LogCreateView.vue'
import Login from '../views/LoginView.vue'
import Register from '../views/RegisterView.vue'

const router = createRouter({
  history: createWebHashHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'home', component: Home },
    { path: '/about', name: 'about', component: About },
    { path: '/news', name: 'news', component: News },
    { path: '/compendium', name: 'compendium', component: Compendium },
    { path: '/log', name: 'log', component: TartarusLog },
    { path: '/log/create', name: 'log-create', component: LogCreate },
    { path: '/log/edit/:id', name: 'log-edit', component: LogCreate },
    { path: '/login', name: 'login', component: Login },
    { path: '/register', name: 'register', component: Register },
  ],
})

export default router