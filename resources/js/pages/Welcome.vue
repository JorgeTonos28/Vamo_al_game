<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    ChartColumn,
    Clock3,
    Coins,
    FileText,
    Medal,
    Receipt,
    ShieldCheck,
    Sparkles,
    Target,
    Trophy,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const landingRoot = ref<HTMLElement | null>(null);
let revealObserver: IntersectionObserver | null = null;

const heroStats = [
    {
        title: 'Miembros',
        value: '48',
        description:
            'Miembros, invitados y prioridades coordinados desde la llegada.',
        icon: Users,
    },
    {
        title: 'Jornadas',
        value: '136',
        description: 'Marcador, cola y tabla conectados en cada día de juego.',
        icon: CalendarDays,
    },
    {
        title: 'Cobros',
        value: 'RD$600',
        description:
            'Cuotas, invitados y recibos compartibles dentro del mismo flujo.',
        icon: Wallet,
    },
];

const featureBands = [
    {
        eyebrow: 'Operación del día',
        title: 'La cancha, la cola y el contador trabajando al mismo ritmo',
        description:
            'Vamo al Game organiza la llegada, forma equipos, registra puntos y mantiene visible quién juega, quién espera y cómo marcha la jornada.',
        items: [
            {
                title: 'Llegada',
                description:
                    'Marca miembros e invitados, valida pagos y activa la jornada manual, automática o por llegada.',
                icon: Clock3,
            },
            {
                title: 'Juego',
                description:
                    'Cuenta por equipo o por jugador, registra canastos de 1, 2 y 3 puntos y permite deshacer errores al instante.',
                icon: Target,
            },
            {
                title: 'Cola, stats y tabla',
                description:
                    'Ordena la fila activa, resume lo que pasa en cancha y mueve la clasificación del día sin perder contexto.',
                icon: ChartColumn,
            },
        ],
    },
    {
        eyebrow: 'Temporada y administración',
        title: 'La liga completa vive dentro del mismo sistema',
        description:
            'La operación diaria se conecta con temporada, cobros, miembros e historial para darle continuidad real al trabajo de la directiva.',
        items: [
            {
                title: 'Temporada',
                description:
                    'Acumula jornadas, perfiles, insignias, victorias e historial anual por jugador.',
                icon: Medal,
            },
            {
                title: 'Finanzas',
                description:
                    'Supervisa cortes, ingresos, gastos, membresías y pagos de invitados con lectura clara y recibos detallados.',
                icon: Coins,
            },
            {
                title: 'Gestión de miembros',
                description:
                    'Agrega, edita o elimina miembros y asigna número de chaqueta con control centralizado.',
                icon: Users,
            },
        ],
    },
    {
        eyebrow: 'Expansión competitiva',
        title: 'Más herramientas para dirigir una liga seria',
        description:
            'El producto sigue creciendo con módulos para scouting, torneos, hoja de anotación digital y reportes estratégicos listos para compartir.',
        items: [
            {
                title: 'Scout',
                description:
                    'Evalúa a cada jugador y ayuda a balancear equipos automáticamente con criterios de rendimiento.',
                icon: ShieldCheck,
            },
            {
                title: 'Torneo',
                description:
                    'Organiza torneos con seguimiento por juego, resultados y control competitivo más eficiente.',
                icon: Trophy,
            },
            {
                title: 'Hoja FIBA + Post',
                description:
                    'Digitaliza la planilla oficial y convierte la jornada en reportes útiles para capitanes y directiva.',
                icon: FileText,
            },
        ],
    },
];

const journeySteps = [
    {
        title: 'Llegada inteligente',
        badge: 'Miembros al día primero',
        description:
            'El sistema identifica quién llegó, quién pagó, quién entra como invitado y cómo se arma la cola desde el primer minuto.',
        icon: Clock3,
    },
    {
        title: 'Equipos a tu modo',
        badge: 'Manual, automático o por llegada',
        description:
            'La jornada puede arrancar con capitanes, con balance por scouting o respetando el orden exacto de llegada.',
        icon: ShieldCheck,
    },
    {
        title: 'Contador flexible',
        badge: 'Por equipo o por jugador',
        description:
            'Registra puntos de 1, 2 y 3, deshace acciones cuando hace falta y mantiene un historial breve de cada juego.',
        icon: Target,
    },
    {
        title: 'Cierre con continuidad',
        badge: 'Tabla, recibos y temporada',
        description:
            'La jornada cierra con tabla del día, rachas, anotadores, cobros, historial y estadísticas listas para seguir creciendo.',
        icon: Trophy,
    },
];

const modulePills = [
    'Llegada',
    'Juego',
    'Cola',
    'Stats',
    'Tabla',
    'Temporada',
    'Scout',
    'Torneo',
    'Hoja de anotación',
    'Post',
    'Finanzas',
    'Miembros',
];

const promoHighlights = [
    {
        title: 'Contador flexible',
        description:
            'Cuenta por equipo o por jugador, registra 1, 2 y 3 puntos y corrige errores sin romper el ritmo.',
        icon: Target,
    },
    {
        title: 'Cobros y recibos',
        description:
            'Valida cuotas e invitados, genera recibos detallados y deja el corte listo para compartir.',
        icon: Receipt,
    },
    {
        title: 'Temporada viva',
        description:
            'Cada jornada alimenta la historia completa de la liga con perfiles, insignias y reportes.',
        icon: Sparkles,
    },
];

const primaryFeatureBand = featureBands[0];
const extendedFeatureBands = featureBands.slice(1);

const closingSignals = [
    'Operación diaria en vivo',
    'Temporada conectada',
    'Cobros y recibos listos',
];

const syncParallax = () => {
    if (!landingRoot.value || typeof window === 'undefined') {
        return;
    }

    landingRoot.value.style.setProperty(
        '--landing-parallax-slow',
        `${Math.round(window.scrollY * 0.12)}px`,
    );
    landingRoot.value.style.setProperty(
        '--landing-parallax-fast',
        `${Math.round(window.scrollY * 0.2)}px`,
    );
};

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    const revealTargets =
        landingRoot.value?.querySelectorAll<HTMLElement>('[data-reveal]') ?? [];

    revealObserver = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) {
                    continue;
                }

                const element = entry.target as HTMLElement;

                if (element.dataset.reveal !== undefined) {
                    element.style.transitionDelay = `${Number(element.dataset.delay ?? 0)}ms`;
                    element.classList.add('is-visible');
                }

                revealObserver?.unobserve(element);
            }
        },
        {
            threshold: 0.18,
            rootMargin: '0px 0px -8% 0px',
        },
    );

    for (const target of revealTargets) {
        revealObserver.observe(target);
    }

    syncParallax();
    window.addEventListener('scroll', syncParallax, { passive: true });
});

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('scroll', syncParallax);
    }

    revealObserver?.disconnect();
});
</script>

<template>
    <Head title="Vamo al Game" />

    <div
        ref="landingRoot"
        class="landing-root relative overflow-hidden bg-[#0A0F1D] text-[#F8FAFC]"
    >
        <div class="landing-fixed-glow" />
        <div class="landing-grid-bg" />
        <div class="landing-orb landing-orb--gold" />
        <div class="landing-orb landing-orb--green" />

        <div class="relative z-10">
            <header class="landing-shell pt-5 md:pt-8">
                <div
                    class="flex items-center justify-between gap-4 rounded-full border border-white/6 bg-[rgba(10,15,29,0.72)] px-4 py-3 backdrop-blur-md"
                >
                    <Link
                        :href="$page.props.auth.user ? dashboard() : '/'"
                        class="shrink-0"
                    >
                        <AppLogo compact />
                    </Link>

                    <nav class="hidden items-center gap-6 lg:flex">
                        <a class="landing-top-link" href="#funciones"
                            >Funciones</a
                        >
                        <a class="landing-top-link" href="#flujo">Flujo</a>
                        <a class="landing-top-link" href="#modulos">Módulos</a>
                    </nav>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <Button
                            v-if="$page.props.auth.user"
                            as-child
                            size="sm"
                            class="min-h-10"
                        >
                            <Link :href="dashboard()">Abrir panel</Link>
                        </Button>

                        <template v-else>
                            <Button
                                as-child
                                variant="ghost"
                                size="sm"
                                class="min-h-10 text-[#F8FAFC] hover:bg-white/5"
                            >
                                <Link :href="login()">Entrar</Link>
                            </Button>

                            <Button
                                v-if="canRegister"
                                as-child
                                size="sm"
                                class="min-h-10"
                            >
                                <Link :href="register()">Crear cuenta</Link>
                            </Button>
                        </template>
                    </div>
                </div>
            </header>

            <main class="landing-shell pt-12 pb-20 md:pt-20 md:pb-24 xl:pt-24">
                <section
                    class="landing-hero-section grid gap-10 xl:grid-cols-[minmax(0,1.08fr)_minmax(440px,0.92fr)] xl:items-start xl:gap-14"
                >
                    <div class="relative space-y-10 xl:pr-8">
                        <div class="landing-hero-mesh" />
                        <div
                            class="landing-reveal landing-reveal-up landing-reveal-soft max-w-2xl space-y-5"
                            data-reveal
                        >
                            <p class="app-kicker text-[#E5B849]">
                                Gestión deportiva para ligas que juegan en serio
                            </p>
                            <h1
                                class="app-display max-w-[13ch] text-[52px] leading-[0.88] text-balance text-[#F8FAFC] sm:text-[72px] lg:text-[88px] 2xl:text-[104px]"
                            >
                                Organiza cada jornada sin perder el ritmo
                            </h1>
                            <p
                                class="max-w-xl text-[16px] leading-8 text-[#94A3B8] sm:text-[17px]"
                            >
                                Vamo al Game conecta llegada, equipos, contador,
                                cola, tabla, cobros y temporada en una
                                experiencia pensada para operar la liga con
                                velocidad, orden y claridad.
                            </p>
                        </div>

                        <div
                            class="landing-reveal landing-reveal-up flex flex-col gap-3 sm:flex-row"
                            data-delay="80"
                            data-reveal
                        >
                            <Button
                                v-if="$page.props.auth.user"
                                as-child
                                size="lg"
                                class="w-full sm:w-auto"
                            >
                                <Link :href="dashboard()">
                                    Abrir panel
                                    <ArrowRight class="ml-2 size-4" />
                                </Link>
                            </Button>

                            <template v-else>
                                <Button
                                    as-child
                                    size="lg"
                                    class="w-full sm:w-auto"
                                >
                                    <Link :href="login()">
                                        Entrar ahora
                                        <ArrowRight class="ml-2 size-4" />
                                    </Link>
                                </Button>
                                <Button
                                    v-if="canRegister"
                                    as-child
                                    size="lg"
                                    variant="secondary"
                                    class="w-full sm:w-auto"
                                >
                                    <Link :href="register()">Crear cuenta</Link>
                                </Button>
                            </template>
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <article
                                v-for="(stat, index) in heroStats"
                                :key="stat.title"
                                class="landing-reveal landing-reveal-scale landing-reveal-soft landing-showcase-card min-h-[188px] transition-transform duration-300 hover:-translate-y-1"
                                :data-delay="140 + index * 70"
                                data-reveal
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <p class="app-kicker text-[#E5B849]">
                                        {{ stat.title }}
                                    </p>
                                    <component
                                        :is="stat.icon"
                                        class="size-5 text-[#E5B849]"
                                    />
                                </div>
                                <p
                                    class="app-display mt-5 text-[54px] leading-none text-[#F8FAFC]"
                                >
                                    {{ stat.value }}
                                </p>
                                <p
                                    class="mt-4 text-[14px] leading-7 text-[#94A3B8]"
                                >
                                    {{ stat.description }}
                                </p>
                            </article>
                        </div>
                    </div>

                    <div class="space-y-5 xl:pt-6">
                        <div
                            class="landing-reveal landing-reveal-right landing-reveal-soft"
                            data-delay="120"
                            data-reveal
                        >
                            <article
                                class="landing-parallax-stack landing-showcase-card p-5 sm:p-6"
                            >
                                <div
                                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p class="app-kicker text-[#E5B849]">
                                            Operación en cancha
                                        </p>
                                        <h2
                                            class="app-display mt-3 text-[44px] leading-[0.9] text-[#F8FAFC] sm:text-[58px]"
                                        >
                                            Todo conectado desde la primera
                                            llegada
                                        </h2>
                                    </div>

                                    <span
                                        class="landing-chip landing-chip--positive"
                                    >
                                        Jornada lista
                                    </span>
                                </div>

                                <p
                                    class="mt-4 max-w-2xl text-[15px] leading-8 text-[#94A3B8]"
                                >
                                    El producto está diseñado para que la liga
                                    avance con menos improvisación: orden de
                                    llegada, equipos, contador flexible, cola
                                    activa, tabla del día y caja operando en el
                                    mismo flujo.
                                </p>

                                <div class="mt-6 grid gap-3 md:grid-cols-3">
                                    <div
                                        class="landing-mini-card landing-reveal landing-reveal-left"
                                        data-delay="220"
                                        data-reveal
                                    >
                                        <p class="app-kicker text-[#4ADE80]">
                                            18 listos
                                        </p>
                                        <p class="landing-mini-copy">
                                            Miembros al día con preferencia
                                            activa para entrar.
                                        </p>
                                    </div>
                                    <div
                                        class="landing-mini-card landing-reveal landing-reveal-up"
                                        data-delay="280"
                                        data-reveal
                                    >
                                        <p class="app-kicker text-[#E5B849]">
                                            4 invitados
                                        </p>
                                        <p class="landing-mini-copy">
                                            Integrados a la cola respetando el
                                            orden de juego.
                                        </p>
                                    </div>
                                    <div
                                        class="landing-mini-card landing-reveal landing-reveal-right"
                                        data-delay="340"
                                        data-reveal
                                    >
                                        <p class="app-kicker text-[#F8FAFC]">
                                            3 modos
                                        </p>
                                        <p class="landing-mini-copy">
                                            Equipos manuales, automáticos o por
                                            llegada.
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-6 grid gap-3 2xl:grid-cols-[minmax(0,1.05fr)_minmax(240px,0.95fr)]"
                                >
                                    <div
                                        class="landing-panel-card landing-reveal landing-reveal-left"
                                        data-delay="240"
                                        data-reveal
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <p
                                                class="app-kicker text-[#E5B849]"
                                            >
                                                Contador flexible
                                            </p>
                                            <Target
                                                class="size-4 text-[#E5B849]"
                                            />
                                        </div>
                                        <p
                                            class="mt-4 text-[15px] font-semibold text-[#F8FAFC]"
                                        >
                                            Por equipo o por jugador, con
                                            acciones reversibles en segundos.
                                        </p>
                                        <div
                                            class="mt-4 flex flex-wrap gap-2 text-[11px] font-semibold tracking-[0.08em] uppercase"
                                        >
                                            <span class="landing-tag"
                                                >1 punto</span
                                            >
                                            <span class="landing-tag"
                                                >2 puntos</span
                                            >
                                            <span class="landing-tag"
                                                >3 puntos</span
                                            >
                                            <span class="landing-tag"
                                                >Deshacer</span
                                            >
                                        </div>
                                    </div>

                                    <div
                                        class="landing-panel-card landing-reveal landing-reveal-right"
                                        data-delay="300"
                                        data-reveal
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <p
                                                class="app-kicker text-[#E5B849]"
                                            >
                                                Cobros y recibos
                                            </p>
                                            <Receipt
                                                class="size-4 text-[#E5B849]"
                                            />
                                        </div>

                                        <div class="mt-4 space-y-3">
                                            <div
                                                class="flex items-center justify-between text-[13px]"
                                            >
                                                <span class="text-[#94A3B8]">
                                                    Cuota validada
                                                </span>
                                                <span class="text-[#4ADE80]">
                                                    Confirmada
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center justify-between text-[13px]"
                                            >
                                                <span class="text-[#94A3B8]">
                                                    Recibo
                                                </span>
                                                <span class="text-[#F8FAFC]">
                                                    Listo para compartir
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center justify-between text-[13px]"
                                            >
                                                <span class="text-[#94A3B8]">
                                                    Corte
                                                </span>
                                                <span class="text-[#F8FAFC]">
                                                    RD$23.4K
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="landing-promo-band-wrap mt-6 xl:mt-8">
                    <div
                        class="landing-promo-band landing-reveal landing-reveal-up"
                        data-delay="220"
                        data-reveal
                    >
                        <article
                            class="landing-floating-card rounded-[24px] border border-white/6 bg-[rgba(14,22,40,0.82)] p-4"
                        >
                            <div class="grid gap-3 md:grid-cols-3">
                                <div
                                    v-for="(
                                        highlight, index
                                    ) in promoHighlights"
                                    :key="highlight.title"
                                    class="landing-reveal rounded-[20px] border border-white/6 bg-[rgba(19,27,47,0.88)] p-4"
                                    :class="
                                        index === 0
                                            ? 'landing-reveal-left'
                                            : index === 1
                                              ? 'landing-reveal-up'
                                              : 'landing-reveal-right'
                                    "
                                    :data-delay="260 + index * 70"
                                    data-reveal
                                >
                                    <component
                                        :is="highlight.icon"
                                        class="size-5 text-[#E5B849]"
                                    />
                                    <p class="app-kicker mt-4 text-[#F8FAFC]">
                                        {{ highlight.title }}
                                    </p>
                                    <p
                                        class="mt-3 text-[13px] leading-6 text-[#94A3B8]"
                                    >
                                        {{ highlight.description }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section
                    id="funciones"
                    class="landing-section mt-24 grid gap-10 xl:grid-cols-[360px_minmax(0,1fr)] xl:gap-14"
                >
                    <div
                        class="landing-reveal landing-reveal-left 2xl:sticky 2xl:top-28 2xl:self-start"
                        data-reveal
                    >
                        <p class="app-kicker text-[#E5B849]">
                            Módulos que hacen avanzar la liga
                        </p>
                        <h2
                            class="app-display mt-4 text-[46px] leading-[0.92] text-[#F8FAFC] sm:text-[58px]"
                        >
                            Menos fricción para jugar, cobrar y cerrar bien el
                            día
                        </h2>
                        <p class="mt-4 text-[15px] leading-8 text-[#94A3B8]">
                            La plataforma reúne la operación deportiva y el
                            control administrativo con una interfaz clara,
                            rápida y lista para crecer con la liga.
                        </p>

                        <div id="modulos" class="mt-6 flex flex-wrap gap-2">
                            <span
                                v-for="module in modulePills"
                                :key="module"
                                class="landing-module-pill"
                            >
                                {{ module }}
                            </span>
                        </div>
                    </div>

                    <article
                        class="landing-reveal landing-reveal-right landing-showcase-card p-5 sm:p-6"
                        data-delay="20"
                        data-reveal
                    >
                        <div
                            class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]"
                        >
                            <div class="space-y-3">
                                <p class="app-kicker text-[#E5B849]">
                                    {{ primaryFeatureBand.eyebrow }}
                                </p>
                                <h3
                                    class="app-display text-[36px] leading-[0.92] text-[#F8FAFC] sm:text-[42px]"
                                >
                                    {{ primaryFeatureBand.title }}
                                </h3>
                                <p class="text-[14px] leading-7 text-[#94A3B8]">
                                    {{ primaryFeatureBand.description }}
                                </p>
                            </div>

                            <div
                                class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3"
                            >
                                <article
                                    v-for="(
                                        item, itemIndex
                                    ) in primaryFeatureBand.items"
                                    :key="item.title"
                                    class="landing-feature-card landing-reveal"
                                    :class="
                                        itemIndex % 3 === 0
                                            ? 'landing-reveal-left'
                                            : itemIndex % 3 === 1
                                              ? 'landing-reveal-up'
                                              : 'landing-reveal-right'
                                    "
                                    :data-delay="110 + itemIndex * 70"
                                    data-reveal
                                >
                                    <div
                                        class="flex size-11 items-center justify-center rounded-2xl border border-white/6 bg-[#0E1628]"
                                    >
                                        <component
                                            :is="item.icon"
                                            class="size-5 text-[#E5B849]"
                                        />
                                    </div>
                                    <p class="app-kicker mt-4 text-[#F8FAFC]">
                                        {{ item.title }}
                                    </p>
                                    <p
                                        class="mt-3 text-[13px] leading-6 text-[#94A3B8]"
                                    >
                                        {{ item.description }}
                                    </p>
                                </article>
                            </div>
                        </div>
                    </article>
                </section>

                <section
                    class="landing-section mt-10 grid gap-8 xl:grid-cols-[360px_minmax(0,1fr)] xl:items-stretch xl:gap-14"
                >
                    <div class="landing-image-stage hidden xl:flex">
                        <div class="landing-image-frame">
                            <img
                                alt="Visual promocional de Vamo al Game"
                                class="landing-image-render"
                                src="/images/landing/league-visual.png"
                            />
                            <div class="landing-image-glow" />
                        </div>
                    </div>

                    <div class="space-y-4">
                        <article
                            v-for="(group, groupIndex) in extendedFeatureBands"
                            :key="group.title"
                            class="landing-reveal landing-showcase-card p-5 sm:p-6"
                            :class="
                                groupIndex % 2 === 0
                                    ? 'landing-reveal-right'
                                    : 'landing-reveal-up'
                            "
                            :data-delay="80 + groupIndex * 100"
                            data-reveal
                        >
                            <div
                                class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]"
                            >
                                <div class="space-y-3">
                                    <p class="app-kicker text-[#E5B849]">
                                        {{ group.eyebrow }}
                                    </p>
                                    <h3
                                        class="app-display text-[36px] leading-[0.92] text-[#F8FAFC] sm:text-[42px]"
                                    >
                                        {{ group.title }}
                                    </h3>
                                    <p
                                        class="text-[14px] leading-7 text-[#94A3B8]"
                                    >
                                        {{ group.description }}
                                    </p>
                                </div>

                                <div
                                    class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3"
                                >
                                    <article
                                        v-for="(item, itemIndex) in group.items"
                                        :key="item.title"
                                        class="landing-feature-card landing-reveal"
                                        :class="
                                            itemIndex % 3 === 0
                                                ? 'landing-reveal-left'
                                                : itemIndex % 3 === 1
                                                  ? 'landing-reveal-up'
                                                  : 'landing-reveal-right'
                                        "
                                        :data-delay="120 + itemIndex * 80"
                                        data-reveal
                                    >
                                        <div
                                            class="flex size-11 items-center justify-center rounded-2xl border border-white/6 bg-[#0E1628]"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="size-5 text-[#E5B849]"
                                            />
                                        </div>
                                        <p
                                            class="app-kicker mt-4 text-[#F8FAFC]"
                                        >
                                            {{ item.title }}
                                        </p>
                                        <p
                                            class="mt-3 text-[13px] leading-6 text-[#94A3B8]"
                                        >
                                            {{ item.description }}
                                        </p>
                                    </article>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section
                    id="flujo"
                    class="landing-section mt-24 grid gap-8 xl:grid-cols-[minmax(0,0.88fr)_minmax(0,1.12fr)] xl:gap-12"
                >
                    <article
                        class="landing-reveal landing-reveal-left landing-showcase-card 2xl:sticky 2xl:top-28 2xl:self-start"
                        data-reveal
                    >
                        <p class="app-kicker text-[#E5B849]">
                            Una jornada completa, sin huecos
                        </p>
                        <h2
                            class="app-display mt-4 text-[46px] leading-[0.92] text-[#F8FAFC] sm:text-[58px]"
                        >
                            Desde la primera llegada hasta el cierre del día
                        </h2>
                        <p class="mt-4 text-[15px] leading-8 text-[#94A3B8]">
                            Vamo al Game acompaña cada tramo de la jornada con
                            decisiones claras, información viva y continuidad
                            real hacia la temporada.
                        </p>

                        <div class="mt-6 grid gap-3 sm:grid-cols-2">
                            <div class="landing-panel-card">
                                <p class="app-kicker text-[#E5B849]">
                                    Scout inteligente
                                </p>
                                <p class="landing-mini-copy mt-3">
                                    Perfiles y ratings para balancear equipos
                                    con más criterio competitivo.
                                </p>
                            </div>
                            <div class="landing-panel-card">
                                <p class="app-kicker text-[#E5B849]">
                                    Hoja FIBA digital
                                </p>
                                <p class="landing-mini-copy mt-3">
                                    Captura oficial, exportación y registro
                                    histórico dentro de la misma plataforma.
                                </p>
                            </div>
                        </div>
                    </article>

                    <div class="landing-timeline space-y-3">
                        <article
                            v-for="(step, index) in journeySteps"
                            :key="step.title"
                            class="landing-step landing-reveal landing-reveal-right rounded-[26px] border border-white/6 bg-[rgba(19,27,47,0.92)] p-5 sm:p-6"
                            :data-delay="index * 90"
                            data-reveal
                        >
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex size-12 shrink-0 items-center justify-center rounded-2xl border border-white/6 bg-[#0E1628]"
                                >
                                    <component
                                        :is="step.icon"
                                        class="size-5 text-[#E5B849]"
                                    />
                                </div>

                                <div class="space-y-3">
                                    <div
                                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <p class="app-kicker text-[#F8FAFC]">
                                            {{ step.title }}
                                        </p>
                                        <span
                                            class="landing-chip landing-chip--neutral"
                                        >
                                            {{ step.badge }}
                                        </span>
                                    </div>
                                    <p
                                        class="text-[14px] leading-7 text-[#94A3B8]"
                                    >
                                        {{ step.description }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="landing-section mt-24">
                    <article
                        class="rounded-[34px] border border-white/6 bg-[linear-gradient(135deg,rgba(19,27,47,0.96),rgba(14,22,40,0.9))] p-6 sm:p-8 lg:p-10"
                    >
                        <div
                            class="mx-auto flex max-w-4xl flex-col items-center gap-6 text-center"
                        >
                            <div class="max-w-2xl space-y-3">
                                <p class="app-kicker text-[#E5B849]">
                                    Diseñada para crecer con tu liga
                                </p>
                                <h2
                                    class="app-display text-[44px] leading-[0.9] text-[#F8FAFC] sm:text-[58px]"
                                >
                                    Una plataforma lista para operar hoy y
                                    escalar mañana
                                </h2>
                                <p class="text-[15px] leading-8 text-[#94A3B8]">
                                    Vamo al Game reúne operación deportiva,
                                    control financiero, temporada e inteligencia
                                    competitiva en una experiencia moderna y
                                    profesional.
                                </p>
                            </div>

                            <div class="flex flex-wrap justify-center gap-2">
                                <span
                                    v-for="signal in closingSignals"
                                    :key="signal"
                                    class="landing-module-pill"
                                >
                                    {{ signal }}
                                </span>
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row">
                                <Button
                                    v-if="$page.props.auth.user"
                                    as-child
                                    size="lg"
                                    class="w-full sm:w-auto"
                                >
                                    <Link :href="dashboard()">Abrir panel</Link>
                                </Button>

                                <template v-else>
                                    <Button
                                        as-child
                                        size="lg"
                                        class="w-full sm:w-auto"
                                    >
                                        <Link :href="login()"
                                            >Entrar ahora</Link
                                        >
                                    </Button>
                                    <Button
                                        v-if="canRegister"
                                        as-child
                                        size="lg"
                                        variant="secondary"
                                        class="w-full sm:w-auto"
                                    >
                                        <Link :href="register()"
                                            >Crear cuenta</Link
                                        >
                                    </Button>
                                </template>
                            </div>
                        </div>
                    </article>
                </section>
            </main>
        </div>
    </div>
</template>

<style scoped>
.landing-root {
    --landing-parallax-slow: 0px;
    --landing-parallax-fast: 0px;
    isolation: isolate;
}

.landing-fixed-glow {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    background:
        radial-gradient(
            circle at top,
            rgba(74, 222, 128, 0.12),
            transparent 26%
        ),
        radial-gradient(
            circle at bottom,
            rgba(229, 184, 73, 0.14),
            transparent 32%
        ),
        #0a0f1d;
}

.landing-shell {
    width: min(100%, 1440px);
    margin-inline: auto;
    padding-inline: clamp(20px, 3.6vw, 48px);
}

.landing-section {
    position: relative;
}

.landing-promo-band {
    width: 100%;
    max-width: 980px;
    margin-inline: auto;
}

.landing-promo-band-wrap {
    display: flex;
    justify-content: center;
    width: 100%;
}

.landing-hero-section {
    position: relative;
}

.landing-hero-mesh {
    position: absolute;
    top: -32px;
    left: -12px;
    width: min(100%, 620px);
    height: clamp(320px, 44vw, 520px);
    border-radius: 40px;
    background-image:
        radial-gradient(
            circle at top left,
            rgba(229, 184, 73, 0.1),
            transparent 42%
        ),
        linear-gradient(rgba(229, 184, 73, 0.09) 1px, transparent 1px),
        linear-gradient(90deg, rgba(229, 184, 73, 0.09) 1px, transparent 1px);
    background-size: 24px 24px;
    mask-image: radial-gradient(circle at center, black 62%, transparent 100%);
    box-shadow:
        inset 0 0 0 1px rgba(229, 184, 73, 0.08),
        0 0 36px rgba(229, 184, 73, 0.04);
    opacity: 0.56;
    transform: translate3d(
        calc(var(--landing-parallax-slow) * -0.02),
        calc(var(--landing-parallax-slow) * -0.04),
        0
    );
    pointer-events: none;
}

.landing-grid-bg {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
    background-position: center;
    background-size: 72px 72px;
    mask-image: radial-gradient(circle at center, black 48%, transparent 100%);
    opacity: 0.14;
    transform: translate3d(0, calc(var(--landing-parallax-slow) * -0.08), 0);
}

.landing-orb {
    position: absolute;
    border-radius: 999px;
    filter: blur(80px);
    pointer-events: none;
    opacity: 0.75;
}

.landing-orb--gold {
    top: 48px;
    right: -120px;
    width: 320px;
    height: 320px;
    background: rgba(229, 184, 73, 0.16);
    animation: landing-float 10s ease-in-out infinite;
}

.landing-orb--green {
    bottom: 120px;
    left: -120px;
    width: 300px;
    height: 300px;
    background: rgba(74, 222, 128, 0.14);
    animation: landing-float 12s ease-in-out infinite reverse;
}

.landing-top-link {
    position: relative;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #94a3b8;
    transition:
        color 0.2s ease,
        transform 0.2s ease;
}

.landing-top-link:hover {
    color: #f8fafc;
    transform: translate3d(0, -1px, 0);
}

.landing-parallax-stack {
    transform: translate3d(0, calc(var(--landing-parallax-slow) * -0.06), 0);
}

.landing-floating-card {
    transform: translate3d(0, calc(var(--landing-parallax-fast) * -0.04), 0);
}

.landing-chip {
    display: inline-flex;
    min-height: 36px;
    align-items: center;
    border-radius: 999px;
    padding: 0 14px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.landing-chip--positive {
    border: 1px solid rgba(74, 222, 128, 0.18);
    background: rgba(74, 222, 128, 0.12);
    color: #4ade80;
}

.landing-chip--neutral {
    border: 1px solid rgba(229, 184, 73, 0.16);
    background: rgba(229, 184, 73, 0.08);
    color: #e5b849;
}

.landing-mini-card,
.landing-panel-card,
.landing-feature-card {
    border: 1px solid rgba(255, 255, 255, 0.06);
    background: rgba(14, 22, 40, 0.84);
}

.landing-mini-card {
    border-radius: 22px;
    padding: 16px;
}

.landing-panel-card {
    border-radius: 24px;
    padding: 18px;
}

.landing-feature-card {
    border-radius: 24px;
    padding: 18px;
    transition:
        transform 0.24s ease,
        border-color 0.24s ease,
        background 0.24s ease;
}

.landing-feature-card:hover {
    transform: translate3d(0, -4px, 0);
    border-color: rgba(229, 184, 73, 0.14);
    background: rgba(18, 29, 50, 0.96);
}

.landing-mini-copy {
    color: #94a3b8;
    font-size: 13px;
    line-height: 1.7;
}

.landing-image-stage {
    position: relative;
    min-height: 100%;
    align-items: stretch;
    justify-content: stretch;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: 34px;
    padding: 22px 16px 0;
    background:
        radial-gradient(
            circle at 50% 24%,
            rgba(229, 184, 73, 0.14),
            transparent 30%
        ),
        linear-gradient(180deg, rgba(19, 27, 47, 0.82), rgba(10, 15, 29, 0.92));
}

.landing-image-frame {
    position: relative;
    width: 100%;
    min-height: 100%;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 28px;
    border: 1px solid rgba(255, 255, 255, 0.04);
    background:
        radial-gradient(
            circle at top,
            rgba(229, 184, 73, 0.08),
            transparent 34%
        ),
        linear-gradient(180deg, rgba(19, 27, 47, 0.34), rgba(10, 15, 29, 0));
    overflow: hidden;
}

.landing-image-render {
    position: relative;
    z-index: 1;
    width: 100%;
    height: 100%;
    max-height: 100%;
    object-fit: contain;
    object-position: center center;
    display: block;
}

.landing-image-glow {
    position: absolute;
    inset: 0;
    background: radial-gradient(
        circle at 50% 18%,
        rgba(229, 184, 73, 0.08),
        transparent 28%
    );
    pointer-events: none;
}

.landing-tag,
.landing-module-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(19, 27, 47, 0.88);
    color: #f8fafc;
}

.landing-tag {
    min-height: 30px;
    padding: 0 12px;
}

.landing-module-pill {
    min-height: 34px;
    padding: 0 14px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #cbd5e1;
    transition:
        transform 0.24s ease,
        border-color 0.24s ease,
        color 0.24s ease;
}

.landing-module-pill:hover {
    transform: translate3d(0, -2px, 0);
    border-color: rgba(229, 184, 73, 0.18);
    color: #f8fafc;
}

.landing-timeline {
    position: relative;
    padding-left: 18px;
}

.landing-timeline::before {
    position: absolute;
    top: 8px;
    bottom: 8px;
    left: 0;
    width: 1px;
    background: linear-gradient(
        180deg,
        rgba(229, 184, 73, 0.24),
        rgba(148, 163, 184, 0.12)
    );
    content: '';
}

.landing-step {
    position: relative;
}

.landing-step::before {
    position: absolute;
    top: 26px;
    left: -22px;
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #e5b849;
    box-shadow: 0 0 0 6px rgba(229, 184, 73, 0.12);
    content: '';
    opacity: 0.48;
    transition:
        opacity 0.35s ease,
        box-shadow 0.35s ease,
        transform 0.35s ease;
}

.landing-reveal {
    opacity: 0;
    transition:
        opacity 0.8s ease,
        transform 0.9s cubic-bezier(0.16, 1, 0.3, 1);
}

.landing-reveal-soft {
    filter: blur(12px);
    transition:
        opacity 0.8s ease,
        transform 0.9s cubic-bezier(0.16, 1, 0.3, 1),
        filter 0.8s ease;
}

.landing-reveal-up {
    transform: translate3d(0, 36px, 0);
}

.landing-reveal-left {
    transform: translate3d(-40px, 30px, 0);
}

.landing-reveal-right {
    transform: translate3d(40px, 30px, 0);
}

.landing-reveal-scale {
    transform: translate3d(0, 28px, 0) scale(0.94);
}

.landing-reveal.is-visible {
    opacity: 1;
    transform: translate3d(0, 0, 0) scale(1);
}

.landing-reveal-soft.is-visible {
    filter: blur(0);
}

.landing-step.is-visible::before {
    opacity: 1;
    transform: scale(1.12);
    box-shadow:
        0 0 0 6px rgba(229, 184, 73, 0.12),
        0 0 18px rgba(229, 184, 73, 0.38);
    animation: landing-dot-pulse 1.6s ease-in-out infinite;
}

@keyframes landing-float {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }

    50% {
        transform: translate3d(0, -24px, 0);
    }
}

@keyframes landing-dot-pulse {
    0%,
    100% {
        opacity: 1;
        box-shadow:
            0 0 0 6px rgba(229, 184, 73, 0.12),
            0 0 14px rgba(229, 184, 73, 0.28);
    }

    50% {
        opacity: 0.72;
        box-shadow:
            0 0 0 8px rgba(229, 184, 73, 0.08),
            0 0 24px rgba(229, 184, 73, 0.42);
    }
}

@media (max-width: 1023px) {
    .landing-timeline {
        padding-left: 0;
    }

    .landing-timeline::before,
    .landing-step::before {
        content: none;
    }

    .landing-hero-mesh {
        width: 100%;
        height: 300px;
        left: 0;
        opacity: 0.18;
    }
}

@media (max-width: 1279px) {
    .landing-parallax-stack,
    .landing-floating-card {
        transform: none;
    }
}

@media (min-width: 1280px) {
    .landing-promo-band {
        width: min(980px, calc(100vw - 96px));
        margin-inline: auto;
    }
}

@media (min-width: 1440px) {
    .landing-section {
        margin-top: 8rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .landing-grid-bg,
    .landing-hero-mesh,
    .landing-parallax-stack,
    .landing-floating-card,
    .landing-reveal,
    .landing-orb,
    .landing-step::before {
        animation: none !important;
        transition: none !important;
        transform: none !important;
        filter: none !important;
        opacity: 1 !important;
    }
}
</style>
