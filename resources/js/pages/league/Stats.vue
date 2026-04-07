<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, Target, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import LeagueShellLayout from '@/components/league/LeagueShellLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    league: { id: number; name: string; emoji: string | null; slug: string };
    role: { value: string; label: string; can_manage: boolean };
    session_selector: {
        selected_session_id: number | null;
        sessions: Array<{
            id: number;
            session_date: string | null;
            status: string;
            entries_count: number;
            completed_games_count: number;
            is_current: boolean;
        }>;
    };
    stats: {
        games_count: number;
        points_leaders: Array<{
            identity: { name: string; is_guest: boolean };
            points: number;
            games: number;
            shots: { 1: number; 2: number; 3: number };
        }>;
        games_leaders: Array<{
            identity: { name: string; is_guest: boolean };
            games: number;
            wins: number;
            losses: number;
        }>;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Stats', href: '/liga/modulos/stats' }];
const deleteDialogOpen = ref(false);
const selectedSession = computed(
    () =>
        props.session_selector.sessions.find(
            (session) => session.id === props.session_selector.selected_session_id,
        ) ?? null,
);
const canDeleteSession = computed(
    () => props.role.can_manage && selectedSession.value !== null,
);

function changeSession(event: Event): void {
    const target = event.target as HTMLSelectElement;
    const sessionId = Number(target.value);

    if (!Number.isFinite(sessionId) || sessionId <= 0) {
        router.get('/liga/modulos/stats', {}, { preserveScroll: true, preserveState: true });

        return;
    }

    router.get(
        '/liga/modulos/stats',
        { session_id: sessionId },
        { preserveScroll: true, preserveState: true },
    );
}

function sessionLabel(session: {
    session_date: string | null;
    status: string;
    completed_games_count: number;
    is_current: boolean;
}): string {
    const base = session.session_date ?? 'Sin fecha';
    const suffix = session.is_current
        ? ' · actual'
        : session.status === 'completed'
          ? ' · cerrada'
          : ' · abierta';

    return `${base}${suffix} · ${session.completed_games_count} juegos`;
}

function confirmDeleteSession(): void {
    if (selectedSession.value === null) {
        return;
    }

    router.delete(`/liga/modulos/stats/sessions/${selectedSession.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleteDialogOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Stats" />

    <LeagueShellLayout
        :breadcrumbs="breadcrumbs"
        :league-name="props.league.name"
        :league-emoji="props.league.emoji"
        :role-label="props.role.label"
        active-module="stats"
        :can-manage-league="props.role.can_manage"
    >
        <section class="app-surface space-y-4">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="flex items-center gap-3">
                    <BarChart3 class="size-5 text-[#E5B849]" />
                    <div>
                        <p class="app-kicker text-[#E5B849]">Estadísticas de jornada</p>
                        <p class="mt-2 text-[13px] leading-6 text-[#94A3B8]">
                            Puntos y juegos completados durante la jornada seleccionada. Puedes revisar también días anteriores.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                    <select
                        :value="props.session_selector.selected_session_id ?? ''"
                        :disabled="props.session_selector.sessions.length === 0"
                        class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-4 text-sm text-[#F8FAFC] outline-none disabled:cursor-not-allowed disabled:opacity-60"
                        @change="changeSession"
                    >
                        <option
                            v-if="
                                props.session_selector.selected_session_id === null &&
                                props.session_selector.sessions.length > 0
                            "
                            value=""
                        >
                            Sin jornada activa · vista vacía de hoy
                        </option>
                        <option
                            v-if="props.session_selector.sessions.length === 0"
                            value=""
                        >
                            Sin jornadas registradas
                        </option>
                        <option
                            v-for="session in props.session_selector.sessions"
                            :key="session.id"
                            :value="session.id"
                        >
                            {{ sessionLabel(session) }}
                        </option>
                    </select>

                    <Button
                        v-if="props.role.can_manage"
                        type="button"
                        variant="secondary"
                        class="min-h-12 rounded-[12px] border border-[rgba(248,113,113,0.22)] bg-[rgba(248,113,113,0.08)] px-4 text-[#FCA5A5] hover:bg-[rgba(248,113,113,0.14)] disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!canDeleteSession"
                        @click="deleteDialogOpen = true"
                    >
                        <Trash2 class="mr-2 size-4" />
                        Eliminar jornada
                    </Button>
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="app-surface space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <Target class="size-5 text-[#E5B849]" />
                        <p class="app-kicker text-[#E5B849]">Puntos anotados</p>
                    </div>
                    <span class="rounded-full border border-white/6 bg-[#0E1628] px-3 py-1 text-[12px] text-[#94A3B8]">
                        {{ props.stats.games_count }} juegos
                    </span>
                </div>
                <div v-if="props.stats.points_leaders.length === 0" class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]">
                    Sin datos todavía.
                </div>
                <div v-else class="grid gap-3">
                    <div
                        v-for="(row, index) in props.stats.points_leaders"
                        :key="`${row.identity.name}-${index}`"
                        class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[15px] font-semibold text-[#F8FAFC]">{{ row.identity.name }}</p>
                                <p class="mt-1 text-[12px] text-[#94A3B8]">
                                    {{ row.games }} juegos · 1P: {{ row.shots[1] }} · 2P: {{ row.shots[2] }} · 3P: {{ row.shots[3] }}
                                </p>
                            </div>
                            <span class="text-[18px] font-semibold text-[#E5B849]">{{ row.points }} pts</span>
                        </div>
                    </div>
                </div>
            </article>

            <article class="app-surface space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <BarChart3 class="size-5 text-[#4ADE80]" />
                        <p class="app-kicker text-[#E5B849]">Juegos jugados</p>
                    </div>
                    <span class="rounded-full border border-white/6 bg-[#0E1628] px-3 py-1 text-[12px] text-[#94A3B8]">
                        {{ props.stats.games_count }} juegos
                    </span>
                </div>
                <div v-if="props.stats.games_leaders.length === 0" class="rounded-[14px] border border-dashed border-white/8 bg-[#0E1628] p-4 text-sm text-[#94A3B8]">
                    Sin datos todavía.
                </div>
                <div v-else class="grid gap-3">
                    <div
                        v-for="(row, index) in props.stats.games_leaders"
                        :key="`${row.identity.name}-${index}`"
                        class="rounded-[14px] border border-white/6 bg-[#0E1628] p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[15px] font-semibold text-[#F8FAFC]">{{ row.identity.name }}</p>
                                <p class="mt-1 text-[12px] text-[#94A3B8]">{{ row.wins }}V - {{ row.losses }}D</p>
                            </div>
                            <span class="text-[18px] font-semibold text-[#4ADE80]">{{ row.games }} juegos</span>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <Dialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
            <DialogContent class="border-white/8 bg-[#1A243A] text-[#F8FAFC]">
                <DialogHeader class="space-y-3">
                    <DialogTitle class="app-display text-[28px]">Eliminar jornada</DialogTitle>
                    <DialogDescription class="text-[13px] leading-7 text-[#94A3B8]">
                        Se eliminará la jornada seleccionada
                        <span v-if="selectedSession">({{ selectedSession.session_date ?? 'Sin fecha' }})</span>.
                        Esta acción borra juegos, cola, stats y trazas asociadas.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="flex items-center justify-end gap-3">
                    <Button
                        type="button"
                        variant="secondary"
                        class="min-h-11 rounded-[12px] border border-white/8 bg-[#131B2F]"
                        @click="deleteDialogOpen = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        class="min-h-11 rounded-[12px] bg-[#F87171] text-[#0A0F1D] hover:bg-[#FB7185]"
                        @click="confirmDeleteSession"
                    >
                        Confirmar eliminación
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </LeagueShellLayout>
</template>
