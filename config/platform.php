<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform commission
    |--------------------------------------------------------------------------
    |
    | The share of gross ticket revenue the platform keeps, as a percentage.
    | An event may override it via events.platform_fee_percent when a rate has
    | been negotiated; this is the fallback.
    |
    | Payment-provider fees are absorbed by the platform out of this share and
    | are deliberately not deducted from an organizer's payout.
    |
    */

    'fee_percent' => (float) env('PLATFORM_FEE_PERCENT', 5),

    /*
    |--------------------------------------------------------------------------
    | Minimum payout
    |--------------------------------------------------------------------------
    |
    | The smallest net amount an organizer may request. Zero means no minimum.
    |
    */

    'minimum_payout' => (float) env('PLATFORM_MINIMUM_PAYOUT', 0),

];
