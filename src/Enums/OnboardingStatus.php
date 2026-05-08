<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * SubTenant onboarding lifecycle status (since v2.0.0).
 *
 * Replaces the legacy 3-state `kyc_status` field. Maps the SuperPDP
 * onboarding pipeline:
 *
 * | Legacy `kyc_status` | New `OnboardingStatus`                                                          |
 * |---------------------|---------------------------------------------------------------------------------|
 * | pending             | PendingSuperPDP, SuperPDPRedirected, SuperPDPAuthorized, SuperPDPPendingReview  |
 * | verified            | Active                                                                          |
 * | rejected            | SuperPDPFailed                                                                  |
 */
enum OnboardingStatus: string
{
    case PendingSuperPDP = 'pending_superpdp';
    case SuperPDPRedirected = 'superpdp_redirected';
    case SuperPDPAuthorized = 'superpdp_authorized';
    case SuperPDPPendingReview = 'superpdp_pending_review';
    case Active = 'active';
    case SuperPDPFailed = 'superpdp_failed';

    public function isTerminal(): bool
    {
        return $this === self::Active || $this === self::SuperPDPFailed;
    }

    public function isInProgress(): bool
    {
        return ! $this->isTerminal();
    }
}
