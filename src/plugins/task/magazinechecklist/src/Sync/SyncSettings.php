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
 * The task parameters the sync itself reads. Owner, repository and token go
 * into the gateway instead.
 */
final class SyncSettings
{
	/**
	 * @param   list<string>  $labels    The labels of the overview issues: the issue
	 *                                   plans and the imagery issues.
	 * @param   string        $heading   The line under which entries are added.
	 * @param   integer       $maxPages  How many event pages one run reads at most.
	 * @param   boolean       $dryRun    Report, but write nothing to GitHub and
	 *                                   leave the cursor where it is.
	 */
	public function __construct(
		public readonly array $labels = ['issue plan', 'Imagery'],
		public readonly string $heading = '### Table of contents',
		public readonly int $maxPages = 10,
		public readonly bool $dryRun = false
	) {
	}
}
