<script setup>
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCartStore } from './stores/cart'
import WhatsAppButton from './components/WhatsAppButton.vue'

const { t, locale } = useI18n()
const cart = useCartStore()
const route = useRoute()

function setLocale(value) {
  locale.value = value
  localStorage.setItem('locale', value)
}

onMounted(() => {
  // lingua via URL (?lang=it) per gli hreflang
  const lang = new URLSearchParams(window.location.search).get('lang')
  if (['en', 'it', 'es'].includes(lang)) setLocale(lang)
  // coupon via URL (ex WooCommerce URL Coupons: link affiliato con QR)
  const coupon = new URLSearchParams(window.location.search).get('coupon')
  if (coupon) sessionStorage.setItem('coupon', coupon)
})
</script>

<template>
  <header class="header">
    <div class="container header__inner">
      <RouterLink to="/" class="header__logo">easy<span>Rent</span>24h</RouterLink>
      <nav class="header__nav">
        <RouterLink to="/">{{ t('nav.home') }}</RouterLink>
        <RouterLink to="/catalog">{{ t('nav.fleet') }}</RouterLink>
        <RouterLink to="/about">{{ t('nav.about') }}</RouterLink>
        <RouterLink to="/contact">{{ t('nav.contact') }}</RouterLink>
        <RouterLink to="/cart">{{ t('nav.cart') }} ({{ cart.items.length }})</RouterLink>
      </nav>
      <div class="header__locales">
        <button
          v-for="lang in ['en', 'it', 'es']"
          :key="lang"
          :class="{ active: locale === lang }"
          @click="setLocale(lang)"
        >
          {{ lang.toUpperCase() }}
        </button>
      </div>
    </div>
  </header>

  <main>
    <RouterView />
  </main>

  <footer class="footer">
    <div class="container">
      <p>easyRent24h — Scooter &amp; Car Rental, Amalfi Coast</p>
    </div>
  </footer>

  <WhatsAppButton />
</template>

<style scoped>
.header {
  background: #fff;
  box-shadow: var(--shadow);
  position: sticky;
  top: 0;
  z-index: 10;
}

.header__inner {
  display: flex;
  align-items: center;
  gap: 2rem;
  padding-top: 0.9rem;
  padding-bottom: 0.9rem;
}

.header__logo {
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--color-dark);
}

.header__logo span {
  color: var(--color-primary);
}

.header__nav {
  display: flex;
  gap: 1.2rem;
  flex: 1;
}

.header__nav a.router-link-active {
  color: var(--color-primary);
  font-weight: 600;
}

.header__locales {
  display: flex;
  gap: 0.3rem;
}

.header__locales button {
  border: 1px solid var(--color-border);
  background: #fff;
  border-radius: 6px;
  padding: 0.25rem 0.5rem;
  font-size: 0.8rem;
}

.header__locales button.active {
  background: var(--color-dark);
  color: #fff;
  border-color: var(--color-dark);
}

main {
  min-height: 70vh;
}

.footer {
  margin-top: 3rem;
  padding: 1.5rem 0;
  background: var(--color-dark);
  color: #fff;
  font-size: 0.9rem;
}
</style>
