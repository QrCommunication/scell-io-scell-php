<?php

declare(strict_types=1);

namespace Scell\Sdk\Enums;

/**
 * Statut d'un avoir (credit note) dans son cycle de vie initial.
 *
 * Aligne sur le check constraint backend `credit_notes_status_check`.
 * Une fois l'avoir envoye / transmis / accepte, il bascule sur les memes
 * statuts que les factures (transmitted, accepted, paid). Ces 2 valeurs
 * couvrent uniquement la phase de creation.
 *
 * Disponible depuis SDK 2.21.0.
 */
enum CreditNoteStatus: string
{
    /** Brouillon - avoir cree mais non envoye. */
    case Draft = 'draft';

    /** Envoye au destinataire. */
    case Sent = 'sent';

    /**
     * Libelle francais.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Sent => 'Envoye',
        };
    }

    /**
     * Indique si l'avoir peut encore etre modifie.
     */
    public function canEdit(): bool
    {
        return $this === self::Draft;
    }
}
