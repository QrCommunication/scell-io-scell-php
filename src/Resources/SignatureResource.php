<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use DateTimeInterface;
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
     * Liste les signatures avec filtrage optionnel.
     *
     * @param array{
     *     status?: SignatureStatus|string,
     *     environment?: string,
     *     company_id?: string,
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
     * @param array{
     *     title: string,
     *     document: string,
     *     document_name: string,
     *     signers: Signer[]|array[],
     *     external_id?: string,
     *     description?: string,
     *     signature_positions?: array[],
     *     ui_config?: array,
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
     */
    public function addSigner(
        string $firstName,
        string $lastName,
        AuthMethod $authMethod,
        ?string $email = null,
        ?string $phone = null
    ): self {
        $this->signers[] = Signer::create($firstName, $lastName, $authMethod, $email, $phone);
        return $this;
    }

    /**
     * Ajoute un signataire avec authentification par email.
     */
    public function addEmailSigner(string $firstName, string $lastName, string $email): self
    {
        return $this->addSigner($firstName, $lastName, AuthMethod::Email, $email);
    }

    /**
     * Ajoute un signataire avec authentification par SMS.
     */
    public function addSmsSigner(string $firstName, string $lastName, string $phone): self
    {
        return $this->addSigner($firstName, $lastName, AuthMethod::Sms, null, $phone);
    }

    /**
     * Ajoute une position de signature visuelle.
     */
    public function addSignaturePosition(int $page, float $x, float $y, ?float $width = null, ?float $height = null): self
    {
        $position = [
            'page' => $page,
            'x' => $x,
            'y' => $y,
        ];
        if ($width !== null) {
            $position['width'] = $width;
        }
        if ($height !== null) {
            $position['height'] = $height;
        }

        $this->signaturePositions[] = $position;
        return $this;
    }

    /**
     * Configure l'interface utilisateur (white-label).
     */
    public function uiConfig(?string $logoUrl = null, ?string $primaryColor = null, ?string $companyName = null): self
    {
        $this->data['ui_config'] = array_filter([
            'logo_url' => $logoUrl,
            'primary_color' => $primaryColor,
            'company_name' => $companyName,
        ], fn($v) => $v !== null);
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

        return $this->resource->create($this->data);
    }
}
