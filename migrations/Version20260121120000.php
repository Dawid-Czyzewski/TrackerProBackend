<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260121120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add vacation_months column to budget table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            SET @exist := (SELECT COUNT(*) FROM information_schema.columns 
                WHERE table_schema = DATABASE() 
                AND table_name = 'budget' 
                AND column_name = 'vacation_months');
            SET @sqlstmt := IF(@exist = 0, 
                'ALTER TABLE budget ADD COLUMN vacation_months INT NOT NULL DEFAULT 12', 
                'SELECT 1');
            PREPARE stmt FROM @sqlstmt;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE budget DROP COLUMN vacation_months');
    }
}
