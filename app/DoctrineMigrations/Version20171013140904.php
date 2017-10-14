<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171013140904 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE supla_rel_cg (channel_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_BE981CD772F5A1AA (channel_id), INDEX IDX_BE981CD7FE54D947 (group_id), PRIMARY KEY(channel_id, group_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE supla_dev_channel_group (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, enabled TINYINT(1) NOT NULL, caption VARCHAR(255) DEFAULT NULL, func INT NOT NULL, INDEX IDX_6B2EFCE5A76ED395 (user_id), INDEX enabled_idx (enabled), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE supla_rel_cg ADD CONSTRAINT FK_BE981CD772F5A1AA FOREIGN KEY (channel_id) REFERENCES supla_dev_channel (id)');
        $this->addSql('ALTER TABLE supla_rel_cg ADD CONSTRAINT FK_BE981CD7FE54D947 FOREIGN KEY (group_id) REFERENCES supla_dev_channel_group (id)');
        $this->addSql('ALTER TABLE supla_dev_channel_group ADD CONSTRAINT FK_6B2EFCE5A76ED395 FOREIGN KEY (user_id) REFERENCES supla_user (id)');
        $this->addSql('ALTER TABLE supla_dev_channel ADD alt_icon INT DEFAULT NULL, ADD hidden TINYINT(1) NOT NULL');
        
        $this->addSql("DROP VIEW IF EXISTS `supla_v_client_channel`");
        $this->addSql("CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `supla_v_client_channel`  AS  select `c`.`id` AS `id`,`c`.`type` AS `type`,`c`.`func` AS `func`,`c`.`param1` AS `param1`,`c`.`param2` AS `param2`,`c`.`caption` AS `caption`,`c`.`param3` AS `param3`,`c`.`user_id` AS `user_id`,`c`.`channel_number` AS `channel_number`,`c`.`iodevice_id` AS `iodevice_id`,`cl`.`id` AS `client_id`,`r`.`location_id` AS `location_id`,IFNULL(`c`.`alt_icon`, 0) AS `alt_icon`,`d`.`protocol_version` AS `protocol_version` from (((((`supla_dev_channel` `c` join `supla_iodevice` `d` on((`d`.`id` = `c`.`iodevice_id`))) join `supla_location` `l` on((`l`.`id` = `d`.`location_id`))) join `supla_rel_aidloc` `r` on((`r`.`location_id` = `l`.`id`))) join `supla_accessid` `a` on((`a`.`id` = `r`.`access_id`))) join `supla_client` `cl` on((`cl`.`access_id` = `r`.`access_id`))) where ((`c`.`func` is not null) and (`c`.`func` <> 0) and (`d`.`enabled` = 1) and (`l`.`enabled` = 1) and (`a`.`enabled` = 1))");
        
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE supla_rel_cg DROP FOREIGN KEY FK_BE981CD7FE54D947');
        $this->addSql('DROP TABLE supla_rel_cg');
        $this->addSql('DROP TABLE supla_dev_channel_group');
        $this->addSql('ALTER TABLE supla_dev_channel DROP alt_icon, DROP hidden');
        
        $this->addSql("DROP VIEW IF EXISTS `supla_v_client_channel`");
        $this->addSql("CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `supla_v_client_channel`  AS  select `c`.`id` AS `id`,`c`.`type` AS `type`,`c`.`func` AS `func`,`c`.`param1` AS `param1`,`c`.`param2` AS `param2`,`c`.`caption` AS `caption`,`c`.`param3` AS `param3`,`c`.`user_id` AS `user_id`,`c`.`channel_number` AS `channel_number`,`c`.`iodevice_id` AS `iodevice_id`,`cl`.`id` AS `client_id`,`r`.`location_id` AS `location_id` from (((((`supla_dev_channel` `c` join `supla_iodevice` `d` on((`d`.`id` = `c`.`iodevice_id`))) join `supla_location` `l` on((`l`.`id` = `d`.`location_id`))) join `supla_rel_aidloc` `r` on((`r`.`location_id` = `l`.`id`))) join `supla_accessid` `a` on((`a`.`id` = `r`.`access_id`))) join `supla_client` `cl` on((`cl`.`access_id` = `r`.`access_id`))) where ((`c`.`func` is not null) and (`c`.`func` <> 0) and (`d`.`enabled` = 1) and (`l`.`enabled` = 1) and (`a`.`enabled` = 1))");
        
    }
}
