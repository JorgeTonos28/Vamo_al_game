<script setup lang="ts">
import {
    IonButton,
    IonContent,
    IonInput,
    IonItem,
    IonLabel,
    IonPage,
    IonRefresher,
    IonRefresherContent,
    IonText,
    onIonViewWillEnter,
} from '@ionic/vue';
import type { AxiosError } from 'axios';
import { reactive, ref } from 'vue';
import MobileAppTopbar from '@/components/MobileAppTopbar.vue';
import {
    createCommandCenterLeague,
    fetchCommandCenterLeagues,
    renameCommandCenterLeague,
    toggleCommandCenterLeague,
} from '@/services/command-center';
import type {
    CommandCenterCreateLeaguePayload,
    CommandCenterLeague,
    ErrorResponse,
} from '@/types/api';

const form = reactive<CommandCenterCreateLeaguePayload>({
    name: '',
    emoji: null,
});
const leagues = ref<CommandCenterLeague[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const activeRequestLeagueId = ref<number | null>(null);
const renameLeagueId = ref<number | null>(null);
const renameName = ref('');
const successMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

function sortLeagues(entries: CommandCenterLeague[]): CommandCenterLeague[] {
    return [...entries].sort((left, right) => {
        if (left.is_active !== right.is_active) {
            return left.is_active ? -1 : 1;
        }

        return left.name.localeCompare(right.name, 'es');
    });
}

async function loadLeagues(): Promise<void> {
    isLoading.value = true;

    try {
        const response = await fetchCommandCenterLeagues();
        leagues.value = sortLeagues(response.leagues);
    } finally {
        isLoading.value = false;
    }
}

async function submit(): Promise<void> {
    if (!form.name.trim()) {
        return;
    }

    isSubmitting.value = true;
    successMessage.value = null;
    errorMessage.value = null;

    try {
        const response = await createCommandCenterLeague({
            name: form.name,
            emoji: form.emoji?.trim() || null,
        });

        leagues.value = sortLeagues([response.league, ...leagues.value]);
        form.name = '';
        form.emoji = null;
        successMessage.value = 'Liga creada correctamente.';
    } catch (error) {
        const response = (error as AxiosError<ErrorResponse>).response?.data;
        const validationErrors = response?.errors as
            | Record<string, string[]>
            | undefined;
        errorMessage.value =
            validationErrors?.name?.[0] ??
            response?.message ??
            'No fue posible crear la liga.';
    } finally {
        isSubmitting.value = false;
    }
}

async function toggleLeague(leagueId: number): Promise<void> {
    activeRequestLeagueId.value = leagueId;

    try {
        const response = await toggleCommandCenterLeague(leagueId);
        leagues.value = sortLeagues(
            leagues.value.map((league) =>
                league.id === leagueId ? response.league : league,
            ),
        );
    } finally {
        activeRequestLeagueId.value = null;
    }
}

function openRename(league: CommandCenterLeague): void {
    renameLeagueId.value = league.id;
    renameName.value = league.name;
    errorMessage.value = null;
}

async function submitRename(): Promise<void> {
    if (!renameLeagueId.value || !renameName.value.trim()) {
        return;
    }

    try {
        const response = await renameCommandCenterLeague(
            renameLeagueId.value,
            renameName.value.trim(),
        );
        leagues.value = sortLeagues(
            leagues.value.map((league) =>
                league.id === renameLeagueId.value ? response.league : league,
            ),
        );
        successMessage.value = 'Nombre de liga actualizado.';
        renameLeagueId.value = null;
        renameName.value = '';
    } catch (error) {
        const response = (error as AxiosError<ErrorResponse>).response?.data;
        const validationErrors = response?.errors as
            | Record<string, string[]>
            | undefined;
        errorMessage.value =
            validationErrors?.name?.[0] ??
            response?.message ??
            'No fue posible actualizar la liga.';
    }
}

async function handleRefresh(event: CustomEvent): Promise<void> {
    try {
        await loadLeagues();
    } finally {
        await (event.target as HTMLIonRefresherElement).complete();
    }
}

onIonViewWillEnter(loadLeagues);
</script>

<template>
    <IonPage>
        <IonContent :fullscreen="true">
            <template v-slot:fixed>
<IonRefresher slot="fixed" @ionRefresh="handleRefresh">
                <IonRefresherContent
                    pulling-text="Desliza para refrescar"
                    refreshing-spinner="crescent"
                />
            </IonRefresher>
</template>

            <div class="mobile-shell">
                <div class="mobile-stack">
                    <MobileAppTopbar
                        command-center
                        title="Ligas"
                        description="Crea ligas nuevas, renómbralas y controla si mantienen acceso operativo dentro del sistema."
                    />

                    <section class="app-surface section-stack">
                        <p class="app-kicker section-kicker">Nueva liga</p>
                        <IonText v-if="successMessage" color="success"
                            ><p class="feedback-text">
                                {{ successMessage }}
                            </p></IonText
                        >
                        <IonText v-if="errorMessage" color="danger"
                            ><p class="feedback-text">
                                {{ errorMessage }}
                            </p></IonText
                        >

                        <div class="field-group">
                            <IonLabel position="stacked"
                                >Nombre de la liga</IonLabel
                            >
                            <IonItem lines="none">
                                <IonInput
                                    v-model="form.name"
                                    :maxlength="120"
                                    placeholder="Liga Aurora"
                                />
                            </IonItem>
                        </div>

                        <div class="field-group">
                            <IonLabel position="stacked">Emoji</IonLabel>
                            <IonItem lines="none">
                                <IonInput
                                    v-model="form.emoji"
                                    :maxlength="16"
                                    placeholder="⚽"
                                />
                            </IonItem>
                        </div>

                        <IonButton
                            :disabled="isSubmitting"
                            expand="block"
                            @click="submit"
                        >
                            {{ isSubmitting ? 'Creando...' : 'Crear liga' }}
                        </IonButton>
                    </section>

                    <section class="app-surface section-stack">
                        <p v-if="isLoading" class="loading-copy">
                            Cargando ligas...
                        </p>

                        <article
                            v-for="league in leagues"
                            :key="league.id"
                            class="league-row"
                        >
                            <div class="league-row__copy">
                                <div class="league-row__title">
                                    <p class="league-row__name">
                                        {{
                                            league.emoji
                                                ? `${league.emoji} ${league.name}`
                                                : league.name
                                        }}
                                    </p>
                                    <span
                                        :class="[
                                            'status-chip',
                                            league.is_active
                                                ? 'status-chip--positive'
                                                : 'status-chip--negative',
                                        ]"
                                    >
                                        {{
                                            league.is_active
                                                ? 'Con acceso'
                                                : 'Acceso revocado'
                                        }}
                                    </span>
                                </div>
                                <p class="league-row__meta">
                                    {{
                                        league.admins
                                            .map((admin) => admin.name)
                                            .filter(Boolean)
                                            .join(', ') ||
                                        'Sin administrador asignado'
                                    }}
                                </p>
                            </div>

                            <div class="league-row__stats">
                                <div class="stat-box">
                                    <p class="app-kicker">Slug</p>
                                    <p>{{ league.slug }}</p>
                                </div>
                                <div class="stat-box">
                                    <p class="app-kicker">Miembros</p>
                                    <p>{{ league.members_count }}</p>
                                </div>
                            </div>

                            <IonButton
                                fill="outline"
                                expand="block"
                                @click="openRename(league)"
                            >
                                Renombrar
                            </IonButton>

                            <IonButton
                                :color="league.is_active ? 'danger' : 'primary'"
                                :disabled="activeRequestLeagueId === league.id"
                                expand="block"
                                @click="toggleLeague(league.id)"
                            >
                                {{
                                    activeRequestLeagueId === league.id
                                        ? 'Actualizando...'
                                        : league.is_active
                                          ? 'Revocar acceso'
                                          : 'Restaurar acceso'
                                }}
                            </IonButton>
                        </article>
                    </section>
                </div>
            </div>

            <div
                v-if="renameLeagueId !== null"
                class="overlay"
                @click.self="renameLeagueId = null"
            >
                <section class="overlay__panel">
                    <p class="app-kicker section-kicker">Renombrar liga</p>
                    <div class="field-group">
                        <IonLabel position="stacked">Nuevo nombre</IonLabel>
                        <IonItem lines="none">
                            <IonInput
                                v-model="renameName"
                                :maxlength="120"
                                placeholder="Liga Aurora"
                            />
                        </IonItem>
                    </div>
                    <div class="overlay__actions">
                        <IonButton fill="outline" @click="renameLeagueId = null"
                            >Cancelar</IonButton
                        >
                        <IonButton @click="submitRename">Guardar</IonButton>
                    </div>
                </section>
            </div>
        </IonContent>
    </IonPage>
</template>

<style scoped>
.section-stack,
.field-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.section-kicker {
    color: #e5b849;
}

.feedback-text,
.loading-copy,
.league-row__meta {
    margin: 0;
    font-size: 13px;
    line-height: 1.6;
    color: #94a3b8;
}

.league-row {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.league-row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}

.league-row__copy,
.league-row__stats {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.league-row__title {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.league-row__name,
.stat-box p:last-child {
    margin: 0;
    color: #f8fafc;
}

.league-row__name {
    font-size: 17px;
    font-weight: 700;
}

.status-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 7px 11px;
    font-size: 12px;
    font-weight: 700;
}

.status-chip--positive {
    background: rgba(74, 222, 128, 0.12);
    color: #4ade80;
}

.status-chip--negative {
    background: rgba(248, 113, 113, 0.12);
    color: #fca5a5;
}

.league-row__stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.stat-box {
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 16px;
    background: #0e1628;
    padding: 14px;
}

.stat-box p:last-child {
    margin-top: 8px;
}

.overlay {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    background: rgba(3, 7, 18, 0.72);
    padding: 16px;
}

.overlay__panel,
.overlay__actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.overlay__panel {
    width: min(100%, 480px);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 28px 28px 20px 20px;
    background: #1a243a;
    padding: 18px 16px 20px;
}

.overlay__actions :deep(.button-native) {
    border-radius: 12px;
}
</style>
