<?php

namespace App\Enums;

enum WebhookDeliveryStatus: string
{
    /** Verified and stored, not yet dispatched. */
    case Received  = 'received';

    /** Parsed to nothing we act on — a provider heartbeat or a status we skip. */
    case Ignored   = 'ignored';

    /** Dispatched and it changed something. */
    case Applied   = 'applied';

    /** Recognised, but there was nothing left to do (a redelivery, usually). */
    case Skipped   = 'skipped';

    /**
     * Verified and parsed, but no payment matches the reference. Money may have
     * moved with nothing on our side to attach it to — this is the queue a
     * human needs to look at.
     */
    case Unmatched = 'unmatched';

    /** Processing threw. The provider will retry; the error is stored. */
    case Failed    = 'failed';

    public function needsAttention(): bool
    {
        return in_array($this, [self::Unmatched, self::Failed], true);
    }
}
