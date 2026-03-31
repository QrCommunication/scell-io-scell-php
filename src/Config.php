<?php

declare(strict_types=1);

namespace Scell\Sdk;

use Scell\Sdk\Enums\Environment;

/**
 * Configuration du SDK Scell.
 */
class Config
{
    /**
     * URL de base par defaut.
     */
    public const DEFAULT_BASE_URL = 'https://api.scell.io/api/v1';

    /**
     * URL de base sandbox.
     * Sandbox is determined by key prefix (sk_test_*), not by a separate domain.
     */
    public const SANDBOX_BASE_URL = 'https://api.scell.io/api/v1';

    /**
     * Timeout par defaut en secondes.
     */
    public const DEFAULT_TIMEOUT = 30;

    /**
     * Timeout de connexion par defaut.
     */
    public const DEFAULT_CONNECT_TIMEOUT = 10;

    /**
     * Nombre de tentatives par defaut.
     */
    public const DEFAULT_RETRY_ATTEMPTS = 3;

    /**
     * Delai de base pour retry en ms.
     */
    public const DEFAULT_RETRY_DELAY = 100;

    public function __construct(
        public readonly string $baseUrl = self::DEFAULT_BASE_URL,
        public readonly int $timeout = self::DEFAULT_TIMEOUT,
        public readonly int $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        public readonly int $retryAttempts = self::DEFAULT_RETRY_ATTEMPTS,
        public readonly int $retryDelay = self::DEFAULT_RETRY_DELAY,
        public readonly bool $verifySsl = true,
        public readonly ?string $webhookSecret = null,
        public readonly Environment $environment = Environment::Production,
    ) {}

    /**
     * Cree une configuration a partir d'un tableau.
     */
    public static function fromArray(array $config): self
    {
        $env = $config['environment'] ?? 'production';
        $environment = Environment::from($env);
        $defaultUrl = $environment === Environment::Sandbox ? self::SANDBOX_BASE_URL : self::DEFAULT_BASE_URL;

        return new self(
            baseUrl: $config['base_url'] ?? $defaultUrl,
            timeout: $config['timeout'] ?? $config['http']['timeout'] ?? self::DEFAULT_TIMEOUT,
            connectTimeout: $config['connect_timeout'] ?? $config['http']['connect_timeout'] ?? self::DEFAULT_CONNECT_TIMEOUT,
            retryAttempts: $config['retry_attempts'] ?? $config['http']['retry_attempts'] ?? self::DEFAULT_RETRY_ATTEMPTS,
            retryDelay: $config['retry_delay'] ?? $config['http']['retry_delay'] ?? self::DEFAULT_RETRY_DELAY,
            verifySsl: $config['verify_ssl'] ?? $config['http']['verify_ssl'] ?? true,
            webhookSecret: $config['webhook_secret'] ?? null,
            environment: $environment,
        );
    }

    /**
     * Cree une configuration pour les tests (sandbox).
     */
    public static function sandbox(): self
    {
        return new self(
            baseUrl: self::SANDBOX_BASE_URL,
            environment: Environment::Sandbox,
        );
    }

    /**
     * Cree une configuration pour le developpement local.
     */
    public static function local(string $baseUrl = 'http://localhost:8000/api/v1'): self
    {
        return new self(
            baseUrl: $baseUrl,
            verifySsl: false,
        );
    }
}
