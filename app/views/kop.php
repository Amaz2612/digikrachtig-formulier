<?php
/**
 * Digikrachtig formulierensysteem
 * View-onderdeel: de balk bovenaan met logo en navigatie.
 *
 * Plaats dit bestand in: app/views/kop.php
 * Gebruik in een view direct na <body>:
 *   <?php require __DIR__ . '/kop.php'; ?>
 *
 * De menu-items zijn alleen voor de stijl (zoals op digikrachtig.nl)
 * en gaan nergens naartoe; daarom hebben ze geen href.
 */
?>
<header class="kop">
    <a class="logo" href="?"><img src="img/logo.png" alt="Digikrachtig Rivierenland"></a>

    <nav class="nav" aria-label="Hoofdmenu">
        <a class="nav-link">Home</a>
        <a class="nav-link">Agenda</a>
        <a class="nav-link">Contact</a>

        <a class="nav-vraag">
            Stel je vraag
            <span class="nav-vraag-pijl" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
        </a>

        <a class="nav-zoek" aria-label="Zoeken">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </a>
    </nav>
</header>
