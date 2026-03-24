<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { regularAppRouteName } from '@/lib/session-routes'
import { updateActiveLeague } from '@/services/tenancy'
import { sessionState } from '@/state/session'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  (event: 'update:isOpen', value: boolean): void
}>()

const router = useRouter()
const isSubmitting = ref(false)

const activeLeagueId = computed(() => sessionState.tenancy?.active_league?.id ?? null)

function close(): void {
  emit('update:isOpen', false)
}

async function switchLeague(leagueId: number): Promise<void> {
  if (isSubmitting.value || leagueId === activeLeagueId.value) {
    return
  }

  isSubmitting.value = true

  try {
    await updateActiveLeague({ league_id: leagueId })
    close()
    await router.replace({ name: regularAppRouteName() })
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div v-if="props.isOpen" class="sheet-backdrop" @click.self="close">
      <section class="sheet-panel">
        <div class="sheet-handle" />

        <div class="sheet-copy">
          <p class="app-kicker sheet-kicker">
            {{ sessionState.tenancy?.active_league ? 'Cambiar liga' : 'Modo actual' }}
          </p>
          <h2 class="sheet-title">
            {{ sessionState.tenancy?.active_league?.name ?? 'Invitado' }}
          </h2>
          <p class="sheet-description">
            <template v-if="sessionState.tenancy?.guest_mode">
              Tu cuenta solo ve informacion personal hasta que un administrador te agregue a una liga.
            </template>
            <template v-else>
              Selecciona la liga activa que debe controlar toda la informacion de este entorno.
            </template>
          </p>
        </div>

        <div v-if="sessionState.tenancy?.available_leagues?.length" class="sheet-list">
          <button
            v-for="league in sessionState.tenancy.available_leagues"
            :key="league.id"
            :class="['sheet-option', { 'is-active': activeLeagueId === league.id }]"
            type="button"
            @click="switchLeague(league.id)"
          >
            <span class="sheet-option__name">{{ league.name }}</span>
            <span class="sheet-option__meta">
              {{ league.role_label }}{{ league.is_active ? '' : ' · acceso revocado' }}
            </span>
          </button>
        </div>

        <button class="sheet-close" type="button" @click="close">Cerrar</button>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.sheet-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  background: rgba(3, 7, 18, 0.72);
  padding: 16px;
}

.sheet-panel,
.sheet-copy,
.sheet-list {
  display: flex;
  flex-direction: column;
}

.sheet-panel {
  width: min(100%, 480px);
  gap: 20px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 28px 28px 20px 20px;
  background: #1a243a;
  padding: 14px 16px 20px;
  animation: sheet-enter 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.sheet-handle {
  width: 52px;
  height: 5px;
  margin: 0 auto;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.16);
}

.sheet-copy,
.sheet-list {
  gap: 10px;
}

.sheet-kicker {
  color: #e5b849;
}

.sheet-title,
.sheet-description {
  margin: 0;
}

.sheet-title {
  font-size: 24px;
  line-height: 1;
  font-weight: 700;
  color: #f8fafc;
}

.sheet-description {
  font-size: 13px;
  line-height: 1.6;
  color: #94a3b8;
}

.sheet-option,
.sheet-close {
  min-height: 52px;
  border-radius: 16px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  transition:
    transform 0.1s ease-out,
    opacity 0.1s ease-out,
    border-color 0.2s ease;
}

.sheet-option:active,
.sheet-close:active {
  transform: scale(0.97);
  opacity: 0.8;
}

.sheet-option {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  background: #0e1628;
  padding: 14px 16px;
  text-align: left;
}

.sheet-option.is-active {
  border-color: rgba(229, 184, 73, 0.28);
  background: rgba(229, 184, 73, 0.12);
}

.sheet-option__name {
  font-size: 15px;
  font-weight: 700;
  color: #f8fafc;
}

.sheet-option__meta {
  font-size: 12px;
  line-height: 1.4;
  color: #94a3b8;
}

.sheet-close {
  background: transparent;
  color: #f8fafc;
}

@keyframes sheet-enter {
  from {
    transform: translate3d(0, 32px, 0);
    opacity: 0;
  }

  to {
    transform: translate3d(0, 0, 0);
    opacity: 1;
  }
}
</style>
