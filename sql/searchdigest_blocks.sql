CREATE TABLE IF NOT EXISTS `searchdigest_blocks` (
	`sd_blocks_query` VARBINARY(255) NOT NULL,
	`sd_blocks_added` DATETIME,
	`sd_blocks_actor` bigint unsigned NOT NULL,
	PRIMARY KEY (`sd_blocks_query`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
