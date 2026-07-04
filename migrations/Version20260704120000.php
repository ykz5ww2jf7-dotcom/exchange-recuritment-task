<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260704120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow wallet deletion with settled history: transactions wallet FKs become nullable ON DELETE SET NULL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            ALTER TABLE `transactions`
                DROP FOREIGN KEY `fk_transactions_from_wallet_id`,
                DROP FOREIGN KEY `fk_transactions_to_wallet_id`,
                MODIFY `from_wallet_id` INT NULL,
                MODIFY `to_wallet_id` INT NULL
            SQL);

        $this->addSql(<<<SQL
            ALTER TABLE `transactions`
                ADD CONSTRAINT `fk_transactions_from_wallet_id`
                    FOREIGN KEY (`from_wallet_id`) REFERENCES `wallets` (`id`)
                    ON DELETE SET NULL,
                ADD CONSTRAINT `fk_transactions_to_wallet_id`
                    FOREIGN KEY (`to_wallet_id`) REFERENCES `wallets` (`id`)
                    ON DELETE SET NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<SQL
            ALTER TABLE `transactions`
                DROP FOREIGN KEY `fk_transactions_from_wallet_id`,
                DROP FOREIGN KEY `fk_transactions_to_wallet_id`,
                MODIFY `from_wallet_id` INT NOT NULL,
                MODIFY `to_wallet_id` INT NOT NULL
            SQL);

        $this->addSql(<<<SQL
            ALTER TABLE `transactions`
                ADD CONSTRAINT `fk_transactions_from_wallet_id`
                    FOREIGN KEY (`from_wallet_id`) REFERENCES `wallets` (`id`)
                    ON DELETE RESTRICT,
                ADD CONSTRAINT `fk_transactions_to_wallet_id`
                    FOREIGN KEY (`to_wallet_id`) REFERENCES `wallets` (`id`)
                    ON DELETE RESTRICT
            SQL);
    }
}
