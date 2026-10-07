<?php

/**
 * @package     MagazineChecklist
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\MagazineChecklist\Github;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * An issue as far as the checklists need it.
 */
final class Issue
{
	public function __construct(
		public readonly int $number,
		public readonly string $title,
		public readonly string $body,
		public readonly bool $isPullRequest = false
	) {
	}

	/**
	 * From one item of GitHub's issue list.
	 *
	 * @param   array<string, mixed>  $data
	 */
	public static function fromApi(array $data): self
	{
		return new self(
			(int) ($data['number'] ?? 0),
			(string) ($data['title'] ?? ''),
			// An issue created without a description has a null body.
			(string) ($data['body'] ?? ''),
			isset($data['pull_request'])
		);
	}
}
