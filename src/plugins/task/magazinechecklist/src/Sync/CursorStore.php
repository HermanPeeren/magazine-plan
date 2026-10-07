<?php

/**
 * @package     MagazineChecklist
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\MagazineChecklist\Sync;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Keeps one cursor per task.
 */
interface CursorStore
{
	/**
	 * Null when the task has never run.
	 */
	public function load(int $taskId): ?Cursor;

	public function save(int $taskId, Cursor $cursor): void;
}
