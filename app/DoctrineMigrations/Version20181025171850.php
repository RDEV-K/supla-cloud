<?php

namespace Supla\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * New table for ImpulseCounter entity,
 * Additional indexes for supla_oauth_table,
 * access_id_id renamed to access_id,
 * New procedures. Ref. #67
 */
class Version20181025171850 extends NoWayBackMigration
{
    public function migrate() {

        $this->addSql('CREATE TABLE supla_ic_log (id INT AUTO_INCREMENT NOT NULL, channel_id INT NOT NULL, date DATETIME NOT NULL COMMENT \'(DC2Type:utcdatetime)\', counter BIGINT NOT NULL, calculated_value BIGINT NOT NULL, INDEX channel_id_idx (channel_id), INDEX date_idx (date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX supla_oauth_clients_random_id_idx ON supla_oauth_clients (random_id)');
        $this->addSql('CREATE INDEX supla_oauth_clients_type_idx ON supla_oauth_clients (type)');
        $this->addSql('ALTER TABLE supla_oauth_access_tokens DROP FOREIGN KEY FK_2402564B96D1E204');
        $this->addSql('DROP INDEX IDX_2402564B96D1E204 ON supla_oauth_access_tokens');
        $this->addSql('ALTER TABLE supla_oauth_access_tokens CHANGE access_id_id access_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE supla_oauth_access_tokens ADD CONSTRAINT FK_2402564B4FEA67CF FOREIGN KEY (access_id) REFERENCES supla_accessid (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_2402564B4FEA67CF ON supla_oauth_access_tokens (access_id)');

        $this->addSql(<<<PROCEDURE
CREATE PROCEDURE `supla_add_channel` (IN `_type` INT, IN `_func` INT, IN `_param1` INT, IN `_param2` INT, IN `_param3` INT, 
IN `_user_id` INT, IN `_channel_number` INT, IN `_iodevice_id` INT, IN `_flist` INT, IN `_flags` INT)  NO SQL
BEGIN

INSERT INTO `supla_dev_channel` (`type`, `func`, `param1`, `param2`, `param3`, `user_id`, `channel_number`, 
`iodevice_id`, `flist`, `flags`) 
VALUES (_type, _func, _param1, _param2, _param3, _user_id, _channel_number, _iodevice_id, _flist, _flags);

END
PROCEDURE
        );

        $this->addSql(<<<PROCEDURE
CREATE PROCEDURE `supla_add_client` (IN `_access_id` INT(11), IN `_guid` VARBINARY(16), IN `_name` VARCHAR(100) CHARSET utf8, 
IN `_reg_ipv4` INT(10) UNSIGNED, IN `_software_version` VARCHAR(20) CHARSET utf8, IN `_protocol_version` INT(11), IN `_user_id` INT(11), 
IN `_auth_key` VARCHAR(64) CHARSET utf8, OUT `_id` INT(11))  NO SQL
BEGIN

IF EXISTS(SELECT 1 FROM `supla_user` WHERE `id` = _user_id
         AND client_reg_enabled IS NOT NULL AND client_reg_enabled >= UTC_TIMESTAMP()) THEN

INSERT INTO `supla_client`(`access_id`, `guid`, `name`, `enabled`, `reg_ipv4`, `reg_date`, `last_access_ipv4`, 
`last_access_date`,`software_version`, `protocol_version`, `user_id`, `auth_key`) 
VALUES (_access_id, _guid, _name, 1, _reg_ipv4, UTC_TIMESTAMP(), _reg_ipv4, UTC_TIMESTAMP(), _software_version, _protocol_version, 
_user_id, _auth_key);

SELECT LAST_INSERT_ID() INTO _id;

END IF;
END
PROCEDURE
        );

        $this->addSql(<<<PROCEDURE
CREATE PROCEDURE `supla_add_em_log_item` (IN `_channel_id` INT(11), IN `_phase1_fae` BIGINT, IN `_phase1_rae` BIGINT, 
IN `_phase1_fre` BIGINT, IN `_phase1_rre` BIGINT, IN `_phase2_fae` BIGINT, IN `_phase2_rae` BIGINT, IN `_phase2_fre` BIGINT, 
IN `_phase2_rre` BIGINT, IN `_phase3_fae` BIGINT, IN `_phase3_rae` BIGINT, IN `_phase3_fre` BIGINT, IN `_phase3_rre` BIGINT)  NO SQL
BEGIN

INSERT INTO `supla_em_log`(`channel_id`, `date`, `phase1_fae`, `phase1_rae`, `phase1_fre`, `phase1_rre`, `phase2_fae`, 
`phase2_rae`, `phase2_fre`, `phase2_rre`, `phase3_fae`, `phase3_rae`, `phase3_fre`, `phase3_rre`) 
VALUES (_channel_id, UTC_TIMESTAMP(), _phase1_fae, _phase1_rae, _phase1_fre, _phase1_rre, 
_phase2_fae, _phase2_rae, _phase2_fre, _phase2_rre, _phase3_fae, _phase3_rae, _phase3_fre, _phase3_rre);

END
PROCEDURE
        );

        $this->addSql(<<<PROCEDURE
CREATE PROCEDURE `supla_add_ic_log_item` (IN `_channel_id` INT(11), IN `_counter` BIGINT, IN `_calculated_value` BIGINT)  NO SQL
BEGIN

INSERT INTO `supla_ic_log`(`channel_id`, `date`, `counter`, `calculated_value`) 
VALUES (_channel_id,UTC_TIMESTAMP(),_counter, _calculated_value);

END
PROCEDURE
        );

        $this->addSql(<<<PROCEDURE
CREATE PROCEDURE `supla_add_temperature_log_item` (IN `_channel_id` INT(11), IN `_temperature` DECIMAL(8,4))  NO SQL
BEGIN

INSERT INTO `supla_temperature_log`(`channel_id`, `date`, `temperature`) VALUES (_channel_id,UTC_TIMESTAMP(),_temperature);

END
PROCEDURE
        );

        $this->addSql(<<<PROCEDURE
CREATE PROCEDURE `supla_add_temphumidity_log_item` (IN `_channel_id` INT(11), IN `_temperature` DECIMAL(8,4), 
IN `_humidity` DECIMAL(8,4))  NO SQL
BEGIN

INSERT INTO `supla_temphumidity_log`(`channel_id`, `date`, `temperature`) VALUES (_channel_id,UTC_TIMESTAMP(),_temperature, _humidiy);

END
PROCEDURE
        );

        $this->addSql(<<<PROCEDURE
CREATE PROCEDURE `supla_add_iodevice` (IN `_location_id` INT(11), IN `_user_id` INT(11), IN `_guid` VARBINARY(16), 
IN `_name` VARCHAR(100) CHARSET utf8, IN `_reg_ipv4` INT(10) UNSIGNED, IN `_software_version` VARCHAR(10), 
IN `_protocol_version` INT(11), IN `_original_location_id` INT(11), IN `_auth_key` VARCHAR(64), 
IN `_flags` INT(11), OUT `_id` INT(11))  NO SQL
BEGIN

INSERT INTO `supla_iodevice`(`location_id`, `user_id`, `guid`, `name`, `enabled`, `reg_date`, `reg_ipv4`, `last_connected`, `last_ipv4`, 
`software_version`, `protocol_version`, `original_location_id`, `auth_key`, `flags`) 
VALUES (_location_id, _user_id, _guid, _name, 1, UTC_TIMESTAMP(), _reg_ipv4, UTC_TIMESTAMP(), _reg_ipv4, _software_version, 
_protocol_version, _original_location_id, _auth_key, _flags);

SELECT LAST_INSERT_ID() INTO _id;

END
PROCEDURE
        );

        $this->addSql(<<<PROCEDURE
CREATE PROCEDURE `supla_oauth_add_client_for_app` (IN `_random_id` VARCHAR(255) CHARSET utf8, 
IN `_secret` VARCHAR(255) CHARSET utf8, OUT `_id` INT(11))  NO SQL
BEGIN

SET @lck = 0;
SET @id_exists = 0;

SELECT GET_LOCK('oauth_add_client', 2) INTO @lck;

IF @lck = 1 THEN

 SELECT id INTO @id_exists FROM `supla_oauth_clients` WHERE `type` = 2 LIMIT 1;

  IF @id_exists <> 0 THEN
     SELECT @id_exists INTO _id;
  ELSE 
     INSERT INTO `supla_oauth_clients`(
         `random_id`, `redirect_uris`, 
         `secret`, `allowed_grant_types`, `type`) VALUES 
     (_random_id, 'a:0:{}', _secret,'a:2:{i:0;s:8:"password";i:1;s:13:"refresh_token";}',2);
     
     SELECT RELEASE_LOCK('oauth_add_client');
  END IF;

END IF;

END
PROCEDURE
        );

        $this->addSql(<<<PROCEDURE
CREATE PROCEDURE `supla_oauth_add_token_for_app` (IN `_user_id` INT(11), IN `_token` VARCHAR(255) CHARSET utf8, 
IN `_expires_at` INT(11), IN `_access_id` INT(11), OUT `_id` INT(11))  NO SQL
BEGIN

SET @client_id = 0;

SELECT `id` INTO @client_id FROM `supla_oauth_clients` WHERE `type` = 2 LIMIT 1;

IF @client_id <> 0 AND EXISTS(SELECT 1 FROM `supla_accessid` WHERE `user_id` = _user_id AND `id` = _access_id) THEN 

  INSERT INTO `supla_oauth_access_tokens`(`client_id`, `user_id`, `token`, `expires_at`, `scope`, `access_id`) VALUES 
   (@client_id, _user_id, _token, _expires_at, 'channels_r channels_files', _access_id);

END IF;

END
PROCEDURE
        );

        $this->addSql(<<<PROCEDURE
CREATE PROCEDURE `supla_update_client` (IN `_access_id` INT(11), IN `_name` VARCHAR(100) CHARSET utf8, 
IN `_last_ipv4` INT(10) UNSIGNED, IN `_software_version` VARCHAR(20) CHARSET utf8, 
IN `_protocol_version` INT(11), IN `_auth_key` VARCHAR(64) CHARSET utf8, IN `_id` INT(11))  NO SQL
BEGIN

UPDATE `supla_client` 

SET 
`access_id` = _access_id,
`name` = _name, 
`last_access_date` = UTC_TIMESTAMP(),
`last_access_ipv4` = _last_ipv4, 
`software_version` = _software_version, 
`protocol_version` = _protocol_version WHERE `id` = _id;

IF _auth_key IS NOT NULL THEN
  UPDATE `supla_client` 
  SET `auth_key` = _auth_key WHERE `id` = _id AND `auth_key` IS NULL;
END IF;

END
PROCEDURE
        );

        $this->addSql(<<<PROCEDURE
CREATE PROCEDURE `supla_update_iodevice` (IN `_name` VARCHAR(100) CHARSET utf8, IN `_last_ipv4` INT(10) UNSIGNED, 
IN `_software_version` VARCHAR(10) CHARSET utf8, IN `_protocol_version` INT(11), IN `_original_location_id` INT(11), 
IN `_auth_key` VARCHAR(64) CHARSET utf8, IN `_id` INT(11))  NO SQL
BEGIN

UPDATE `supla_iodevice` 
SET 
`name` = _name, 
`last_connected` = UTC_TIMESTAMP(),
`last_ipv4` = _last_ipv4, 
`software_version` = _software_version, 
`protocol_version` = _protocol_version, 
original_location_id = _original_location_id WHERE `id` = _id;

IF _auth_key IS NOT NULL THEN
  UPDATE `supla_iodevice` 
  SET `auth_key` = _auth_key WHERE `id` = _id AND `auth_key` IS NULL;
END IF;

END
PROCEDURE
        );
    }

}
