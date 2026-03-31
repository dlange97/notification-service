<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260307000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add instance_id column to inbox_notification and notification_template tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inbox_notification ADD instance_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE notification_template ADD instance_id VARCHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_template DROP instance_id');
        $this->addSql('ALTER TABLE inbox_notification DROP instance_id');
    }
}
