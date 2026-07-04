<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Transaction;
use App\Entity\Wallet;
use App\Enum\Currency;
use App\Enum\TransactionStatus;
use App\Exception\WalletAlreadyExistsException;
use App\Exception\WalletBlockedException;
use App\Exception\WalletHasUnsettledTransactionsException;
use App\Exception\WalletNotEmptyException;
use App\Exception\WalletNotFoundException;
use App\Repository\TransactionRepositoryInterface;
use App\Repository\WalletRepositoryInterface;

readonly class WalletService
{
    private const float BALANCE_EPSILON = 0.005;

    private const array UNSETTLED_STATUSES = [TransactionStatus::PENDING, TransactionStatus::FRAUD_REVIEW];

    public function __construct(
        private WalletRepositoryInterface $walletRepository,
        private TransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function createWallet(int $userId, Currency $currency): Wallet
    {
        $existing = $this->walletRepository->findByUserIdAndCurrency($userId, $currency);

        if (null !== $existing) {
            throw new WalletAlreadyExistsException($userId, $currency);
        }

        $wallet = Wallet::create($userId, $currency);
        $this->walletRepository->save($wallet);

        return $wallet;
    }

    public function deleteWallet(int $userId, int $walletId): void
    {
        $wallet = $this->walletRepository->findById($walletId);
        if (null === $wallet || $wallet->getUserId() !== $userId) {
            throw new WalletNotFoundException($walletId);
        }

        if ($wallet->isBlocked()) {
            throw new WalletBlockedException($walletId);
        }

        if (abs($wallet->getBalance()) >= self::BALANCE_EPSILON) {
            throw new WalletNotEmptyException($walletId);
        }

        $transactions = $this->transactionRepository->findByWalletId($walletId);
        if (array_any($transactions, static fn (Transaction $transaction): bool => in_array($transaction->getStatus(), self::UNSETTLED_STATUSES, true))) {
            throw new WalletHasUnsettledTransactionsException($walletId);
        }

        $this->walletRepository->delete($wallet);
    }
}
