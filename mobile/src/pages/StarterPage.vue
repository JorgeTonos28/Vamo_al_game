<script setup lang="ts">
import { IonContent, IonPage } from '@ionic/vue';
import { onBeforeUnmount, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import BrandLogo from '@/components/BrandLogo.vue';
import { hydrateSessionState } from '@/state/session';

const router = useRouter();

let starterTimeout: ReturnType<typeof setTimeout> | null = null;

onMounted(async () => {
    await hydrateSessionState();

    starterTimeout = setTimeout(async () => {
        await router.replace({ name: 'login' });
    }, 1500);
});

onBeforeUnmount(() => {
    if (starterTimeout) {
        clearTimeout(starterTimeout);
    }
});
</script>

<template>
    <IonPage>
        <IonContent :fullscreen="true">
            <div class="starter-shell">
                <div class="starter-glow starter-glow--gold" />
                <div class="starter-glow starter-glow--green" />

                <div class="starter-card">
                    <div class="starter-logo-wrap">
                        <BrandLogo centered />
                    </div>

                    <p class="starter-tag">
                        Gestión inteligente para ligas que juegan en serio
                    </p>
                </div>
            </div>
        </IonContent>
    </IonPage>
</template>

<style scoped>
.starter-shell {
    position: relative;
    display: flex;
    min-height: 100svh;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 24px;
    background:
        radial-gradient(
            circle at top,
            rgba(229, 184, 73, 0.16),
            transparent 36%
        ),
        radial-gradient(
            circle at bottom,
            rgba(74, 222, 128, 0.14),
            transparent 32%
        ),
        #0a0f1d;
}

.starter-glow {
    position: absolute;
    border-radius: 999px;
    filter: blur(40px);
    opacity: 0.9;
    animation: starter-float 5.4s ease-in-out infinite;
}

.starter-glow--gold {
    top: 16%;
    left: -12%;
    height: 220px;
    width: 220px;
    background: rgba(229, 184, 73, 0.2);
}

.starter-glow--green {
    right: -18%;
    bottom: 12%;
    height: 240px;
    width: 240px;
    background: rgba(74, 222, 128, 0.14);
    animation-direction: reverse;
}

.starter-card {
    position: relative;
    display: flex;
    width: min(100%, 320px);
    flex-direction: column;
    align-items: center;
    gap: 20px;
    text-align: center;
}

.starter-logo-wrap {
    animation:
        starter-rise 0.85s cubic-bezier(0.16, 1, 0.3, 1) forwards,
        starter-pulse 2.4s ease-in-out 0.8s infinite;
}

.starter-tag {
    margin: 0;
    max-width: 18rem;
    font-size: 13px;
    line-height: 1.7;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #94a3b8;
    opacity: 0;
    animation: starter-fade 0.8s ease-out 0.35s forwards;
}

@keyframes starter-rise {
    from {
        opacity: 0;
        transform: translate3d(0, 24px, 0) scale(0.92);
    }

    to {
        opacity: 1;
        transform: translate3d(0, 0, 0) scale(1);
    }
}

@keyframes starter-fade {
    from {
        opacity: 0;
        transform: translate3d(0, 12px, 0);
    }

    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

@keyframes starter-pulse {
    0%,
    100% {
        transform: scale(1);
    }

    50% {
        transform: scale(1.03);
    }
}

@keyframes starter-float {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }

    50% {
        transform: translate3d(0, -18px, 0);
    }
}
</style>
