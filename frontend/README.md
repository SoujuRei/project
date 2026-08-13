# Vue 3 + Vite

This template should help get you started developing with Vue 3 in Vite. The template uses Vue 3 `<script setup>` SFCs, check out the [script setup docs](https://v3.vuejs.org/api/sfc-script-setup.html#sfc-script-setup) to learn more.

Learn more about IDE Support for Vue in the [Vue Docs Scaling up Guide](https://vuejs.org/guide/scaling-up/tooling.html#ide-support).


# Tartarus Frontend

A Vue 3 + Vite frontend for the Tartarus log app.

## Setup

```bash
npm install
npm run dev
```

The development server will start at the Vite default address.

## Build for Production

```bash
npm run build
```

The build output will be generated in the `dist` folder.

## Notes

* The frontend expects the backend API to be available at the configured API base URL.
* For local development, make sure the backend is running and reachable.
* If deploying to Mercury, verify that the Vite base path matches the public folder path on the server.