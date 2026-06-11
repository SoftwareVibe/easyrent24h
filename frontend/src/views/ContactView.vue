<script setup>
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../api/client'

const { t, locale } = useI18n()

const form = reactive({ name: '', email: '', phone: '', message: '' })
const sent = ref(false)
const sending = ref(false)
const error = ref(null)

async function submit() {
  sending.value = true
  error.value = null
  try {
    await api.post('/contact', { ...form, locale: locale.value })
    sent.value = true
  } catch (e) {
    error.value = e.data?.message || e.message
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <section class="contact container">
    <h1>{{ t('contact.title') }}</h1>

    <div class="card contact__card">
      <template v-if="sent">
        <h2>✓ {{ t('contact.sent') }}</h2>
      </template>
      <form v-else @submit.prevent="submit">
        <div class="contact__grid">
          <div class="field">
            <label for="c-name">{{ t('contact.name') }}</label>
            <input id="c-name" v-model="form.name" required type="text" autocomplete="name" />
          </div>
          <div class="field">
            <label for="c-email">Email</label>
            <input id="c-email" v-model="form.email" required type="email" autocomplete="email" />
          </div>
          <div class="field">
            <label for="c-phone">{{ t('contact.phone') }}</label>
            <input id="c-phone" v-model="form.phone" type="tel" autocomplete="tel" />
          </div>
        </div>
        <div class="field">
          <label for="c-message">{{ t('contact.message') }}</label>
          <textarea id="c-message" v-model="form.message" required rows="6"></textarea>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <button class="btn" type="submit" :disabled="sending">{{ t('contact.send') }}</button>
      </form>
    </div>
  </section>
</template>

<style scoped>
.contact {
  padding-top: 2rem;
  max-width: 760px;
}

.contact__card {
  padding: 1.8rem;
}

.contact__grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1rem;
}

textarea {
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 0.6rem 0.8rem;
  font: inherit;
  width: 100%;
  resize: vertical;
}

.field {
  margin-bottom: 1rem;
}

.error {
  color: var(--color-primary-dark);
  font-weight: 600;
}

@media (max-width: 700px) {
  .contact__grid {
    grid-template-columns: 1fr;
  }
}
</style>
