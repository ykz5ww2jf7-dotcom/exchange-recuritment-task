<?php

declare(strict_types=1);

namespace App\Tests\Service;

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
use App\Service\WalletService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class WalletServiceTest extends TestCase
{
    private WalletRepositoryInterface $walletRepository;
    private TransactionRepositoryInterface $transactionRepository;
    private WalletService $walletService;

    protected function setUp(): void
    {
        $this->walletRepository = $this->createMock(WalletRepositoryInterface::class);
        $this->transactionRepository = $this->createMock(TransactionRepositoryInterface::class);
        $this->walletService = new WalletService($this->walletRepository, $this->transactionRepository);
    }

    public function testCreateWalletSuccessfully(): void
    {
        $userId = 1;
        $currency = Currency::EUR;

        $this->walletRepository
            ->expects(self::once())
            ->method('findByUserIdAndCurrency')
            ->with($userId, $currency)
            ->willReturn(null);

        $this->walletRepository
            ->expects(self::once())
            ->method('save')
            ->with($this->isInstanceOf(Wallet::class));

        $wallet = $this->walletService->createWallet($userId, $currency);

        self::assertSame($userId, $wallet->getUserId());
        self::assertSame($currency, $wallet->getCurrency());
        self::assertSame(0.0, $wallet->getBalance());
        self::assertFalse($wallet->isBlocked());
    }

    public function testCreateWalletThrowsWhenWalletAlreadyExists(): void
    {
        $userId = 1;
        $currency = Currency::PLN;

        $existingWallet = Wallet::create($userId, $currency);

        $this->walletRepository
            ->expects(self::once())
            ->method('findByUserIdAndCurrency')
            ->with($userId, $currency)
            ->willReturn($existingWallet);

        $this->walletRepository
            ->expects(self::never())
            ->method('save');

        $this->expectException(WalletAlreadyExistsException::class);
        $this->expectExceptionMessage('Wallet for user 1 in currency PLN already exists.');

        $this->walletService->createWallet($userId, $currency);
    }

    public function testDeleteWalletSuccessfully(): void
    {
        $wallet = Wallet::create(1, Currency::EUR);

        $this->walletRepository
            ->expects(self::once())
            ->method('findById')
            ->with(5)
            ->willReturn($wallet);

        $this->transactionRepository
            ->expects(self::once())
            ->method('findByWalletId')
            ->with(5)
            ->willReturn([]);

        $this->walletRepository
            ->expects(self::once())
            ->method('delete')
            ->with($wallet);

        $this->walletService->deleteWallet(1, 5);
    }

    public function testDeleteWalletThrowsWhenWalletNotFound(): void
    {
        $this->walletRepository
            ->expects(self::once())
            ->method('findById')
            ->with(99)
            ->willReturn(null);

        $this->walletRepository
            ->expects(self::never())
            ->method('delete');

        $this->expectException(WalletNotFoundException::class);
        $this->expectExceptionMessage('Wallet 99 not found.');

        $this->walletService->deleteWallet(1, 99);
    }

    public function testDeleteWalletThrowsWhenWalletBelongsToOtherUser(): void
    {
        $wallet = Wallet::create(2, Currency::EUR);

        $this->walletRepository
            ->expects(self::once())
            ->method('findById')
            ->with(5)
            ->willReturn($wallet);

        $this->walletRepository
            ->expects(self::never())
            ->method('delete');

        $this->expectException(WalletNotFoundException::class);
        $this->expectExceptionMessage('Wallet 5 not found.');

        $this->walletService->deleteWallet(1, 5);
    }

    public function testDeleteWalletThrowsWhenWalletBlocked(): void
    {
        $wallet = Wallet::create(1, Currency::EUR);
        $wallet->setIsBlocked(true);

        $this->walletRepository
            ->expects(self::once())
            ->method('findById')
            ->with(5)
            ->willReturn($wallet);

        $this->walletRepository
            ->expects(self::never())
            ->method('delete');

        $this->expectException(WalletBlockedException::class);
        $this->expectExceptionMessage('Wallet 5 is blocked.');

        $this->walletService->deleteWallet(1, 5);
    }

    public function testDeleteWalletThrowsWhenBalanceIsNotZero(): void
    {
        $wallet = Wallet::create(1, Currency::EUR);
        $wallet->setBalance(0.01);

        $this->walletRepository
            ->expects(self::once())
            ->method('findById')
            ->with(5)
            ->willReturn($wallet);

        $this->walletRepository
            ->expects(self::never())
            ->method('delete');

        $this->expectException(WalletNotEmptyException::class);
        $this->expectExceptionMessage('Wallet 5 is not empty.');

        $this->walletService->deleteWallet(1, 5);
    }

    #[DataProvider('unsettledStatusProvider')]
    public function testDeleteWalletThrowsWhenWalletHasUnsettledTransactions(TransactionStatus $status): void
    {
        $wallet = Wallet::create(1, Currency::EUR);

        $this->walletRepository
            ->expects(self::once())
            ->method('findById')
            ->with(5)
            ->willReturn($wallet);

        $this->transactionRepository
            ->expects(self::once())
            ->method('findByWalletId')
            ->with(5)
            ->willReturn([$this->buildTransaction($status)]);

        $this->walletRepository
            ->expects(self::never())
            ->method('delete');

        $this->expectException(WalletHasUnsettledTransactionsException::class);
        $this->expectExceptionMessage('Wallet 5 has unsettled transactions.');

        $this->walletService->deleteWallet(1, 5);
    }

    /**
     * @return iterable<string, array{TransactionStatus}>
     */
    public static function unsettledStatusProvider(): iterable
    {
        yield 'pending' => [TransactionStatus::PENDING];
        yield 'fraud review' => [TransactionStatus::FRAUD_REVIEW];
    }

    public function testDeleteWalletSucceedsWhenAllTransactionsAreSettled(): void
    {
        $wallet = Wallet::create(1, Currency::EUR);

        $this->walletRepository
            ->expects(self::once())
            ->method('findById')
            ->with(5)
            ->willReturn($wallet);

        $this->transactionRepository
            ->expects(self::once())
            ->method('findByWalletId')
            ->with(5)
            ->willReturn([
                $this->buildTransaction(TransactionStatus::COMPLETED),
                $this->buildTransaction(TransactionStatus::REJECTED),
            ]);

        $this->walletRepository
            ->expects(self::once())
            ->method('delete')
            ->with($wallet);

        $this->walletService->deleteWallet(1, 5);
    }

    private function buildTransaction(TransactionStatus $status): Transaction
    {
        return new Transaction(
            id: 42,
            fromWalletId: 5,
            toWalletId: 2,
            fromAmount: '100.0000',
            toAmount: '25.1234',
            fromCurrency: Currency::PLN,
            toCurrency: Currency::EUR,
            spread: '0.13',
            exchangeRate: '0.250000',
            status: $status,
            requiresAntiFraudCheck: false,
            antiFraudCheckedAt: null,
            createdAt: new DateTimeImmutable(),
        );
    }
}
