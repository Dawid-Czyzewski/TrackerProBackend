<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260122000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create savings_budget and savings_transaction tables';
    }

    public function up(Schema $schema): void
    {
        // Create savings_budget table
        $this->addSql('CREATE TABLE savings_budget (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            balance NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_savings_budget_user_id (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_savings_budget_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create savings_transaction table
        $this->addSql('CREATE TABLE savings_transaction (
            id INT AUTO_INCREMENT NOT NULL,
            savings_budget_id INT NOT NULL,
            type VARCHAR(20) NOT NULL,
            amount NUMERIC(10, 2) NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_savings_transaction_budget (savings_budget_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_savings_transaction_budget FOREIGN KEY (savings_budget_id) REFERENCES savings_budget (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE savings_transaction');
        $this->addSql('DROP TABLE savings_budget');
    }
}
