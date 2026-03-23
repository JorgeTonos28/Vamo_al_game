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
import {
  gridOutline,
  peopleOutline,
  settingsOutline,
  shieldCheckmarkOutline,
} from 'ionicons/icons'
</script>

<template>
  <IonPage>
    <IonTabs>
      <IonRouterOutlet />

      <template #bottom>
        <IonTabBar>
          <IonTabButton tab="dashboard" href="/command-center/dashboard">
            <IonIcon :icon="gridOutline" />
            <IonLabel>Panel</IonLabel>
          </IonTabButton>

          <IonTabButton tab="users" href="/command-center/users">
            <IonIcon :icon="peopleOutline" />
            <IonLabel>Usuarios</IonLabel>
          </IonTabButton>

          <IonTabButton tab="leagues" href="/command-center/leagues">
            <IonIcon :icon="shieldCheckmarkOutline" />
            <IonLabel>Ligas</IonLabel>
          </IonTabButton>

          <IonTabButton tab="settings" href="/command-center/settings/profile">
            <IonIcon :icon="settingsOutline" />
            <IonLabel>Ajustes</IonLabel>
          </IonTabButton>
        </IonTabBar>
      </template>
    </IonTabs>
  </IonPage>
</template>
