<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\MagazineChecklist\Tests\Support;

use Yepr\Plugin\Task\MagazineChecklist\Github\HttpResult;
use Yepr\Plugin\Task\MagazineChecklist\Github\HttpTransport;

/**
 * Answers requests from a queue, and records what was asked.
 */
final class ScriptedTransport implements HttpTransport
{
    /** @var list<HttpResult> */
    private array $answers = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string|null}> */
    public array $requests = [];

    /**
     * @param array<string, string> $headers
     */
    public function answer(int $status, mixed $json, array $headers = []): self
    {
        $this->answers[] = new HttpResult(
            $status,
            array_change_key_case($headers),
            $json === null ? '' : json_encode($json, JSON_THROW_ON_ERROR)
        );

        return $this;
    }

    public function request(string $method, string $url, array $headers, ?string $body = null): HttpResult
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        $answer = array_shift($this->answers);

        if ($answer === null) {
            throw new \LogicException('No answer scripted for ' . $method . ' ' . $url);
        }

        return $answer;
    }
}
