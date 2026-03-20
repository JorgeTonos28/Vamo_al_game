<script setup lang="ts">
import {
  IonIcon,
  IonLabel,
  IonRouterOutlet,
  IonTabBar,
  IonTabButton,
  IonTabs,
} from '@ionic/vue'
import { homeOutline, personCircleOutline, pulseOutline } from 'ionicons/icons'
</script>

<template>
  <IonTabs>
    <IonRouterOutlet />

    <IonTabBar slot="bottom">
      <IonTabButton tab="home" href="/tabs/home">
        <IonIcon :icon="homeOutline" />
        <IonLabel>Home</IonLabel>
      </IonTabButton>

      <IonTabButton tab="profile" href="/tabs/profile">
        <IonIcon :icon="personCircleOutline" />
        <IonLabel>Perfil</IonLabel>
      </IonTabButton>

      <IonTabButton tab="health" href="/tabs/health">
        <IonIcon :icon="pulseOutline" />
        <IonLabel>Health</IonLabel>
      </IonTabButton>
    </IonTabBar>
  </IonTabs>
</template>
