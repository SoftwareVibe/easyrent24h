import { defineStore } from 'pinia'
import api from '../api/client'

export const useCatalogStore = defineStore('catalog', {
  state: () => ({
    locations: [],
    vehicleTypes: [],
    vehicles: [],
    loading: false,
    error: null,
  }),

  actions: {
    async loadReferenceData() {
      if (this.locations.length) return
      const [locations, types] = await Promise.all([
        api.get('/locations'),
        api.get('/vehicle-types'),
      ])
      this.locations = locations
      this.vehicleTypes = types
    },

    async loadVehicles(filters = {}) {
      this.loading = true
      this.error = null
      try {
        const params = new URLSearchParams()
        if (filters.pickup) params.set('pickup', filters.pickup)
        if (filters.type) params.set('type', filters.type)
        if (filters.sort) params.set('sort', filters.sort)
        const qs = params.toString()
        this.vehicles = await api.get(`/vehicles${qs ? `?${qs}` : ''}`)
      } catch (e) {
        this.error = e.message
        this.vehicles = []
      } finally {
        this.loading = false
      }
    },
  },
})
