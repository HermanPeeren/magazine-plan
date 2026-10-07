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
 * One entry of the repository's issue event list: something that happened to
 * an issue, such as `milestoned`, `labeled` or `closed`.
 *
 * Event ids only ever go up, which is what makes "everything after the last
 * one handled" a usable cursor.
 */
final class IssueEvent
{
	public const MILESTONED   = 'milestoned';
	public const DEMILESTONED = 'demilestoned';

	public function __construct(
		public readonly int $id,
		public readonly string $type,
		public readonly int $issueNumber,
		public readonly ?string $milestoneTitle = null,
		public readonly bool $onPullRequest = false
	) {
	}

	/**
	 * @param   array<string, mixed>  $data
	 */
	public static function fromApi(array $data): self
	{
		$issue     = \is_array($data['issue'] ?? null) ? $data['issue'] : [];
		$milestone = \is_array($data['milestone'] ?? null) ? $data['milestone'] : [];

		return new self(
			(int) ($data['id'] ?? 0),
			(string) ($data['event'] ?? ''),
			(int) ($issue['number'] ?? 0),
			isset($milestone['title']) ? (string) $milestone['title'] : null,
			isset($issue['pull_request'])
		);
	}

	/**
	 * A milestone added to or taken off an issue - not a pull request, which
	 * the old workflow never saw either.
	 */
	public function isMilestoneChange(): bool
	{
		return !$this->onPullRequest
			&& $this->issueNumber > 0
			&& ($this->type === self::MILESTONED || $this->type === self::DEMILESTONED);
	}
}
