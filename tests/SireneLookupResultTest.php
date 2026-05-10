<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Scell\Sdk\DTOs\CompanyData;
use Scell\Sdk\DTOs\SireneLookupResult;

/**
 * Verifie que le DTO parse correctement la VRAIE shape JSON renvoyee par
 * l'API `/widget/onboarding/sirene/lookup` (regression du 2026-05-10).
 *
 * Avant v2.4.0 :
 *   - CompanyData::fromArray lisait `data['address']['line1']` (nested)
 *     mais l'API renvoie `data['address_line1']` (flat) -> tous les
 *     champs adresse arrivaient vides ('').
 *   - SireneLookupResult lisait `sirene_lookup_succeeded` au niveau racine
 *     mais l'API n'a JAMAIS expose ce champ (elle expose
 *     `data.sirene_lookup_failed` au cas manual_entry).
 */
class SireneLookupResultTest extends TestCase
{
    /**
     * Cas 1 : Etalab a trouve l'entreprise (RL CONSEIL, capture prod 2026-05-10).
     */
    #[Test]
    public function it_parses_full_etalab_response(): void
    {
        $payload = [
            'data' => [
                'name' => 'RL CONSEIL',
                'legal_name' => 'RL CONSEIL',
                'siret' => '10178342100015',
                'siren' => '101783421',
                'vat_number' => 'FR95101783421',
                'legal_form' => '5710',
                'naf_code' => '62.02A',
                'address_line1' => '200 RUE DE LA CROIX NIVERT',
                'address_line2' => null,
                'postal_code' => '75015',
                'city' => 'PARIS',
                'country' => 'FR',
                'is_active' => true,
                'creation_date' => '2026-01-19',
                'employee_range' => 'NN',
            ],
        ];

        $result = SireneLookupResult::fromArray($payload);

        $this->assertTrue($result->sireneLookupSucceeded);
        $this->assertFalse($result->manualEntryRequired);
        $this->assertNull($result->code);
        $this->assertInstanceOf(CompanyData::class, $result->data);

        $company = $result->data;
        $this->assertSame('10178342100015', $company->siret);
        $this->assertSame('101783421', $company->siren);
        $this->assertSame('RL CONSEIL', $company->name);
        $this->assertSame('RL CONSEIL', $company->legalName);
        // PIEGE HISTORIQUE : ces 4 champs etaient vides avant le fix
        $this->assertSame('200 RUE DE LA CROIX NIVERT', $company->addressLine1);
        $this->assertSame('75015', $company->postalCode);
        $this->assertSame('PARIS', $company->city);
        $this->assertSame('FR', $company->country);
        $this->assertSame('5710', $company->legalForm);
        $this->assertSame('62.02A', $company->nafCode);
        $this->assertSame('FR95101783421', $company->vatNumber);
        $this->assertSame('2026-01-19', $company->creationDate);
        $this->assertSame('NN', $company->employeeRange);
        $this->assertTrue($company->isActive);
    }

    /**
     * Cas 2 : manual_entry fallback (Microsoft, capture prod 2026-05-10).
     */
    #[Test]
    public function it_parses_manual_entry_fallback(): void
    {
        $payload = [
            'data' => [
                'sirene_lookup_failed' => true,
                'siret' => '73282932000074',
                'siren' => '732829320',
                'vat_number' => 'FR44732829320',
                'country' => 'FR',
            ],
            'code' => 'SIRENE_MANUAL_ENTRY_REQUIRED',
        ];

        $result = SireneLookupResult::fromArray($payload);

        $this->assertFalse($result->sireneLookupSucceeded);
        $this->assertTrue($result->manualEntryRequired);
        $this->assertSame('SIRENE_MANUAL_ENTRY_REQUIRED', $result->code);
        // En fallback, on n'expose pas un CompanyData partiel : le widget
        // doit lire $result->code et basculer en saisie manuelle.
        $this->assertNull($result->data);
    }

    /**
     * Cas 3 : SIRET pas trouve dans Sirene (404 cote API mais structure JSON
     * peut quand meme arriver si la couche HTTP releve l'exception en data=null).
     */
    #[Test]
    public function it_parses_not_found_response(): void
    {
        $payload = [
            'data' => null,
            'code' => 'SIRENE_NOT_FOUND',
            'error' => 'SIRET 99999999999999 introuvable dans la base Sirene.',
        ];

        $result = SireneLookupResult::fromArray($payload);

        $this->assertNull($result->data);
        $this->assertSame('SIRENE_NOT_FOUND', $result->code);
        $this->assertFalse($result->manualEntryRequired);
    }

    /**
     * Compat retroactif : l'ancien shape `address.line1` (nested) doit
     * encore parser pour ne pas casser un test legacy ou un mock.
     */
    #[Test]
    public function it_keeps_compat_with_nested_address_shape(): void
    {
        $payload = [
            'data' => [
                'siret' => '10178342100015',
                'siren' => '101783421',
                'name' => 'RL CONSEIL',
                'address' => [
                    'line1' => '200 RUE DE LA CROIX NIVERT',
                    'postal_code' => '75015',
                    'city' => 'PARIS',
                    'country' => 'FR',
                ],
                'is_active' => true,
            ],
        ];

        $result = SireneLookupResult::fromArray($payload);

        $this->assertNotNull($result->data);
        $this->assertSame('200 RUE DE LA CROIX NIVERT', $result->data->addressLine1);
        $this->assertSame('75015', $result->data->postalCode);
        $this->assertSame('PARIS', $result->data->city);
    }

    /**
     * Empty payload defensive parse — ne doit pas crasher.
     */
    #[Test]
    public function it_handles_empty_payload(): void
    {
        $result = SireneLookupResult::fromArray([]);

        $this->assertNull($result->data);
        $this->assertFalse($result->manualEntryRequired);
        $this->assertNull($result->code);
    }
}
