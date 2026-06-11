<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '../stores/catalog'
import VehicleCard from '../components/VehicleCard.vue'
import BookingModal from '../components/BookingModal.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const catalog = useCatalogStore()

const filters = reactive({
  pickup: route.query.pickup || '',
  type: route.query.type || '',
  sort: route.query.sort || '',
})

const selectedVehicle = ref(null)

onMounted(async () => {
  await catalog.loadReferenceData()
  await catalog.loadVehicles(filters)
})

watch(filters, async () => {
  router.replace({ query: { ...route.query, ...filters } })
  await catalog.loadVehicles(filters)
})

const bookingInitial = computed(() => ({
  start: route.query.start || '',
  end: route.query.end || '',
  pickup: filters.pickup ? Number(filters.pickup) : '',
}))
</script>

<template>
  <section class="catalog container">
    <h1>{{ t('catalog.title') }}</h1>

    <div class="catalog__bar card">
      <div class="field">
        <label>{{ t('search.pickup') }}</label>
        <select v-model="filters.pickup">
          <option value="">—</option>
          <option v-for="location in catalog.locations" :key="location.id" :value="String(location.id)">
            {{ location.name }}
          </option>
        </select>
      </div>
      <div class="field">
        <label>{{ t('search.type') }}</label>
        <select v-model="filters.type">
          <option value="">—</option>
          <option v-for="type in catalog.vehicleTypes" :key="type.id" :value="type.slug">
            {{ type.name }}
          </option>
        </select>
      </div>
      <div class="field">
        <label>Sort</label>
        <select v-model="filters.sort">
          <option value="">Default</option>
          <option value="low-price">€ ↑</option>
          <option value="high-price">€ ↓</option>
        </select>
      </div>
      <p class="catalog__count">
        {{ t('catalog.results', { count: catalog.vehicles.length }) }}
      </p>
    </div>

    <p v-if="catalog.loading">…</p>
    <p v-else-if="catalog.error" class="error">{{ catalog.error }}</p>
    <p v-else-if="!catalog.vehicles.length">{{ t('catalog.empty') }}</p>

    <div class="catalog__grid">
      <VehicleCard
        v-for="vehicle in catalog.vehicles"
        :key="vehicle.id"
        :vehicle="vehicle"
        @book="selectedVehicle = vehicle"
      />
    </div>

    <BookingModal
      v-if="selectedVehicle"
      :vehicle="selectedVehicle"
      :initial="bookingInitial"
      @close="selectedVehicle = null"
    />
  </section>
</template>

<style scoped>
.catalog {
  padding-top: 2rem;
}

.catalog__bar {
  display: grid;
  grid-template-columns: repeat(3, minmax(160px, 220px)) 1fr;
  gap: 1rem;
  align-items: end;
  padding: 1.2rem;
  margin-bottom: 1.5rem;
}

.catalog__count {
  text-align: right;
  color: var(--color-muted);
  margin: 0;
}

.catalog__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1.2rem;
}

.error {
  color: var(--color-primary-dark);
}

@media (max-width: 800px) {
  .catalog__bar {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
