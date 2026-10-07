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
 * Where a task stopped reading: the newest event it has handled, and the ETag
 * of the first event page at that moment.
 */
final class Cursor
{
	public function __construct(
		public readonly int $lastEventId,
		public readonly ?string $etag = null
	) {
	}
}
