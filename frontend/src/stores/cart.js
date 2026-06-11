import { defineStore } from 'pinia'

const STORAGE_KEY = 'easyrent-cart'

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'),
  }),

  actions: {
    add(order) {
      this.items.push(order)
      this.persist()
    },

    remove(index) {
      this.items.splice(index, 1)
      this.persist()
    },

    persist() {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(this.items))
    },
  },
})
