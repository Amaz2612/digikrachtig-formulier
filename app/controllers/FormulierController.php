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

        $this->toonView('formulier', [
            'formulier'  => $formulier,
            'structuur'  => $structuur,
            'antwoorden' => $antwoorden,
            'fouten'     => $fouten,
        ]);
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
