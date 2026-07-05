<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store wallet balances as exact fixed-point DECIMAL(15,4) instead of DOUBLE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            ALTER TABLE `wallets`
                MODIFY `balance` DECIMAL(15,4) NOT NULL DEFAULT 0
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<SQL
            ALTER TABLE `wallets`
                MODIFY `balance` DOUBLE NOT NULL DEFAULT 0
            SQL);
    }
}
