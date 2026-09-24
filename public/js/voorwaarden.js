/**
 * Digikrachtig formulierensysteem
 * Deelt het formulier op in stappen, toont en verbergt vragen,
 * bewaakt de invulplicht en houdt de voortgangsbalk bij.
 *
 * Plaats dit bestand in: public/js/voorwaarden.js
 *
 * Dit is de opvolger van validatie.js. Die versie was geschreven
 * voor het handgemaakte formulier met vaste stappen en vaste id's
 * (.form-step, .btn-next, word_ja_vragen, ...). Het formulier komt
 * nu uit de database, dus die namen bestaan niet meer. Een stap is
 * hier een sectie uit de vragenlijst, en de vragen staan in de
 * opzet die de view meegeeft:
 *
 *   data-sectie          code van de sectie: dit wordt een stap
 *   data-vraag           code van de vraag
 *   data-verplicht       staat erbij als de vraag ingevuld moet worden
 *   data-toon-als        code van de vraag waar dit van afhangt
 *   data-toon-als-waarde de antwoorden die dit veld tonen,
 *                        meerdere gescheiden door |
 *
 * De regels voor tonen en verbergen zijn dezelfde als in
 * app/Voorwaarden.php. Wat hier gebeurt is alleen voor het gemak
 * van de invuller: de server rekent alles opnieuw uit, dus
 * meesleutelen in de browser levert niets op.
 *
 * Staat JavaScript uit, dan gebeurt hier niets en blijft het
 * formulier een gewone lange pagina die nog steeds werkt.
 */
document.addEventListener('DOMContentLoaded', () => {

    /**
     * Staat dit op true, dan moet elke vraag die in beeld staat
     * ingevuld zijn voordat de invuller verder mag. Op false telt
     * alleen de kolom `verplicht` uit de database, en mag hij een
     * niet-verplichte vraag overslaan.
     *
     * Let op: in database/vragenlijst.sql staan 51 van de 84 vragen
     * op verplicht = 0, waaronder het telefoonnummer en de open
     * toelichtingen. Met true erbij moeten die dus toch ingevuld
     * worden. De server blijft alleen de echte verplichte vragen
     * afdwingen (app/Validatie.php), dus dit is strenger dan wat er
     * uiteindelijk wordt opgeslagen.
     */
    const ALLES_VERPLICHT = true;

    const MELDING_LEEG = 'Deze vraag moet ingevuld worden.';

    const formulier = document.querySelector('form[action*="opslaan"]');

    if (formulier === null) {
        return;
    }

    /*
     * Vanaf hier doen wij de controle zelf. Liet je dat aan de browser
     * over, dan hield die het verzenden tegen met zijn eigen ballonnetje
     * voordat onze melding onder de vraag kon verschijnen, en dan kreeg
     * de invuller twee verschillende meldingen te zien. checkValidity()
     * blijft gewoon werken, dus geldige e-mailadressen en getallen
     * controleren we nog steeds; we laten het alleen zelf zien.
     *
     * Dit gebeurt in JavaScript en niet in de view: staat JavaScript uit,
     * dan blijft de browser het werk doen.
     */
    formulier.noValidate = true;

    // Alle vragen op een rij, met hun code als sleutel.
    const vragen = new Map();

    formulier.querySelectorAll('[data-vraag]').forEach(element => {
        vragen.set(element.dataset.vraag, {
            element:   element,
            melding:   element.classList.contains('melding'),
            verplicht: element.dataset.verplicht !== undefined,
            ouderCode: element.dataset.toonAls ?? null,
            waarden:   (element.dataset.toonAlsWaarde ?? '')
                           .split('|')
                           .filter(waarde => waarde !== '')
        });
    });

    /**
     * De invulvelden die bij een vraag horen. Bij een checkbox heet
     * het veld code[], bij de rest gewoon code.
     */
    function velden(code) {
        return formulier.querySelectorAll(
            '[name="' + CSS.escape(code) + '"], '
            + '[name="' + CSS.escape(code + '[]') + '"]'
        );
    }

    /**
     * Het gegeven antwoord op een vraag.
     * Bij een checkbox is dat een array, anders een string.
     * Niets ingevuld: null.
     */
    function antwoord(code) {
        const lijst = velden(code);

        if (lijst.length === 0) {
            return null;
        }

        const aangevinkt = [];
        let isVinkje = false;

        for (const veld of lijst) {
            if (veld.type === 'checkbox') {
                isVinkje = true;

                if (veld.checked) {
                    aangevinkt.push(veld.value);
                }

                continue;
            }

            if (veld.type === 'radio') {
                if (veld.checked) {
                    return veld.value;
                }

                continue;
            }

            return veld.value === '' ? null : veld.value;
        }

        if (isVinkje) {
            return aangevinkt;
        }

        return null;
    }

    /**
     * Is er iets ingevuld? Bij een checkbox telt minstens één vinkje.
     */
    function isIngevuld(code) {
        const gegeven = antwoord(code);

        return Array.isArray(gegeven) ? gegeven.length > 0 : gegeven !== null;
    }

    /**
     * Moet deze vraag ingevuld worden voordat de invuller verder mag?
     * Meldingen zijn alleen tekst, daar valt niets in te vullen.
     */
    function moetIngevuld(vraag) {
        if (vraag.melding) {
            return false;
        }

        return ALLES_VERPLICHT || vraag.verplicht;
    }

    /**
     * Is een vraag zichtbaar bij de antwoorden van dit moment?
     * `bezocht` vangt een voorwaarde op die naar zichzelf wijst,
     * zodat dat geen oneindige lus wordt.
     */
    function isZichtbaar(code, bezocht = new Set()) {
        const vraag = vragen.get(code);

        // Vraag bestaat niet, of een rondje in de voorwaarden.
        if (vraag === undefined || bezocht.has(code)) {
            return false;
        }

        bezocht.add(code);

        // Geen voorwaarde: altijd zichtbaar.
        if (vraag.ouderCode === null) {
            return true;
        }

        // De vraag waar dit van afhangt moet zelf ook zichtbaar zijn.
        if (!isZichtbaar(vraag.ouderCode, bezocht)) {
            return false;
        }

        const gegeven = antwoord(vraag.ouderCode);

        if (gegeven === null || vraag.waarden.length === 0) {
            return false;
        }

        // Bij een checkbox telt het als een van de aangevinkte
        // waarden erbij zit.
        if (Array.isArray(gegeven)) {
            return gegeven.some(waarde => vraag.waarden.includes(waarde));
        }

        return vraag.waarden.includes(gegeven);
    }

    // --- Foutmeldingen bij een vraag ---

    /**
     * Zet een melding onder de vraag zelf. De browser laat bij
     * reportValidity() een ballonnetje zien dat meteen weer weg is
     * zodra je ergens klikt; deze melding blijft staan tot de vraag
     * ingevuld is.
     */
    function markeerFout(vraag, tekst) {
        vraag.element.classList.add('fout');

        let melding = vraag.element.querySelector('.veld-fout');

        if (melding === null) {
            melding = document.createElement('p');
            melding.className = 'veld-fout';
            melding.setAttribute('role', 'alert');
            vraag.element.append(melding);
        }

        melding.textContent = tekst;
    }

    /**
     * Haalt onze eigen melding weg. Een melding die de server heeft
     * meegestuurd (die staat in een <strong>) laten we staan: die
     * gaat over wat er is opgeslagen, niet over dit klikmoment.
     */
    function wisFout(vraag) {
        const melding = vraag.element.querySelector('.veld-fout');

        if (melding !== null) {
            melding.remove();
        }

        if (vraag.element.querySelector('strong') === null) {
            vraag.element.classList.remove('fout');
        }
    }

    // --- De stappen ---

    const stappen = Array.from(formulier.querySelectorAll('[data-sectie]'));

    // Eén sectie is geen stappenplan: dan laten we de navigatie weg.
    const metStappen = stappen.length > 1;

    // Kwam de pagina terug met fouten, dan beginnen we bij de stap
    // waar de eerste fout staat. Anders leest de invuller een melding
    // over velden die hij nergens kan vinden.
    let huidigeStap = Math.max(0, stappen.findIndex(
        stap => stap.querySelector('.fout') !== null
    ));

    const knoppen  = formulier.querySelector('.knoppen');
    const verstuur = formulier.querySelector('[name="verstuur"]');

    let knopVorige   = null;
    let knopVolgende = null;

    if (metStappen && knoppen !== null) {
        formulier.classList.add('stappen');

        knopVorige = document.createElement('button');
        knopVorige.type = 'button';
        knopVorige.className = 'knop-vorige';
        knopVorige.textContent = 'Vorige';

        knopVolgende = document.createElement('button');
        knopVolgende.type = 'button';
        knopVolgende.className = 'knop-volgende';
        knopVolgende.textContent = 'Volgende';

        knoppen.prepend(knopVorige, knopVolgende);

        // Terug mag altijd: daar raakt niemand iets mee kwijt.
        knopVorige.addEventListener('click', () => {
            if (huidigeStap > 0) {
                huidigeStap--;
                bijwerken();
                naarBoven();
            }
        });

        knopVolgende.addEventListener('click', () => {
            if (!stapIsGoedIngevuld()) {
                return;
            }

            if (huidigeStap < stappen.length - 1) {
                huidigeStap++;
                bijwerken();
                naarBoven();
            }
        });
    }

    function naarBoven() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /**
     * Loopt de vragen van de huidige stap na. Alles wat nog open
     * staat krijgt een melding, en de eerste daarvan komt in beeld
     * met de aandacht erop. Geeft false als de invuller nog niet
     * verder mag.
     *
     * Verborgen vragen slaan we over: die horen niet bij het pad dat
     * deze invuller loopt.
     */
    function stapIsGoedIngevuld() {
        const stap = metStappen ? stappen[huidigeStap] : formulier;

        if (stap === undefined) {
            return true;
        }

        let eerste = null;

        vragen.forEach((vraag, code) => {
            if (!stap.contains(vraag.element) || vraag.element.hidden) {
                return;
            }

            const lijst = velden(code);

            // Niets ingevuld terwijl dat wel moet.
            if (moetIngevuld(vraag) && !isIngevuld(code)) {
                markeerFout(vraag, MELDING_LEEG);
                eerste = eerste ?? lijst[0] ?? null;
                return;
            }

            // Wel ingevuld, maar de browser heeft bezwaar: geen geldig
            // e-mailadres, een getal buiten de grenzen, dat soort werk.
            for (const veld of lijst) {
                if (!veld.checkValidity()) {
                    markeerFout(vraag, veld.validationMessage);
                    eerste = eerste ?? veld;
                    return;
                }
            }

            wisFout(vraag);
        });

        if (eerste === null) {
            return true;
        }

        eerste.focus({ preventScroll: true });
        eerste.scrollIntoView({ behavior: 'smooth', block: 'center' });

        return false;
    }

    const spoor     = document.querySelector('.voortgang-spoor span');
    const voortgang = document.getElementById('voortgang');

    /**
     * Zet alle vragen en knoppen goed en werk de balk bovenaan bij.
     */
    function bijwerken() {
        stappen.forEach((stap, nummer) => {
            stap.classList.toggle('actief', !metStappen || nummer === huidigeStap);
        });

        const stapInBeeld = metStappen ? stappen[huidigeStap] : null;

        let totaal   = 0;
        let ingevuld = 0;

        vragen.forEach((vraag, code) => {
            const zichtbaar = isZichtbaar(code);

            vraag.element.hidden = !zichtbaar;

            // Alleen de stap waar de invuller nu staat mag het
            // doorklikken tegenhouden. Een verplicht veld op een stap
            // die niet in beeld is blokkeert anders het verzenden
            // zonder dat de browser kan aanwijzen waar het misgaat.
            // De eerdere stappen zijn al nagelopen bij 'Volgende', en
            // de server controleert hoe dan ook alles opnieuw.
            const inBeeld = stapInBeeld === null
                || stapInBeeld.contains(vraag.element);

            // Vinkjes slaan we over: `required` zou daar betekenen dat
            // ze allemaal aangevinkt moeten worden. Die bewaken we zelf
            // in stapIsGoedIngevuld().
            velden(code).forEach(veld => {
                if (veld.type !== 'checkbox') {
                    veld.required = zichtbaar && inBeeld && moetIngevuld(vraag);
                }
            });

            // De melding verdwijnt zodra de vraag ingevuld is of uit
            // beeld raakt. Wie nog niets heeft ingevuld houdt hem.
            if (!zichtbaar || isIngevuld(code)) {
                wisFout(vraag);
            }

            // Meldingen zijn alleen tekst, die tellen niet mee.
            if (!zichtbaar || vraag.melding) {
                return;
            }

            totaal++;

            if (isIngevuld(code)) {
                ingevuld++;
            }
        });

        if (metStappen) {
            knopVorige.hidden   = huidigeStap === 0;
            knopVolgende.hidden = huidigeStap === stappen.length - 1;

            // Versturen kan pas als de invuller de laatste stap ziet.
            if (verstuur !== null) {
                verstuur.hidden = huidigeStap !== stappen.length - 1;
            }
        }

        const deel = totaal === 0 ? 0 : Math.round((ingevuld / totaal) * 100);

        if (spoor !== null) {
            spoor.style.width = deel + '%';
        }

        if (voortgang !== null) {
            const tekst = ingevuld + ' van de ' + totaal
                + ' vragen ingevuld (' + deel + '%)';

            voortgang.textContent = metStappen
                ? 'Stap ' + (huidigeStap + 1) + ' van ' + stappen.length
                  + ' - ' + tekst
                : tekst;
        }
    }

    formulier.addEventListener('change', bijwerken);
    formulier.addEventListener('input', bijwerken);

    /**
     * Versturen gaat langs dezelfde controle als 'Volgende', zodat
     * ook de laatste stap compleet is. Tussentijds opslaan mag altijd:
     * de student is dan nog bezig en de server klaagt daar ook niet
     * over lege velden.
     */
    formulier.addEventListener('submit', gebeurtenis => {
        const knop = gebeurtenis.submitter;

        if (knop !== null && knop.name === 'opslaan') {
            return;
        }

        if (!stapIsGoedIngevuld()) {
            gebeurtenis.preventDefault();
        }
    });

    /**
     * Enter in een tekstveld verstuurt normaal het hele formulier.
     * Halverwege de stappen is dat zelden de bedoeling, dus alleen
     * de knoppen onderaan versturen.
     */
    formulier.addEventListener('keydown', gebeurtenis => {
        if (gebeurtenis.key !== 'Enter') {
            return;
        }

        const doel = gebeurtenis.target;

        if (doel.tagName === 'TEXTAREA' || doel.tagName === 'BUTTON') {
            return;
        }

        gebeurtenis.preventDefault();
    });

    bijwerken();
});
