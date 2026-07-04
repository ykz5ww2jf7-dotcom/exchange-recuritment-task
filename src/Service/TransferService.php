<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Transaction;
use App\Enum\Currency;
use App\Exception\WalletNotFoundException;
use App\Repository\TransactionRepositoryInterface;
use App\Repository\WalletRepositoryInterface;

readonly class TransferService
{
    public function __construct(
        private WalletRepositoryInterface $walletRepository,
        private TransactionRepositoryInterface $transactionRepository,
        private ExchangeRateService $exchangeRateService,
        private SpreadService $spreadService,
    ) {
    }

    public function transfer(
        int $userId,
        int $fromWalletId,
        int $toWalletId,
        string $fromAmount,
    ): Transaction {
        $fromWallet = $this->walletRepository->findById($fromWalletId);
        if (null === $fromWallet || $fromWallet->getUserId() !== $userId) {
            throw new WalletNotFoundException($fromWalletId);
        }

        $toWallet = $this->walletRepository->findById($toWalletId);
        if (null === $toWallet || $toWallet->getUserId() !== $userId) {
            throw new WalletNotFoundException($toWalletId);
        }

        $fromCurrency = $fromWallet->getCurrency();
        $toCurrency = $toWallet->getCurrency();
        $fraudThreshold = 15000.0;

        $exchangeRate = $this->exchangeRateService->getExchangeRateBetween($fromCurrency, $toCurrency);
        $rawToAmount = (float) $fromAmount * $exchangeRate;
        $spread = $this->spreadService->calculateSpread($rawToAmount, $fromCurrency, $toCurrency);
        $netToAmount = $rawToAmount - (float) $spread;

        $toAmountFormatted = number_format($netToAmount, 4, '.', '');

        $eurValue = (float) $fromAmount * $this->exchangeRateService->getExchangeRateBetween($fromCurrency, Currency::EUR);

        $transaction = Transaction::create(
            fromWalletId: $fromWalletId,
            toWalletId: $toWalletId,
            fromAmount: $fromAmount,
            toAmount: $toAmountFormatted,
            fromCurrency: $fromCurrency,
            toCurrency: $toCurrency,
            spread: $spread,
            exchangeRate: number_format($exchangeRate, 6, '.', ''),
            requiresAntiFraudCheck: $eurValue > $fraudThreshold,
        );

        $this->transactionRepository->save($transaction);

        return $transaction;
    }
}
