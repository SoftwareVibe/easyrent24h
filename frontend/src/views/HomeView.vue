<script setup>
import { onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCatalogStore } from '../stores/catalog'

const { t } = useI18n()
const router = useRouter()
const catalog = useCatalogStore()

const form = reactive({
  pickup: '',
  type: '',
  start: '',
  end: '',
})

onMounted(() => catalog.loadReferenceData())

function search() {
  router.push({
    name: 'catalog',
    query: {
      ...(form.pickup && { pickup: form.pickup }),
      ...(form.type && { type: form.type }),
      ...(form.start && { start: form.start }),
      ...(form.end && { end: form.end }),
    },
  })
}
</script>

<template>
  <section class="hero">
    <div class="container">
      <h1>easyRent24h</h1>
      <p>Scooter, Vespa &amp; car rental — Amalfi Coast</p>

      <form class="hero__form card" @submit.prevent="search">
        <div class="field">
          <label>{{ t('search.pickup') }}</label>
          <select v-model="form.pickup">
            <option value="">—</option>
            <option v-for="location in catalog.locations" :key="location.id" :value="location.id">
              {{ location.name }}
            </option>
          </select>
        </div>
        <div class="field">
          <label>{{ t('search.type') }}</label>
          <select v-model="form.type">
            <option value="">—</option>
            <option v-for="type in catalog.vehicleTypes" :key="type.id" :value="type.slug">
              {{ type.name }}
            </option>
          </select>
        </div>
        <div class="field">
          <label>{{ t('booking.start') }}</label>
          <input v-model="form.start" type="date" />
        </div>
        <div class="field">
          <label>{{ t('booking.end') }}</label>
          <input v-model="form.end" type="date" :min="form.start" />
        </div>
        <button class="btn" type="submit">{{ t('search.submit') }}</button>
      </form>
    </div>
  </section>
</template>

<style scoped>
.hero {
  padding: 5rem 0;
  background: linear-gradient(135deg, var(--color-dark), #457b9d);
  color: #fff;
  text-align: center;
}

.hero h1 {
  font-size: 3rem;
  margin: 0 0 0.5rem;
}

.hero p {
  font-size: 1.2rem;
  opacity: 0.9;
}

.hero__form {
  margin-top: 2.5rem;
  padding: 1.5rem;
  display: grid;
  grid-template-columns: repeat(4, 1fr) auto;
  gap: 1rem;
  align-items: end;
  color: var(--color-text);
  text-align: left;
}

@media (max-width: 900px) {
  .hero__form {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
