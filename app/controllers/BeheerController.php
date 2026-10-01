<?php
/**
 * Digikrachtig formulierensysteem
 * Controller voor de beheerpagina (public/admin.php).
 *
 * Plaats dit bestand in: app/controllers/BeheerController.php
 *
 * Net als FormulierController: geen SQL (dat doen de modellen) en
 * geen HTML (dat doen de views in app/views/beheer/).
 *
 * Elke methode die iets laat zien of verandert begint met
 * vereisBeheerder(). Wie niet als beheerder is ingelogd, gaat naar
 * de inlogpagina.
 */
class BeheerController
{
    /** De gewone inlogpagina, voor studenten en beheerders samen. */
    private const INLOGPAGINA = 'index.php?actie=login';

    private PDO $pdo;
    private BeheerModel $beheerModel;
    private FormulierModel $formulierModel;
    private InzendingModel $inzendingModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo            = $pdo;
        $this->beheerModel    = new BeheerModel($pdo);
        $this->formulierModel = new FormulierModel($pdo);
        $this->inzendingModel = new InzendingModel($pdo);
    }

    // --- Uitloggen ---
    // Inloggen gebeurt op de gewone inlogpagina (public/index.php):
    // vul bij Studentnummer je gebruikersnaam in.

    public function uitloggen(): void
    {
        $this->controleerToken();

        BeheerAuth::uitloggen();
        $this->stuurDoor(self::INLOGPAGINA);
    }

    // --- Bekijken ---

    /**
     * Tellingen en de tabel met alle inzendingen.
     */
    public function overzicht(): void
    {
        $this->vereisBeheerder();

        $this->toonView('overzicht', [
            'telling'     => $this->beheerModel->telling(),
            'inzendingen' => $this->beheerModel->inzendingen(),
        ]);
    }

    /**
     * Eén inzending: alle antwoorden per sectie en het logboek.
     */
    public function detail(): void
    {
        $this->vereisBeheerder();

        $inzending = $this->haalInzending($_GET['id'] ?? null);

        $this->toonView('detail', [
            'inzending' => $inzending,
            'secties'   => $this->antwoordenPerSectie($inzending),
            'logboek'   => $this->beheerModel->logboek((int) $inzending['id']),
            'gereset'   => isset($_GET['gereset']),
        ]);
    }

    // --- Resetten ---

    /**
     * Vraagt eerst of het echt de bedoeling is. Dit is een gewone
     * pagina (GET) en verandert nog niets; pas de knop daarop stuurt
     * een POST met CSRF-token. Zo werkt de bevestiging ook zonder
     * JavaScript.
     */
    public function resetBevestigen(): void
    {
        $this->vereisBeheerder();

        $this->toonView('reset', [
            'inzending' => $this->haalInzending($_GET['id'] ?? null),
        ]);
    }

    public function reset(): void
    {
        $this->vereisBeheerder();
        $this->controleerToken();

        $id = $this->leesId($_POST['id'] ?? null);

        if ($id === null || !$this->beheerModel->reset($id, (string) BeheerAuth::gebruikersnaam())) {
            $this->nietGevonden();
        }

        $this->stuurDoor('?actie=detail&id=' . $id . '&gereset=1');
    }

    // --- Hulpjes ---

    /**
     * Zet de antwoorden klaar in dezelfde volgorde en met dezelfde
     * secties als het formulier.
     *
     * Keuze voor wat er getoond wordt:
     *   - Vragen die voor deze inzending verborgen waren worden
     *     overgeslagen. Ze hoorden niet bij het pad van deze invuller,
     *     dus "niet ingevuld" zou de lezer op het verkeerde been zetten.
     *   - Vragen die zichtbaar waren maar leeg zijn gebleven worden wel
     *     getoond, met "niet ingevuld".
     *   - Staat er toch een antwoord bij een vraag die verborgen is, dan
     *     tonen we het toch: wat in de database staat, moet je kunnen zien.
     *   - Meldingen (alleen tekst) worden overgeslagen.
     *   - Een sectie waarvan niets over is, wordt helemaal overgeslagen.
     */
    private function antwoordenPerSectie(array $inzending): array
    {
        $formulierId   = (int) $inzending['form_id'];
        $vragenPerCode = $this->formulierModel->vragenPerCode($formulierId);
        $antwoorden    = $this->inzendingModel->antwoorden((int) $inzending['id']);

        $secties = [];

        foreach ($this->formulierModel->structuur($formulierId) as $sectie) {
            $regels = [];

            foreach ($sectie['vragen'] as $vraag) {
                if ($vraag['is_melding']) {
                    continue;
                }

                $code        = $vraag['code'];
                $heeftWaarde = isset($antwoorden[$code]);

                if (!$heeftWaarde && !Voorwaarden::isZichtbaar($code, $vragenPerCode, $antwoorden)) {
                    continue;
                }

                $regels[] = [
                    'label'   => $vraag['label'],
                    'type'    => $vraag['type'],
                    // Altijd een lijst, ook bij één antwoord: dan hoeft
                    // de view geen verschil te maken met checkboxen.
                    'waarden' => $heeftWaarde ? (array) $antwoorden[$code] : [],
                ];
            }

            if ($regels === []) {
                continue;
            }

            $secties[] = [
                // De eerste en de laatste sectie hebben in het formulier
                // geen kopje. Hier wel, anders lijken de vragen bij de
                // sectie erboven te horen.
                'titel'  => $sectie['titel'] !== '' ? $sectie['titel'] : ucfirst($sectie['code']),
                'regels' => $regels,
            ];
        }

        return $secties;
    }

    private function vereisBeheerder(): void
    {
        if (!BeheerAuth::isIngelogd()) {
            $this->stuurDoor(self::INLOGPAGINA);
        }
    }

    private function controleerToken(): void
    {
        if (!Beveiliging::tokenKlopt($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            exit('Het formulier is verlopen. Ga terug en probeer het opnieuw.');
        }
    }

    /**
     * Een id uit de adresbalk of het formulier: alleen een positief
     * geheel getal telt.
     */
    private function leesId($waarde): ?int
    {
        if (!is_string($waarde)) {
            return null;
        }

        $id = filter_var($waarde, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }

    private function haalInzending($waarde): array
    {
        $id        = $this->leesId($waarde);
        $inzending = $id === null ? null : $this->beheerModel->inzending($id);

        if ($inzending === null) {
            $this->nietGevonden();
        }

        return $inzending;
    }

    private function nietGevonden(): void
    {
        http_response_code(404);
        $this->toonView('niet-gevonden', []);
        exit;
    }

    private function toonView(string $naam, array $data): void
    {
        extract($data, EXTR_SKIP);

        require __DIR__ . '/../views/beheer/' . $naam . '.php';
    }

    private function stuurDoor(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
