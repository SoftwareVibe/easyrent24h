<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

defineProps({
  vehicle: { type: Object, required: true },
  eager: { type: Boolean, default: false },
})

defineEmits(['book'])
</script>

<template>
  <article class="vehicle card">
    <div class="vehicle__badge" v-if="vehicle.sale_badge">{{ vehicle.sale_badge }}</div>
    <div class="vehicle__media">
      <img
        v-if="vehicle.image"
        :src="vehicle.image"
        :alt="vehicle.name"
        :loading="eager ? 'eager' : 'lazy'"
        :fetchpriority="eager ? 'high' : 'auto'"
        decoding="async"
        width="640"
        height="420"
      />
    </div>
    <div class="vehicle__body">
      <h2>{{ vehicle.name }}</h2>
      <p v-if="vehicle.subheader" class="vehicle__subheader">{{ vehicle.subheader }}</p>
      <ul class="vehicle__features">
        <li v-for="feature in vehicle.features.slice(0, 4)" :key="feature.id">{{ feature.name }}</li>
      </ul>
    </div>
    <div class="vehicle__footer">
      <div class="vehicle__price">
        <template v-if="vehicle.price_on_request || !vehicle.base_price">
          <strong>{{ vehicle.custom_price_text || t('catalog.priceOnRequest') }}</strong>
        </template>
        <template v-else>
          <small>{{ t('catalog.from') }}</small>
          <strong>€{{ vehicle.base_price }}</strong>
          <small>{{ t('catalog.perDay') }}</small>
        </template>
      </div>
      <button class="btn" @click="$emit('book', vehicle)">{{ t('catalog.book') }}</button>
    </div>
  </article>
</template>

<style scoped>
.vehicle {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
}

.vehicle__badge {
  position: absolute;
  top: 0.8rem;
  right: 0.8rem;
  background: var(--color-primary);
  color: #fff;
  font-size: 0.75rem;
  font-weight: 700;
  border-radius: 6px;
  padding: 0.2rem 0.5rem;
}

.vehicle__media {
  height: 190px;
  background: #fff;
}

.vehicle__media img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

.vehicle__body {
  padding: 1.2rem 1.2rem 0;
  flex: 1;
}

.vehicle__body h2 {
  margin: 0;
  font-size: 1.25rem;
}

.vehicle__subheader {
  color: var(--color-muted);
  margin: 0.2rem 0 0;
  font-size: 0.9rem;
}

.vehicle__features {
  list-style: none;
  padding: 0;
  margin: 0.8rem 0 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.vehicle__features li {
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: 999px;
  font-size: 0.75rem;
  padding: 0.15rem 0.6rem;
}

.vehicle__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.2rem;
}

.vehicle__price strong {
  font-size: 1.3rem;
  color: var(--color-dark);
}

.vehicle__price small {
  color: var(--color-muted);
}
</style>
