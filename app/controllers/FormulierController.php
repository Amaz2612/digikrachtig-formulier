<?php
/**
 * Digikrachtig formulierensysteem
 * Controller voor het formulier.
 *
 * Plaats dit bestand in: app/controllers/FormulierController.php
 *
 * De controller beslist wat er gebeurt. Hij haalt data op bij de
 * modellen en kiest welke view getoond wordt. Er staat hier bewust
 * geen SQL (dat doen de modellen) en geen HTML (dat doen de views).
 */
class FormulierController
{
    private PDO $pdo;
    private FormulierModel $formulierModel;
    private InzendingModel $inzendingModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo            = $pdo;
        $this->formulierModel = new FormulierModel($pdo);
        $this->inzendingModel = new InzendingModel($pdo);
    }

    /**
     * Toont het formulier met de eerder opgeslagen antwoorden erin.
     */
    public function toon(array $fouten = [], array $ingevuld = []): void
    {
        $formulier = $this->haalFormulier();
        $inzending = $this->inzendingModel->haalOfMaak(
            (int) $formulier['id'],
            Auth::gebruikerId()
        );

        // Al ingediend: niet opnieuw laten invullen.
        if ($inzending['status'] === 'ingediend') {
            $this->toonView('klaar', [
                'formulier' => $formulier,
                'opnieuw'   => false,
            ]);

            return;
        }

        $structuur = $this->formulierModel->structuur((int) $formulier['id']);

        // Bij een fout tonen we wat de student net invulde, anders
        // wat er in de database staat.
        $antwoorden = $fouten === []
            ? $this->inzendingModel->antwoorden((int) $inzending['id'])
            : $ingevuld;

        // Naam en e-mail van de login vooraf invullen, maar alleen als
        // de student daar zelf nog niets heeft staan.
        if ($fouten === []) {
            $vooraf = [
                'ingevuld_door' => Auth::naam(),
                'email'         => Auth::email(),
                'naam_student'  => Auth::naam(),
            ];

            foreach ($vooraf as $code => $waarde) {
                if ($waarde !== null && trim((string) ($antwoorden[$code] ?? '')) === '') {
                    $antwoorden[$code] = $waarde;
                }
            }
        }

        $this->toonView('formulier', [
            'formulier'  => $formulier,
            'structuur'  => $structuur,
            'antwoorden' => $antwoorden,
            'fouten'     => $fouten,
            'startStap'  => $inzending['huidige_stap'] ?? null,
        ]);
    }

    /**
     * Automatisch opslaan, aangeroepen door voorwaarden.js terwijl de
     * student invult. Geeft JSON terug in plaats van een pagina.
     *
     * Werkt als tussentijds opslaan: verplichte velden mogen leeg.
     * Antwoorden die niet kloppen (een half ingetypt e-mailadres) worden
     * niet bewaard; de rest wel. De student krijgt hier geen foutmeldingen
     * van te zien, die komen pas bij Volgende of Versturen.
     */
    public function autosave(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Beveiliging::tokenKlopt($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reden' => 'verlopen']);

            return;
        }

        $formulier   = $this->haalFormulier();
        $formulierId = (int) $formulier['id'];

        $inzending   = $this->inzendingModel->haalOfMaak(
            $formulierId,
            Auth::gebruikerId()
        );
        $inzendingId = (int) $inzending['id'];

        if ($inzending['status'] === 'ingediend') {
            http_response_code(409);
            echo json_encode(['ok' => false, 'reden' => 'ingediend']);

            return;
        }

        $vragenPerCode = $this->formulierModel->vragenPerCode($formulierId);
        $antwoorden    = $this->haalAntwoordenUitPost($vragenPerCode);

        $fouten = Validatie::controleer($vragenPerCode, $antwoorden, false);

        foreach (array_keys($fouten) as $code) {
            unset($antwoorden[$code]);
        }

        $this->inzendingModel->slaAntwoordenOp(
            $inzendingId,
            $vragenPerCode,
            $antwoorden,
            false
        );

        // De stap alleen onthouden als het een sectie is die bestaat.
        $stap = $_POST['stap'] ?? null;

        if (is_string($stap) && $this->formulierModel->heeftSectie($formulierId, $stap)) {
            $this->inzendingModel->slaStapOp($inzendingId, $stap);
        }

        echo json_encode(['ok' => true]);
    }

    /**
     * Verwerkt het verzonden formulier.
     *
     * Twee knoppen:
     *   opslaan   - tussentijds bewaren, verplichte velden mogen leeg
     *   verstuur  - definitief indienen, alles wordt gecontroleerd
     */
    public function verwerk(): void
    {
        if (!Beveiliging::tokenKlopt($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            exit('Het formulier is verlopen. Ga terug en probeer het opnieuw.');
        }

        $formulier   = $this->haalFormulier();
        $formulierId = (int) $formulier['id'];

        $inzending   = $this->inzendingModel->haalOfMaak(
            $formulierId,
            Auth::gebruikerId()
        );
        $inzendingId = (int) $inzending['id'];

        if ($inzending['status'] === 'ingediend') {
            $this->stuurDoor('?actie=klaar');
        }

        $definitief    = isset($_POST['verstuur']);
        $vragenPerCode = $this->formulierModel->vragenPerCode($formulierId);
        $antwoorden    = $this->haalAntwoordenUitPost($vragenPerCode);

        $fouten = Validatie::controleer(
            $vragenPerCode,
            $antwoorden,
            $definitief
        );

        if ($fouten !== []) {
            $this->inzendingModel->logGebeurtenis(
                $inzendingId,
                'validatie_mislukt',
                count($fouten) . ' fouten'
            );

            $this->toon($fouten, $antwoorden);

            return;
        }

        $this->inzendingModel->slaAntwoordenOp(
            $inzendingId,
            $vragenPerCode,
            $antwoorden
        );

        if ($definitief) {
            $this->inzendingModel->dienIn($inzendingId);
            $this->stuurDoor('?actie=klaar');
        }

        $this->stuurDoor('?actie=formulier&opgeslagen=1');
    }

    /**
     * De bedankpagina na het versturen.
     */
    public function klaar(): void
    {
        $this->toonView('klaar', [
            'formulier' => $this->haalFormulier(),
            'opnieuw'   => false,
        ]);
    }

    /**
     * Haalt uit $_POST alleen de velden die bij een bestaande vraag
     * horen. Alles wat iemand er zelf bij verzint wordt genegeerd.
     */
    private function haalAntwoordenUitPost(array $vragenPerCode): array
    {
        $antwoorden = [];

        foreach ($vragenPerCode as $code => $vraag) {
            if ($vraag['is_melding'] || !isset($_POST[$code])) {
                continue;
            }

            $waarde = $_POST[$code];

            if ($vraag['type'] === 'checkbox') {
                $antwoorden[$code] = is_array($waarde) ? $waarde : [$waarde];
            } elseif (is_array($waarde)) {
                // Onverwachte array bij een gewoon veld: negeren.
                continue;
            } else {
                $antwoorden[$code] = $waarde;
            }
        }

        return $antwoorden;
    }

    private function haalFormulier(): array
    {
        $formulier = $this->formulierModel->actiefFormulier();

        if ($formulier === null) {
            exit('Er is op dit moment geen actief formulier.');
        }

        return $formulier;
    }

    private function toonView(string $naam, array $data): void
    {
        extract($data, EXTR_SKIP);

        require __DIR__ . '/../views/' . $naam . '.php';
    }

    private function stuurDoor(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
