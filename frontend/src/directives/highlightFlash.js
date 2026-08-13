// v-highlight-flash="isTarget" adds a brief gold flash animation to an
// element when the bound value is true 
export const highlightFlash = {
  mounted(el, binding) {
    if (binding.value) {
      el.classList.add('highlight-flash')
      setTimeout(() => el.classList.remove('highlight-flash'), 2000)
    }
  },
  updated(el, binding) {
    if (binding.value && !binding.oldValue) {
      el.classList.add('highlight-flash')
      setTimeout(() => el.classList.remove('highlight-flash'), 2000)
    }
  },
}