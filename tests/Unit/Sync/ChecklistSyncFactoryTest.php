<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\MagazineChecklist\Tests\Unit\Sync;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\MagazineChecklist\Checklist\ChecklistEditor;
use Yepr\Plugin\Task\MagazineChecklist\Checklist\TitleMatcher;
use Yepr\Plugin\Task\MagazineChecklist\Sync\ChecklistSyncFactory;
use Yepr\Plugin\Task\MagazineChecklist\Sync\Cursor;
use Yepr\Plugin\Task\MagazineChecklist\Sync\MissingConfiguration;
use Yepr\Plugin\Task\MagazineChecklist\Tests\Support\MemoryCursorStore;
use Yepr\Plugin\Task\MagazineChecklist\Tests\Support\ScriptedTransport;

/**
 * The factory turns a task's parameters into a sync on the injected services.
 * What it built is checked through what it does: the requests it sends.
 */
final class ChecklistSyncFactoryTest extends TestCase
{
    private ScriptedTransport $http;

    private MemoryCursorStore $cursors;

    private ChecklistSyncFactory $factory;

    protected function setUp(): void
    {
        $this->http    = new ScriptedTransport();
        $this->cursors = new MemoryCursorStore();
        $this->factory = new ChecklistSyncFactory($this->http, $this->cursors, new ChecklistEditor(), new TitleMatcher());
    }

    public function testUsesTheTasksRepositoryAndToken(): void
    {
        $this->http->answer(200, []);

        $this->factory->createForTask($this->params(), $this->ignoreLog(...))->run(1);

        self::assertStringStartsWith(
            'https://api.github.com/repos/HermanPeeren/magazine-fork/issues/events',
            $this->http->requests[0]['url']
        );
        self::assertSame('Bearer test-token', $this->http->requests[0]['headers']['Authorization']);
        self::assertEquals(new Cursor(0, null), $this->cursors->load(1));
    }

    public function testUsesTheLabelsFromTheTask(): void
    {
        $this->cursors->save(1, new Cursor(100));
        $this->http
            ->answer(200, [['id' => 101, 'event' => 'milestoned', 'issue' => ['number' => 12], 'milestone' => ['title' => 'May']]])
            ->answer(200, [])
            ->answer(200, []);

        $this->factory
            ->createForTask($this->params(['plan_label' => 'Plan', 'imagery_label' => 'Pictures']), $this->ignoreLog(...))
            ->run(1);

        self::assertStringContainsString('labels=Plan&', $this->http->requests[1]['url']);
        self::assertStringContainsString('labels=Pictures&', $this->http->requests[2]['url']);
    }

    public function testADryRunUnlessSwitchedOff(): void
    {
        $this->cursors->save(1, new Cursor(100));
        $this->http->answer(200, [['id' => 101, 'event' => 'labeled', 'issue' => ['number' => 12]]]);

        $params = $this->params();
        unset($params->dry_run);

        $this->factory->createForTask($params, $this->ignoreLog(...))->run(1);

        self::assertEquals(new Cursor(100), $this->cursors->load(1));
    }

    public function testRefusesATaskWithoutAToken(): void
    {
        $this->expectException(MissingConfiguration::class);

        $this->factory->createForTask($this->params(['token' => '  ']), $this->ignoreLog(...));
    }

    public function testRefusesATaskWithoutARepository(): void
    {
        $this->expectException(MissingConfiguration::class);

        $this->factory->createForTask((object) ['owner' => 'HermanPeeren', 'token' => 'x'], $this->ignoreLog(...));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function params(array $overrides = []): object
    {
        return (object) ($overrides + [
            'owner'         => 'HermanPeeren',
            'repository'    => 'magazine-fork',
            'token'         => 'test-token',
            'plan_label'    => 'issue plan',
            'imagery_label' => 'Imagery',
            'heading'       => '### Table of contents',
            'max_pages'     => 10,
            'dry_run'       => 0,
        ]);
    }

    private function ignoreLog(string $message, string $priority): void
    {
    }
}
