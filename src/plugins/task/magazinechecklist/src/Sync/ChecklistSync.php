<?php

/**
 * @package     MagazineChecklist
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\MagazineChecklist\Sync;

use Yepr\Plugin\Task\MagazineChecklist\Checklist\ChecklistEditor;
use Yepr\Plugin\Task\MagazineChecklist\Checklist\TitleMatcher;
use Yepr\Plugin\Task\MagazineChecklist\Github\GithubGateway;
use Yepr\Plugin\Task\MagazineChecklist\Github\Issue;
use Yepr\Plugin\Task\MagazineChecklist\Github\IssueEvent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One run of the sync: what the old GitHub workflow did per event, done for all
 * events since the previous run.
 *
 * 1. Read the issue events newer than the cursor, page by page.
 * 2. Keep the milestone changes on issues, oldest first.
 * 3. Load the open overview issues once, and replay the changes on their
 *    bodies in memory:
 *    - milestoned: add `- [ ] #n` to the overview issues whose title names the
 *      milestone, and remove it from the other overview issues, so an issue
 *      moved from one milestone to another does not stay in both;
 *    - demilestoned: remove it from all overview issues.
 * 4. Write each changed body back once, then move the cursor.
 *
 * The cursor only moves after the writes, so a run that fails halfway is done
 * again by the next one. That is safe, because adding an entry that is there
 * and removing one that is not both change nothing.
 */
final class ChecklistSync
{
	/**
	 * @var \Closure(string, string): void
	 */
	private \Closure $log;

	/**
	 * Made by ChecklistSyncFactory, once per task run.
	 *
	 * @param   callable(string, string): void  $log  Receives a message and a priority:
	 *                                                info, warning or error.
	 */
	public function __construct(
		private readonly GithubGateway $github,
		private readonly CursorStore $cursors,
		private readonly SyncSettings $settings,
		callable $log,
		private readonly ChecklistEditor $editor,
		private readonly TitleMatcher $matcher
	) {
		$this->log = $log(...);
	}

	/**
	 * @return  integer  The number of overview issues changed, or that would be
	 *                   in a dry run.
	 */
	public function run(int $taskId): int
	{
		$cursor = $this->cursors->load($taskId);

		if ($cursor === null) {
			$this->start($taskId);

			return 0;
		}

		$read = $this->readNewEvents($cursor);

		if ($read === null) {
			$this->log('No new events since the last run.');

			return 0;
		}

		[$events, $next] = $read;

		$changes = array_values(array_filter($events, static fn (IssueEvent $event): bool => $event->isMilestoneChange()));
		usort($changes, static fn (IssueEvent $a, IssueEvent $b): int => $a->id <=> $b->id);

		$this->log(\sprintf('%d new event(s), %d of them milestone changes.', \count($events), \count($changes)));

		$changed = $changes === [] ? 0 : $this->apply($changes);

		if ($this->settings->dryRun) {
			$this->log('Dry run: the cursor stays where it was, so the next run reads these events again.');
		} else {
			$this->cursors->save($taskId, $next);
		}

		return $changed;
	}

	/**
	 * The first run: remember where the repository is now, and replay nothing.
	 * Its history goes back up to 90 days and was handled by the old workflow,
	 * or by hand.
	 */
	private function start(int $taskId): void
	{
		$page   = $this->github->eventPage(1);
		$newest = 0;

		foreach ($page->events as $event) {
			$newest = max($newest, $event->id);
		}

		$this->cursors->save($taskId, new Cursor($newest, $page->etag));
		$this->log(\sprintf(
			'First run: starting after event %d. Milestone changes made before now are not replayed.',
			$newest
		));
	}

	/**
	 * The events newer than the cursor, and the cursor to save once they are
	 * handled. Null when GitHub says nothing changed.
	 *
	 * @return  array{0: list<IssueEvent>, 1: Cursor}|null
	 */
	private function readNewEvents(Cursor $cursor): ?array
	{
		$events   = [];
		$newest   = $cursor->lastEventId;
		$etag     = $cursor->etag;
		$complete = false;

		for ($pageNumber = 1; $pageNumber <= max(1, $this->settings->maxPages); $pageNumber++) {
			$page = $this->github->eventPage($pageNumber, $pageNumber === 1 ? $cursor->etag : null);

			if ($page->notModified) {
				return null;
			}

			if ($pageNumber === 1) {
				$etag = $page->etag;
			}

			foreach ($page->events as $event) {
				if ($event->id <= $cursor->lastEventId) {
					$complete = true;

					break 2;
				}

				$events[] = $event;
				$newest   = max($newest, $event->id);
			}

			if (!$page->hasNext) {
				$complete = true;

				break;
			}
		}

		if (!$complete) {
			$this->log(
				\sprintf(
					'There were more than %d page(s) of new events; the oldest were not read. '
					. 'Check the checklists by hand, or raise the maximum and run more often.',
					$this->settings->maxPages
				),
				'warning'
			);
		}

		return [$events, new Cursor($newest, $etag)];
	}

	/**
	 * Replay the milestone changes on the overview issues and write back what
	 * changed.
	 *
	 * @param   list<IssueEvent>  $changes  Oldest first.
	 */
	private function apply(array $changes): int
	{
		/** @var array<string, list<Issue>> $groups  The overview issues, per label. */
		$groups = [];

		/** @var array<int, Issue> $overview */
		$overview = [];

		foreach ($this->settings->labels as $label) {
			$groups[$label] = $this->github->openIssuesWithLabel($label);

			foreach ($groups[$label] as $issue) {
				$overview[$issue->number] = $issue;
			}
		}

		/** @var array<int, string> $bodies */
		$bodies = array_map(static fn (Issue $issue): string => $issue->body, $overview);

		foreach ($changes as $change) {
			if (isset($overview[$change->issueNumber])) {
				$this->log(\sprintf('Skipped #%d: it is an overview issue itself.', $change->issueNumber));

				continue;
			}

			$targets = [];

			if ($change->type === IssueEvent::MILESTONED) {
				$targets = $this->targetsFor($change, $groups);
			}

			foreach ($overview as $number => $issue) {
				if (\in_array($number, $targets, true)) {
					$bodies[$number] = $this->addTo($issue, $bodies[$number], $change->issueNumber);
				} else {
					$bodies[$number] = $this->removeFrom($issue, $bodies[$number], $change->issueNumber);
				}
			}
		}

		$changed = 0;

		foreach ($overview as $number => $issue) {
			if ($bodies[$number] === $issue->body) {
				continue;
			}

			$changed++;

			if ($this->settings->dryRun) {
				$this->log(\sprintf('Dry run: would update #%d "%s".', $number, $issue->title));

				continue;
			}

			$this->github->updateIssueBody($number, $bodies[$number]);
			$this->log(\sprintf('Updated #%d "%s".', $number, $issue->title));
		}

		return $changed;
	}

	/**
	 * The overview issues an issue with this milestone belongs in: per label,
	 * those whose title names the milestone.
	 *
	 * @param   array<string, list<Issue>>  $groups
	 *
	 * @return  list<int>
	 */
	private function targetsFor(IssueEvent $change, array $groups): array
	{
		$targets = [];

		foreach ($groups as $label => $issues) {
			$matching = array_values(array_filter(
				$issues,
				fn (Issue $issue): bool => $this->matcher->matches($issue->title, (string) $change->milestoneTitle)
			));

			if ($matching === []) {
				$this->log(\sprintf(
					'No open "%s" issue has the milestone "%s" in its title, so #%d is not added there.',
					$label,
					(string) $change->milestoneTitle,
					$change->issueNumber
				));
			}

			if (\count($matching) > 1) {
				$this->log(\sprintf(
					'%d open "%s" issues have the milestone "%s" in their title; #%d is added to all of them.',
					\count($matching),
					$label,
					(string) $change->milestoneTitle,
					$change->issueNumber
				), 'warning');
			}

			foreach ($matching as $issue) {
				$targets[] = $issue->number;
			}
		}

		return $targets;
	}

	private function addTo(Issue $overview, string $body, int $number): string
	{
		$added = $this->editor->add($body, $number, $this->settings->heading);

		if ($added === null) {
			$this->log(\sprintf(
				'#%d "%s" has no line "%s", so #%d could not be added to it.',
				$overview->number,
				$overview->title,
				$this->settings->heading,
				$number
			), 'warning');

			return $body;
		}

		if ($added !== $body) {
			$this->log(\sprintf('Added #%d to #%d "%s".', $number, $overview->number, $overview->title));
		}

		return $added;
	}

	private function removeFrom(Issue $overview, string $body, int $number): string
	{
		$removed = $this->editor->remove($body, $number);

		if ($removed !== $body) {
			$this->log(\sprintf('Removed #%d from #%d "%s".', $number, $overview->number, $overview->title));
		}

		return $removed;
	}

	private function log(string $message, string $priority = 'info'): void
	{
		($this->log)($message, $priority);
	}
}
