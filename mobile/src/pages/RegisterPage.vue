<script setup lang="ts">
import {
    IonButton,
    IonContent,
    IonInput,
    IonItem,
    IonLabel,
    IonPage,
    IonSpinner,
    IonText,
} from '@ionic/vue';
import type { AxiosError } from 'axios';
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import BrandLogo from '@/components/BrandLogo.vue';
import { googleAuthUrl, register } from '@/services/auth';
import type { ErrorResponse, RegisterPayload } from '@/types/api';

const router = useRouter();

const form = reactive<RegisterPayload>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});

const canSubmit = computed(
    () =>
        form.name.trim() !== '' &&
        form.email.trim() !== '' &&
        form.password.trim() !== '' &&
        form.password_confirmation.trim() !== '',
);

function firstFieldError(field: string): string | null {
    return fieldErrors.value[field]?.[0] ?? null;
}

async function submit(): Promise<void> {
    if (!canSubmit.value) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = null;
    fieldErrors.value = {};

    try {
        await register(form);
        await router.replace({ name: 'login', query: { registered: '1' } });
    } catch (error) {
        const response = (error as AxiosError<ErrorResponse>).response?.data;

        errorMessage.value =
            response?.message ?? 'No fue posible crear la cuenta.';
        fieldErrors.value = response?.errors ?? {};
    } finally {
        isSubmitting.value = false;
    }
}

function continueWithGoogle(): void {
    window.location.href = googleAuthUrl();
}
</script>

<template>
    <IonPage>
        <IonContent :fullscreen="true">
            <div class="mobile-shell">
                <div class="mobile-stack">
                    <section class="app-surface auth-hero">
                        <BrandLogo compact />
                        <p class="app-kicker auth-brand">Crear cuenta</p>
                        <h1 class="app-display auth-title">Activa tu cuenta</h1>
                        <p class="app-body-copy">
                            Registra tu cuenta y verifica tu correo antes de
                            entrar al panel de la liga.
                        </p>
                    </section>

                    <section class="app-surface auth-form">
                        <div class="field-group">
                            <IonLabel position="stacked">Nombre</IonLabel>
                            <IonItem lines="none">
                                <IonInput
                                    v-model="form.name"
                                    autocomplete="name"
                                    placeholder="Nombre completo"
                                />
                            </IonItem>
                            <p
                                v-if="firstFieldError('name')"
                                class="field-error"
                            >
                                {{ firstFieldError('name') }}
                            </p>
                        </div>

                        <div class="field-group">
                            <IonLabel position="stacked">Correo</IonLabel>
                            <IonItem lines="none">
                                <IonInput
                                    v-model="form.email"
                                    autocomplete="email"
                                    inputmode="email"
                                    placeholder="email@example.com"
                                    type="email"
                                />
                            </IonItem>
                            <p
                                v-if="firstFieldError('email')"
                                class="field-error"
                            >
                                {{ firstFieldError('email') }}
                            </p>
                        </div>

                        <div class="field-group">
                            <IonLabel position="stacked">Contraseña</IonLabel>
                            <IonItem lines="none">
                                <IonInput
                                    v-model="form.password"
                                    autocomplete="new-password"
                                    placeholder="Crea una contraseña"
                                    type="password"
                                />
                            </IonItem>
                            <p
                                v-if="firstFieldError('password')"
                                class="field-error"
                            >
                                {{ firstFieldError('password') }}
                            </p>
                        </div>

                        <div class="field-group">
                            <IonLabel position="stacked"
                                >Confirmar contraseña</IonLabel
                            >
                            <IonItem lines="none">
                                <IonInput
                                    v-model="form.password_confirmation"
                                    autocomplete="new-password"
                                    placeholder="Repite tu contraseña"
                                    type="password"
                                />
                            </IonItem>
                            <p
                                v-if="firstFieldError('password_confirmation')"
                                class="field-error"
                            >
                                {{ firstFieldError('password_confirmation') }}
                            </p>
                        </div>

                        <IonText v-if="errorMessage" color="danger">
                            <p class="auth-error">{{ errorMessage }}</p>
                        </IonText>

                        <IonButton
                            :disabled="!canSubmit || isSubmitting"
                            class="auth-button"
                            expand="block"
                            @click="submit"
                        >
                            <IonSpinner v-if="isSubmitting" name="crescent" />
                            <span v-else>Crear cuenta</span>
                        </IonButton>

                        <IonButton
                            class="google-action"
                            color="light"
                            expand="block"
                            fill="outline"
                            @click="continueWithGoogle"
                        >
                            Crear cuenta con Google
                        </IonButton>

                        <IonButton
                            class="secondary-action"
                            color="secondary"
                            expand="block"
                            @click="router.push({ name: 'login' })"
                        >
                            Ya tengo cuenta
                        </IonButton>
                    </section>
                </div>
            </div>
        </IonContent>
    </IonPage>
</template>

<style scoped>
.auth-hero,
.auth-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.auth-brand {
    color: #e5b849;
}

.auth-title {
    margin: 0;
    font-size: 52px;
    line-height: 0.9;
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field-error,
.auth-error {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    color: #f87171;
}

.auth-button,
.secondary-action {
    margin: 0;
}

.google-action {
    margin: 0;
    --border-color: rgba(255, 255, 255, 0.08);
    --color: #f8fafc;
}
</style>
