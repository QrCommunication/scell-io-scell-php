<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Scell\Sdk\Resources\BalanceResource;

/**
 * Verifie que `BalanceResource` est correctement marquee `@deprecated`
 * (SDK 2.24.0) — tous les endpoints `/api/v1/balance/*` ont ete supprimes
 * cote backend Scell.io le 2026-05-10.
 *
 * On verifie la presence du tag `@deprecated` dans les docblocks plutot
 * que de faire des appels HTTP qui retourneraient 404 (les anciens
 * consommateurs continuent de tomber dans le code mais doivent migrer).
 */
class BalanceResourceDeprecatedTest extends TestCase
{
    #[Test]
    public function class_has_deprecated_tag_in_docblock(): void
    {
        $ref = new ReflectionClass(BalanceResource::class);
        $doc = $ref->getDocComment();

        $this->assertNotFalse($doc, 'BalanceResource should have a class-level docblock');
        $this->assertStringContainsString('@deprecated', $doc);
        $this->assertStringContainsString('2.24.0', $doc);
        $this->assertStringContainsString('BillingResource', $doc);
    }

    #[Test]
    public function all_public_methods_are_deprecated(): void
    {
        $ref = new ReflectionClass(BalanceResource::class);
        $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);

        $publicBusinessMethods = array_filter(
            $methods,
            static fn(ReflectionMethod $m): bool =>
                $m->getDeclaringClass()->getName() === BalanceResource::class
                && $m->getName() !== '__construct'
        );

        $this->assertNotEmpty($publicBusinessMethods);

        foreach ($publicBusinessMethods as $method) {
            $doc = $method->getDocComment();
            $this->assertNotFalse(
                $doc,
                "Method BalanceResource::{$method->getName()} should have a docblock"
            );
            $this->assertStringContainsString(
                '@deprecated',
                $doc,
                "Method BalanceResource::{$method->getName()} should be @deprecated"
            );
        }
    }

    #[Test]
    public function deprecation_doc_mentions_billing_replacement(): void
    {
        $ref = new ReflectionClass(BalanceResource::class);
        $doc = $ref->getDocComment();

        // Le mapping vers BillingResource doit etre documente pour guider la migration
        $this->assertStringContainsString('billing()', $doc);
        $this->assertStringContainsString('topUp', $doc);
        $this->assertStringContainsString('usage', $doc);
        $this->assertStringContainsString('transactions', $doc);
    }
}
