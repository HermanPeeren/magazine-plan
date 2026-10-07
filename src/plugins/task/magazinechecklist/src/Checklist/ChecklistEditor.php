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
 * Adds and removes `- [ ] #123` lines in the body of an overview issue.
 *
 * Pure string work, no GitHub, so every case can be tested on its own. It
 * replaces the two helpers of the old workflow and fixes what they got wrong:
 *
 * - "already there" looked for `#12` anywhere in the body, so it also matched
 *   `#123` and any mention in running text. Here only a checklist line counts.
 * - removing an entry also collapsed every blank line in the whole body. Here
 *   only the entry's own line goes.
 * - issue bodies written in GitHub's editor use CRLF line endings. A new line
 *   gets the same ending as the rest of the body.
 */
final class ChecklistEditor
{
	/**
	 * Whether the body has a checklist line for the issue, ticked or not.
	 */
	public function hasEntry(string $body, int $number): bool
	{
		return preg_match($this->entryPattern($number), $body) === 1;
	}

	/**
	 * The body with `- [ ] #number` at the top of the list under the heading.
	 *
	 * Returns the body unchanged when the entry is already there, and null when
	 * the heading is not: then there is no list to add to, which the caller
	 * reports rather than guessing a place.
	 */
	public function add(string $body, int $number, string $heading): ?string
	{
		if ($this->hasEntry($body, $number)) {
			return $body;
		}

		$lines        = explode("\n", $body);
		$heading      = trim($heading);
		$headingIndex = null;

		foreach ($lines as $index => $line) {
			if (trim($line) === $heading) {
				$headingIndex = $index;

				break;
			}
		}

		if ($headingIndex === null) {
			return null;
		}

		// At the top of the list under the heading: past the blank lines, so the
		// entry joins the list rather than sitting between the heading and it.
		// When there is no list yet - the body ends, or the next section starts
		// - straight under the heading, so it does not drift down to the next
		// section or the end of the body.
		$insertAt = $headingIndex + 1;

		while ($insertAt < \count($lines) && trim($lines[$insertAt]) === '') {
			$insertAt++;
		}

		if ($insertAt === \count($lines) || preg_match('/^[ \t]*[-*] \[[ xX]\] /', $lines[$insertAt]) !== 1) {
			$insertAt = $headingIndex + 1;
		}

		$carriageReturn = str_contains($body, "\r\n") ? "\r" : '';

		array_splice($lines, $insertAt, 0, ['- [ ] #' . $number . $carriageReturn]);

		return implode("\n", $lines);
	}

	/**
	 * The body without the checklist lines for the issue. Unchanged when there
	 * are none.
	 */
	public function remove(string $body, int $number): string
	{
		return (string) preg_replace($this->entryPattern($number, true), '', $body);
	}

	/**
	 * A checklist line for the issue: `- [ ] #12`, `- [x] #12 Some title`,
	 * `* [X] #12`. Never `#123`.
	 *
	 * @param   boolean  $wholeLine  Match up to and including the line ending, to
	 *                               remove it.
	 */
	private function entryPattern(int $number, bool $wholeLine = false): string
	{
		$entry = '^[ \t]*[-*] \[[ xX]\] #' . $number . '\b';

		return $wholeLine
			? '/' . $entry . '[^\n]*(?:\n|$)/m'
			: '/' . $entry . '/m';
	}
}
