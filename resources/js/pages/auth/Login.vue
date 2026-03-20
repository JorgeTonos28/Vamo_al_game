<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import GoogleLogo from '@/components/GoogleLogo.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthBase
        title="Entrar a tu cuenta"
        description="Accede con tu correo y contrasena. Toda cuenta debe verificar el email antes de entrar al sistema."
    >
        <Head title="Iniciar sesion" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-[#E5B849]"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Correo electronico</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Contrasena</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="5"
                        >
                            Olvide mi contrasena
                        </TextLink>
                    </div>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Tu contrasena"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Recordarme</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Entrar
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
                        href="/auth/google/redirect"
                        class="inline-flex items-center justify-center gap-3"
                    >
                        <GoogleLogo />
                        <span>Continuar con Google</span>
                    </a>
                </Button>
            </div>

            <div
                class="text-center text-sm text-muted-foreground"
                v-if="canRegister"
            >
                Aun no tienes cuenta?
                <TextLink :href="register()" :tabindex="5">Crear cuenta</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
