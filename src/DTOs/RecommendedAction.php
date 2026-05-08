<?php

declare(strict_types=1);

namespace Scell\Sdk\DTOs;

/**
 * Recommended next action presented to the partner UI for a sub-tenant.
 *
 * Includes structured FR/EN i18n labels plus a stable machine `code`
 * for analytics / logic branching.
 */
readonly class RecommendedAction
{
    public function __construct(
        public string $code,
        public string $severity,
        public string $titleFr,
        public string $titleEn,
        public string $messageFr,
        public string $messageEn,
        public string $ctaLabelFr,
        public string $ctaLabelEn,
        public ?string $ctaUrl = null,
        public bool $dismissible = false,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) $data['code'],
            severity: (string) $data['severity'],
            titleFr: (string) $data['title_fr'],
            titleEn: (string) $data['title_en'],
            messageFr: (string) $data['message_fr'],
            messageEn: (string) $data['message_en'],
            ctaLabelFr: (string) $data['cta_label_fr'],
            ctaLabelEn: (string) $data['cta_label_en'],
            ctaUrl: $data['cta_url'] ?? null,
            dismissible: (bool) ($data['dismissible'] ?? false),
        );
    }

    public function title(string $locale = 'fr'): string
    {
        return $locale === 'en' ? $this->titleEn : $this->titleFr;
    }

    public function message(string $locale = 'fr'): string
    {
        return $locale === 'en' ? $this->messageEn : $this->messageFr;
    }

    public function ctaLabel(string $locale = 'fr'): string
    {
        return $locale === 'en' ? $this->ctaLabelEn : $this->ctaLabelFr;
    }
}
