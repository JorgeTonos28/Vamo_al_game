<script setup lang="ts">
import { IonContent, IonPage } from '@ionic/vue'
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'

const route = useRoute()

const moduleLabel = computed(() => {
  return String(route.params.module ?? 'módulo')
    .replace(/-/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
})
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <div class="mobile-stack">
          <MobileAppTopbar
            :title="moduleLabel"
            description="Este módulo ya forma parte del shell de la liga, pero todavía no está listo."
          />

          <section class="app-surface placeholder-card">
            <p class="app-kicker placeholder-card__kicker">{{ moduleLabel }}</p>
            <h2 class="placeholder-card__title">Módulo aún en construcción...</h2>
            <p class="placeholder-card__copy">Vuelve más tarde para encontrarlo conectado a la misma lógica operativa de legacy.</p>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.placeholder-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-height: 320px;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.placeholder-card__kicker {
  color: #e5b849;
}

.placeholder-card__title,
.placeholder-card__copy {
  margin: 0;
}

.placeholder-card__title {
  font-size: 32px;
  line-height: 0.95;
  font-family: var(--font-display), ui-sans-serif, sans-serif;
  text-transform: uppercase;
  color: #f8fafc;
}

.placeholder-card__copy {
  max-width: 280px;
  font-size: 13px;
  line-height: 1.6;
  color: #94a3b8;
}
</style>
