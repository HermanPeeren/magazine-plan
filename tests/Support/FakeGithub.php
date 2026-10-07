<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\MagazineChecklist\Tests\Support;

use Yepr\Plugin\Task\MagazineChecklist\Github\EventPage;
use Yepr\Plugin\Task\MagazineChecklist\Github\GithubGateway;
use Yepr\Plugin\Task\MagazineChecklist\Github\Issue;
use Yepr\Plugin\Task\MagazineChecklist\Github\IssueEvent;

/**
 * A repository in memory: events, labelled overview issues, and a record of
 * what was written back.
 */
final class FakeGithub implements GithubGateway
{
    /** @var list<IssueEvent> Newest first, as GitHub lists them. */
    private array $events = [];

    /** @var array<string, list<Issue>> */
    private array $issuesByLabel = [];

    /** @var array<int, string> */
    public array $updates = [];

    /** @var list<array{0: int, 1: string|null}> */
    public array $eventPageCalls = [];

    public ?string $etag = 'etag-1';

    public bool $unchangedSinceEtag = false;

    public function __construct(private readonly int $perPage = 100)
    {
    }

    public function addEvent(IssueEvent $event): void
    {
        array_unshift($this->events, $event);
    }

    public function addIssue(string $label, Issue $issue): void
    {
        $this->issuesByLabel[$label][] = $issue;
    }

    public function eventPage(int $page, ?string $etag = null): EventPage
    {
        $this->eventPageCalls[] = [$page, $etag];

        if ($page === 1 && $this->unchangedSinceEtag && $etag === $this->etag) {
            return EventPage::notModified($etag);
        }

        $slice = \array_slice($this->events, ($page - 1) * $this->perPage, $this->perPage);

        return new EventPage(
            $slice,
            \count($this->events) > $page * $this->perPage,
            $page === 1 ? $this->etag : null
        );
    }

    public function openIssuesWithLabel(string $label): array
    {
        return $this->issuesByLabel[$label] ?? [];
    }

    public function updateIssueBody(int $number, string $body): void
    {
        $this->updates[$number] = $body;
    }
}
