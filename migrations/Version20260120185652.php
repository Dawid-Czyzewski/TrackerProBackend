<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260120185652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add updated_at columns to tables using TimestampableTrait (goal, budget, application)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            SET @exist := (SELECT COUNT(*) FROM information_schema.columns 
                WHERE table_schema = DATABASE() 
                AND table_name = 'goal' 
                AND column_name = 'updated_at');
            SET @sqlstmt := IF(@exist = 0, 
                'ALTER TABLE goal ADD COLUMN updated_at DATETIME NOT NULL', 
                'SELECT 1');
            PREPARE stmt FROM @sqlstmt;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        $this->addSql("UPDATE goal SET updated_at = COALESCE(created_at, NOW()) WHERE updated_at IS NULL OR updated_at = '0000-00-00 00:00:00'");
        
        $this->addSql("
            SET @exist := (SELECT COUNT(*) FROM information_schema.columns 
                WHERE table_schema = DATABASE() 
                AND table_name = 'budget' 
                AND column_name = 'updated_at');
            SET @sqlstmt := IF(@exist = 0, 
                'ALTER TABLE budget ADD COLUMN updated_at DATETIME NOT NULL', 
                'SELECT 1');
            PREPARE stmt FROM @sqlstmt;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        $this->addSql("UPDATE budget SET updated_at = COALESCE(created_at, NOW()) WHERE updated_at IS NULL OR updated_at = '0000-00-00 00:00:00'");
        
        $this->addSql("
            SET @exist := (SELECT COUNT(*) FROM information_schema.columns 
                WHERE table_schema = DATABASE() 
                AND table_name = 'application' 
                AND column_name = 'updated_at');
            SET @sqlstmt := IF(@exist = 0, 
                'ALTER TABLE application ADD COLUMN updated_at DATETIME NOT NULL', 
                'SELECT 1');
            PREPARE stmt FROM @sqlstmt;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        ");
        $this->addSql("UPDATE application SET updated_at = COALESCE(created_at, NOW()) WHERE updated_at IS NULL OR updated_at = '0000-00-00 00:00:00'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE goal DROP COLUMN updated_at');
        $this->addSql('ALTER TABLE budget DROP COLUMN updated_at');
        $this->addSql('ALTER TABLE application DROP COLUMN updated_at');
    }
}
