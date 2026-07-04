<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Transaction;
use App\Enum\TransactionStatus;
use App\Repository\CompanyWalletRepositoryInterface;
use App\Repository\TransactionRepositoryInterface;
use App\Repository\WalletRepositoryInterface;
use DateTimeImmutable;

final readonly class TransactionProcessorService
{
    public function __construct(
        private WalletRepositoryInterface $walletRepository,
        private TransactionRepositoryInterface $transactionRepository,
        private CompanyWalletRepositoryInterface $companyWalletRepository,
    ) {
    }

    public function complete(Transaction $transaction): void
    {
        $fromWalletId = $transaction->getFromWalletId();
        $toWalletId = $transaction->getToWalletId();

        $fromWallet = null !== $fromWalletId ? $this->walletRepository->findById($fromWalletId) : null;
        $toWallet = null !== $toWalletId ? $this->walletRepository->findById($toWalletId) : null;

        if (null === $fromWallet || null === $toWallet) {
            $this->reject($transaction);

            return;
        }

        if ($fromWallet->isBlocked() || $toWallet->isBlocked()) {
            $this->reject($transaction);

            return;
        }

        if ($fromWallet->getBalance() < (float) $transaction->getFromAmount()) {
            $this->reject($transaction);

            return;
        }

        $fromWallet->setBalance($fromWallet->getBalance() - (float) $transaction->getFromAmount());
        $fromWallet->setLastActivityAt(new DateTimeImmutable());

        $toWallet->setBalance($toWallet->getBalance() + (float) $transaction->getToAmount());
        $toWallet->setLastActivityAt(new DateTimeImmutable());

        $this->walletRepository->save($fromWallet);
        $this->walletRepository->save($toWallet);

        $transaction->setStatus(TransactionStatus::COMPLETED);

        if ($transaction->requiresAntiFraudCheck()) {
            $transaction->setAntiFraudCheckedAt(new DateTimeImmutable());
        }

        $this->transactionRepository->save($transaction);

        $this->companyWalletRepository->addToBalance(
            $transaction->getToCurrency(),
            $transaction->getSpread(),
        );
    }

    public function reject(Transaction $transaction): void
    {
        $transaction->setStatus(TransactionStatus::REJECTED);

        if ($transaction->requiresAntiFraudCheck()) {
            $transaction->setAntiFraudCheckedAt(new DateTimeImmutable());
        }

        $this->transactionRepository->save($transaction);
    }
}
