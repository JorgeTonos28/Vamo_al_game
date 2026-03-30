<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CommandCenterLayout from '@/layouts/CommandCenterLayout.vue';

type LeagueAdmin = { id: number | null; name: string };
type LeagueRow = {
    id: number;
    name: string;
    emoji: string | null;
    slug: string;
    is_active: boolean;
    admins: LeagueAdmin[];
    members_count: number;
    created_at: string | null;
};

const props = defineProps<{ leagues: LeagueRow[] }>();
const page = usePage();
const status = computed(
    () => (page.props.flash as { status?: string } | undefined)?.status,
);
const form = useForm({ name: '', emoji: '' });
const editForm = useForm({ name: '', emoji: '' });
const editingLeagueId = ref<number | null>(null);
const emojiOptions = ['\u{1F3C0}', '\u{1F3C6}', '\u{1F525}', '\u{26A1}', '\u{1F3AF}', '\u{1F4AA}', '\u{1F6E1}\u{FE0F}', '\u{1F3BD}', '\u{1F45F}', '\u{1F680}', '\u{1F31F}', '\u{1F3DF}\u{FE0F}'];

const submit = () =>
    form.post('/command-center/leagues', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
        },
    });

const toggleLeague = (league: LeagueRow) =>
    router.patch(`/command-center/leagues/${league.id}`, undefined, {
        preserveScroll: true,
    });

const openEdit = (league: LeagueRow) => {
    editForm.name = league.name;
    editForm.emoji = league.emoji ?? '';
    editForm.clearErrors();
    editingLeagueId.value = league.id;
};

const closeEdit = () => {
    editForm.reset();
    editForm.clearErrors();
    editingLeagueId.value = null;
};

const submitEdit = () => {
    const leagueId = editingLeagueId.value;

    if (!leagueId) {
        return;
    }

    editForm.patch(`/command-center/leagues/${leagueId}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeEdit();
        },
    });
};
</script>

<template>
    <Head title="Ligas" />

    <CommandCenterLayout>
        <div class="app-page-stack">
            <section class="app-surface space-y-4">
                <div class="space-y-2">
                    <p class="app-kicker text-[#E5B849]">Control de ligas</p>
                    <h1 class="text-[26px] font-semibold text-[#F8FAFC]">
                        Acceso operativo por liga
                    </h1>
                    <p class="app-body-copy">
                        Si una liga queda inactiva, sus administradores y
                        miembros perderan acceso al entorno regular mientras no
                        tengan otra liga activa disponible.
                    </p>
                </div>
                <p v-if="status" class="app-badge-positive inline-flex">
                    {{ status }}
                </p>
            </section>

            <section class="app-surface space-y-5">
                <div class="space-y-2">
                    <p class="app-kicker text-[#E5B849]">Nueva liga</p>
                    <h2 class="text-[22px] font-semibold text-[#F8FAFC]">
                        Crear liga activa
                    </h2>
                    <p class="app-body-copy">
                        El nombre debe ser unico de forma exacta. La liga se
                        crea activa y disponible para asignaciones.
                    </p>
                </div>
                <form
                    class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto]"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label for="league_name">Nombre de la liga</Label>
                        <Input
                            id="league_name"
                            v-model="form.name"
                            required
                            maxlength="120"
                            placeholder="Liga Aurora"
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="league_emoji">Emoji</Label>
                        <div
                            class="grid grid-cols-6 gap-2 rounded-[16px] border border-white/6 bg-[#0E1628] p-3 sm:grid-cols-12"
                        >
                            <button
                                v-for="option in emojiOptions"
                                :key="option"
                                type="button"
                                class="flex min-h-12 cursor-pointer items-center justify-center rounded-[12px] border text-[22px] transition active:scale-[0.97] active:opacity-80"
                                :class="
                                    form.emoji === option
                                        ? 'border-[rgba(229,184,73,0.32)] bg-[rgba(229,184,73,0.14)]'
                                        : 'border-white/6 bg-[#131B2F] hover:bg-[#1B2740]'
                                "
                                @click="form.emoji = option"
                            >
                                {{ option }}
                            </button>
                        </div>
                        <Input
                            id="league_emoji"
                            v-model="form.emoji"
                            maxlength="16"
                            :placeholder="emojiOptions[0]"
                        />
                        <p class="text-[13px] text-[#94A3B8]">
                            Opcional. Puedes elegir uno del selector o escribir
                            otro manualmente.
                        </p>
                        <InputError :message="form.errors.emoji" />
                    </div>
                    <div class="md:col-span-2 md:self-end">
                        <Button
                            :disabled="form.processing"
                            class="w-full md:w-auto"
                        >
                            {{ form.processing ? 'Creando...' : 'Crear liga' }}
                        </Button>
                    </div>
                </form>
            </section>

            <section class="app-surface space-y-4">
                <div class="space-y-2">
                    <p class="app-kicker text-[#E5B849]">Ligas registradas</p>
                    <p class="app-body-copy">
                        Usa el boton `Editar nombre y emoji` dentro de cada liga
                        para actualizar su identidad visible.
                    </p>
                </div>

                <div class="app-divider-list">
                    <article
                        v-for="league in props.leagues"
                        :key="league.id"
                        class="flex flex-col gap-4"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p
                                        class="text-[18px] font-semibold text-[#F8FAFC]"
                                    >
                                        {{
                                            league.emoji
                                                ? `${league.emoji} ${league.name}`
                                                : league.name
                                        }}
                                    </p>
                                    <span
                                        :class="
                                            league.is_active
                                                ? 'app-badge-positive'
                                                : 'app-badge-negative'
                                        "
                                    >
                                        {{
                                            league.is_active
                                                ? 'Con acceso'
                                                : 'Acceso revocado'
                                        }}
                                    </span>
                                </div>
                                <p class="text-[13px] text-[#94A3B8]">
                                    {{
                                        league.admins
                                            .map((admin) => admin.name)
                                            .join(', ') ||
                                        'Sin administrador asignado'
                                    }}
                                </p>
                            </div>

                            <div
                                class="flex w-full flex-col gap-2 lg:w-auto lg:flex-row"
                            >
                                <Button
                                    variant="secondary"
                                    class="w-full border border-[rgba(229,184,73,0.28)] bg-[rgba(229,184,73,0.14)] text-[#F8FAFC] hover:bg-[rgba(229,184,73,0.2)] lg:w-auto"
                                    @click="openEdit(league)"
                                >
                                    Editar nombre y emoji
                                </Button>
                                <Button
                                    :variant="
                                        league.is_active
                                            ? 'destructive'
                                            : 'default'
                                    "
                                    class="w-full lg:w-auto"
                                    @click="toggleLeague(league)"
                                >
                                    {{
                                        league.is_active
                                            ? 'Revocar acceso'
                                            : 'Restaurar acceso'
                                    }}
                                </Button>
                            </div>
                        </div>

                        <div
                            class="grid gap-3 text-[13px] text-[#94A3B8] sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <div
                                class="rounded-[12px] border border-white/6 bg-[#0E1628] p-3"
                            >
                                <p class="app-kicker">Slug</p>
                                <p class="mt-2 text-[#F8FAFC]">
                                    {{ league.slug }}
                                </p>
                            </div>
                            <div
                                class="rounded-[12px] border border-white/6 bg-[#0E1628] p-3"
                            >
                                <p class="app-kicker">Miembros</p>
                                <p class="mt-2 text-[#F8FAFC]">
                                    {{ league.members_count }}
                                </p>
                            </div>
                            <div
                                class="rounded-[12px] border border-white/6 bg-[#0E1628] p-3"
                            >
                                <p class="app-kicker">Creada</p>
                                <p class="mt-2 text-[#F8FAFC]">
                                    {{ league.created_at ?? 'Sin fecha' }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </CommandCenterLayout>

    <Dialog
        :open="editingLeagueId !== null"
        @update:open="
            (open) => {
                if (!open) closeEdit();
            }
        "
    >
        <DialogContent
            class="border-white/8 bg-[#1A243A] text-[#F8FAFC] sm:max-w-[540px]"
        >
            <DialogHeader>
                <DialogTitle class="app-display text-[28px]">
                    Editar nombre y emoji
                </DialogTitle>
                <DialogDescription
                    class="text-[13px] leading-6 text-[#94A3B8]"
                >
                    Ajusta el nombre operativo y el emoji visible en web y
                    mobile.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="edit_league_name">Nombre de la liga</Label>
                    <Input
                        id="edit_league_name"
                        v-model="editForm.name"
                        required
                        maxlength="120"
                        placeholder="Liga Aurora"
                    />
                    <InputError :message="editForm.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit_league_emoji">Emoji</Label>
                    <div
                        class="grid grid-cols-6 gap-2 rounded-[16px] border border-white/6 bg-[#0E1628] p-3 sm:grid-cols-12"
                    >
                        <button
                            type="button"
                            class="flex min-h-12 cursor-pointer items-center justify-center rounded-[12px] border px-2 text-[11px] font-semibold uppercase tracking-[0.18em] transition active:scale-[0.97] active:opacity-80"
                            :class="
                                editForm.emoji === ''
                                    ? 'border-[rgba(229,184,73,0.32)] bg-[rgba(229,184,73,0.14)] text-[#F8FAFC]'
                                    : 'border-white/6 bg-[#131B2F] text-[#94A3B8] hover:bg-[#1B2740]'
                            "
                            @click="editForm.emoji = ''"
                        >
                            Sin
                        </button>
                        <button
                            v-for="option in emojiOptions"
                            :key="`edit-${option}`"
                            type="button"
                            class="flex min-h-12 cursor-pointer items-center justify-center rounded-[12px] border text-[22px] transition active:scale-[0.97] active:opacity-80"
                            :class="
                                editForm.emoji === option
                                    ? 'border-[rgba(229,184,73,0.32)] bg-[rgba(229,184,73,0.14)]'
                                    : 'border-white/6 bg-[#131B2F] hover:bg-[#1B2740]'
                            "
                            @click="editForm.emoji = option"
                        >
                            {{ option }}
                        </button>
                    </div>
                    <Input
                        id="edit_league_emoji"
                        v-model="editForm.emoji"
                        maxlength="16"
                        :placeholder="emojiOptions[0]"
                    />
                    <InputError :message="editForm.errors.emoji" />
                </div>
            </div>
            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    class="border border-white/8 bg-[#0E1628]"
                    @click="closeEdit"
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    class="bg-[#E5B849] text-[#0A0F1D] hover:bg-[#e8c25d]"
                    :disabled="editForm.processing"
                    @click="submitEdit"
                >
                    {{ editForm.processing ? 'Guardando...' : 'Guardar cambios' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
