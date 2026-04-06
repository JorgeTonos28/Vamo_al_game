<script setup lang="ts">
import {
    IonContent,
    IonPage,
    IonReorder,
    IonReorderGroup,
    IonRefresher,
    IonRefresherContent,
    onIonViewWillEnter,
} from '@ionic/vue';
import { computed, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import LeagueRosterSheet from '@/components/LeagueRosterSheet.vue';
import MobileAppTopbar from '@/components/MobileAppTopbar.vue';
import { extractApiErrors } from '@/services/api';
import { handleMobileRefresher } from '@/services/app-refresh';
import {
    addLeagueArrivalGuest,
    deleteLeagueArrivalGuest,
    fetchLeagueArrival,
    prepareLeagueArrival,
    reorderLeagueArrivalQueue,
    resetLeagueArrival,
    toggleLeagueArrivalPlayer,
    updateLeagueArrivalGuest,
} from '@/services/league';
import type { LeagueArrivalPayload } from '@/services/league';

const router = useRouter();
const payload = ref<LeagueArrivalPayload | null>(null);
const isLoading = ref(false);
const guestName = ref('');
const selectedPlayerId = ref<number | null>(null);
const prepareOpen = ref(false);
const resetConfirmOpen = ref(false);
const rosterOpen = ref(false);
const guestPayments = reactive<Record<number, boolean>>({});
const actionErrors = ref<string[]>([]);
const queuePreviewEntries = ref<LeagueArrivalPayload['queue_preview']['entries']>(
    [],
);
const canManageArrival = computed(
    () => payload.value?.role.can_manage ?? false,
);
const sortedPlayers = computed(() => payload.value?.players ?? []);
const liveArrivalLocked = computed(() =>
    ['prepared', 'in_progress'].includes(payload.value?.session.status ?? ''),
);
const prepareLocked = computed(() =>
    ['prepared', 'in_progress'].includes(payload.value?.session.status ?? ''),
);
const canReorderQueuePreview = computed(
    () => payload.value?.queue_preview.can_reorder ?? false,
);

watch(
    () => payload.value?.queue_preview.entries ?? [],
    (entries) => {
        queuePreviewEntries.value = entries.map((entry) => ({ ...entry }));
    },
    { immediate: true, deep: true },
);

const queuePreviewPositionById = computed(
    () => new Map(queuePreviewEntries.value.map((entry) => [entry.id, entry.position])),
);

function guestQueueLabel(guest: LeagueArrivalPayload['guests'][number]): string {
    const queuedPosition = queuePreviewPositionById.value.get(guest.id);

    if (queuedPosition !== undefined) {
        return `Cola #${queuedPosition}`;
    }

    return `Llegada #${guest.arrival_order}`;
}

function queuePreviewMeta(
    entry: LeagueArrivalPayload['queue_preview']['entries'][number],
): string {
    const labels = [`Cola #${entry.position}`];

    if (!entry.is_guest) {
        labels.push(`Llegada #${entry.arrival_order}`);
    }

    if (entry.preferred_position) {
        labels.push(entry.preferred_position);
    }

    return labels.join(' · ');
}

async function loadPage(): Promise<void> {
    isLoading.value = true;
    actionErrors.value = [];

    try {
        payload.value = await fetchLeagueArrival();
    } catch (error) {
        actionErrors.value = extractApiErrors(error);
    } finally {
        isLoading.value = false;
    }
}

async function handleRefresh(event: CustomEvent): Promise<void> {
    await handleMobileRefresher(event, loadPage);
}

onIonViewWillEnter(loadPage);

async function togglePlayer(playerId: number, paid?: boolean): Promise<void> {
    if (!canManageArrival.value) {
return;
}

    actionErrors.value = [];

    try {
        payload.value = await toggleLeagueArrivalPlayer(playerId, paid);
        selectedPlayerId.value = null;
    } catch (error) {
        actionErrors.value = extractApiErrors(error);
    }
}

async function addGuest(): Promise<void> {
    if (!canManageArrival.value || !guestName.value.trim()) {
return;
}

    actionErrors.value = [];

    try {
        payload.value = await addLeagueArrivalGuest(guestName.value);
        guestName.value = '';
    } catch (error) {
        actionErrors.value = extractApiErrors(error);
    }
}

async function toggleGuest(guestId: number, paid: boolean): Promise<void> {
    if (!canManageArrival.value) {
return;
}

    actionErrors.value = [];

    try {
        payload.value = await updateLeagueArrivalGuest(guestId, !paid);
    } catch (error) {
        actionErrors.value = extractApiErrors(error);
    }
}

async function removeGuest(guestId: number): Promise<void> {
    if (!canManageArrival.value) {
return;
}

    actionErrors.value = [];

    try {
        payload.value = await deleteLeagueArrivalGuest(guestId);
    } catch (error) {
        actionErrors.value = extractApiErrors(error);
    }
}

function openPrepare(): void {
    if (!canManageArrival.value || prepareLocked.value) {
return;
}

    actionErrors.value = [];
    (payload.value?.guests ?? []).forEach((guest) => {
        guestPayments[guest.id] = guest.guest_fee_paid;
    });
    prepareOpen.value = true;
}

async function prepareSession(): Promise<void> {
    if (!canManageArrival.value) {
return;
}

    actionErrors.value = [];

    try {
        payload.value = await prepareLeagueArrival(
            (payload.value?.guests ?? []).map((guest) => ({
                id: guest.id,
                paid: Boolean(guestPayments[guest.id]),
            })),
        );
        prepareOpen.value = false;
        await router.push({ name: 'league-game' });
    } catch (error) {
        actionErrors.value = extractApiErrors(error);
    }
}

async function resetSession(): Promise<void> {
    if (!canManageArrival.value) {
return;
}

    actionErrors.value = [];

    try {
        payload.value = await resetLeagueArrival();
        resetConfirmOpen.value = false;
    } catch (error) {
        actionErrors.value = extractApiErrors(error);
    }
}

function money(amountCents: number): string {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'DOP',
        maximumFractionDigits: 0,
    }).format(amountCents / 100);
}

async function reorderQueuePreview(
    event: CustomEvent<{ from: number; to: number; complete: () => void }>,
): Promise<void> {
    const updatedEntries = [...queuePreviewEntries.value];
    const from = event.detail.from;
    const to = event.detail.to;
    const [movedEntry] = updatedEntries.splice(from, 1);

    updatedEntries.splice(to, 0, movedEntry);
    queuePreviewEntries.value = updatedEntries.map((entry, index) => ({
        ...entry,
        position: index + 1,
    }));
    event.detail.complete();

    try {
        payload.value = await reorderLeagueArrivalQueue(
            queuePreviewEntries.value.map((entry) => entry.id),
        );
    } catch (error) {
        actionErrors.value = extractApiErrors(error);

        if (payload.value) {
            queuePreviewEntries.value = payload.value.queue_preview.entries.map(
                (entry) => ({ ...entry }),
            );
        }
    }
}
</script>

<template>
    <IonPage>
        <IonContent :fullscreen="true">
            <template v-slot:fixed>
<IonRefresher  @ionRefresh="handleRefresh">
                <IonRefresherContent
                    pulling-icon="refresh-circle"
                    pulling-text="Desliza para refrescar"
                    refreshing-spinner="crescent"
                    refreshing-text="Actualizando..."
                />
            </IonRefresher>
</template>

            <div class="mobile-shell">
                <div class="mobile-stack">
                    <MobileAppTopbar
                        :title="payload?.league.name ?? 'Llegada'"
                        :description="
                            payload?.cut.is_past_due
                                ? 'Solo mantienen prioridad quienes están al día.'
                                : 'Todos los miembros siguen con prioridad dentro del corte.'
                        "
                    />

                    <section class="app-surface summary-grid">
                        <article class="summary-card">
                            <p class="app-kicker">
                                {{
                                    canManageArrival
                                        ? 'Estado draft'
                                        : 'Corte activo'
                                }}
                            </p>
                            <p
                                :class="[
                                    'summary-card__value',
                                    canManageArrival &&
                                    !(
                                        payload?.session.counts.draft_ready ??
                                        false
                                    )
                                        ? 'summary-card__value--negative'
                                        : canManageArrival
                                          ? 'summary-card__value--positive'
                                          : '',
                                ]"
                            >
                                {{
                                    canManageArrival
                                        ? (payload?.session.counts
                                              .draft_ready ?? false)
                                            ? 'Draft listo'
                                            : 'Sin draft'
                                        : (payload?.cut.label ?? 'Cargando...')
                                }}
                            </p>
                        </article>
                        <article class="summary-card">
                            <p class="app-kicker">Miembros</p>
                            <p class="summary-card__value">
                                {{
                                    payload?.session.counts.arrived_members ??
                                    0
                                }}/{{
                                    payload?.session.counts.total_members ?? 0
                                }}
                            </p>
                        </article>
                        <article class="summary-card">
                            <p class="app-kicker">Invitados</p>
                            <p class="summary-card__value">
                                {{ payload?.session.counts.guests ?? 0 }}
                            </p>
                        </article>
                    </section>

                    <section
                        v-if="actionErrors.length > 0"
                        class="app-surface section-stack error-block"
                    >
                        <p class="app-kicker">Error</p>
                        <p
                            v-for="message in actionErrors"
                            :key="message"
                            class="body-copy body-copy--error"
                        >
                            {{ message }}
                        </p>
                    </section>

                    <section class="app-surface section-stack">
                        <div class="section-head">
                            <div class="section-head__copy">
                                <p class="app-kicker section-head__kicker">
                                    Miembros
                                </p>
                                <span class="section-head__badge">
                                    {{
                                        payload?.session.counts.arrived_members ??
                                        0
                                    }}/{{ payload?.session.counts.total_members ?? 0 }}
                                </span>
                            </div>
                            <div
                                v-if="canManageArrival"
                                class="section-head__actions"
                            >
                                <button
                                    v-if="payload?.roster_management.can_manage"
                                    class="action-button action-button--secondary"
                                    type="button"
                                    @click="rosterOpen = true"
                                >
                                    Miembros
                                </button>
                                <button
                                    class="action-button action-button--ghost"
                                    type="button"
                                    @click="resetConfirmOpen = true"
                                >
                                    Reiniciar llegada
                                </button>
                                <button
                                    class="action-button action-button--primary"
                                    :disabled="prepareLocked"
                                    type="button"
                                    @click="openPrepare"
                                >
                                    Iniciar
                                </button>
                            </div>
                        </div>
                        <p v-if="!canManageArrival" class="body-copy">
                            Modo solo lectura para miembros de la liga.
                        </p>
                        <p v-if="isLoading" class="body-copy">
                            Cargando llegada...
                        </p>
                        <button
                            v-for="player in sortedPlayers"
                            :key="player.id"
                            class="member-row"
                            :disabled="
                                !canManageArrival ||
                                (player.has_arrived && liveArrivalLocked)
                            "
                            type="button"
                            @click="
                                player.has_arrived
                                    ? liveArrivalLocked
                                        ? null
                                        : togglePlayer(player.id)
                                    : player.current_cut_paid
                                      ? togglePlayer(player.id)
                                      : (selectedPlayerId = player.id)
                            "
                        >
                            <div>
                                <div class="member-row__header">
                                    <p class="member-row__name">
                                        {{ player.name }}
                                    </p>
                                    <span class="member-row__meta-chip">
                                        #{{ player.jersey_number ?? 'S/N' }}
                                    </span>
                                </div>
                                <p class="member-row__copy">
                                    {{ player.status_message }}
                                </p>
                                <p class="member-row__meta">
                                    {{ player.attendance_count }} jornadas registradas
                                </p>
                            </div>
                            <span
                                :class="[
                                    'member-chip',
                                    !player.has_arrived && !player.current_cut_paid
                                        ? 'member-chip--status'
                                        : '',
                                    player.has_arrived && !liveArrivalLocked
                                        ? 'member-chip--negative'
                                        : player.has_arrived
                                          ? 'member-chip--registered'
                                          : player.current_cut_paid
                                            ? 'member-chip--positive'
                                            : 'member-chip--warning',
                                ]"
                                >{{
                                    player.has_arrived
                                        ? liveArrivalLocked
                                            ? 'Ya registrado'
                                            : `#${player.arrival_order}`
                                        : player.current_cut_paid
                                          ? 'Al día'
                                          : canManageArrival
                                            ? 'Pendiente'
                                            : 'Ver'
                                }}</span
                            >
                        </button>
                    </section>

                    <section class="app-surface section-stack">
                        <div class="section-head">
                            <div class="section-head__copy">
                                <p class="app-kicker section-head__kicker">
                                    Invitados
                                </p>
                                <span class="section-head__badge">
                                    {{ payload?.guests?.length ?? 0 }}
                                </span>
                            </div>
                        </div>
                        <div v-if="canManageArrival" class="guest-form">
                            <input
                                v-model="guestName"
                                type="text"
                                class="guest-form__input"
                                placeholder="Nombre del invitado"
                            />
                            <button
                                class="action-button action-button--secondary"
                                type="button"
                                @click="addGuest"
                            >
                                Agregar
                            </button>
                        </div>
                        <p v-else class="body-copy">
                            Los invitados solo se muestran como referencia.
                        </p>
                        <article
                            v-for="guest in payload?.guests ?? []"
                            :key="guest.id"
                            class="guest-row"
                        >
                            <div>
                                <div class="member-row__header">
                                    <p class="member-row__name">{{ guest.name }}</p>
                                    <span class="member-row__meta-chip">
                                        {{ guestQueueLabel(guest) }}
                                    </span>
                                </div>
                                <p class="member-row__copy">
                                    Pago por invitado:
                                    {{
                                        money(
                                            payload?.cut
                                                .guest_fee_amount_cents ?? 0,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="guest-row__actions">
                                <button
                                    :class="[
                                        'member-chip',
                                        guest.guest_fee_paid
                                            ? 'member-chip--positive'
                                            : 'member-chip--warning',
                                    ]"
                                    type="button"
                                    :disabled="!canManageArrival"
                                    @click="
                                        toggleGuest(
                                            guest.id,
                                            guest.guest_fee_paid,
                                        )
                                    "
                                >
                                    {{
                                        guest.guest_fee_paid
                                            ? 'Pagado'
                                            : 'Pendiente'
                                    }}
                                </button>
                                <button
                                    v-if="canManageArrival"
                                    class="member-chip member-chip--negative"
                                    type="button"
                                    @click="removeGuest(guest.id)"
                                >
                                    Quitar
                                </button>
                            </div>
                        </article>
                    </section>

                    <section class="app-surface section-stack">
                        <div class="section-head">
                            <div class="section-head__copy">
                                <p class="app-kicker section-head__kicker">
                                    Cola inicial
                                </p>
                                <span class="section-head__badge">
                                    {{ queuePreviewEntries.length }}
                                </span>
                            </div>
                        </div>
                        <p class="body-copy">
                            Las primeras 10 posiciones alimentan el primer
                            draft. Los miembros empujan a los invitados solo
                            dentro de esa ventana; después, la cola conserva el
                            orden ya establecido.
                        </p>
                        <p
                            v-if="canReorderQueuePreview"
                            class="body-copy body-copy--accent"
                        >
                            Mantén presionado y arrastra con el dedo para mover
                            posiciones antes del primer juego.
                        </p>
                        <p
                            v-if="queuePreviewEntries.length === 0"
                            class="queue-empty"
                        >
                            La cola inicial se llenará con miembros llegados e
                            invitados pagados.
                        </p>
                        <IonReorderGroup
                            v-else
                            :disabled="!canReorderQueuePreview"
                            @ionItemReorder="reorderQueuePreview"
                        >
                            <IonItem
                                v-for="entry in queuePreviewEntries"
                                :key="entry.id"
                                lines="none"
                                class="queue-reorder-item"
                            >
                                <div class="queue-card">
                                    <div class="queue-card__copy">
                                        <div class="member-row__header">
                                            <p class="member-row__name">
                                                {{ entry.name }}
                                            </p>
                                            <span
                                                v-if="entry.is_guest"
                                                class="member-row__meta-chip"
                                            >
                                                Invitado
                                            </span>
                                            <span
                                                v-else
                                                class="member-row__meta-chip"
                                            >
                                                #{{ entry.jersey_number ?? 'S/N' }}
                                            </span>
                                        </div>
                                        <p class="member-row__copy">
                                            {{ queuePreviewMeta(entry) }}
                                        </p>
                                    </div>
                                    <div class="queue-card__meta">
                                        <span class="member-chip member-chip--neutral">
                                            #{{ entry.position }}
                                        </span>
                                        <IonReorder
                                            v-if="canReorderQueuePreview"
                                        />
                                    </div>
                                </div>
                            </IonItem>
                        </IonReorderGroup>
                    </section>
                </div>
            </div>

            <div
                v-if="selectedPlayerId !== null && canManageArrival"
                class="overlay"
                @click.self="selectedPlayerId = null"
            >
                <section class="overlay__panel">
                    <p class="app-kicker overlay__kicker">Registrar llegada</p>
                    <p class="body-copy">
                        Si este miembro pagó ahora, conserva prioridad.
                    </p>
                    <div class="overlay__actions">
                        <button
                            class="action-button action-button--secondary"
                            type="button"
                            @click="togglePlayer(selectedPlayerId, false)"
                        >
                            Llegó sin pagar
                        </button>
                        <button
                            class="action-button action-button--primary"
                            type="button"
                            @click="togglePlayer(selectedPlayerId, true)"
                        >
                            Pagó y llegó
                        </button>
                    </div>
                </section>
            </div>

            <div
                v-if="resetConfirmOpen && canManageArrival"
                class="overlay"
                @click.self="resetConfirmOpen = false"
            >
                <section class="overlay__panel">
                    <p class="app-kicker overlay__kicker">Reiniciar llegada</p>
                    <p class="body-copy">
                        Esta accion limpiara la lista de llegada actual. Usala
                        solo si quieres comenzar de nuevo.
                    </p>
                    <div class="overlay__actions">
                        <button
                            class="action-button action-button--secondary"
                            type="button"
                            @click="resetConfirmOpen = false"
                        >
                            Cancelar
                        </button>
                        <button
                            class="action-button action-button--ghost"
                            type="button"
                            @click="resetSession"
                        >
                            Confirmar reinicio
                        </button>
                    </div>
                </section>
            </div>

            <div
                v-if="prepareOpen && canManageArrival"
                class="overlay"
                @click.self="prepareOpen = false"
            >
                <section class="overlay__panel">
                    <p class="app-kicker overlay__kicker">Iniciar jornada</p>
                    <p class="body-copy">
                        Confirma el cobro de invitados. Solo los pagos quedarán
                        habilitados y necesitas 10 integrantes listos para
                        iniciar.
                    </p>
                    <div class="section-stack">
                        <article
                            v-for="guest in payload?.guests ?? []"
                            :key="guest.id"
                            class="guest-row"
                        >
                            <p class="member-row__name">{{ guest.name }}</p>
                            <button
                                :class="[
                                    'member-chip',
                                    guestPayments[guest.id]
                                        ? 'member-chip--positive'
                                        : 'member-chip--negative',
                                ]"
                                type="button"
                                @click="
                                    guestPayments[guest.id] =
                                        !guestPayments[guest.id]
                                "
                            >
                                {{
                                    guestPayments[guest.id]
                                        ? 'Pagado'
                                        : 'Pendiente'
                                }}
                            </button>
                        </article>
                    </div>
                    <div class="overlay__actions">
                        <button
                            class="action-button action-button--secondary"
                            type="button"
                            @click="prepareOpen = false"
                        >
                            Cerrar
                        </button>
                        <button
                            class="action-button action-button--primary"
                            type="button"
                            @click="prepareSession"
                        >
                            Confirmar
                        </button>
                    </div>
                </section>
            </div>

            <LeagueRosterSheet
                v-if="payload?.roster_management.can_manage"
                v-model:is-open="rosterOpen"
                :roster-management="payload.roster_management"
                @changed="loadPage"
            />
        </IonContent>
    </IonPage>
</template>

<style scoped>
.summary-grid,
.section-stack,
.summary-card,
.overlay__panel,
.overlay__actions,
.error-block {
    display: flex;
    flex-direction: column;
}
.summary-grid,
.section-stack,
.overlay__panel,
.overlay__actions {
    gap: 12px;
}
.summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.summary-card,
.member-row,
.guest-row,
.queue-row {
    background: #0e1628;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 16px;
}
.summary-card {
    padding: 14px;
    min-width: 0;
}
.summary-card .app-kicker {
    line-height: 1.25;
    word-break: keep-all;
}
.summary-card__value,
.member-row__name,
.member-row__copy,
.queue-row,
.body-copy {
    margin: 0;
}
.summary-card__value {
    margin-top: 10px;
    font-size: clamp(1.5rem, 6vw, 1.75rem);
    line-height: 1.05;
    font-weight: 700;
    color: #f8fafc;
}
.summary-card__value--positive {
    color: #4ade80;
}
.summary-card__value--negative {
    color: #fca5a5;
}
.body-copy,
.member-row__copy {
    font-size: 13px;
    line-height: 1.6;
    color: #94a3b8;
}
.body-copy--accent {
    color: #cbd5e1;
}
.body-copy--error {
    color: #fca5a5;
}
.section-head,
.member-row,
.guest-row,
.guest-form,
.section-head__actions,
.section-head__copy,
.member-row__header {
    display: flex;
    align-items: center;
    gap: 12px;
}
.section-head__copy,
.guest-row__actions {
    min-width: 0;
}
.section-head {
    justify-content: space-between;
}
.section-head__actions {
    justify-content: flex-end;
    flex-wrap: wrap;
}
.section-head__actions .action-button {
    flex: 0 1 auto;
}
.section-head__kicker,
.overlay__kicker {
    color: #e5b849;
}
.error-block {
    gap: 8px;
    border-color: rgba(248, 113, 113, 0.28);
    background: rgba(248, 113, 113, 0.12);
}
.member-row,
.guest-row {
    padding: 14px;
    text-align: left;
}
.member-row__name {
    font-size: 15px;
    font-weight: 700;
    color: #f8fafc;
}
.member-row__meta,
.section-head__badge {
    margin: 0;
    font-size: 12px;
    color: #94a3b8;
}
.member-row__meta {
    margin-top: 4px;
}
.member-row__meta-chip,
.section-head__badge {
    display: inline-flex;
    min-height: 30px;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    background: #131b2f;
    padding: 0 10px;
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
}
.member-chip,
.action-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    padding: 0 16px;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}
.member-chip {
    padding: 0 14px;
    text-align: center;
}
.member-chip--status {
    padding-inline: 16px;
    min-width: 96px;
}
.guest-row__actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}
.member-chip--positive,
.action-button--primary {
    background: rgba(74, 222, 128, 0.12);
    border-color: rgba(74, 222, 128, 0.28);
    color: #4ade80;
}
.member-chip--registered {
    background: rgba(229, 184, 73, 0.18);
    border-color: rgba(229, 184, 73, 0.38);
    color: #fef3c7;
}
.member-chip--warning {
    background: rgba(229, 184, 73, 0.12);
    border-color: rgba(229, 184, 73, 0.28);
    color: #f8fafc;
}
.member-chip--negative {
    background: rgba(248, 113, 113, 0.12);
    border-color: rgba(248, 113, 113, 0.28);
    color: #fca5a5;
}
.guest-form {
    align-items: stretch;
    gap: 10px;
}
.guest-form__input {
    width: 100%;
    min-height: 48px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: #0e1628;
    padding: 0 14px;
    color: #f8fafc;
}
.overlay__actions .action-button {
    width: 100%;
}
.action-button--secondary {
    background: #131b2f;
    color: #f8fafc;
    padding: 0 14px;
}
.action-button--ghost {
    background: rgba(248, 113, 113, 0.12);
    border-color: rgba(248, 113, 113, 0.28);
    color: #fca5a5;
    padding: 0 14px;
}
.queue-empty {
    margin: 0;
    border: 1px dashed rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    background: #0e1628;
    padding: 14px;
    font-size: 13px;
    line-height: 1.6;
    color: #94a3b8;
}
.queue-reorder-item {
    --background: transparent;
    --padding-start: 0;
    --inner-padding-end: 0;
    --inner-border-width: 0;
    --min-height: auto;
}
.queue-card,
.queue-card__meta {
    display: flex;
    align-items: center;
    gap: 12px;
}
.queue-card {
    width: 100%;
    justify-content: space-between;
}
.queue-card__copy {
    min-width: 0;
}
.queue-card__meta {
    justify-content: flex-end;
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
.overlay__panel {
    width: min(100%, 480px);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 28px 28px 20px 20px;
    background: #1a243a;
    padding: 18px 16px 20px;
}

@media (max-width: 420px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .summary-grid > :last-child {
        grid-column: 1 / -1;
    }

    .section-head,
    .guest-form {
        align-items: stretch;
        flex-direction: column;
    }

    .section-head {
        align-items: flex-start;
    }

    .section-head__actions,
    .guest-row__actions {
        width: 100%;
        justify-content: flex-start;
    }

    .section-head__actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .section-head__actions .action-button:last-child {
        grid-column: 1 / -1;
    }

    .guest-row {
        display: grid;
        grid-template-columns: 1fr;
        align-items: stretch;
    }

    .guest-row__actions {
        flex-wrap: nowrap;
    }

    .member-chip,
    .action-button {
        min-width: 0;
        width: 100%;
    }

    .member-chip--status {
        min-width: 88px;
    }
}

@media (min-width: 421px) {
    .summary-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
</style>
