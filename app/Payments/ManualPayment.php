<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Methods where the customer pays outside the site (bank or blockchain
 * transfer), submits a reference, and authorised staff verify it.
 */
interface ManualPayment
{
    /** Validates the customer-submitted reference (transaction ID). */
    public function validReference(string $reference): bool;

    /** Human description of the reference expected, e.g. "TRC20 transaction hash". */
    public function referenceLabel(): string;

    /** Link for staff to check the reference, or null. */
    public function explorerUrl(string $reference): ?string;
}
