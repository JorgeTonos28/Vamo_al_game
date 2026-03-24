<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import GoogleLogo from '@/components/GoogleLogo.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';

defineProps<{
    invitation: {
        id: number;
        token: string;
        email: string;
        first_name: string | null;
        last_name: string | null;
        document_id: string | null;
        phone: string | null;
        address: string | null;
        account_role: string | null;
        account_role_label: string | null;
    };
}>();
</script>

<template>
    <AuthBase
        title="Aceptar invitacion"
        :description="`Completa tu acceso como ${invitation.account_role_label?.toLowerCase() ?? 'usuario'} y termina el onboarding inicial.`"
    >
        <Head title="Aceptar invitacion" />

        <Form
            :action="`/invitations/${invitation.id}/accept`"
            method="post"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <input type="hidden" name="token" :value="invitation.token" />

            <div class="grid gap-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="first_name">Nombre</Label>
                        <Input
                            id="first_name"
                            name="first_name"
                            required
                            :default-value="invitation.first_name ?? ''"
                        />
                        <InputError :message="errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">Apellido</Label>
                        <Input
                            id="last_name"
                            name="last_name"
                            required
                            :default-value="invitation.last_name ?? ''"
                        />
                        <InputError :message="errors.last_name" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="email">Correo</Label>
                    <Input
                        id="email"
                        :model-value="invitation.email"
                        disabled
                        readonly
                    />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="document_id">Cedula</Label>
                        <Input
                            id="document_id"
                            name="document_id"
                            :default-value="invitation.document_id ?? ''"
                        />
                        <InputError :message="errors.document_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Telefono</Label>
                        <Input
                            id="phone"
                            name="phone"
                            :default-value="invitation.phone ?? ''"
                        />
                        <InputError :message="errors.phone" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="address">Direccion</Label>
                    <Input
                        id="address"
                        name="address"
                        :default-value="invitation.address ?? ''"
                    />
                    <InputError :message="errors.address" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Contrasena</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmar contrasena</Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button type="submit" class="w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    Completar acceso
                </Button>
            </div>

            <div class="space-y-4">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <span class="w-full border-t border-[rgba(255,255,255,0.08)]" />
                    </div>
                    <div class="relative flex justify-center text-[12px] uppercase tracking-[0.08em]">
                        <span class="bg-[#131B2F] px-3 text-[#94A3B8]">o</span>
                    </div>
                </div>

                <Button variant="outline" class="w-full" as-child>
                    <a
                        :href="`/auth/google/redirect?invitation=${invitation.id}&token=${invitation.token}`"
                        class="inline-flex items-center justify-center gap-3"
                    >
                        <GoogleLogo />
                        <span>Continuar con Google</span>
                    </a>
                </Button>
            </div>
        </Form>
    </AuthBase>
</template>
