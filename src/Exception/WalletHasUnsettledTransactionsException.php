<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class WalletHasUnsettledTransactionsException extends RuntimeException
{
    public function __construct(int $walletId)
    {
        parent::__construct(sprintf('Wallet %d has unsettled transactions.', $walletId));
    }
}
