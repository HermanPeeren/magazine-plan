--
-- Where each task stopped reading the repository's events, so the next run
-- starts after it. One row per task: two tasks can sync two repositories.
--
CREATE TABLE IF NOT EXISTS `#__magazinechecklist_cursor` (
  `task_id` int unsigned NOT NULL,
  `last_event_id` bigint unsigned NOT NULL DEFAULT 0,
  `etag` varchar(255) DEFAULT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
