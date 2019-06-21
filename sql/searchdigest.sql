CREATE TABLE IF NOT EXISTS `searchdigest` (
  `sd_query` VARBINARY(255) NOT NULL,
  `sd_misses` INT(9) UNSIGNED NOT NULL,
  `sd_touched` DATETIME,

  PRIMARY KEY (`sd_query`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;