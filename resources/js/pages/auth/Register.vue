<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import GoogleLogo from '@/components/GoogleLogo.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
</script>

<template>
    <AuthBase
        title="Crear cuenta"
        description="Registra tu cuenta y verifica tu correo antes de entrar al panel de la liga."
    >
        <Head title="Crear cuenta" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Nombre</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="Nombre completo"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Correo electronico</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Contrasena</Label>
                    <PasswordInput
                        id="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Crea una contrasena"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmar contrasena</Label>
                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Repite tu contrasena"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="5"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Crear cuenta
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
                        <span>Crear cuenta con Google</span>
                    </a>
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Ya tienes cuenta?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="6"
                    >Entrar</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
