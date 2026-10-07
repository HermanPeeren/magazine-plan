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
 * One page of the repository's issue events, newest first.
 */
final class EventPage
{
	/**
	 * @param   list<IssueEvent>  $events
	 */
	public function __construct(
		public readonly array $events,
		public readonly bool $hasNext,
		public readonly ?string $etag = null,
		public readonly bool $notModified = false
	) {
	}

	/**
	 * GitHub's answer to a conditional request whose ETag still holds: nothing
	 * happened since. Such an answer does not count against the rate limit.
	 */
	public static function notModified(?string $etag): self
	{
		return new self([], false, $etag, true);
	}
}
