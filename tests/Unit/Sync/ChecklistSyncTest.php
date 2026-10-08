<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\MagazineChecklist\Tests\Unit\Sync;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\MagazineChecklist\Checklist\ChecklistEditor;
use Yepr\Plugin\Task\MagazineChecklist\Checklist\TitleMatcher;
use Yepr\Plugin\Task\MagazineChecklist\Github\Issue;
use Yepr\Plugin\Task\MagazineChecklist\Github\IssueEvent;
use Yepr\Plugin\Task\MagazineChecklist\Sync\ChecklistSync;
use Yepr\Plugin\Task\MagazineChecklist\Sync\Cursor;
use Yepr\Plugin\Task\MagazineChecklist\Sync\SyncSettings;
use Yepr\Plugin\Task\MagazineChecklist\Tests\Support\FakeGithub;
use Yepr\Plugin\Task\MagazineChecklist\Tests\Support\MemoryCursorStore;

final class ChecklistSyncTest extends TestCase
{
    private const TASK = 7;

    private const EMPTY_PLAN = "Plan\n\n### Table of contents\n\n";

    private FakeGithub $github;

    private MemoryCursorStore $cursors;

    /** @var list<array{string, string}> */
    private array $log = [];

    protected function setUp(): void
    {
        $this->github  = new FakeGithub();
        $this->cursors = new MemoryCursorStore();

        $this->github->addIssue('issue plan', new Issue(1, 'Issue plan November 2026', self::EMPTY_PLAN));
        $this->github->addIssue('issue plan', new Issue(2, 'Issue plan December 2026', self::EMPTY_PLAN));
        $this->github->addIssue('Imagery', new Issue(3, 'Imagery November 2026', self::EMPTY_PLAN));
    }

    public function testTheFirstRunOnlyRemembersWhereTheRepositoryIs(): void
    {
        $this->github->addEvent(new IssueEvent(100, IssueEvent::MILESTONED, 12, 'November 2026'));
        $this->github->addEvent(new IssueEvent(101, 'labeled', 12));

        $changed = $this->sync()->run(self::TASK);

        self::assertSame(0, $changed);
        self::assertSame([], $this->github->updates);
        self::assertEquals(new Cursor(101, 'etag-1'), $this->cursors->load(self::TASK));
    }

    public function testAMilestoneAddsTheIssueToItsPlanAndItsImagery(): void
    {
        $this->cursors->save(self::TASK, new Cursor(100));
        $this->github->addEvent(new IssueEvent(101, IssueEvent::MILESTONED, 12, 'November 2026'));

        $changed = $this->sync()->run(self::TASK);

        self::assertSame(2, $changed);
        self::assertSame([1, 3], array_keys($this->github->updates));
        self::assertStringContainsString("### Table of contents\n- [ ] #12", $this->github->updates[1]);
        self::assertStringContainsString("### Table of contents\n- [ ] #12", $this->github->updates[3]);
        self::assertEquals(new Cursor(101, 'etag-1'), $this->cursors->load(self::TASK));
    }

    public function testRemovingTheMilestoneRemovesTheIssueEverywhere(): void
    {
        $this->github = new FakeGithub();
        $this->github->addIssue('issue plan', new Issue(1, 'Issue plan November 2026', "### Table of contents\n- [ ] #12\n- [ ] #13"));
        $this->github->addIssue('Imagery', new Issue(3, 'Imagery November 2026', "### Table of contents\n- [x] #12"));
        $this->cursors->save(self::TASK, new Cursor(100));
        $this->github->addEvent(new IssueEvent(101, IssueEvent::DEMILESTONED, 12, 'November 2026'));

        $this->sync()->run(self::TASK);

        self::assertSame([1 => "### Table of contents\n- [ ] #13", 3 => "### Table of contents\n"], $this->github->updates);
    }

    public function testMovingToAnotherMilestoneMovesTheEntry(): void
    {
        $this->github = new FakeGithub();
        $this->github->addIssue('issue plan', new Issue(1, 'Issue plan November 2026', "### Table of contents\n- [ ] #12"));
        $this->github->addIssue('issue plan', new Issue(2, 'Issue plan December 2026', "### Table of contents\n"));
        $this->cursors->save(self::TASK, new Cursor(100));
        // Changing the milestone in GitHub's sidebar records only the new one.
        $this->github->addEvent(new IssueEvent(101, IssueEvent::MILESTONED, 12, 'December 2026'));

        $this->sync()->run(self::TASK);

        self::assertSame("### Table of contents\n", $this->github->updates[1]);
        self::assertSame("### Table of contents\n- [ ] #12\n", $this->github->updates[2]);
    }

    public function testEventsAreReplayedOldestFirst(): void
    {
        $this->cursors->save(self::TASK, new Cursor(100));
        $this->github->addEvent(new IssueEvent(101, IssueEvent::MILESTONED, 12, 'November 2026'));
        $this->github->addEvent(new IssueEvent(102, IssueEvent::DEMILESTONED, 12, 'November 2026'));
        $this->github->addEvent(new IssueEvent(103, IssueEvent::MILESTONED, 14, 'November 2026'));

        $this->sync()->run(self::TASK);

        self::assertStringNotContainsString('#12', $this->github->updates[1]);
        self::assertStringContainsString('- [ ] #14', $this->github->updates[1]);
        self::assertEquals(new Cursor(103, 'etag-1'), $this->cursors->load(self::TASK));
    }

    public function testEventsTheCursorHasPassedAreNotReplayed(): void
    {
        $this->github->addEvent(new IssueEvent(100, IssueEvent::MILESTONED, 12, 'November 2026'));
        $this->github->addEvent(new IssueEvent(101, IssueEvent::MILESTONED, 13, 'November 2026'));
        $this->cursors->save(self::TASK, new Cursor(100));

        $this->sync()->run(self::TASK);

        self::assertStringNotContainsString('#12', $this->github->updates[1]);
        self::assertStringContainsString('#13', $this->github->updates[1]);
    }

    public function testReadsAsManyPagesAsItNeeds(): void
    {
        $this->github = new FakeGithub(perPage: 2);
        $this->github->addIssue('issue plan', new Issue(1, 'Issue plan November 2026', self::EMPTY_PLAN));
        $this->cursors->save(self::TASK, new Cursor(100));

        for ($id = 100; $id <= 105; $id++) {
            $this->github->addEvent(new IssueEvent($id, IssueEvent::MILESTONED, $id, 'November 2026'));
        }

        $this->sync()->run(self::TASK);

        self::assertCount(3, $this->github->eventPageCalls);

        foreach ([101, 102, 103, 104, 105] as $number) {
            self::assertStringContainsString('#' . $number, $this->github->updates[1]);
        }

        self::assertStringNotContainsString('#100', $this->github->updates[1]);
    }

    public function testWarnsWhenThereAreMoreNewEventsThanItMayRead(): void
    {
        $this->github = new FakeGithub(perPage: 2);
        $this->cursors->save(self::TASK, new Cursor(100));

        for ($id = 101; $id <= 110; $id++) {
            $this->github->addEvent(new IssueEvent($id, 'labeled', 1));
        }

        $this->sync(new SyncSettings(maxPages: 2))->run(self::TASK);

        self::assertCount(2, $this->github->eventPageCalls);
        self::assertContains('warning', array_column($this->log, 1));
        self::assertSame(110, $this->cursors->load(self::TASK)?->lastEventId);
    }

    public function testNothingHappensWhenGithubSaysNothingChanged(): void
    {
        $this->cursors->save(self::TASK, new Cursor(100, 'etag-1'));
        $this->github->unchangedSinceEtag = true;

        self::assertSame(0, $this->sync()->run(self::TASK));
        self::assertSame([[1, 'etag-1']], $this->github->eventPageCalls);
        self::assertSame([], $this->github->updates);
    }

    public function testADryRunWritesNothingAndKeepsTheCursor(): void
    {
        $this->cursors->save(self::TASK, new Cursor(100));
        $this->github->addEvent(new IssueEvent(101, IssueEvent::MILESTONED, 12, 'November 2026'));

        $changed = $this->sync(new SyncSettings(dryRun: true))->run(self::TASK);

        self::assertSame(2, $changed);
        self::assertSame([], $this->github->updates);
        self::assertEquals(new Cursor(100), $this->cursors->load(self::TASK));
    }

    public function testPullRequestsAndOverviewIssuesAreLeftOut(): void
    {
        $this->cursors->save(self::TASK, new Cursor(100));
        $this->github->addEvent(new IssueEvent(101, IssueEvent::MILESTONED, 40, 'November 2026', onPullRequest: true));
        $this->github->addEvent(new IssueEvent(102, IssueEvent::MILESTONED, 1, 'November 2026'));

        self::assertSame(0, $this->sync()->run(self::TASK));
        self::assertSame([], $this->github->updates);
    }

    public function testAPlanWithoutTheHeadingIsReportedAndLeftAlone(): void
    {
        $this->github = new FakeGithub();
        $this->github->addIssue('issue plan', new Issue(1, 'Issue plan November 2026', 'No list here'));
        $this->cursors->save(self::TASK, new Cursor(100));
        $this->github->addEvent(new IssueEvent(101, IssueEvent::MILESTONED, 12, 'November 2026'));

        $this->sync()->run(self::TASK);

        self::assertSame([], $this->github->updates);
        self::assertContains('warning', array_column($this->log, 1));
    }

    public function testAnEntryThatIsAlreadyThereIsNotWrittenAgain(): void
    {
        $this->github = new FakeGithub();
        $this->github->addIssue('issue plan', new Issue(1, 'Issue plan November 2026', "### Table of contents\n- [x] #12"));
        $this->cursors->save(self::TASK, new Cursor(100));
        $this->github->addEvent(new IssueEvent(101, IssueEvent::MILESTONED, 12, 'November 2026'));

        self::assertSame(0, $this->sync()->run(self::TASK));
        self::assertSame([], $this->github->updates);
    }

    private function sync(?SyncSettings $settings = null): ChecklistSync
    {
        return new ChecklistSync(
            $this->github,
            $this->cursors,
            $settings ?? new SyncSettings(),
            function (string $message, string $priority): void {
                $this->log[] = [$message, $priority];
            },
            new ChecklistEditor(),
            new TitleMatcher()
        );
    }
}
