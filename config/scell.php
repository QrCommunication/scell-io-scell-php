<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | L'URL de base de l'API Scell.io. Utilisez l'URL de production par defaut
    | ou une URL personnalisee pour les environnements de developpement.
    |
    */
    'base_url' => env('SCELL_BASE_URL', 'https://api.scell.io/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Configurez l'authentification selon votre cas d'usage:
    | - Bearer token pour le dashboard (utilisateur connecte)
    | - API Key pour l'integration externe (serveur a serveur)
    |
    */
    'auth' => [
        // Bearer token pour l'authentification utilisateur (dashboard)
        'bearer_token' => env('SCELL_BEARER_TOKEN'),

        // API Key pour l'authentification serveur (integration externe)
        'api_key' => env('SCELL_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Le secret utilise pour verifier les signatures des webhooks entrants.
    | Ce secret est fourni lors de la creation d'un webhook.
    |
    */
    'webhook_secret' => env('SCELL_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Options
    |--------------------------------------------------------------------------
    |
    | Options de configuration pour le client HTTP Guzzle.
    |
    */
    'http' => [
        // Timeout en secondes pour les requetes
        'timeout' => env('SCELL_HTTP_TIMEOUT', 30),

        // Timeout de connexion en secondes
        'connect_timeout' => env('SCELL_HTTP_CONNECT_TIMEOUT', 10),

        // Nombre de tentatives en cas d'echec (retry)
        'retry_attempts' => env('SCELL_HTTP_RETRY_ATTEMPTS', 3),

        // Delai de base pour le backoff exponentiel (millisecondes)
        'retry_delay' => env('SCELL_HTTP_RETRY_DELAY', 100),

        // Verification SSL (desactiver uniquement en dev)
        'verify_ssl' => env('SCELL_HTTP_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | L'environnement par defaut pour les operations.
    | 'sandbox' pour les tests, 'production' pour les vraies operations.
    |
    */
    'environment' => env('SCELL_ENVIRONMENT', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configuration des logs pour le SDK.
    |
    */
    'logging' => [
        'enabled' => env('SCELL_LOGGING_ENABLED', true),
        'channel' => env('SCELL_LOGGING_CHANNEL', 'stack'),
    ],
];
