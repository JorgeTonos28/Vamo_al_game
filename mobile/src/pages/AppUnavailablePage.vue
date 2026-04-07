<script setup lang="ts">
import { IonContent, IonPage, IonRefresher, IonRefresherContent } from '@ionic/vue'
import MobileAppTopbar from '@/components/MobileAppTopbar.vue'
import { handleMobileRefresher } from '@/services/app-refresh'

async function handleRefresh(event: CustomEvent): Promise<void> {
  await handleMobileRefresher(event)
}
</script>

<template>
  <IonPage>
    <IonContent :fullscreen="true">
      <template #fixed>
        <IonRefresher @ionRefresh="handleRefresh">
          <IonRefresherContent pulling-text="Desliza para refrescar" refreshing-spinner="crescent" />
        </IonRefresher>
      </template>

      <div class="mobile-shell">
        <div class="mobile-stack">
          <MobileAppTopbar
            title="Acceso no disponible"
            description="La liga seleccionada ya no tiene acceso operativo. Puedes cambiar a otra liga desde el selector superior."
          />

          <section class="app-surface unavailable-card">
            <p class="app-kicker unavailable-kicker">Acceso restringido</p>
            <h2 class="unavailable-title">
              ¡Ups! Lo sentimos, ha ocurrido un problema accediendo a la app. Comuníquese con la administración para más detalles.
            </h2>
          </section>
        </div>
      </div>
    </IonContent>
  </IonPage>
</template>

<style scoped>
.unavailable-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  text-align: center;
}

.unavailable-kicker {
  color: #e5b849;
}

.unavailable-title {
  margin: 0;
  font-size: 24px;
  line-height: 1.5;
  font-weight: 700;
  color: #f8fafc;
}
</style>
