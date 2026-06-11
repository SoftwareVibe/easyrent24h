<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '../api/client'

const { t } = useI18n()
const route = useRoute()

const order = ref(null)
const providers = ref([])
const loading = ref(true)
const paying = ref(false)
const error = ref(null)
const payType = ref('deposit')
const stripeState = ref(null) // { stripe, elements, clientSecret, paymentId }

const number = route.params.number

const remaining = computed(() =>
  order.value ? (Number(order.value.total) - Number(order.value.paid_total)).toFixed(2) : 0,
)
const depositPayable = computed(
  () => order.value && Number(order.value.paid_total) === 0 && Number(order.value.deposit_amount) > 0,
)
const isClosed = computed(() => ['paid', 'cancelled', 'refunded'].includes(order.value?.status))

onMounted(async () => {
  try {
    const [orderData, config] = await Promise.all([
      api.get(`/orders/${number}`),
      api.get('/payment-config'),
    ])
    order.value = orderData
    providers.value = config.providers
    if (!depositPayable.value) payType.value = 'balance'

    // ritorno da PayPal: cattura il pagamento
    if (route.query.paypal === 'return') {
      const paymentId = sessionStorage.getItem(`paypal-payment-${number}`)
      if (paymentId) {
        await api.post(`/orders/${number}/confirm`, { payment_id: Number(paymentId) })
        sessionStorage.removeItem(`paypal-payment-${number}`)
        order.value = await api.get(`/orders/${number}`)
      }
    }
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})

async function pay(provider) {
  paying.value = true
  error.value = null
  try {
    const result = await api.post(`/orders/${number}/pay`, {
      provider,
      type: payType.value,
    })

    if (provider === 'offline') {
      order.value = await api.get(`/orders/${number}`)
    } else if (provider === 'paypal' && result.approve_url) {
      sessionStorage.setItem(`paypal-payment-${number}`, result.payment_id)
      window.location.href = result.approve_url
    } else if (provider === 'stripe' && result.client_secret) {
      await mountStripe(result)
    }
  } catch (e) {
    error.value = e.data?.message || e.message
  } finally {
    paying.value = false
  }
}

async function mountStripe(result) {
  if (!window.Stripe) {
    await new Promise((resolve, reject) => {
      const script = document.createElement('script')
      script.src = 'https://js.stripe.com/v3/'
      script.onload = resolve
      script.onerror = reject
      document.head.appendChild(script)
    })
  }
  const stripe = window.Stripe(result.publishable_key)
  const elements = stripe.elements({ clientSecret: result.client_secret })
  stripeState.value = { stripe, elements, clientSecret: result.client_secret, paymentId: result.payment_id }
  requestAnimationFrame(() => {
    elements.create('payment').mount('#stripe-element')
  })
}

async function confirmStripe() {
  if (!stripeState.value) return
  paying.value = true
  error.value = null
  const { stripe, elements, paymentId } = stripeState.value
  const { error: stripeError } = await stripe.confirmPayment({
    elements,
    redirect: 'if_required',
  })
  if (stripeError) {
    error.value = stripeError.message
  } else {
    await api.post(`/orders/${number}/confirm`, { payment_id: paymentId })
    order.value = await api.get(`/orders/${number}`)
    stripeState.value = null
  }
  paying.value = false
}
</script>

<template>
  <section class="checkout container">
    <h1>{{ t('checkout.title') }}</h1>

    <p v-if="loading">…</p>
    <p v-else-if="error && !order" class="error">{{ error }}</p>

    <template v-else-if="order">
      <div class="checkout__grid">
        <div class="card checkout__summary">
          <h2>{{ order.number }}</h2>
          <p class="checkout__status" :data-status="order.status">{{ t(`checkout.status.${order.status}`) }}</p>

          <div v-for="(booking, i) in order.bookings" :key="i" class="checkout__booking">
            <strong>{{ booking.vehicle }}</strong>
            <p>
              {{ booking.pickup }} {{ booking.date_start }} {{ booking.time_start }} →
              {{ booking.dropoff || booking.pickup }} {{ booking.date_end }} {{ booking.time_end }}
              ({{ t('booking.days', booking.days) }})
            </p>
            <p v-if="booking.extras?.length" class="muted">
              {{ booking.extras.map((e) => `${e.name} x ${e.qty}`).join(', ') }}
            </p>
          </div>

          <table class="checkout__totals">
            <tbody>
              <tr v-if="Number(order.discount_total) > 0">
                <td>{{ t('checkout.discount') }} ({{ order.coupon_code }})</td>
                <td>−€{{ order.discount_total }}</td>
              </tr>
              <tr>
                <td>{{ t('booking.total') }}</td>
                <td><strong>€{{ order.total }}</strong></td>
              </tr>
              <tr>
                <td>{{ t('checkout.paid') }}</td>
                <td>€{{ order.paid_total }}</td>
              </tr>
              <tr v-if="!isClosed">
                <td>{{ t('checkout.remaining') }}</td>
                <td>€{{ remaining }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="card checkout__pay" v-if="!isClosed">
          <h3>{{ t('checkout.payTitle') }}</h3>

          <div class="checkout__type" v-if="depositPayable">
            <label>
              <input v-model="payType" type="radio" value="deposit" />
              {{ t('checkout.deposit') }} (€{{ order.deposit_amount }})
            </label>
            <label>
              <input v-model="payType" type="radio" value="full" />
              {{ t('checkout.fullAmount') }} (€{{ order.total }})
            </label>
          </div>

          <div v-if="stripeState" class="checkout__stripe">
            <div id="stripe-element"></div>
            <button class="btn" :disabled="paying" @click="confirmStripe">
              {{ t('checkout.confirmPayment') }}
            </button>
          </div>

          <div v-else class="checkout__providers">
            <button
              v-for="provider in providers"
              :key="provider.id"
              class="btn"
              :disabled="paying"
              @click="pay(provider.id)"
            >
              {{ t(`checkout.providers.${provider.id}`) }}
            </button>
          </div>

          <p v-if="error" class="error">{{ error }}</p>
        </div>

        <div class="card checkout__pay" v-else-if="order.status === 'paid'">
          <h3>✓ {{ t('checkout.completed') }}</h3>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.checkout {
  padding-top: 2rem;
}

.checkout__grid {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 1.5rem;
  align-items: start;
}

.checkout__summary,
.checkout__pay {
  padding: 1.5rem;
}

.checkout__status {
  display: inline-block;
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  background: var(--color-bg);
  border-radius: 999px;
  padding: 0.2rem 0.7rem;
}

.checkout__status[data-status='paid'],
.checkout__status[data-status='deposit_paid'] {
  background: #d8f3dc;
  color: #1b4332;
}

.checkout__booking {
  border-top: 1px solid var(--color-border);
  padding: 0.8rem 0;
}

.checkout__booking p {
  margin: 0.2rem 0;
}

.muted {
  color: var(--color-muted);
  font-size: 0.9rem;
}

.checkout__totals {
  width: 100%;
  border-collapse: collapse;
  margin-top: 0.5rem;
}

.checkout__totals td {
  padding: 0.35rem 0;
}

.checkout__totals td:last-child {
  text-align: right;
}

.checkout__type {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin-bottom: 1rem;
}

.checkout__providers {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.checkout__stripe {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.error {
  color: var(--color-primary-dark);
  font-weight: 600;
}

@media (max-width: 800px) {
  .checkout__grid {
    grid-template-columns: 1fr;
  }
}
</style>
