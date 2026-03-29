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
    useIonRouter,
} from '@ionic/vue';
import type { AxiosError } from 'axios';
import { computed, nextTick, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BrandLogo from '@/components/BrandLogo.vue';
import { defaultAuthenticatedRoutePath } from '@/lib/session-routes';
import { fetchCurrentUser, googleAuthUrl, login } from '@/services/auth';
import type { ErrorResponse, LoginPayload } from '@/types/api';

const router = useRouter();
const route = useRoute();
const ionRouter = useIonRouter();

const form = reactive<LoginPayload>({
    email: '',
    password: '',
    device_name: 'Ionic Dev',
});

const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);

const canSubmit = computed(
    () => form.email.trim() !== '' && form.password.trim() !== '',
);
const statusMessage = computed(() =>
    route.query.registered === '1'
        ? 'Cuenta creada. Revisa tu correo, verifica tu email y luego vuelve a entrar.'
        : null,
);

async function submit(): Promise<void> {
    if (!canSubmit.value) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = null;

    try {
        const result = await login({
            ...form,
            email: form.email.trim().toLowerCase(),
        });

        if (result.kind === 'two-factor') {
            ionRouter.navigate(
                `/two-factor-challenge?challenge=${encodeURIComponent(result.response.data.challenge_token)}`,
                'root',
                'replace',
            );

            return;
        }

        await fetchCurrentUser();
        await nextTick();
        ionRouter.navigate(defaultAuthenticatedRoutePath(), 'root', 'replace');
    } catch (error) {
        const response = (error as AxiosError<ErrorResponse>).response?.data;

        errorMessage.value =
            response?.errors?.email?.[0] ??
            response?.message ??
            'No fue posible iniciar sesión.';
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
                        <p class="app-kicker auth-brand">Entrar a tu cuenta</p>
                        <h1 class="app-display auth-title">Acceso móvil</h1>
                        <p class="app-body-copy">
                            Accede con tu correo y contraseña. Toda cuenta debe
                            verificar el email antes de entrar al sistema.
                        </p>
                    </section>

                    <section class="app-surface auth-form">
                        <IonText v-if="statusMessage" color="warning">
                            <p class="status-message">{{ statusMessage }}</p>
                        </IonText>

                        <div class="field-group">
                            <IonLabel position="stacked">Correo</IonLabel>
                            <IonItem lines="none">
                                <IonInput
                                    v-model="form.email"
                                    autocomplete="email"
                                    autocapitalize="off"
                                    autocorrect="off"
                                    inputmode="email"
                                    placeholder="demo@vamoalgame.test"
                                    spellcheck="false"
                                    type="email"
                                />
                            </IonItem>
                        </div>

                        <div class="field-group">
                            <IonLabel position="stacked">Contraseña</IonLabel>
                            <IonItem lines="none">
                                <IonInput
                                    v-model="form.password"
                                    autocomplete="current-password"
                                    placeholder="password"
                                    type="password"
                                />
                            </IonItem>
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
                            <span v-else>Iniciar sesión</span>
                        </IonButton>

                        <IonButton
                            class="google-action"
                            color="light"
                            expand="block"
                            fill="outline"
                            @click="continueWithGoogle"
                        >
                            Continuar con Google
                        </IonButton>

                        <IonButton
                            class="secondary-action"
                            color="secondary"
                            expand="block"
                            @click="router.push({ name: 'register' })"
                        >
                            Crear cuenta
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

.auth-error {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
}

.auth-button {
    margin: 0;
}

.secondary-action {
    margin-top: 8px;
}

.google-action {
    margin: 0;
    --border-color: rgba(255, 255, 255, 0.08);
    --color: #f8fafc;
}

.status-message {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    color: #e5b849;
}
</style>
