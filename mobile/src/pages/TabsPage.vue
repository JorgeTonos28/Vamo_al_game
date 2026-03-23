<script setup lang="ts">
import {
  IonIcon,
  IonLabel,
  IonPage,
  IonRouterOutlet,
  IonTabBar,
  IonTabButton,
  IonTabs,
} from '@ionic/vue'
import { gridOutline, settingsOutline } from 'ionicons/icons'
</script>

<template>
  <IonPage>
    <IonTabs>
      <IonRouterOutlet />

      <template v-slot:bottom>
        <IonTabBar>
          <IonTabButton tab="home" href="/app/home">
            <IonIcon :icon="gridOutline" />
            <IonLabel>Panel</IonLabel>
          </IonTabButton>

          <IonTabButton tab="settings" href="/app/settings/profile">
            <IonIcon :icon="settingsOutline" />
            <IonLabel>Ajustes</IonLabel>
          </IonTabButton>
        </IonTabBar>
      </template>
    </IonTabs>
  </IonPage>
</template>
