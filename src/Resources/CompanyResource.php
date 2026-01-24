<?php

declare(strict_types=1);

namespace Scell\Sdk\Resources;

use Scell\Sdk\DTOs\Company;
use Scell\Sdk\Http\HttpClient;

/**
 * Resource pour les entreprises.
 *
 * Permet de gerer les entreprises et leur verification KYC.
 */
class CompanyResource
{
    public function __construct(
        private readonly HttpClient $http
    ) {}

    /**
     * Liste toutes les entreprises.
     *
     * @return Company[]
     */
    public function list(): array
    {
        $response = $this->http->get('companies');
        return array_map(
            fn(array $data) => Company::fromArray($data),
            $response['data'] ?? []
        );
    }

    /**
     * Recupere une entreprise par son ID.
     */
    public function get(string $id): Company
    {
        $response = $this->http->get("companies/{$id}");
        return Company::fromArray($response['data']);
    }

    /**
     * Cree une nouvelle entreprise.
     *
     * @param array{
     *     name: string,
     *     siret: string,
     *     address_line1: string,
     *     postal_code: string,
     *     city: string,
     *     vat_number?: string,
     *     legal_form?: string,
     *     address_line2?: string,
     *     country?: string,
     *     phone?: string,
     *     email?: string,
     *     website?: string
     * } $data
     */
    public function create(array $data): Company
    {
        $response = $this->http->post('companies', $data);
        return Company::fromArray($response['data']);
    }

    /**
     * Met a jour une entreprise.
     *
     * @param string $id ID de l'entreprise
     * @param array<string, mixed> $data Donnees a mettre a jour
     */
    public function update(string $id, array $data): Company
    {
        $response = $this->http->put("companies/{$id}", $data);
        return Company::fromArray($response['data']);
    }

    /**
     * Supprime une entreprise.
     *
     * @return array{message: string}
     */
    public function delete(string $id): array
    {
        return $this->http->delete("companies/{$id}");
    }

    /**
     * Initie la verification KYC.
     *
     * @return array{message: string, kyc_reference: string, redirect_url: string}
     */
    public function initiateKyc(string $id): array
    {
        return $this->http->post("companies/{$id}/kyc");
    }

    /**
     * Recupere le statut KYC.
     *
     * @return array{status: string, kyc_reference: ?string, kyc_completed_at: ?string, message: string}
     */
    public function kycStatus(string $id): array
    {
        return $this->http->get("companies/{$id}/kyc/status");
    }
}
