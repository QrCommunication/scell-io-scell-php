<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use DateTimeInterface;
use Scell\Sdk\DTOs\DateBlock;
use Scell\Sdk\DTOs\InitialsBlock;
use Scell\Sdk\DTOs\Mention;
use Scell\Sdk\DTOs\PaginatedResult;
use Scell\Sdk\DTOs\Signature;
use Scell\Sdk\DTOs\Signer;
use Scell\Sdk\Enums\AuthMethod;
use Scell\Sdk\Enums\SignatureStatus;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les signatures electroniques.
 *
 * Permet de creer et gerer les demandes de signature eIDAS EU-SES.
 */
class SignatureResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste les signatures du tenant courant avec filtrage optionnel.
     *
     * Depuis v2.5.0, l'endpoint est expose aux SDKs avec auth `sk_live_*` /
     * `sk_test_*` (avant : Sanctum SPA only). Le scope est `tenant_id` (toutes
     * les signatures du tenant et de ses sub-tenants) et non plus `user_id`.
     *
     * Pour limiter aux signatures d'un sub-tenant precis, passer
     * `sub_tenant_id` : le backend verifie l'appartenance au tenant courant
     * (anti-IDOR) et retourne 403 si le sub-tenant n'appartient pas au tenant.
     *
     * @param array{
     *     status?: SignatureStatus|string,
     *     environment?: string,
     *     company_id?: string,
     *     sub_tenant_id?: string,
     *     per_page?: int,
     *     page?: int
     * } $filters
     * @return PaginatedResult<Signature>
     */
    public function list(array $filters = []): PaginatedResult
    {
        $query = $this->normalizeFilters($filters);
        $response = $this->http->get('signatures', $query);

        return PaginatedResult::fromArray($response, fn(array $data) => Signature::fromArray($data));
    }

    /**
     * Recupere une signature par son ID.
     */
    public function get(string $id): Signature
    {
        $response = $this->http->get("signatures/{$id}");
        return Signature::fromArray($response['data']);
    }

    /**
     * Cree une nouvelle demande de signature.
     *
     * Multi-document (v2.16.0) — pour faire signer plusieurs PDFs en une
     * seule operation, passer `attachments[]` (max 10 PJ, 20 Mo cumules).
     * Le backend Scell.io merge automatiquement le document principal +
     * les pieces jointes en un PDF unique avant submission au prestataire
     * de signature (page de garde + numerotation continue). Pour cibler
     * un document specifique dans une `signature_positions[]`,
     * `mentions[].position`, `initials_block.positions[]` ou `date_block.position`,
     * utiliser le champ `document_index` (`0` = principal, `1..N` =
     * attachments dans l'ordre).
     *
     * @param array{
     *     title: string,
     *     document: string,
     *     document_name: string,
     *     signers: Signer[]|array[],
     *     external_id?: string,
     *     description?: string,
     *     signature_positions?: array[],
     *     ui_config?: array,
     *     signature_options?: array,
     *     initials_block?: InitialsBlock|array,
     *     mentions?: array<Mention|array>,
     *     date_block?: DateBlock|array,
     *     attachments?: array<array{document: string, document_name: string}>,
     *     redirect_complete_url?: string,
     *     redirect_cancel_url?: string,
     *     expires_at?: DateTimeInterface|string,
     *     archive_enabled?: bool
     * } $data
     */
    public function create(array $data): Signature
    {
        $payload = $this->normalizeCreatePayload($data);
        $response = $this->http->post('signatures', $payload);
        return Signature::fromArray($response['data']);
    }

    /**
     * Cree une signature avec le builder fluent.
     */
    public function builder(): SignatureBuilder
    {
        return new SignatureBuilder($this);
    }

    /**
     * Telecharge un fichier de signature.
     *
     * @param string $id ID de la signature
     * @param string $type Type de fichier: 'original', 'signed', 'audit_trail'
     * @return array{url: string, expires_at: string}
     */
    public function download(string $id, string $type = 'signed'): array
    {
        return $this->http->get("signatures/{$id}/download/{$type}");
    }

    /**
     * Envoie un rappel aux signataires en attente.
     *
     * @return array{message: string, signers_reminded: int}
     */
    public function remind(string $id): array
    {
        return $this->http->post("signatures/{$id}/remind");
    }

    /**
     * Annule une demande de signature.
     *
     * @return array{message: string}
     */
    public function cancel(string $id): array
    {
        return $this->http->post("signatures/{$id}/cancel");
    }

    /**
     * Recupere la piste d'audit de la signature (format JSON).
     *
     * Retourne l'historique complet des actions sur la signature :
     * creation, envoi, ouverture, signature, refus, etc.
     *
     * @param string $id ID de la signature
     * @return array{data: array[], integrity_valid: bool}
     */
    public function auditTrail(string $id): array
    {
        return $this->http->get("signatures/{$id}/audit-trail");
    }

    /**
     * Normalise les filtres de liste.
     */
    private function normalizeFilters(array $filters): array
    {
        $query = [];

        foreach ($filters as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($value instanceof SignatureStatus) {
                $query[$key] = $value->value;
            } else {
                $query[$key] = $value;
            }
        }

        return $query;
    }

    /**
     * Normalise le payload de creation.
     */
    private function normalizeCreatePayload(array $data): array
    {
        $payload = [
            'title' => $data['title'],
            'document' => $data['document'],
            'document_name' => $data['document_name'],
        ];

        // Signataires
        $payload['signers'] = array_map(
            fn($signer) => $signer instanceof Signer ? $signer->toArray() : $signer,
            $data['signers']
        );

        // Champs optionnels
        if (isset($data['external_id'])) {
            $payload['external_id'] = $data['external_id'];
        }
        if (isset($data['description'])) {
            $payload['description'] = $data['description'];
        }
        if (isset($data['signature_positions'])) {
            $payload['signature_positions'] = $data['signature_positions'];
        }
        if (isset($data['ui_config'])) {
            $payload['ui_config'] = $data['ui_config'];
        }
        if (isset($data['signature_options'])) {
            $payload['signature_options'] = $data['signature_options'];
        }
        // v2.12 — Signature blocks (paraphe / mentions / date)
        if (isset($data['initials_block'])) {
            $payload['initials_block'] = $data['initials_block'] instanceof InitialsBlock
                ? $data['initials_block']->toArray()
                : $data['initials_block'];
        }
        if (isset($data['mentions'])) {
            $payload['mentions'] = array_map(
                fn($m) => $m instanceof Mention ? $m->toArray() : $m,
                $data['mentions']
            );
        }
        if (isset($data['date_block'])) {
            $payload['date_block'] = $data['date_block'] instanceof DateBlock
                ? $data['date_block']->toArray()
                : $data['date_block'];
        }
        // v2.16 — Multi-document : pieces jointes mergees cote backend.
        if (isset($data['attachments']) && !empty($data['attachments'])) {
            $payload['attachments'] = array_values($data['attachments']);
        }
        if (isset($data['redirect_complete_url'])) {
            $payload['redirect_complete_url'] = $data['redirect_complete_url'];
        }
        if (isset($data['redirect_cancel_url'])) {
            $payload['redirect_cancel_url'] = $data['redirect_cancel_url'];
        }
        if (isset($data['expires_at'])) {
            $payload['expires_at'] = $data['expires_at'] instanceof DateTimeInterface
                ? $data['expires_at']->format('c')
                : $data['expires_at'];
        }
        if (isset($data['archive_enabled'])) {
            $payload['archive_enabled'] = $data['archive_enabled'];
        }

        return $payload;
    }
}

/**
 * Builder fluent pour creer des demandes de signature.
 */
class SignatureBuilder
{
    private array $data = [];
    private array $signers = [];
    private array $signaturePositions = [];
    /** @var list<array{document: string, document_name: string}> */
    private array $attachments = [];

    public function __construct(
        private readonly SignatureResource $resource
    ) {}

    public function title(string $title): self
    {
        $this->data['title'] = $title;
        return $this;
    }

    public function description(string $description): self
    {
        $this->data['description'] = $description;
        return $this;
    }

    public function externalId(string $id): self
    {
        $this->data['external_id'] = $id;
        return $this;
    }

    /**
     * Definit le document a signer.
     *
     * @param string $content Contenu du fichier
     * @param string $name Nom du fichier (ex: 'contrat.pdf')
     * @param bool $isBase64 Si true, le contenu est deja encode en base64
     */
    public function document(string $content, string $name, bool $isBase64 = false): self
    {
        $this->data['document'] = $isBase64 ? $content : base64_encode($content);
        $this->data['document_name'] = $name;
        return $this;
    }

    /**
     * Charge un document depuis un fichier.
     *
     * @param string $path Chemin vers le fichier
     */
    public function documentFromFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Fichier non trouve: {$path}");
        }

        $content = file_get_contents($path);
        $name = basename($path);

        return $this->document($content, $name);
    }

    /**
     * Ajoute un signataire.
     *
     * @param string|null $message Message custom optionnel envoye au signataire (max 500 chars).
     *                             Supporte le placeholder `{OTP}` qui sera remplace par le code OTP.
     */
    public function addSigner(
        string $firstName,
        string $lastName,
        AuthMethod $authMethod,
        ?string $email = null,
        ?string $phone = null,
        ?string $message = null,
    ): self {
        $this->signers[] = Signer::create($firstName, $lastName, $authMethod, $email, $phone, $message);
        return $this;
    }

    /**
     * Ajoute un signataire avec authentification par email.
     *
     * @param string|null $message Message custom optionnel (max 500 chars, placeholder `{OTP}`).
     */
    public function addEmailSigner(string $firstName, string $lastName, string $email, ?string $message = null): self
    {
        return $this->addSigner($firstName, $lastName, AuthMethod::Email, $email, null, $message);
    }

    /**
     * Ajoute un signataire avec authentification par SMS.
     *
     * @param string|null $message Message custom optionnel (max 500 chars, placeholder `{OTP}`).
     */
    public function addSmsSigner(string $firstName, string $lastName, string $phone, ?string $message = null): self
    {
        return $this->addSigner($firstName, $lastName, AuthMethod::Sms, null, $phone, $message);
    }

    /**
     * Ajoute un signataire a partir d'un DTO Signer.
     */
    public function addSignerDto(Signer $signer): self
    {
        $this->signers[] = $signer;
        return $this;
    }

    /**
     * Ajoute une position de signature visuelle.
     *
     * Les coordonnees `x` / `y` (et `width` / `height`) sont exprimees dans l'unite `$unit` :
     *  - `'percent'` (defaut) : valeurs entre 0 et 100, relatives a la page.
     *  - `'pixel'`            : coordonnees absolues en pixels @72dpi.
     *
     * Les dimensions de page (`pageWidthPx` / `pageHeightPx`) sont optionnelles.
     * Si absentes, le backend les detecte automatiquement via parser PDF avec
     * fallback A4 (595x842 px) — pratique pour la plupart des cas. A fournir
     * explicitement uniquement si vous avez deja parse le PDF cote client.
     *
     * Multi-document (v2.16.0) — `documentIndex` permet de cibler un PDF
     * specifique dans un bundle (`attachments[]`) : `0` = document principal,
     * `1..N` = attachments dans l'ordre. Defaut `null` = document principal.
     *
     * Multi-position par signataire (v2.27.0) — `signerIndex` (0-base) affecte
     * explicitement la position a un signataire precis (`0` = premier signataire
     * ajoute, `1` = deuxieme, etc.). EU-SES autorise desormais **plusieurs**
     * positions de signature pour un meme signataire : appeler
     * `addSignaturePosition()` autant de fois que necessaire avec le meme
     * `signerIndex` (ex: le signataire 0 signe pages 1 et 3). Defaut `null` =
     * mapping positionnel historique (1 position par signataire dans l'ordre).
     *
     * @param int        $page          Numero de page (1-indexe).
     * @param float      $x             Position X (percent 0-100 ou pixels).
     * @param float      $y             Position Y (percent 0-100 ou pixels).
     * @param float|null $width         Largeur optionnelle.
     * @param float|null $height        Hauteur optionnelle.
     * @param string     $unit          `'percent'` (defaut) ou `'pixel'`.
     * @param int|null   $pageWidthPx   Largeur de la page en px @72dpi (override du parser auto).
     * @param int|null   $pageHeightPx  Hauteur de la page en px @72dpi (override du parser auto).
     * @param int|null   $documentIndex Cible un document du bundle (0 = principal, 1..N = attachments). 0..10.
     * @param int|null   $signerIndex   Affecte la position a un signataire precis (0-base). >= 0.
     */
    public function addSignaturePosition(
        int $page,
        float $x,
        float $y,
        ?float $width = null,
        ?float $height = null,
        string $unit = 'percent',
        ?int $pageWidthPx = null,
        ?int $pageHeightPx = null,
        ?int $documentIndex = null,
        ?int $signerIndex = null,
    ): self {
        if ($documentIndex !== null && ($documentIndex < 0 || $documentIndex > 10)) {
            throw new \InvalidArgumentException(
                "Invalid documentIndex {$documentIndex}. Must be between 0 and 10 (0 = main document, 1..N = attachments)."
            );
        }
        if ($signerIndex !== null && $signerIndex < 0) {
            throw new \InvalidArgumentException(
                "Invalid signerIndex {$signerIndex}. Must be a 0-based index (>= 0)."
            );
        }
        $position = [
            'page' => $page,
            'x' => $x,
            'y' => $y,
            'unit' => $unit,
        ];
        if ($width !== null) {
            $position['width'] = $width;
        }
        if ($height !== null) {
            $position['height'] = $height;
        }
        if ($pageWidthPx !== null) {
            $position['page_width_px'] = $pageWidthPx;
        }
        if ($pageHeightPx !== null) {
            $position['page_height_px'] = $pageHeightPx;
        }
        if ($documentIndex !== null) {
            $position['document_index'] = $documentIndex;
        }
        if ($signerIndex !== null) {
            $position['signer_index'] = $signerIndex;
        }

        $this->signaturePositions[] = $position;
        return $this;
    }

    /**
     * Definit la liste complete des pieces jointes (multi-document, v2.16.0).
     *
     * Le backend Scell.io accepte un PDF principal (`document`) plus jusqu'a
     * **10 pieces jointes** (`attachments[]`), pour un total cumule de
     * **20 Mo**. Tous les fichiers sont mergees en un PDF unique cote serveur
     * (page de garde + numerotation continue) avant submission au prestataire
     * de signature partenaire. Les positions de signature, mentions, paraphes
     * et bloc date peuvent cibler un document precis via leur champ
     * `document_index` (`0` = principal, `1..N` = attachments dans l'ordre).
     *
     * Chaque entree doit etre un tableau de la forme :
     * ```
     * ['document' => '<base64>', 'document_name' => 'annexe.pdf']
     * ```
     *
     * Remplace toute liste precedente. Pour ajouter une PJ incrementalement,
     * utiliser {@see addAttachment()}.
     *
     * @param list<array{document: string, document_name: string}> $attachments
     */
    public function attachments(array $attachments): self
    {
        if (count($attachments) > 10) {
            throw new \InvalidArgumentException(
                'A signature can have a maximum of 10 attachments (got ' . count($attachments) . ').'
            );
        }
        $this->attachments = array_values($attachments);
        return $this;
    }

    /**
     * Ajoute une piece jointe (PDF) au bundle multi-document — v2.16.0.
     *
     * Le contenu doit etre deja encode en **base64**. Limite : 10 PJ max,
     * 20 Mo cumules avec le document principal. Le backend Scell.io merge
     * automatiquement principal + PJs en un PDF unique avant submission au
     * prestataire de signature partenaire.
     *
     * @example
     * ```php
     * $resource->builder()
     *     ->title('Contrat + annexes')
     *     ->document(file_get_contents('contrat.pdf'), 'contrat.pdf')
     *     ->addAttachment(base64_encode(file_get_contents('cgv.pdf')), 'cgv.pdf')
     *     ->addAttachment(base64_encode(file_get_contents('annexe.pdf')), 'annexe.pdf')
     *     ->addEmailSigner('Jean', 'Dupont', 'jean@example.com')
     *     // Le signataire 0 signe a deux endroits : page 5 du contrat ET page 1 de l'annexe.
     *     ->addSignaturePosition(page: 5, x: 70, y: 80, documentIndex: 0, signerIndex: 0) // sur contrat
     *     ->addSignaturePosition(page: 1, x: 70, y: 80, documentIndex: 2, signerIndex: 0) // sur annexe
     *     ->create();
     * ```
     *
     * @param string $document     Contenu PDF encode en base64.
     * @param string $documentName Nom du fichier (ex: `'annexe.pdf'`).
     */
    public function addAttachment(string $document, string $documentName): self
    {
        if (count($this->attachments) >= 10) {
            throw new \InvalidArgumentException(
                'A signature can have a maximum of 10 attachments.'
            );
        }
        $this->attachments[] = [
            'document' => $document,
            'document_name' => $documentName,
        ];
        return $this;
    }

    /**
     * Ajoute une piece jointe depuis un fichier local (encodage base64 auto) — v2.16.0.
     *
     * Helper de convenance qui lit le fichier, l'encode en base64 et le push
     * dans le tableau d'attachments. Voir {@see addAttachment()} pour les
     * limites et le comportement de merge cote backend.
     *
     * @param string      $path     Chemin vers le PDF a ajouter.
     * @param string|null $name     Nom optionnel (defaut : basename($path)).
     */
    public function addAttachmentFromFile(string $path, ?string $name = null): self
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Fichier non trouve: {$path}");
        }
        $content = file_get_contents($path);
        return $this->addAttachment(base64_encode($content), $name ?? basename($path));
    }

    /**
     * Configure l'interface utilisateur (white-label) — 21 champs alignés sur
     * la spec EU-SES (eIDAS certifiée).
     *
     * Couleurs : tous les champs `*_color` attendent du hex `#RRGGBB`.
     * Logo : URL absolue HTTPS publique (max 500 chars).
     *
     * Sidebar :
     *  - `sidebar_logo`              (string URL)
     *  - `sidebar_background_color`  (#RRGGBB)
     *  - `sidebar_title_color`       (#RRGGBB)
     *  - `sidebar_text_color`        (#RRGGBB)
     *
     * Header :
     *  - `header_background_color`   (#RRGGBB)
     *  - `header_title_color`        (#RRGGBB)
     *  - `header_subtitle_color`     (#RRGGBB)
     *
     * Footer :
     *  - `footer_background_color`   (#RRGGBB)
     *
     * Boutons standards :
     *  - `button_text_color`              (#RRGGBB)
     *  - `button_text_color_hover`        (#RRGGBB)
     *  - `button_background_color`        (#RRGGBB)
     *  - `button_background_color_hover`  (#RRGGBB)
     *
     * Bouton "Signer" (override des boutons standards) :
     *  - `sign_button_text_color`             (#RRGGBB)
     *  - `sign_button_text_color_hover`       (#RRGGBB)
     *  - `sign_button_background_color`       (#RRGGBB)
     *  - `sign_button_background_color_hover` (#RRGGBB)
     *
     * Toggles d'affichage :
     *  - `hide_sidebar`            (bool)
     *  - `hide_header`             (bool)
     *  - `hide_download_validated` (bool)
     *  - `hide_download_signed`    (bool)
     *
     * Iframe (max 20 URLs autorisees) :
     *  - `iframe_ancestors` (string[]): domaines autorises a embarquer la
     *    page de signature. Si Scell.io heberge la page wrapper, le backend
     *    injecte automatiquement `https://sign.scell.io` en plus de vos URLs.
     *
     * Les valeurs `null` sont filtrees. Appels multiples fusionnes.
     *
     * @param array<string, mixed> $config
     */
    public function uiConfig(array $config): self
    {
        $filtered = array_filter($config, fn($v) => $v !== null);
        $existing = $this->data['ui_config'] ?? [];
        $this->data['ui_config'] = array_merge($existing, $filtered);
        return $this;
    }

    /**
     * Configure les options de signature (comportement non-UI).
     *
     * Champs supportes (tous optionnels) :
     *  - `signature_mode` (string) : mode de saisie. Valeurs valides :
     *      - `'typed'` : signature tapee au clavier uniquement
     *      - `'drawn'` : signature dessinee uniquement
     *      - `'both'`  : laisse le signataire choisir
     *  - `signer_must_read` (bool) : force le signataire a parcourir tout
     *    le document avant de pouvoir signer.
     *  - `user_editable_data` (array) : autorise le signataire a modifier
     *    certaines de ses donnees. Forme :
     *      ['name' => bool, 'mobile' => bool, 'email' => bool]
     *  - `timezone` (string) : identifiant IANA (ex. `'Europe/Paris'`).
     *
     * @param array<string, mixed> $options
     */
    public function signatureOptions(array $options): self
    {
        $filtered = array_filter($options, fn($v) => $v !== null);
        if (!empty($filtered)) {
            $this->data['signature_options'] = $filtered;
        }
        return $this;
    }

    /**
     * Configure le bloc paraphe (initiales automatiques sur les pages du
     * PDF signe) — v2.12.0.
     *
     * Accepte un DTO {@see InitialsBlock} ou un tableau associatif :
     * ```php
     * ->initialsBlock([
     *     'enabled'  => true,
     *     'mode'     => 'auto',           // 'auto' | 'custom'
     *     'source'   => 'signer_name',    // 'signer_name' | 'custom'
     *     'pages'    => 'all',            // 'all' | 'except_last' | [1,2,5]
     *     'position' => ['x' => 90, 'y' => 95, 'unit' => 'percent'],
     *     'font_size'=> 10,
     *     'color'    => '#333333',
     * ])
     * ```
     *
     * @param InitialsBlock|array<string, mixed> $config
     */
    public function initialsBlock(InitialsBlock|array $config): self
    {
        $this->data['initials_block'] = $config;
        return $this;
    }

    /**
     * Configure le tableau des mentions juridiques (ex: "Lu et approuve",
     * "Bon pour accord"...) gravees sur le PDF — v2.12.0.
     *
     * Remplace toute liste precedente. Pour ajouter une seule mention
     * incrementalement, utiliser {@see addMention()}.
     *
     * @param array<Mention|array<string, mixed>> $mentions
     */
    public function mentions(array $mentions): self
    {
        $this->data['mentions'] = $mentions;
        return $this;
    }

    /**
     * Ajoute une mention juridique au tableau — v2.12.0.
     *
     * @param Mention|array<string, mixed> $mention
     */
    public function addMention(Mention|array $mention): self
    {
        if (!isset($this->data['mentions']) || !is_array($this->data['mentions'])) {
            $this->data['mentions'] = [];
        }
        $this->data['mentions'][] = $mention;
        return $this;
    }

    /**
     * Configure le bloc date du jour grave sur le PDF signe — v2.12.0.
     *
     * Accepte un DTO {@see DateBlock} ou un tableau associatif :
     * ```php
     * ->dateBlock([
     *     'enabled'  => true,
     *     'format'   => 'd/m/Y',
     *     'timezone' => 'Europe/Paris',
     *     'position' => ['page' => 'last', 'x' => 80, 'y' => 10, 'unit' => 'percent'],
     *     'font_size'=> 10,
     *     'color'    => '#000000',
     * ])
     * ```
     *
     * @param DateBlock|array<string, mixed> $config
     */
    public function dateBlock(DateBlock|array $config): self
    {
        $this->data['date_block'] = $config;
        return $this;
    }

    /**
     * Configure les URLs de redirection.
     */
    public function redirectUrls(?string $completeUrl = null, ?string $cancelUrl = null): self
    {
        if ($completeUrl !== null) {
            $this->data['redirect_complete_url'] = $completeUrl;
        }
        if ($cancelUrl !== null) {
            $this->data['redirect_cancel_url'] = $cancelUrl;
        }
        return $this;
    }

    /**
     * Configure la date d'expiration.
     */
    public function expiresAt(DateTimeInterface|string $date): self
    {
        $this->data['expires_at'] = $date;
        return $this;
    }

    /**
     * Active l'archivage.
     */
    public function archiveEnabled(bool $enabled = true): self
    {
        $this->data['archive_enabled'] = $enabled;
        return $this;
    }

    /**
     * Cree la demande de signature.
     */
    public function create(): Signature
    {
        if (empty($this->signers)) {
            throw new \InvalidArgumentException('Au moins un signataire est requis');
        }

        $this->data['signers'] = $this->signers;

        if (!empty($this->signaturePositions)) {
            $this->data['signature_positions'] = $this->signaturePositions;
        }

        if (!empty($this->attachments)) {
            $this->data['attachments'] = $this->attachments;
        }

        return $this->resource->create($this->data);
    }
}
