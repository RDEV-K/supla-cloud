<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Direct links.
 */
class Version20180812205513 extends AbstractMigration {
    public function up(Schema $schema) {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('CREATE TABLE supla_direct_link (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, channel_id INT NOT NULL, slug VARCHAR(255) NOT NULL, caption VARCHAR(255) DEFAULT NULL, allowed_actions VARCHAR(255) NOT NULL, active_from DATETIME DEFAULT NULL COMMENT \'(DC2Type:utcdatetime)\', active_to DATETIME DEFAULT NULL COMMENT \'(DC2Type:utcdatetime)\', executions_limit INT DEFAULT NULL, last_used DATETIME DEFAULT NULL COMMENT \'(DC2Type:utcdatetime)\', last_ipv4 INT DEFAULT NULL, enabled TINYINT(1) NOT NULL, INDEX IDX_6AE7809FA76ED395 (user_id), INDEX IDX_6AE7809F72F5A1AA (channel_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE supla_direct_link ADD CONSTRAINT FK_6AE7809FA76ED395 FOREIGN KEY (user_id) REFERENCES supla_user (id)');
        $this->addSql('ALTER TABLE supla_direct_link ADD CONSTRAINT FK_6AE7809F72F5A1AA FOREIGN KEY (channel_id) REFERENCES supla_dev_channel (id)');
    }

    public function down(Schema $schema) {
        $this->abortIf(true, 'There is no way back');
    }
}
