<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250511103511 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD first_name VARCHAR(50) DEFAULT NULL, ADD last_name VARCHAR(50) DEFAULT NULL, ADD address VARCHAR(255) DEFAULT NULL, ADD gender VARCHAR(10) DEFAULT NULL, DROP firstname, DROP lastname');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD firstname VARCHAR(100) NOT NULL, ADD lastname VARCHAR(100) NOT NULL, DROP first_name, DROP last_name, DROP address, DROP gender');
    }
}
