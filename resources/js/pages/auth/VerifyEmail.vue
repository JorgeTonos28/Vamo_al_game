<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

const props = defineProps<{
    status?: string;
}>();

const page = usePage<{
    auth: {
        user: {
            email?: string | null;
        } | null;
    };
}>();

const verificationEmail = page.props.auth.user?.email ?? null;
</script>

<template>
    <AuthLayout
        title="Verifica tu correo"
        :description="
            verificationEmail
                ? `Te enviamos un enlace de verificacion a ${verificationEmail}. Debes confirmar ese correo antes de poder entrar a la app.`
                : 'Te enviamos un enlace de verificacion. Debes confirmar tu email antes de poder entrar a la app.'
        "
    >
        <Head title="Verificacion de correo" />

        <div
            v-if="props.status === 'verification-link-sent'"
            class="mb-4 rounded-[12px] border border-[rgba(74,222,128,0.24)] bg-[rgba(74,222,128,0.12)] px-4 py-3 text-center text-sm font-medium text-[#4ADE80]"
        >
            <span v-if="verificationEmail">
                Enviamos un nuevo enlace de verificacion a <strong>{{ verificationEmail }}</strong>.
            </span>
            <span v-else>
                Enviamos un nuevo enlace de verificacion al correo que usaste al registrarte.
            </span>
        </div>

        <p
            v-if="verificationEmail"
            class="mb-6 text-center text-sm text-[#94A3B8]"
        >
            Si no lo ves en tu bandeja de entrada, revisa spam o promociones.
        </p>

        <Form
            v-bind="send.form()"
            class="space-y-6 text-center"
            v-slot="{ processing }"
        >
            <Button :disabled="processing" variant="secondary">
                <Spinner v-if="processing" />
                Reenviar correo de verificacion
            </Button>

            <TextLink
                :href="logout()"
                as="button"
                class="mx-auto block text-sm"
            >
                Cerrar sesion
            </TextLink>
        </Form>
    </AuthLayout>
</template>
