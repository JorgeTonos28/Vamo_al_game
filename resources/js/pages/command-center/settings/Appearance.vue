<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import AppLogo from '@/components/AppLogo.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import CommandCenterLayout from '@/layouts/CommandCenterLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { commandCenterSettingsNavItems } from '@/pages/command-center/settings/nav';
import type { Branding } from '@/types';

const props = defineProps<{
    branding: Branding;
}>();

const page = usePage();
const status = computed(
    () =>
        (page.props.flash as { status?: string } | undefined)?.status,
);

const form = useForm<{
    logo: File | null;
    favicon: File | null;
}>( {
    logo: null,
    favicon: null,
});

const submit = () => {
    form.post('/command-center/settings', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
        },
    });
};
</script>

<template>
    <Head title="Ajustes" />

    <CommandCenterLayout>
        <SettingsLayout
            title="Ajustes"
            description="Administra tu cuenta de administrador general sin salir del Centro de mando."
            kicker="Ajustes de cuenta"
            sidebar-description="Actualiza tus datos, fortalece el acceso y define la apariencia del Centro de mando desde la misma estructura que usa la app regular."
            :nav-items="commandCenterSettingsNavItems"
        >
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Apariencia"
                    description="Define como se ve tu cuenta en el Centro de mando y administra el branding compartido de toda la plataforma."
                />
                <AppearanceTabs />
            </div>

            <div class="space-y-5">
                <div class="space-y-2">
                    <p class="app-kicker text-[#E5B849]">
                        Branding principal de la app
                    </p>
                    <p class="app-body-copy">
                        El logo definido aqui se reutiliza en la landing,
                        login, shell autenticado y correos. El favicon se
                        aplica a toda la app web.
                    </p>
                </div>

                <p v-if="status" class="app-badge-positive inline-flex">
                    {{ status }}
                </p>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-[18px] border border-white/6 bg-[#0E1628] p-4">
                        <p class="app-kicker">Vista actual del logo</p>
                        <div
                            class="mt-4 flex min-h-28 items-center justify-center rounded-[16px] border border-dashed border-white/8 bg-[#0A0F1D] p-4"
                        >
                            <AppLogo />
                        </div>
                        <p class="mt-4 text-[13px] text-[#94A3B8]">
                            Dimension recomendada: logo horizontal en PNG, SVG o
                            WebP de al menos 960 x 240 px, con fondo
                            transparente.
                        </p>
                    </div>

                    <div class="rounded-[18px] border border-white/6 bg-[#0E1628] p-4">
                        <p class="app-kicker">Vista actual del favicon</p>
                        <div
                            class="mt-4 flex min-h-28 items-center justify-center rounded-[16px] border border-dashed border-white/8 bg-[#0A0F1D] p-4"
                        >
                            <img
                                v-if="props.branding.favicon_url"
                                :src="props.branding.favicon_url"
                                alt="Favicon actual"
                                class="size-16 rounded-[14px] object-contain"
                            />
                            <div
                                v-else
                                class="flex size-16 items-center justify-center rounded-[14px] border border-white/8 bg-[#131B2F] text-xs font-semibold text-[#94A3B8]"
                            >
                                ICO
                            </div>
                        </div>
                        <p class="mt-4 text-[13px] text-[#94A3B8]">
                            Dimension recomendada: favicon cuadrado en PNG o ICO
                            de 512 x 512 px. Si usas SVG, mantenlo simple y
                            legible a 32 x 32 px.
                        </p>
                    </div>
                </div>

                <form class="grid gap-4 lg:grid-cols-2" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="logo">Logo principal</Label>
                        <input
                            id="logo"
                            type="file"
                            accept=".png,.jpg,.jpeg,.webp,.svg"
                            class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-3 py-3 text-sm text-[#F8FAFC] outline-none transition file:mr-3 file:rounded-full file:border-0 file:bg-[rgba(229,184,73,0.16)] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-[#E5B849]"
                            @change="form.logo = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                        <InputError :message="form.errors.logo" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="favicon">Favicon</Label>
                        <input
                            id="favicon"
                            type="file"
                            accept=".png,.ico,.svg"
                            class="min-h-12 rounded-[12px] border border-white/8 bg-[#0E1628] px-3 py-3 text-sm text-[#F8FAFC] outline-none transition file:mr-3 file:rounded-full file:border-0 file:bg-[rgba(229,184,73,0.16)] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-[#E5B849]"
                            @change="form.favicon = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                        <InputError :message="form.errors.favicon" />
                    </div>

                    <div class="lg:col-span-2">
                        <Button :disabled="form.processing" class="w-full md:w-auto">
                            Guardar branding
                        </Button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </CommandCenterLayout>
</template>
