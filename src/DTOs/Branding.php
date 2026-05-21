<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

use DateTimeImmutable;

/**
 * Represente la configuration de marque (branding) d'un tenant ou sub-tenant.
 *
 * Le branding est applique aux emails et PDFs emis sous ce perimetre.
 * Si tous les champs requis sont renseignes (logo + couleur + footer),
 * l'API utilisera ce branding a la place du branding Scell.io par defaut.
 */
final readonly class Branding
{
    public function __construct(
        /** URL publique du logo (stocke sur S3). */
        public ?string $logoUrl,
        /** Couleur primaire au format hexadecimal (ex: '#1a73e8'). */
        public ?string $primaryColor,
        /** Texte de pied de page des emails (mentions legales, etc.). */
        public ?string $emailFooter,
        /** Signature de fin d'email (coordonnees, slogan, etc.). */
        public ?string $emailSignature,
        /** Indique si le branding est complet (pret a etre utilise). */
        public bool $isComplete,
        /** Scope du branding : 'tenant' ou 'sub_tenant'. */
        public string $scope,
        /** ID de l'entite concernee (tenant_id ou sub_tenant_id). */
        public ?string $entityId,
        public ?DateTimeImmutable $updatedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            logoUrl: $data['logo_url'] ?? $data['brand_logo_url'] ?? null,
            primaryColor: $data['primary_color'] ?? $data['brand_primary_color'] ?? null,
            emailFooter: $data['email_footer'] ?? $data['brand_email_footer'] ?? null,
            emailSignature: $data['email_signature'] ?? $data['brand_email_signature'] ?? null,
            isComplete: (bool) ($data['is_complete'] ?? false),
            scope: $data['scope'] ?? 'tenant',
            entityId: $data['entity_id'] ?? null,
            updatedAt: isset($data['updated_at'])
                ? new DateTimeImmutable($data['updated_at'])
                : null,
        );
    }

    /**
     * Indique si le branding est complet et sera utilise a la place de Scell.io.
     */
    public function isReady(): bool
    {
        return $this->isComplete
            && $this->logoUrl !== null
            && $this->primaryColor !== null
            && $this->emailFooter !== null;
    }
}
