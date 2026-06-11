<script setup>
import { useI18n } from 'vue-i18n'
import { useCartStore } from '../stores/cart'

const { t } = useI18n()
const cart = useCartStore()
</script>

<template>
  <section class="cart container">
    <h1>{{ t('cart.title') }}</h1>

    <p v-if="!cart.items.length">{{ t('cart.empty') }}</p>

    <div v-else class="cart__list">
      <div v-for="(item, index) in cart.items" :key="item.order_number" class="cart__item card">
        <div>
          <strong>{{ item.order_number }}</strong>
          <p>{{ item.vehicle }} — {{ item.start }} → {{ item.end }}</p>
          <p>
            {{ t('booking.total') }}: <strong>€{{ item.total }}</strong>
            <span v-if="item.deposit_amount"> (deposit €{{ item.deposit_amount }})</span>
          </p>
        </div>
        <button class="btn btn--outline" @click="cart.remove(index)">{{ t('cart.remove') }}</button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.cart {
  padding-top: 2rem;
}

.cart__item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.2rem;
  margin-bottom: 1rem;
}

.cart__item p {
  margin: 0.2rem 0;
  color: var(--color-muted);
}
</style>
