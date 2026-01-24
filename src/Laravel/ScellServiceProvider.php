<?php

declare(strict_types=1);

namespace Scell\Sdk\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Scell\Sdk\Config;
use Scell\Sdk\ScellApiClient;
use Scell\Sdk\ScellClient;
use Scell\Sdk\Webhooks\WebhookVerifier;

/**
 * Service Provider Laravel pour le SDK Scell.io.
 *
 * Ce provider:
 * - Publie la configuration (config/scell.php)
 * - Enregistre les clients dans le container
 * - Configure l'auto-discovery pour Laravel
 */
class ScellServiceProvider extends ServiceProvider
{
    /**
     * Indique si le chargement du provider est differe.
     */
    protected bool $defer = true;

    /**
     * Enregistre les services dans le container.
     */
    public function register(): void
    {
        // Fusionner la config par defaut
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/scell.php',
            'scell'
        );

        // Enregistrer la configuration
        $this->app->singleton(Config::class, function (Application $app) {
            return Config::fromArray($app['config']->get('scell', []));
        });

        // Enregistrer le client Bearer (dashboard)
        $this->app->singleton(ScellClient::class, function (Application $app) {
            $config = $app->make(Config::class);
            $token = $app['config']->get('scell.auth.bearer_token');

            if (empty($token)) {
                throw new \RuntimeException(
                    'Bearer token non configure. Definissez SCELL_BEARER_TOKEN dans votre .env ou utilisez ScellApiClient pour l\'authentification par API Key.'
                );
            }

            return new ScellClient($token, $config);
        });

        // Enregistrer le client API Key (integration backend)
        $this->app->singleton(ScellApiClient::class, function (Application $app) {
            $config = $app->make(Config::class);
            $apiKey = $app['config']->get('scell.auth.api_key');

            if (empty($apiKey)) {
                throw new \RuntimeException(
                    'API Key non configuree. Definissez SCELL_API_KEY dans votre .env.'
                );
            }

            return ScellApiClient::withApiKey($apiKey, $config);
        });

        // Enregistrer le verificateur de webhooks
        $this->app->singleton(WebhookVerifier::class, function (Application $app) {
            $secret = $app['config']->get('scell.webhook_secret');

            if (empty($secret)) {
                throw new \RuntimeException(
                    'Webhook secret non configure. Definissez SCELL_WEBHOOK_SECRET dans votre .env.'
                );
            }

            return new WebhookVerifier($secret);
        });

        // Alias pour la facade
        $this->app->alias(ScellClient::class, 'scell');
        $this->app->alias(ScellApiClient::class, 'scell.api');
        $this->app->alias(WebhookVerifier::class, 'scell.webhook');
    }

    /**
     * Bootstrap des services.
     */
    public function boot(): void
    {
        // Publier la configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/scell.php' => config_path('scell.php'),
            ], 'scell-config');
        }
    }

    /**
     * Services fournis par le provider.
     *
     * @return string[]
     */
    public function provides(): array
    {
        return [
            Config::class,
            ScellClient::class,
            ScellApiClient::class,
            WebhookVerifier::class,
            'scell',
            'scell.api',
            'scell.webhook',
        ];
    }
}
