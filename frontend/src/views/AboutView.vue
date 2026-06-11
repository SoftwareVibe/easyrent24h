<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <section class="about container">
    <h1>{{ t('about.title') }}</h1>
    <div class="card about__card">
      <p>{{ t('about.p1') }}</p>
      <p>{{ t('about.p2') }}</p>
      <ul>
        <li>{{ t('about.li1') }}</li>
        <li>{{ t('about.li2') }}</li>
        <li>{{ t('about.li3') }}</li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.about {
  padding-top: 2rem;
  max-width: 860px;
}

.about__card {
  padding: 1.8rem;
  line-height: 1.7;
}
</style>
