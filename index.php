<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProfeGo | Tutorías para todas las edades</title>
    <meta name="description" content="ProfeGo conecta estudiantes con tutores para aprender o enseñar, de forma presencial o virtual.">
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="index.php">
                <span class="brand__crop">
                    <img src="assets/images/logo.png" alt="ProfeGo" width="1254" height="1254">
                </span>
            </a>

            <nav class="header-actions" aria-label="Cuenta">
                <a class="button button--small button--account" href="/pages/register.php">Crear Cuenta</a>
                <a class="button button--small button--login" href="/pages/login.php">Iniciar Sesión</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero container" aria-labelledby="hero-title">
            <div class="hero__copy">
                <h1 class="hero__title" id="hero-title">¿Qué es ProfeGo?</h1>
                <p class="hero__lead">
                    ProfeGo es una app para tutoría para todas las edades, con el fin de ayudar a los estudiantes o 
                    universitarios con sus estudios ya sea desde una ubicación acordada o virtualmente!
                </p>
                <a class="button button--primary hero__action" href="#secciones">Ir a secciones</a>
            </div>

            <div class="hero__media">
                <img 
                    src="assets/images/hero.png" 
                    alt="Vista previa de la aplicación móvil de ProfeGo y sus funciones de tutoría" 
                    width="1280" 
                    height="640"
                >
            </div>
        </section>

        <section class="app-info container" id="secciones" aria-labelledby="app-info-title">
            <h2 class="section-title" id="app-info-title">Información de la App</h2>

            <div class="info-grid">
                <article class="info-card">
                    <div class="info-card__media">
                        <img src="assets/images/info-tutors.png" alt="" width="405" height="405">
                    </div>
                    <div class="info-card__copy">
                        <h3>Tutores de todo tipo</h3>
                        <p>Cualquiera con conocimientos en algo puede enseñar. Siempre y cuando se mantenga legal.</p>
                    </div>
                </article>

                <article class="info-card">
                    <div class="info-card__media">
                        <img src="assets/images/info-reputation.png" alt="" width="405" height="405">
                    </div>
                    <div class="info-card__copy">
                        <h3>Reputación</h3>
                        <p>Los tutores serán calificados por los estudiantes en una escala del 1 al 5.</p>
                    </div>
                </article>

                <article class="info-card">
                    <div class="info-card__media">
                        <img src="assets/images/info-modality.png" alt="" width="405" height="405">
                    </div>
                    <div class="info-card__copy">
                        <h3>Modalidad flexible</h3>
                        <p>Presencial o virtual, esa es la cuestión. Puedes seleccionar cualquiera de las 2 opciones.</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="steps container" aria-labelledby="steps-title">
            <h2 class="section-title" id="steps-title">¿Qué necesitas hacer?</h2>

            <div class="steps__content">
                <div class="steps__list">
                    <div class="steps__item">
                        <h3>Registrarte en esta página</h3>
                        <p>
                            <span class="desktop-only">
                                Presiona alguno de los botones de arriba a la derecha, selecciona si eres estudiante o 
                                deseas enseñarles.
                            </span>
                            <span class="mobile-only">
                                Regístrate o inicia sesión, para registrarte debes seleccionar 
                                si eres estudiante o deseas enseñarles.
                            </span>
                        </p>
                    </div>

                    <div class="steps__item">
                        <h3>Seleccionar tus objetivos</h3>
                        <p>
                            Elige esas áreas del conocimiento en las que deseas aprender o enseñar, hacerlo nos permitirá 
                            conocerte mejor para hacerte recomendaciones acertadas.
                        </p>
                    </div>

                    <div class="steps__item">
                        <h3>¡Y listo!</h3>
                        <p>
                            Explora el mapa para encontrar la ubicación aproximada de tutores o estudiantes en tu área a 
                            precios justos en ubicaciones cómodas para ambas partes.
                        </p>
                    </div>
                </div>

                <div class="steps__media">
                    <img 
                        src="assets/images/steps.jpg" 
                        alt="Pasos para empezar en ProfeGo: registrarte, seleccionar tus objetivos y comenzar a aprender o enseñar" 
                        width="691" height="705"
                    >
                </div>
            </div>
        </section>

        <section class="roles container" aria-labelledby="roles-title">
            <h2 class="section-title roles__title" id="roles-title">
                <span class="desktop-only">Recibe ayuda para fortalecer tus habilidades.<br>O brinda esa ayuda</span>
                <span class="mobile-only">Roles de la aplicación</span>
            </h2>

            <div class="roles__grid">
                <article class="role-card">
                    <div class="role-card__media">
                        <img src="assets/images/role-student.png" alt="" width="625" height="352">
                    </div>
                    <div class="role-card__copy">
                        <h3>
                            <span class="desktop-only">Ser estudiante</span>
                            <span class="mobile-only">Estudiante</span>
                        </h3>
                        <p>
                            Encuentra profesores dispuestos a ayudarte en las materias que más necesitas cuando quieras, 
                            siempre y cuando estén disponibles.
                        </p>
                    </div>
                </article>

                <article class="role-card">
                    <div class="role-card__media">
                        <img src="assets/images/role-teacher.png" alt="" width="625" height="338">
                    </div>
                    <div class="role-card__copy">
                        <h3>
                            <span class="desktop-only">Ser docente</span>
                            <span class="mobile-only">Docente</span>
                        </h3>
                        <p>
                            Gestiona tu disponibilidad, acepta lecciones y enseña a quien quieras, tienes control total 
                            sobre lo que vas a hacer en la aplicación.
                        </p>
                    </div>
                </article>
            </div>
        </section>

        <section class="cta" aria-labelledby="cta-title">
            <div class="cta__inner">
                <h2 class="cta__title" id="cta-title">¿Qué necesitas?</h2>
                <div class="cta__actions">
                    <a class="button button--accent" href="/pages/register.php">Enseñar</a>
                    <a class="button button--muted" href="/pages/register.php">Aprender</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="site-footer__inner">
            <a class="brand brand--footer" href="index.php">
                <span class="brand__crop">
                    <img src="assets/images/logo.png" alt="ProfeGo" width="1254" height="1254">
                </span>
            </a>

            <ul class="footer-social" aria-label="Redes sociales">
                <li>
                    <a href="#" aria-label="Facebook de ProfeGo">
                        <img src="assets/icons/icon-social-4.svg" alt="" width="20" height="20">
                        <span>ProfeGo</span>
                    </a>
                </li>
                <li>
                    <a href="#" aria-label="LinkedIn de ProfeGo">
                        <img src="assets/icons/icon-social-3.svg" alt="" width="20" height="20">
                        <span>ProfeGo</span>
                    </a>
                </li>
                <li>
                    <a href="#" aria-label="YouTube de ProfeGo">
                        <img src="assets/icons/icon-social-2.svg" alt="" width="20" height="20">
                        <span>@ProfeGo</span>
                    </a>
                </li>
                <li>
                    <a href="#" aria-label="Instagram de ProfeGo">
                        <img src="assets/icons/icon-social-1.svg" alt="" width="20" height="20">
                        <span>@ProfeGo</span>
                    </a>
                </li>
            </ul>

            <div class="footer-contact">
                <h2>Contacto</h2>
                <p>Correo Electrónico: ProfeGo@gmail.com</p>
                <p>Teléfono: 3042825913</p>
            </div>
        </div>
    </footer>
</body>
</html>
