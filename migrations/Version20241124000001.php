<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241124000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create accounts and transactions tables for fund transfer system';
    }

    public function up(Schema $schema): void
    {
        // Create accounts table
        $this->addSql('CREATE TABLE accounts (
            id INT AUTO_INCREMENT NOT NULL,
            account_number VARCHAR(50) NOT NULL,
            holder_name VARCHAR(100) NOT NULL,
            balance DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(3) NOT NULL DEFAULT \'USD\',
            status VARCHAR(20) NOT NULL DEFAULT \'active\',
            version INT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_account_number (account_number),
            INDEX idx_status (status),
            UNIQUE INDEX UNIQ_CAC89EAC5AC7F972 (account_number),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create transactions table
        $this->addSql('CREATE TABLE transactions (
            id INT AUTO_INCREMENT NOT NULL,
            from_account_id INT DEFAULT NULL,
            to_account_id INT NOT NULL,
            transaction_id VARCHAR(100) NOT NULL,
            type VARCHAR(20) NOT NULL,
            amount DECIMAL(15, 2) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT \'USD\',
            status VARCHAR(20) NOT NULL DEFAULT \'pending\',
            description LONGTEXT DEFAULT NULL,
            failure_reason LONGTEXT DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            processed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_transaction_id (transaction_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_from_account (from_account_id),
            INDEX idx_to_account (to_account_id),
            UNIQUE INDEX UNIQ_EAA81A4C2FC0CB0F (transaction_id),
            INDEX IDX_EAA81A4C78D0C5E1 (from_account_id),
            INDEX IDX_EAA81A4C6B9DD454 (to_account_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign key constraints
        $this->addSql('ALTER TABLE transactions 
            ADD CONSTRAINT FK_EAA81A4C78D0C5E1 
            FOREIGN KEY (from_account_id) 
            REFERENCES accounts (id)');
            
        $this->addSql('ALTER TABLE transactions 
            ADD CONSTRAINT FK_EAA81A4C6B9DD454 
            FOREIGN KEY (to_account_id) 
            REFERENCES accounts (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key constraints first
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4C78D0C5E1');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4C6B9DD454');
        
        // Drop tables
        $this->addSql('DROP TABLE transactions');
        $this->addSql('DROP TABLE accounts');
    }
}
