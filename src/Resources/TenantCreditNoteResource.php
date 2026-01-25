<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les avoirs (credit notes) des sub-tenants.
 *
 * Permet de creer, lister et gerer les avoirs pour les clients finaux
 * d'un tenant partenaire.
 */
class TenantCreditNoteResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste les avoirs d'un sub-tenant.
     *
     * @param string $subTenantId UUID du sub-tenant
     * @param array{
     *     status?: string,
     *     date_from?: string,
     *     date_to?: string,
     *     page?: int,
     *     per_page?: int
     * } $params Filtres optionnels
     * @return array{data: array[], meta?: array}
     */
    public function list(string $subTenantId, array $params = []): array
    {
        return $this->http->get("tenant/sub-tenants/{$subTenantId}/credit-notes", $params);
    }

    /**
     * Cree un avoir pour un sub-tenant.
     *
     * @param string $subTenantId UUID du sub-tenant
     * @param array{
     *     invoice_id: string,
     *     reason: string,
     *     type: string,
     *     items?: array[]
     * } $data Donnees de l'avoir
     * @return array{data: array, message?: string}
     */
    public function create(string $subTenantId, array $data): array
    {
        return $this->http->post("tenant/sub-tenants/{$subTenantId}/credit-notes", $data);
    }

    /**
     * Recupere un avoir par son ID.
     *
     * @param string $creditNoteId UUID de l'avoir
     * @return array{data: array}
     */
    public function get(string $creditNoteId): array
    {
        return $this->http->get("tenant/credit-notes/{$creditNoteId}");
    }

    /**
     * Envoie (valide et transmet) un avoir.
     *
     * @param string $creditNoteId UUID de l'avoir
     * @return array{data: array, message?: string}
     */
    public function send(string $creditNoteId): array
    {
        return $this->http->post("tenant/credit-notes/{$creditNoteId}/send");
    }

    /**
     * Supprime un avoir en brouillon.
     *
     * @param string $creditNoteId UUID de l'avoir
     * @return array{message?: string}
     */
    public function delete(string $creditNoteId): array
    {
        return $this->http->delete("tenant/credit-notes/{$creditNoteId}");
    }

    /**
     * Telecharge le PDF d'un avoir.
     *
     * @param string $creditNoteId UUID de l'avoir
     * @return string Contenu binaire du PDF
     */
    public function download(string $creditNoteId): string
    {
        return $this->http->getRaw("tenant/credit-notes/{$creditNoteId}/download");
    }

    /**
     * Recupere les montants restants creditables pour une facture.
     *
     * @param string $invoiceId UUID de la facture
     * @return array{data: array}
     */
    public function remainingCreditable(string $invoiceId): array
    {
        return $this->http->get("tenant/invoices/{$invoiceId}/remaining-creditable");
    }
}
