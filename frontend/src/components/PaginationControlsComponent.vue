<script setup>
import Paginate from 'vuejs-paginate-next'

defineProps({
  pageCount: { type: Number, required: true },
})

const emit = defineEmits(['page-change'])
</script>

<template>
  <nav class="mt-4" aria-label="Pagination">
    <Paginate
      :page-count="pageCount"
      :click-handler="(page) => emit('page-change', page)"
      :prev-text="'Prev'"
      :next-text="'Next'"
      :container-class="'pagination justify-content-center'"
      :page-class="'page-item'"
      :page-link-class="'page-link'"
      :prev-class="'page-item'"
      :prev-link-class="'page-link'"
      :next-class="'page-item'"
      :next-link-class="'page-link'"
      :active-class="'active'"
    />
  </nav>
</template>