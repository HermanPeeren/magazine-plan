<?php

/**
 * @package     MagazineChecklist
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\MagazineChecklist\Checklist;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Whether an overview issue belongs to a milestone, judged by its title.
 *
 * The old workflow used `title.includes(milestone)`, so a milestone "2026"
 * matched every title with 2026 in it and "May" matched "Mayhem". Here the
 * milestone title has to stand on its own in the issue title: not glued to
 * letters or digits on either side. Case is ignored.
 */
final class TitleMatcher
{
	public function matches(string $issueTitle, string $milestoneTitle): bool
	{
		$milestoneTitle = trim($milestoneTitle);

		if ($milestoneTitle === '') {
			return false;
		}

		$pattern = '/(?<![\p{L}\p{N}])' . preg_quote($milestoneTitle, '/') . '(?![\p{L}\p{N}])/iu';

		return preg_match($pattern, $issueTitle) === 1;
	}
}
