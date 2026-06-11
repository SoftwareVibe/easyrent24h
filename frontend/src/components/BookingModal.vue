<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../api/client'
import { useCartStore } from '../stores/cart'

const { t, locale } = useI18n()
const cart = useCartStore()

const props = defineProps({
  vehicle: { type: Object, required: true },
  initial: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close'])

const form = reactive({
  start: props.initial.start || '',
  end: props.initial.end || '',
  pick_up: props.initial.pickup || props.vehicle.pickup_locations[0]?.id || '',
  drop_off: props.vehicle.dropoff_locations[0]?.id || '',
  time_start: '',
  time_end: '',
  quantity: 1,
  extras: {},
})

const customer = reactive({ name: '', email: '', phone: '' })

const quote = ref(null)
const quoting = ref(false)
const submitting = ref(false)
const confirmation = ref(null)
const errorMessage = ref(null)

const canQuote = computed(() => form.start && form.end && form.pick_up)
const canSubmit = computed(
  () =>
    quote.value?.available &&
    form.time_start &&
    form.time_end &&
    customer.name &&
    customer.email,
)

let debounce = null
watch(
  () => [form.start, form.end, form.pick_up, form.drop_off, form.time_start, form.time_end, form.quantity, JSON.stringify(form.extras)],
  () => {
    if (!canQuote.value) return
    clearTimeout(debounce)
    debounce = setTimeout(refreshQuote, 250)
  },
  { immediate: true },
)

async function refreshQuote() {
  quoting.value = true
  errorMessage.value = null
  try {
    quote.value = await api.post('/quote', {
      vehicle_id: props.vehicle.id,
      start: form.start,
      end: form.end,
      pick_up: form.pick_up || null,
      drop_off: form.drop_off || null,
      quantity: form.quantity,
      extras: form.extras,
      time_start: form.time_start || null,
      time_end: form.time_end || null,
    })
    if (form.time_start && !quote.value.start_slots.includes(form.time_start)) {
      form.time_start = ''
    }
    if (form.time_end && !quote.value.end_slots.includes(form.time_end)) {
      form.time_end = ''
    }
  } catch (e) {
    quote.value = null
    errorMessage.value = e.message
  } finally {
    quoting.value = false
  }
}

async function submit() {
  submitting.value = true
  errorMessage.value = null
  try {
    const order = await api.post('/bookings', {
      vehicle_id: props.vehicle.id,
      start: form.start,
      end: form.end,
      pick_up: form.pick_up,
      drop_off: form.drop_off || null,
      time_start: form.time_start,
      time_end: form.time_end,
      quantity: form.quantity,
      extras: form.extras,
      customer: { ...customer },
      locale: locale.value,
    })
    confirmation.value = order
    cart.add({
      ...order,
      vehicle: props.vehicle.name,
      start: form.start,
      end: form.end,
    })
  } catch (e) {
    errorMessage.value = e.data?.message || e.message
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <div class="modal card">
      <button class="modal__close" @click="emit('close')">×</button>

      <template v-if="confirmation">
        <h2>✓ {{ confirmation.order_number }}</h2>
        <p>{{ vehicle.name }} — {{ form.start }} → {{ form.end }}</p>
        <p>
          <strong>{{ t('booking.total') }}: €{{ confirmation.total }}</strong>
          <span v-if="confirmation.deposit_amount"> (deposit €{{ confirmation.deposit_amount }})</span>
        </p>
        <button class="btn" @click="emit('close')">OK</button>
      </template>

      <template v-else>
        <h2>{{ vehicle.name }}</h2>

        <div class="grid">
          <div class="field">
            <label>{{ t('booking.start') }}</label>
            <input v-model="form.start" type="date" />
          </div>
          <div class="field">
            <label>{{ t('booking.end') }}</label>
            <input v-model="form.end" type="date" :min="form.start" />
          </div>
          <div class="field">
            <label>{{ t('booking.pickup') }}</label>
            <select v-model="form.pick_up">
              <option v-for="l in vehicle.pickup_locations" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
          </div>
          <div class="field">
            <label>{{ t('booking.dropoff') }}</label>
            <select v-model="form.drop_off">
              <option v-for="l in vehicle.dropoff_locations" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
          </div>
          <div class="field">
            <label>{{ t('booking.startTime') }}</label>
            <select v-model="form.time_start" :disabled="!quote?.start_slots?.length">
              <option value="">—</option>
              <option v-for="slot in quote?.start_slots || []" :key="slot" :value="slot">{{ slot }}</option>
            </select>
          </div>
          <div class="field">
            <label>{{ t('booking.endTime') }}</label>
            <select v-model="form.time_end" :disabled="!quote?.end_slots?.length">
              <option value="">—</option>
              <option v-for="slot in quote?.end_slots || []" :key="slot" :value="slot">{{ slot }}</option>
            </select>
          </div>
          <div class="field" v-if="vehicle.stock > 1">
            <label>{{ t('booking.quantity') }}</label>
            <input v-model.number="form.quantity" type="number" min="1" :max="vehicle.stock" />
          </div>
        </div>

        <div v-if="quote?.extras?.length" class="extras">
          <h3>{{ t('booking.extras') }}</h3>
          <div v-for="extra in quote.extras" :key="extra.id" class="extras__row">
            <label>
              <input
                v-if="extra.max_qty === 1"
                type="checkbox"
                :checked="!!form.extras[extra.id]"
                @change="form.extras[extra.id] = $event.target.checked ? 1 : 0"
              />
              {{ extra.name }} — €{{ extra.effective_price }}
              <del v-if="extra.effective_price !== extra.list_price">€{{ extra.list_price }}</del>
            </label>
            <input
              v-if="extra.max_qty > 1"
              v-model.number="form.extras[extra.id]"
              type="number"
              min="0"
              :max="extra.max_qty"
            />
          </div>
        </div>

        <p v-if="quote && !quote.available" class="error">{{ quote.message || t('booking.unavailable') }}</p>
        <p v-else-if="errorMessage" class="error">{{ errorMessage }}</p>

        <div v-if="quote?.available" class="total">
          <span>{{ t('booking.days', quote.days) }}</span>
          <strong>{{ t('booking.total') }}: €{{ quote.total }}</strong>
        </div>

        <div class="grid grid--customer" v-if="quote?.available">
          <div class="field">
            <label>Name</label>
            <input v-model="customer.name" type="text" autocomplete="name" />
          </div>
          <div class="field">
            <label>Email</label>
            <input v-model="customer.email" type="email" autocomplete="email" />
          </div>
          <div class="field">
            <label>Phone</label>
            <input v-model="customer.phone" type="tel" autocomplete="tel" />
          </div>
        </div>

        <button class="btn modal__submit" :disabled="!canSubmit || submitting || quoting" @click="submit">
          {{ t('booking.addToCart') }}
        </button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
  padding: 1rem;
}

.modal {
  width: min(640px, 100%);
  max-height: 90vh;
  overflow-y: auto;
  padding: 1.8rem;
  position: relative;
}

.modal__close {
  position: absolute;
  top: 0.6rem;
  right: 0.9rem;
  border: none;
  background: none;
  font-size: 1.6rem;
  color: var(--color-muted);
}

.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.9rem;
  margin-top: 1rem;
}

.grid--customer {
  grid-template-columns: 1fr 1fr 1fr;
}

.extras {
  margin-top: 1.2rem;
}

.extras h3 {
  margin: 0 0 0.5rem;
  font-size: 1.05rem;
}

.extras__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.3rem 0;
}

.extras__row del {
  color: var(--color-muted);
  margin-left: 0.4rem;
}

.extras__row input[type='number'] {
  width: 70px;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  padding: 0.3rem;
}

.total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1.2rem;
  padding: 0.9rem;
  background: var(--color-bg);
  border-radius: var(--radius);
}

.total strong {
  font-size: 1.2rem;
  color: var(--color-dark);
}

.error {
  color: var(--color-primary-dark);
  font-weight: 600;
}

.modal__submit {
  margin-top: 1.2rem;
  width: 100%;
}

@media (max-width: 600px) {
  .grid,
  .grid--customer {
    grid-template-columns: 1fr;
  }
}
</style>
