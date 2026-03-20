<script setup lang="ts">
import { computed } from 'vue'
import { IonContent, IonPage } from '@ionic/vue'
import { sessionState } from '@/state/session'

const userName = computed(() => sessionState.user?.name ?? 'Equipo')
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <div class="mobile-shell">
        <div class="mobile-stack">
          <section class="app-surface home-hero">
            <p class="app-kicker home-status">Mobile shell activo</p>
            <h1 class="app-display home-title">{{ userName }}</h1>
            <p class="app-body-copy">
              Esta base ya esta conectada al backend reusable. Lo nuevo debe salir primero en API y luego reflejarse aqui y en la web.
            </p>
          </section>

          <section class="home-grid">
            <article class="app-surface">
              <p class="app-kicker">Contrato</p>
              <p class="home-value">OpenAPI</p>
              <p class="app-body-copy">Tipos consumidos desde `packages/contracts`.</p>
            </article>

            <article class="app-surface">
              <p class="app-kicker">UI</p>
              <p class="home-value">Compartida</p>
              <p class="app-body-copy">Mismos tokens visuales que la web actual.</p>
            </article>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.home-hero {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.home-status {
  color: #e5b849;
}

.home-title {
  margin: 0;
  font-size: 52px;
  line-height: 0.9;
}

.home-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.home-value {
  margin: 0;
  font-size: 28px;
  line-height: 1;
  font-weight: 700;
  color: #f8fafc;
}
</style>
