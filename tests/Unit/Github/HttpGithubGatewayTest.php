<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\MagazineChecklist\Tests\Unit\Github;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\MagazineChecklist\Github\GithubException;
use Yepr\Plugin\Task\MagazineChecklist\Github\HttpGithubGateway;
use Yepr\Plugin\Task\MagazineChecklist\Github\IssueEvent;
use Yepr\Plugin\Task\MagazineChecklist\Tests\Support\ScriptedTransport;

final class HttpGithubGatewayTest extends TestCase
{
    private ScriptedTransport $http;

    private HttpGithubGateway $gateway;

    protected function setUp(): void
    {
        $this->http    = new ScriptedTransport();
        $this->gateway = new HttpGithubGateway($this->http, 'joomla', 'magazine articles', 'secret-token');
    }

    public function testReadsAnEventPage(): void
    {
        $this->http->answer(200, [
            [
                'id'        => 502,
                'event'     => 'milestoned',
                'issue'     => ['number' => 12],
                'milestone' => ['title' => 'November 2026'],
            ],
            ['id' => 501, 'event' => 'labeled', 'issue' => ['number' => 12]],
        ], ['ETag' => 'W/"abc"', 'Link' => '<https://api.github.com/x?page=2>; rel="next"']);

        $page = $this->gateway->eventPage(1);

        self::assertCount(2, $page->events);
        self::assertSame(502, $page->events[0]->id);
        self::assertSame(IssueEvent::MILESTONED, $page->events[0]->type);
        self::assertSame(12, $page->events[0]->issueNumber);
        self::assertSame('November 2026', $page->events[0]->milestoneTitle);
        self::assertTrue($page->hasNext);
        self::assertSame('W/"abc"', $page->etag);
        self::assertFalse($page->notModified);
    }

    public function testSendsTheTokenAndTheApiVersion(): void
    {
        $this->http->answer(200, []);

        $this->gateway->eventPage(1);

        $request = $this->http->requests[0];
        self::assertSame('GET', $request['method']);
        self::assertSame(
            'https://api.github.com/repos/joomla/magazine%20articles/issues/events?page=1&per_page=100',
            $request['url']
        );
        self::assertSame('Bearer secret-token', $request['headers']['Authorization']);
        self::assertSame('2022-11-28', $request['headers']['X-GitHub-Api-Version']);
        self::assertArrayHasKey('User-Agent', $request['headers']);
        self::assertArrayNotHasKey('If-None-Match', $request['headers']);
    }

    public function testAsksTheFirstPageConditionally(): void
    {
        $this->http->answer(304, null);

        $page = $this->gateway->eventPage(1, 'W/"abc"');

        self::assertSame('W/"abc"', $this->http->requests[0]['headers']['If-None-Match']);
        self::assertTrue($page->notModified);
        self::assertSame('W/"abc"', $page->etag);
    }

    public function testListsOpenIssuesAcrossPagesWithoutPullRequests(): void
    {
        $this->http
            ->answer(200, [
                ['number' => 3, 'title' => 'Issue plan November 2026', 'body' => '### Table of contents'],
                ['number' => 4, 'title' => 'A pull request', 'body' => '', 'pull_request' => ['url' => 'x']],
            ], ['Link' => '<https://api.github.com/x?page=2>; rel="next"'])
            ->answer(200, [
                ['number' => 9, 'title' => 'Issue plan December 2026', 'body' => null],
            ]);

        $issues = $this->gateway->openIssuesWithLabel('issue plan');

        self::assertSame([3, 9], array_map(static fn ($issue) => $issue->number, $issues));
        self::assertSame('', $issues[1]->body);
        self::assertStringContainsString('state=open&labels=issue%20plan&page=1', $this->http->requests[0]['url']);
        self::assertStringContainsString('page=2', $this->http->requests[1]['url']);
    }

    public function testUpdatesABody(): void
    {
        $this->http->answer(200, ['number' => 3]);

        $this->gateway->updateIssueBody(3, "### Table of contents\n- [ ] #12");

        $request = $this->http->requests[0];
        self::assertSame('PATCH', $request['method']);
        self::assertSame('https://api.github.com/repos/joomla/magazine%20articles/issues/3', $request['url']);
        self::assertSame(['body' => "### Table of contents\n- [ ] #12"], json_decode((string) $request['body'], true));
    }

    public function testReportsGithubsReasonForARefusal(): void
    {
        $this->http->answer(401, ['message' => 'Bad credentials']);

        $this->expectException(GithubException::class);
        $this->expectExceptionMessage('Bad credentials');
        $this->expectExceptionCode(401);

        $this->gateway->eventPage(1);
    }

    public function testRefusesAnAnswerThatIsNotAList(): void
    {
        $this->http->answer(200, ['message' => 'Not a list']);

        $this->expectException(GithubException::class);

        $this->gateway->openIssuesWithLabel('Imagery');
    }
}
