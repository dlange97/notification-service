<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create inbox_notification and notification_template tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE inbox_notification (id INT AUTO_INCREMENT NOT NULL, recipient_user_id VARCHAR(36) NOT NULL, recipient_email VARCHAR(255) NOT NULL, type VARCHAR(80) NOT NULL, title VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, is_read TINYINT(1) DEFAULT 0 NOT NULL, payload JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_inbox_notification_recipient_created (recipient_user_id, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification_template (id INT AUTO_INCREMENT NOT NULL, template_key VARCHAR(80) NOT NULL, inbox_enabled TINYINT(1) DEFAULT 1 NOT NULL, inbox_title VARCHAR(255) NOT NULL, inbox_body LONGTEXT NOT NULL, email_enabled TINYINT(1) DEFAULT 0 NOT NULL, email_title VARCHAR(255) DEFAULT NULL, email_body LONGTEXT DEFAULT NULL, push_enabled TINYINT(1) DEFAULT 0 NOT NULL, push_title VARCHAR(255) DEFAULT NULL, push_body LONGTEXT DEFAULT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_notification_template_key (template_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE inbox_notification');
        $this->addSql('DROP TABLE notification_template');
    }
}
