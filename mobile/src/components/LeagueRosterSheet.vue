<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { extractApiErrors, extractApiFieldErrors } from '@/services/api';
import {
    addLeaguePlayer,
    setLeaguePlayerStatus,
    updateLeaguePlayer
    
} from '@/services/league';
import type {LeagueRosterManagement} from '@/services/league';

type RosterTab = 'invite' | 'edit' | 'active' | 'inactive';
type RosterField =
    | 'first_name'
    | 'last_name'
    | 'document_id'
    | 'phone'
    | 'address'
    | 'email'
    | 'jersey_number'
    | 'account_role';

const props = defineProps<{
    isOpen: boolean;
    rosterManagement: LeagueRosterManagement;
}>();
const emit = defineEmits<{
    (event: 'update:isOpen', value: boolean): void;
    (event: 'changed'): void;
}>();

const tabs: Array<{ key: RosterTab; label: string }> = [
    { key: 'invite', label: 'Invitar' },
    { key: 'edit', label: 'Editar' },
    { key: 'active', label: 'Activos' },
    { key: 'inactive', label: 'Inactivos' },
];

const activeTab = ref<RosterTab>('invite');
const touchStartX = ref<number | null>(null);
const inviteSubmitting = ref(false);
const editSubmitting = ref(false);
const feedbackMessages = ref<string[]>([]);
const inviteFieldErrors = ref<Partial<Record<RosterField, string>>>({});
const editFieldErrors = ref<Partial<Record<RosterField, string>>>({});
const sheetContentRef = ref<HTMLElement | null>(null);
const inviteForm = reactive({
    first_name: '',
    last_name: '',
    document_id: '',
    phone: '',
    address: '',
    email: '',
    jersey_number: '',
    account_role: 'member' as 'league_admin' | 'member',
});
const editPlayerId = ref<number | null>(null);
const editSearch = ref('');
const editForm = reactive({
    first_name: '',
    last_name: '',
    document_id: '',
    phone: '',
    address: '',
    email: '',
    jersey_number: '',
    account_role: 'member' as 'league_admin' | 'member',
});
const allPlayers = computed(() => [
    ...props.rosterManagement.active_players,
    ...props.rosterManagement.inactive_players,
]);
const filteredPlayers = computed(() => {
    const tokens = editSearch.value
        .trim()
        .toLocaleLowerCase()
        .split(/\s+/)
        .filter(Boolean);

    if (tokens.length === 0) {
        return allPlayers.value;
    }

    return allPlayers.value.filter((player) => {
        const searchable =
            `${player.name} ${player.jersey_number ?? ''}`.toLocaleLowerCase();

        return tokens.every((token) => searchable.includes(token));
    });
});
const selectedPlayer = computed(
    () =>
        allPlayers.value.find((player) => player.id === editPlayerId.value) ??
        null,
);

watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            activeTab.value = 'invite';
            feedbackMessages.value = [];
            inviteFieldErrors.value = {};
            editFieldErrors.value = {};
        }
    },
);

function inputClass(hasError = false): string {
    return ['sheet-input', hasError ? 'sheet-input--error' : '']
        .join(' ')
        .trim();
}

function scrollToTop(): void {
    nextTick(() => {
        sheetContentRef.value?.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

function close(): void {
    emit('update:isOpen', false);
}

function setTab(tab: RosterTab): void {
    activeTab.value = tab;
}

function moveTab(direction: 'previous' | 'next'): void {
    const currentIndex = tabs.findIndex((tab) => tab.key === activeTab.value);
    const nextIndex =
        direction === 'next' ? currentIndex + 1 : currentIndex - 1;

    if (nextIndex < 0 || nextIndex >= tabs.length) {
        return;
    }

    activeTab.value = tabs[nextIndex].key;
}

function handleTouchStart(event: TouchEvent): void {
    touchStartX.value = event.changedTouches[0]?.clientX ?? null;
}

function handleTouchEnd(event: TouchEvent): void {
    const startX = touchStartX.value;
    const endX = event.changedTouches[0]?.clientX;

    touchStartX.value = null;

    if (startX === null || endX === undefined) {
        return;
    }

    const deltaX = endX - startX;

    if (Math.abs(deltaX) < 48) {
        return;
    }

    if (deltaX < 0) {
        moveTab('next');

        return;
    }

    moveTab('previous');
}

async function submitInvite(): Promise<void> {
    inviteSubmitting.value = true;
    feedbackMessages.value = [];

    try {
        await addLeaguePlayer({
            first_name: inviteForm.first_name,
            last_name: inviteForm.last_name,
            document_id: inviteForm.document_id || null,
            phone: inviteForm.phone || null,
            address: inviteForm.address || null,
            email: inviteForm.email || null,
            jersey_number: inviteForm.jersey_number
                ? Number(inviteForm.jersey_number)
                : null,
            account_role: inviteForm.account_role,
        });

        inviteForm.first_name = '';
        inviteForm.last_name = '';
        inviteForm.document_id = '';
        inviteForm.phone = '';
        inviteForm.address = '';
        inviteForm.email = '';
        inviteForm.jersey_number = '';
        inviteForm.account_role = 'member';
        emit('changed');
        close();
    } catch (error) {
        feedbackMessages.value = extractApiErrors(error);
        inviteFieldErrors.value = Object.fromEntries(
            Object.entries(extractApiFieldErrors(error)).map(
                ([field, messages]) => [field, messages[0]],
            ),
        );
        editFieldErrors.value = {};
        scrollToTop();
    } finally {
        inviteSubmitting.value = false;
    }
}

function loadEditPlayer(playerId: number): void {
    const player = allPlayers.value.find((entry) => entry.id === playerId);

    if (!player) {
return;
}

    editPlayerId.value = playerId;
    editSearch.value = player.name;
    editForm.first_name = player.first_name;
    editForm.last_name = player.last_name;
    editForm.document_id = player.document_id ?? '';
    editForm.phone = player.phone ?? '';
    editForm.address = player.address ?? '';
    editForm.email = player.email ?? '';
    editForm.jersey_number = player.jersey_number?.toString() ?? '';
    editForm.account_role = player.account_role;
    activeTab.value = 'edit';
    feedbackMessages.value = [];
    editFieldErrors.value = {};
}

async function submitEdit(): Promise<void> {
    if (!editPlayerId.value) {
return;
}

    editSubmitting.value = true;
    feedbackMessages.value = [];

    try {
        await updateLeaguePlayer(editPlayerId.value, {
            first_name: editForm.first_name,
            last_name: editForm.last_name,
            document_id: editForm.document_id,
            phone: editForm.phone || null,
            address: editForm.address || null,
            email: editForm.email || null,
            jersey_number: editForm.jersey_number
                ? Number(editForm.jersey_number)
                : null,
            account_role: editForm.account_role,
        });

        editPlayerId.value = null;
        editSearch.value = '';
        editForm.first_name = '';
        editForm.last_name = '';
        editForm.document_id = '';
        editForm.phone = '';
        editForm.address = '';
        editForm.email = '';
        editForm.jersey_number = '';
        editForm.account_role = 'member';
        emit('changed');
    } catch (error) {
        feedbackMessages.value = extractApiErrors(error);
        editFieldErrors.value = Object.fromEntries(
            Object.entries(extractApiFieldErrors(error)).map(
                ([field, messages]) => [field, messages[0]],
            ),
        );
        inviteFieldErrors.value = {};
        scrollToTop();
    } finally {
        editSubmitting.value = false;
    }
}

async function toggleStatus(playerId: number, active: boolean): Promise<void> {
    feedbackMessages.value = [];

    try {
        await setLeaguePlayerStatus(playerId, active);
        emit('changed');
    } catch (error) {
        feedbackMessages.value = extractApiErrors(error);
        scrollToTop();
    }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="props.isOpen" class="sheet-backdrop" @click.self="close">
            <section class="sheet-panel">
                <div class="sheet-handle" />
                <p class="app-kicker sheet-kicker">Gestionar miembros</p>
                <p class="sheet-copy">
                    Desliza a izquierda o derecha para cambiar de seccion.
                </p>

                <div class="sheet-tabs">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        :class="[
                            'sheet-tab',
                            activeTab === tab.key ? 'sheet-tab--active' : '',
                        ]"
                        type="button"
                        @click="setTab(tab.key)"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div
                    ref="sheetContentRef"
                    class="sheet-content"
                    @touchstart="handleTouchStart"
                    @touchend="handleTouchEnd"
                >
                    <div v-if="feedbackMessages.length > 0" class="sheet-alert">
                        <p class="sheet-alert__title">
                            No se pudo completar la acción.
                        </p>
                        <p
                            v-for="message in feedbackMessages"
                            :key="message"
                            class="sheet-note"
                        >
                            {{ message }}
                        </p>
                    </div>

                    <div v-if="activeTab === 'invite'" class="sheet-block">
                        <p class="sheet-label">Invitar miembro</p>
                        <p class="sheet-note">
                            Nombre, apellido y cédula son obligatorios. El
                            correo es opcional.
                        </p>
                        <div class="sheet-grid">
                            <input
                                v-model="inviteForm.first_name"
                                type="text"
                                :class="
                                    inputClass(
                                        Boolean(inviteFieldErrors.first_name),
                                    )
                                "
                                placeholder="Nombre"
                            />
                            <input
                                v-model="inviteForm.last_name"
                                type="text"
                                :class="
                                    inputClass(
                                        Boolean(inviteFieldErrors.last_name),
                                    )
                                "
                                placeholder="Apellido"
                            />
                        </div>
                        <div class="sheet-grid">
                            <input
                                v-model="inviteForm.document_id"
                                type="text"
                                :class="
                                    inputClass(
                                        Boolean(inviteFieldErrors.document_id),
                                    )
                                "
                                placeholder="Cédula"
                            />
                            <input
                                v-model="inviteForm.jersey_number"
                                type="number"
                                min="0"
                                max="99"
                                :class="
                                    inputClass(
                                        Boolean(
                                            inviteFieldErrors.jersey_number,
                                        ),
                                    )
                                "
                                placeholder="Chaqueta"
                            />
                        </div>
                        <div class="sheet-grid">
                            <input
                                v-model="inviteForm.phone"
                                type="text"
                                :class="
                                    inputClass(Boolean(inviteFieldErrors.phone))
                                "
                                placeholder="Teléfono"
                            />
                            <input
                                v-model="inviteForm.email"
                                type="email"
                                :class="
                                    inputClass(Boolean(inviteFieldErrors.email))
                                "
                                placeholder="Correo"
                            />
                        </div>
                        <div class="sheet-grid sheet-grid--single">
                            <input
                                v-model="inviteForm.address"
                                type="text"
                                :class="
                                    inputClass(
                                        Boolean(inviteFieldErrors.address),
                                    )
                                "
                                placeholder="Dirección"
                            />
                            <select
                                v-model="inviteForm.account_role"
                                :class="
                                    inputClass(
                                        Boolean(inviteFieldErrors.account_role),
                                    )
                                "
                            >
                                <option value="member">Miembro</option>
                                <option value="league_admin">
                                    Administrador
                                </option>
                            </select>
                        </div>
                        <button
                            class="sheet-button sheet-button--primary"
                            type="button"
                            :disabled="inviteSubmitting"
                            @click="submitInvite"
                        >
                            {{
                                inviteSubmitting
                                    ? 'Guardando...'
                                    : 'Agregar miembro'
                            }}
                        </button>
                    </div>

                    <div v-else-if="activeTab === 'edit'" class="sheet-block">
                        <p class="sheet-label">Editar miembro</p>
                        <input
                            v-model="editSearch"
                            type="text"
                            class="sheet-input"
                            placeholder="Busca por nombre o número"
                        />
                        <div class="sheet-list">
                            <button
                                v-for="player in filteredPlayers"
                                :key="player.id"
                                :class="[
                                    'sheet-row',
                                    editPlayerId === player.id
                                        ? 'sheet-row--active'
                                        : '',
                                ]"
                                type="button"
                                @click="loadEditPlayer(player.id)"
                            >
                                <div>
                                    <p class="sheet-row__name">
                                        {{ player.name }}
                                    </p>
                                    <p class="sheet-row__meta">
                                        #{{ player.jersey_number ?? 'S/N' }}
                                    </p>
                                </div>
                            </button>
                            <p
                                v-if="filteredPlayers.length === 0"
                                class="sheet-note"
                            >
                                No encontramos miembros con ese filtro.
                            </p>
                        </div>
                        <div v-if="selectedPlayer" class="sheet-block">
                            <div class="sheet-grid">
                                <input
                                    v-model="editForm.first_name"
                                    type="text"
                                    :class="
                                        inputClass(
                                            Boolean(editFieldErrors.first_name),
                                        )
                                    "
                                    placeholder="Nombre"
                                />
                                <input
                                    v-model="editForm.last_name"
                                    type="text"
                                    :class="
                                        inputClass(
                                            Boolean(editFieldErrors.last_name),
                                        )
                                    "
                                    placeholder="Apellido"
                                />
                            </div>
                            <div class="sheet-grid">
                                <input
                                    v-model="editForm.document_id"
                                    type="text"
                                    :class="
                                        inputClass(
                                            Boolean(
                                                editFieldErrors.document_id,
                                            ),
                                        )
                                    "
                                    placeholder="Cédula"
                                />
                                <input
                                    v-model="editForm.jersey_number"
                                    type="number"
                                    min="0"
                                    max="99"
                                    :class="
                                        inputClass(
                                            Boolean(
                                                editFieldErrors.jersey_number,
                                            ),
                                        )
                                    "
                                    placeholder="Chaqueta"
                                />
                            </div>
                            <div class="sheet-grid">
                                <input
                                    v-model="editForm.phone"
                                    type="text"
                                    :class="
                                        inputClass(
                                            Boolean(editFieldErrors.phone),
                                        )
                                    "
                                    placeholder="Teléfono"
                                />
                                <input
                                    v-model="editForm.email"
                                    type="email"
                                    :class="
                                        inputClass(
                                            Boolean(editFieldErrors.email),
                                        )
                                    "
                                    placeholder="Correo"
                                />
                            </div>
                            <div class="sheet-grid sheet-grid--single">
                                <input
                                    v-model="editForm.address"
                                    type="text"
                                    :class="
                                        inputClass(
                                            Boolean(editFieldErrors.address),
                                        )
                                    "
                                    placeholder="Dirección"
                                />
                                <select
                                    v-model="editForm.account_role"
                                    :class="
                                        inputClass(
                                            Boolean(
                                                editFieldErrors.account_role,
                                            ),
                                        )
                                    "
                                >
                                    <option value="member">Miembro</option>
                                    <option value="league_admin">
                                        Administrador
                                    </option>
                                </select>
                            </div>
                            <button
                                class="sheet-button sheet-button--secondary"
                                :disabled="editSubmitting"
                                type="button"
                                @click="submitEdit"
                            >
                                {{
                                    editSubmitting
                                        ? 'Guardando...'
                                        : 'Guardar cambios'
                                }}
                            </button>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'active'" class="sheet-block">
                        <div class="sheet-heading">
                            <p class="sheet-label">Activos</p>
                            <span class="sheet-count">{{
                                props.rosterManagement.active_players.length
                            }}</span>
                        </div>
                        <article
                            v-for="player in props.rosterManagement
                                .active_players"
                            :key="player.id"
                            class="sheet-row sheet-row--static"
                        >
                            <div>
                                <p class="sheet-row__name">{{ player.name }}</p>
                                <p class="sheet-row__meta">
                                    #{{ player.jersey_number ?? 'S/N' }}
                                </p>
                            </div>
                            <div class="sheet-row__actions">
                                <button
                                    class="sheet-chip sheet-chip--warning"
                                    type="button"
                                    @click="loadEditPlayer(player.id)"
                                >
                                    Editar
                                </button>
                                <button
                                    class="sheet-chip sheet-chip--danger"
                                    type="button"
                                    @click="toggleStatus(player.id, false)"
                                >
                                    Dar de baja
                                </button>
                            </div>
                        </article>
                        <p
                            v-if="
                                props.rosterManagement.active_players.length ===
                                0
                            "
                            class="sheet-note"
                        >
                            No hay miembros activos registrados todavía.
                        </p>
                    </div>

                    <div v-else class="sheet-block">
                        <div class="sheet-heading">
                            <p class="sheet-label">Inactivos</p>
                            <span class="sheet-count">{{
                                props.rosterManagement.inactive_players.length
                            }}</span>
                        </div>
                        <article
                            v-for="player in props.rosterManagement
                                .inactive_players"
                            :key="player.id"
                            class="sheet-row sheet-row--static"
                        >
                            <div>
                                <p class="sheet-row__name">{{ player.name }}</p>
                                <p class="sheet-row__meta">
                                    #{{ player.jersey_number ?? 'S/N' }}
                                </p>
                            </div>
                            <button
                                class="sheet-chip sheet-chip--success"
                                type="button"
                                @click="toggleStatus(player.id, true)"
                            >
                                Reactivar
                            </button>
                        </article>
                        <p
                            v-if="
                                props.rosterManagement.inactive_players
                                    .length === 0
                            "
                            class="sheet-note"
                        >
                            No hay miembros dados de baja.
                        </p>
                    </div>
                </div>

                <p class="sheet-note">
                    Crédito por referido:
                    {{
                        new Intl.NumberFormat('es-DO', {
                            style: 'currency',
                            currency: 'DOP',
                            maximumFractionDigits: 0,
                        }).format(
                            props.rosterManagement
                                .referral_credit_amount_cents / 100,
                        )
                    }}
                </p>
                <button
                    class="sheet-button sheet-button--secondary"
                    type="button"
                    @click="close"
                >
                    Cerrar
                </button>
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
.sheet-block,
.sheet-heading,
.sheet-grid,
.sheet-row__actions,
.sheet-tabs,
.sheet-alert {
    display: flex;
}

.sheet-panel {
    width: min(100%, 480px);
    max-height: calc(100vh - 32px);
    flex-direction: column;
    gap: 14px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 28px 28px 20px 20px;
    background: #1a243a;
    padding: 14px 16px 20px;
}

.sheet-block {
    min-height: 0;
    flex-direction: column;
    gap: 12px;
}

.sheet-alert {
    flex-direction: column;
    gap: 6px;
    border: 1px solid rgba(248, 113, 113, 0.28);
    border-radius: 16px;
    background: rgba(248, 113, 113, 0.12);
    padding: 14px;
    color: #fca5a5;
}

.sheet-handle {
    width: 52px;
    height: 5px;
    margin: 0 auto;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
}

.sheet-tabs {
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 2px;
}

.sheet-tab {
    min-height: 44px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: #0e1628;
    padding: 0 16px;
    color: #94a3b8;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.sheet-tab--active {
    border-color: rgba(229, 184, 73, 0.28);
    background: rgba(229, 184, 73, 0.12);
    color: #f8fafc;
}

.sheet-content,
.sheet-list,
.sheet-tabs {
    scrollbar-width: thin;
    scrollbar-color: rgba(229, 184, 73, 0.5) rgba(14, 22, 40, 0.92);
}

.sheet-content::-webkit-scrollbar,
.sheet-list::-webkit-scrollbar,
.sheet-tabs::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.sheet-content::-webkit-scrollbar-track,
.sheet-list::-webkit-scrollbar-track,
.sheet-tabs::-webkit-scrollbar-track {
    border-radius: 999px;
    background: rgba(14, 22, 40, 0.92);
}

.sheet-content::-webkit-scrollbar-thumb,
.sheet-list::-webkit-scrollbar-thumb,
.sheet-tabs::-webkit-scrollbar-thumb {
    border: 2px solid rgba(14, 22, 40, 0.92);
    border-radius: 999px;
    background: rgba(229, 184, 73, 0.5);
}

.sheet-content {
    min-height: 0;
    flex: 1;
    overflow-y: auto;
    padding-right: 2px;
}

.sheet-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.sheet-grid--single {
    grid-template-columns: 1fr;
}

.sheet-list {
    max-height: 220px;
    overflow-y: auto;
    padding-right: 2px;
}

.sheet-heading {
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.sheet-kicker,
.sheet-label {
    color: #e5b849;
}

.sheet-alert__title {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
}

.sheet-copy,
.sheet-row__meta,
.sheet-note,
.sheet-count {
    margin: 0;
    font-size: 13px;
    line-height: 1.6;
    color: #94a3b8;
}

.sheet-input {
    min-height: 48px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: #0e1628;
    padding: 0 14px;
    color: #f8fafc;
}

.sheet-input--error {
    border-color: rgba(248, 113, 113, 0.48);
}

.sheet-row {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 16px;
    background: #0e1628;
    padding: 14px;
    text-align: left;
}

.sheet-row--active {
    border-color: rgba(229, 184, 73, 0.28);
    background: rgba(229, 184, 73, 0.12);
}

.sheet-row--static {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
}

.sheet-row__actions {
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.sheet-row__name {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #f8fafc;
}

.sheet-button,
.sheet-chip {
    min-height: 44px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    font-size: 13px;
    font-weight: 700;
}

.sheet-button--primary,
.sheet-chip--success {
    background: rgba(74, 222, 128, 0.12);
    border-color: rgba(74, 222, 128, 0.28);
    color: #4ade80;
}

.sheet-button--secondary {
    background: #0e1628;
    color: #f8fafc;
}

.sheet-chip {
    padding: 0 12px;
}

.sheet-chip--warning {
    background: rgba(229, 184, 73, 0.12);
    border-color: rgba(229, 184, 73, 0.28);
    color: #f8fafc;
}

.sheet-chip--danger {
    background: rgba(248, 113, 113, 0.12);
    border-color: rgba(248, 113, 113, 0.28);
    color: #fca5a5;
}
</style>
