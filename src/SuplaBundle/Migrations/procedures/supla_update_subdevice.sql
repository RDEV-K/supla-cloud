DROP PROCEDURE IF EXISTS `supla_update_subdevice`;

CREATE PROCEDURE `supla_update_subdevice`(IN `_id` INT, IN `_iodevice_id` INT, IN `_name` VARCHAR(200),
                                          IN `_software_version` VARCHAR(20), IN `_product_code` VARCHAR(50),
                                          IN `_serial_number` VARCHAR(50))
    NOT DETERMINISTIC CONTAINS SQL SQL SECURITY DEFINER
BEGIN
    UPDATE supla_subdevice
    SET updated_at = UTC_TIMESTAMP()
    WHERE id = _id
      AND iodevice_id = _iodevice_id
      AND (!(name <=> NULLIF(_name, ''))
        OR !(software_version <=> NULLIF(_software_version, ''))
        OR !(product_code <=> NULLIF(_product_code, ''))
        OR !(serial_number <=> NULLIF(_serial_number, '')));

    INSERT INTO supla_subdevice (id, iodevice_id, reg_date, name, software_version, product_code, serial_number)
    VALUES (_id, _iodevice_id, UTC_TIMESTAMP(), NULLIF(_name, ''), NULLIF(_software_version, ''), NULLIF(_product_code, ''),
            NULLIF(_serial_number, ''))
    ON DUPLICATE KEY
        UPDATE name             = NULLIF(_name, ''),
               software_version = NULLIF(_software_version, ''),
               product_code     = NULLIF(_product_code, ''),
               serial_number    = NULLIF(_serial_number, '');
END
