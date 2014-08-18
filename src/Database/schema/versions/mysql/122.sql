begin;

CREATE TABLE `sessions` (
    `session_name` VARCHAR(255) NOT NULL,
    `session_id` CHAR(40) NOT NULL,
    `mtime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `data` MEDIUMTEXT DEFAULT '' NOT NULL,
    `update_count` INT(1) UNSIGNED DEFAULT 0 NOT NULL ,
    PRIMARY KEY (`session_id`, `session_name`),
    INDEX `on_mtime` (`mtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

update ttrss_version set schema_version = 122;

commit;
