<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Add user_id column to supla_client.
 */
class Version20170818114139 extends AbstractMigration {
    public function up(Schema $schema) {
        $this->addSql('ALTER TABLE supla_client ADD user_id INT NULL');
        $this->addSql('UPDATE supla_client SET user_id=(SELECT user_id FROM supla_accessid WHERE id=access_id)');
        $this->addSql('ALTER TABLE supla_client CHANGE COLUMN user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE supla_client ADD CONSTRAINT FK_5430007FA76ED395 FOREIGN KEY (user_id) REFERENCES supla_user (id)');
        $this->addSql('CREATE INDEX IDX_5430007FA76ED395 ON supla_client (user_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) {
        $this->addSql('ALTER TABLE supla_client DROP FOREIGN KEY FK_5430007FA76ED395');
        $this->addSql('DROP INDEX IDX_5430007FA76ED395 ON supla_client');
        $this->addSql('ALTER TABLE supla_client DROP user_id');
    }
}
