<script setup lang="ts">
import {
  IonIcon,
  IonLabel,
  IonRouterOutlet,
  IonTabBar,
  IonTabButton,
  IonTabs,
} from '@ionic/vue'
import { gridOutline, pulseOutline, settingsOutline } from 'ionicons/icons'
</script>

<template>
  <IonTabs>
    <IonRouterOutlet />

    <template v-slot:bottom>
      <IonTabBar>
      <IonTabButton tab="home" href="/tabs/home">
        <IonIcon :icon="gridOutline" />
        <IonLabel>Panel</IonLabel>
      </IonTabButton>

      <IonTabButton tab="settings" href="/tabs/settings/profile">
        <IonIcon :icon="settingsOutline" />
        <IonLabel>Ajustes</IonLabel>
      </IonTabButton>

      <IonTabButton tab="health" href="/tabs/health">
        <IonIcon :icon="pulseOutline" />
        <IonLabel>Health</IonLabel>
      </IonTabButton>
      </IonTabBar>
    </template>
  </IonTabs>
</template>
