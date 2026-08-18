<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useAppearance } from '@/composables/useAppearance';
import { dashboard, login, register } from '@/routes';

const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};
</script>

<template>
    <Head title="Dile adiós al cuaderno de reparaciones" />

    <div class="landing">
        <header class="header">
            <div class="header__inner container">
                <a href="#top" class="header__brand">
                    <!-- <span class="header__brand-logo">Logo</span> -->
                    <span>
                        <span class="header__brand-repair">Repair</span
                        ><span class="header__brand-track">Track</span>
                    </span>
                </a>

                <nav class="header__nav">
                    <a class="header__nav-link" href="#problema">El problema</a>
                    <a class="header__nav-link" href="#funciona"
                        >Cómo funciona</a
                    >
                    <a class="header__nav-link" href="#precio">Precio</a>
                    <a class="header__nav-link" href="#faq">Preguntas</a>
                </nav>

                <div class="header__actions">
                    <button
                        class="header__theme-toggle"
                        type="button"
                        :aria-label="
                            resolvedAppearance === 'dark'
                                ? 'Cambiar a modo claro'
                                : 'Cambiar a modo oscuro'
                        "
                        @click="toggleTheme"
                    >
                        <svg
                            class="header__theme-icon--light"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                        >
                            <circle cx="12" cy="12" r="4" />
                            <path
                                d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"
                            />
                        </svg>
                        <svg
                            class="header__theme-icon--dark"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                        >
                            <path
                                d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"
                            />
                        </svg>
                    </button>

                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="btn btn--ghost btn--sm header__cta"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="btn btn--ghost btn--sm header__cta"
                        >
                            Iniciar sesión
                        </Link>
                        <a
                            href="#precio"
                            class="btn btn--primary btn--sm header__cta"
                        >
                            Empieza por $3/mes
                        </a>
                    </template>
                </div>
            </div>
        </header>

        <section class="hero" id="top">
            <div class="hero__inner container">
                <div class="hero__copy">
                    <span class="hero__eyebrow"
                        >Para el que repara, no para el taller entero</span
                    >
                    <h1 class="hero__title">
                        Ese cuaderno con el celular de un cliente
                        <span class="hero__title-accent">de hace 6 meses</span>
                        ya no tiene que existir.
                    </h1>
                    <p class="hero__subtitle">
                        RepairTrack ordena cada equipo que te dejan a ti: quién
                        lo dejó, en qué va y cuándo lo retira.
                        <strong>Un poquito de orden nunca está de más</strong>
                        — y tus clientes dejan de llamarte cada dos días para
                        preguntar.
                    </p>
                    <div class="hero__actions">
                        <a href="#precio" class="btn btn--primary"
                            >Quiero ordenar mis reparaciones — $3/mes</a
                        >
                        <a href="#funciona" class="btn btn--ghost"
                            >Ver cómo funciona</a
                        >
                    </div>
                    <p class="hero__fineprint">
                        Sin contratos raros. Cancelas cuando quieras. Tu marca,
                        no la nuestra.
                    </p>
                </div>

                <div class="hero__visual">
                    <div class="hero__stack">
                        <div class="notebook">
                            <span class="notebook__label">Método actual</span>
                            <div class="notebook__line">
                                Sra. Carmen — tablet — dejó feb??
                            </div>
                            <div class="notebook__line">
                                iPhone roto - Jose - $$ x confirmar
                            </div>
                            <div class="notebook__line">
                                PS4 no prende!! — ??? — urgente
                            </div>
                        </div>

                        <div class="hero__swap-arrow" aria-hidden="true">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.4"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </div>

                        <div class="ticket">
                            <div class="ticket__top">
                                <span class="ticket__id">#RT-0142</span>
                                <span class="ticket__status"
                                    >En reparación</span
                                >
                            </div>
                            <div class="ticket__device">
                                iPhone 12 — Carmen R.
                            </div>
                            <div class="ticket__track">
                                <span class="ticket__step ticket__step--done" />
                                <span class="ticket__bar" />
                                <span
                                    class="ticket__step ticket__step--current"
                                />
                                <span class="ticket__bar" />
                                <span class="ticket__step" />
                                <span class="ticket__bar" />
                                <span class="ticket__step" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="trust container">
            <p class="trust__label">
                Hecho para el que repara consolas, el que repara teléfonos, el
                que repara laptops — aunque compartan el mismo mostrador. Y
                cualquier cosa que la gente deje "por un ratico" y venga a
                buscar en tres meses.
            </p>
        </div>

        <section class="section section--alt" id="problema">
            <div class="container">
                <div class="section__head">
                    <span class="section__eyebrow">¿Te suena familiar?</span>
                    <h2 class="section__title">
                        Tú no tienes un problema de reparación. Tienes un
                        problema de memoria.
                    </h2>
                    <p class="section__subtitle">
                        No es que arregles mal los equipos — es que nadie, ni
                        tú, se acuerda de dónde quedó cada historia.
                    </p>
                </div>
                <div class="pain__grid">
                    <div class="pain__card">
                        <span class="pain__card-icon">📓</span>
                        <div>
                            <div class="pain__card-title">
                                "¿Y esa hoja dónde la puse?"
                            </div>
                            <p class="pain__card-text">
                                El cuaderno de las reparaciones desapareció otra
                                vez, y con él, el número del cliente que dejó la
                                tablet.
                            </p>
                        </div>
                    </div>
                    <div class="pain__card">
                        <span class="pain__card-icon">📞</span>
                        <div>
                            <div class="pain__card-title">
                                "¿Cómo va mi teléfono?"
                            </div>
                            <p class="pain__card-text">
                                Tres llamadas al día del mismo cliente,
                                preguntando lo mismo, porque no tiene forma de
                                saberlo sin molestarte.
                            </p>
                        </div>
                    </div>
                    <div class="pain__card">
                        <span class="pain__card-icon">🕵️</span>
                        <div>
                            <div class="pain__card-title">
                                "¿Ese equipo es de quién?"
                            </div>
                            <p class="pain__card-text">
                                Una consola sin nombre en el mostrador desde
                                hace un mes, y ya no recuerdas quién la trajo ni
                                por qué.
                            </p>
                        </div>
                    </div>
                    <div class="pain__card">
                        <span class="pain__card-icon">🧾</span>
                        <div>
                            <div class="pain__card-title">
                                "Yo creo que ya avisé..."
                            </div>
                            <p class="pain__card-text">
                                El equipo está listo desde hace días, pero
                                avisar por WhatsApp uno por uno siempre queda
                                para "después".
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="funciona-que-es">
            <div class="container">
                <div class="section__head">
                    <span class="section__eyebrow">La solución</span>
                    <h2 class="section__title">
                        RepairTrack se acuerda por ti — y le avisa al cliente
                        antes de que llame.
                    </h2>
                    <p class="section__subtitle">
                        Todo lo que hoy vive en tu cabeza, un cuaderno y catorce
                        chats de WhatsApp, ordenado en un solo lugar.
                    </p>
                </div>
                <div class="features__grid">
                    <div class="features__card">
                        <div class="features__card-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <rect
                                    x="3"
                                    y="4"
                                    width="18"
                                    height="15"
                                    rx="2"
                                />
                                <path d="M3 8h18M8 4v4" />
                            </svg>
                        </div>
                        <div class="features__card-title">
                            Un ticket por equipo
                        </div>
                        <p class="features__card-text">
                            Cliente, equipo, problema y fecha en una sola
                            pantalla. Se acabó adivinar de quién es cada cosa.
                        </p>
                    </div>
                    <div class="features__card">
                        <div class="features__card-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M3 7l9 6 9-6" />
                                <rect
                                    x="3"
                                    y="5"
                                    width="18"
                                    height="14"
                                    rx="2"
                                />
                            </svg>
                        </div>
                        <div class="features__card-title">
                            Correos automáticos
                        </div>
                        <p class="features__card-text">
                            Cada vez que mueves el estado, tu cliente recibe un
                            correo con tu marca. Tú no vuelves a escribir "ya
                            casi está".
                        </p>
                    </div>
                    <div class="features__card">
                        <div class="features__card-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M12 2l3 6 6 .9-4.5 4.3 1 6.3L12 16.9 6.5 19.5l1-6.3L3 8.9 9 8z"
                                />
                            </svg>
                        </div>
                        <div class="features__card-title">
                            Con tu marca, no la nuestra
                        </div>
                        <p class="features__card-text">
                            Logo y colores tuyos en cada correo y en la página
                            de status. Tu cliente ve tu nombre, no una app
                            genérica.
                        </p>
                    </div>
                    <div class="features__card">
                        <div class="features__card-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M10 20v-6M10 10V4M20 20v-9M20 7V4M4 20v-3M4 13V4M2 14h4M12 8h4M18 5h4"
                                />
                            </svg>
                        </div>
                        <div class="features__card-title">
                            Un link para ver el status
                        </div>
                        <p class="features__card-text">
                            Tu cliente le da clic al link del correo y ve
                            exactamente en qué va su equipo. Sin registrarse,
                            sin llamarte.
                        </p>
                    </div>
                    <div class="features__card">
                        <div class="features__card-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <rect x="3" y="3" width="7" height="7" rx="1" />
                                <rect
                                    x="14"
                                    y="3"
                                    width="7"
                                    height="7"
                                    rx="1"
                                />
                                <rect
                                    x="3"
                                    y="14"
                                    width="7"
                                    height="7"
                                    rx="1"
                                />
                                <rect
                                    x="14"
                                    y="14"
                                    width="7"
                                    height="7"
                                    rx="1"
                                />
                            </svg>
                        </div>
                        <div class="features__card-title">
                            Dashboard de todo lo pendiente
                        </div>
                        <p class="features__card-text">
                            Filtra por fecha de entrada, de entrega o por tipo
                            de equipo. Sabes al toque qué se te está atrasando.
                        </p>
                    </div>
                    <div class="features__card">
                        <div class="features__card-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M12 8v4l3 2" />
                                <circle cx="12" cy="12" r="9" />
                            </svg>
                        </div>
                        <div class="features__card-title">
                            Historial completo
                        </div>
                        <p class="features__card-text">
                            Cada cambio de estado queda registrado. Si un
                            cliente reclama, tienes la fecha y la hora exacta a
                            la mano.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="section section--alt" id="funciona">
            <div class="container">
                <div class="section__head">
                    <span class="section__eyebrow"
                        >Del mostrador al correo del cliente</span
                    >
                    <h2 class="section__title">Así de simple es el proceso</h2>
                </div>
                <div class="how__steps">
                    <div class="how__step">
                        <div class="how__step-title">Registras el equipo</div>
                        <p class="how__step-text">
                            Nombre del cliente, tipo de equipo y el problema que
                            reportó. Un minuto, no una hoja perdida.
                        </p>
                    </div>
                    <div class="how__step">
                        <div class="how__step-title">Actualizas el estado</div>
                        <p class="how__step-text">
                            Recibido, en revisión, en reparación, listo para
                            entregar. Tú decides, RepairTrack lo recuerda.
                        </p>
                    </div>
                    <div class="how__step">
                        <div class="how__step-title">
                            El cliente recibe un correo
                        </div>
                        <p class="how__step-text">
                            Con tu marca y un link directo al status. Ni una
                            llamada de más de tu parte.
                        </p>
                    </div>
                    <div class="how__step">
                        <div class="how__step-title">
                            El cliente ve todo, en vivo
                        </div>
                        <p class="how__step-text">
                            Le da clic al link cuando quiera y ve exactamente en
                            qué va, sin registrarse ni escribirte.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <div class="quote">
                    <p class="quote__text">
                        "Antes tenía tres cuadernos, dos rotos por el café.
                        Ahora tengo un link y una taza que ya no se derrama
                        encima de nada importante."
                    </p>
                    <p class="quote__author">
                        — Un técnico ficticio, pero muy real en espíritu
                    </p>
                </div>
            </div>
        </section>

        <section class="section section--alt" id="precio">
            <div class="container">
                <div class="section__head section__head--center">
                    <span class="section__eyebrow"
                        >Precio, sin letra pequeña</span
                    >
                    <h2 class="section__title">
                        Cuesta menos que el café que se te riega en el cuaderno
                    </h2>
                </div>
                <div class="pricing__wrap">
                    <div class="pricing__card">
                        <span class="pricing__badge">Plan personal</span>
                        <div class="pricing__amount">$3<span>/mes</span></div>
                        <p class="pricing__note">
                            Tu cuenta, tus clientes, equipos ilimitados.
                        </p>
                        <ul class="pricing__list">
                            <li class="pricing__list-item">
                                <span class="pricing__list-icon">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M20 6L9 17l-5-5" />
                                    </svg>
                                </span>
                                Dashboard con filtros por fecha y equipo
                            </li>
                            <li class="pricing__list-item">
                                <span class="pricing__list-icon">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M20 6L9 17l-5-5" />
                                    </svg>
                                </span>
                                Correos automáticos con tu marca
                            </li>
                            <li class="pricing__list-item">
                                <span class="pricing__list-icon">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M20 6L9 17l-5-5" />
                                    </svg>
                                </span>
                                Link de status público para tus clientes
                            </li>
                            <li class="pricing__list-item">
                                <span class="pricing__list-icon">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M20 6L9 17l-5-5" />
                                    </svg>
                                </span>
                                Historial completo de cada ticket
                            </li>
                        </ul>
                        <Link
                            :href="register()"
                            class="btn btn--primary btn--block"
                        >
                            Empezar ahora
                        </Link>
                        <div class="pricing__pay">
                            <div class="pricing__pay-label">
                                Métodos de pago
                            </div>
                            <div class="pricing__pay-methods">
                                <span class="pricing__pay-badge">PayPal</span>
                                <span class="pricing__pay-badge"
                                    >Binance ID</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="faq">
            <div class="container">
                <div class="section__head">
                    <span class="section__eyebrow">Antes de que preguntes</span>
                    <h2 class="section__title">Preguntas frecuentes</h2>
                </div>
                <div class="faq__list">
                    <details class="faq__item">
                        <summary class="faq__question">
                            ¿Mis clientes necesitan crear una cuenta para ver el
                            status?
                            <span class="faq__question-icon">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.4"
                                    stroke-linecap="round"
                                >
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                            </span>
                        </summary>
                        <p class="faq__answer">
                            No. Le llega un link único por correo y con eso ve
                            el status de su equipo. Nada de contraseñas ni
                            registros.
                        </p>
                    </details>
                    <details class="faq__item">
                        <summary class="faq__question">
                            ¿Puedo usar mi logo y mis colores?
                            <span class="faq__question-icon">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.4"
                                    stroke-linecap="round"
                                >
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                            </span>
                        </summary>
                        <p class="faq__answer">
                            Sí, es justo la idea. Los correos y la vista de
                            status llevan tu marca — tu cliente ni se entera de
                            que existe RepairTrack detrás.
                        </p>
                    </details>
                    <details class="faq__item">
                        <summary class="faq__question">
                            ¿Si trabajo en un local con otros técnicos, ellos
                            ven mis clientes?
                            <span class="faq__question-icon">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.4"
                                    stroke-linecap="round"
                                >
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                            </span>
                        </summary>
                        <p class="faq__answer">
                            No. Cada cuenta es de una persona. Tus clientes, tus
                            precios y tus tickets son solo tuyos — aunque
                            compartan el mismo mostrador.
                        </p>
                    </details>
                    <details class="faq__item">
                        <summary class="faq__question">
                            ¿Cómo pago los $3 al mes?
                            <span class="faq__question-icon">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.4"
                                    stroke-linecap="round"
                                >
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                            </span>
                        </summary>
                        <p class="faq__answer">
                            Por PayPal o por Binance usando tu ID. Sin tarjetas
                            raras ni comisiones escondidas.
                        </p>
                    </details>
                    <details class="faq__item">
                        <summary class="faq__question">
                            ¿Sirve para reparaciones que no sean de teléfonos?
                            <span class="faq__question-icon">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.4"
                                    stroke-linecap="round"
                                >
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                            </span>
                        </summary>
                        <p class="faq__answer">
                            Sí — consolas, laptops, tablets o cualquier equipo
                            que te dejen y te vengan a buscar. El tipo de equipo
                            lo defines tú.
                        </p>
                    </details>
                    <details class="faq__item">
                        <summary class="faq__question">
                            ¿Puedo cancelar cuando quiera?
                            <span class="faq__question-icon">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.4"
                                    stroke-linecap="round"
                                >
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                            </span>
                        </summary>
                        <p class="faq__answer">
                            Sí, sin preguntas incómodas ni permanencia mínima.
                            Tus datos siguen siendo tuyos.
                        </p>
                    </details>
                </div>
            </div>
        </section>

        <section class="final">
            <div class="container">
                <h2 class="final__title">
                    Tu próximo cliente va a preguntar "¿y mi equipo?" en 3, 2,
                    1...
                </h2>
                <div class="final__actions">
                    <a href="#precio" class="btn btn--primary"
                        >Ordenar mis equipos por $3/mes</a
                    >
                    <a href="#problema" class="btn btn--ghost"
                        >Todavía no estoy seguro</a
                    >
                </div>
            </div>
        </section>

        <footer class="footer">
            <div class="footer__inner container">
                <span class="footer__brand">
                    <span class="footer__brand-accent">Repair</span>Track
                </span>
                <span class="footer__note">
                    Hecho para el técnico que ya no quiere depender de un
                    cuaderno. © 2026 RepairTrack.
                </span>
            </div>
        </footer>
    </div>
</template>
