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
 * GithubGateway on GitHub's REST API, with a personal access token.
 *
 * A GitHub App would only change where the token comes from: an installation
 * token is sent in the same Authorization header.
 *
 * @see  https://docs.github.com/en/rest/issues/events
 * @see  https://docs.github.com/en/rest/issues/issues
 */
final class HttpGithubGateway implements GithubGateway
{
	private const PER_PAGE = 100;

	public function __construct(
		private readonly HttpTransport $http,
		private readonly string $owner,
		private readonly string $repository,
		#[\SensitiveParameter]
		private readonly string $token,
		private readonly string $apiBase = 'https://api.github.com'
	) {
	}

	public function eventPage(int $page, ?string $etag = null): EventPage
	{
		$headers = [];

		// Only the first page is asked conditionally: that is the page whose
		// ETag was kept, and a new event always lands on it.
		if ($page === 1 && $etag !== null && $etag !== '') {
			$headers['If-None-Match'] = $etag;
		}

		$result = $this->send('GET', $this->repositoryUrl('/issues/events', ['page' => $page]), $headers);

		if ($result->status === 304) {
			return EventPage::notModified($etag);
		}

		$events = array_map(
			static fn (array $item): IssueEvent => IssueEvent::fromApi($item),
			$this->decodeList($result)
		);

		return new EventPage($events, $this->hasNextPage($result), $result->header('etag'));
	}

	public function openIssuesWithLabel(string $label): array
	{
		$issues = [];
		$page   = 1;

		do {
			$result = $this->send(
				'GET',
				$this->repositoryUrl('/issues', ['state' => 'open', 'labels' => $label, 'page' => $page])
			);

			foreach ($this->decodeList($result) as $item) {
				$issue = Issue::fromApi($item);

				// The issues endpoint lists pull requests too.
				if (!$issue->isPullRequest) {
					$issues[] = $issue;
				}
			}

			$page++;
		} while ($this->hasNextPage($result));

		return $issues;
	}

	public function updateIssueBody(int $number, string $body): void
	{
		$this->send(
			'PATCH',
			$this->repositoryUrl('/issues/' . $number),
			['Content-Type' => 'application/json'],
			json_encode(['body' => $body], JSON_THROW_ON_ERROR)
		);
	}

	/**
	 * @param   array<string, int|string>  $query
	 */
	private function repositoryUrl(string $path, array $query = []): string
	{
		$url = rtrim($this->apiBase, '/')
			. '/repos/' . rawurlencode($this->owner) . '/' . rawurlencode($this->repository)
			. $path;

		if ($query === []) {
			return $url;
		}

		$query += ['per_page' => self::PER_PAGE];

		return $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * @param   array<string, string>  $headers
	 *
	 * @throws  GithubException  On any answer but 2xx or 304.
	 */
	private function send(string $method, string $url, array $headers = [], ?string $body = null): HttpResult
	{
		$headers += [
			'Accept'               => 'application/vnd.github+json',
			'Authorization'        => 'Bearer ' . $this->token,
			'X-GitHub-Api-Version' => '2022-11-28',
			// GitHub refuses requests without one.
			'User-Agent'           => 'magazine-checklist-sync',
		];

		$result = $this->http->request($method, $url, $headers, $body);

		if ($result->status === 304 || ($result->status >= 200 && $result->status < 300)) {
			return $result;
		}

		$message = '';
		$decoded = json_decode($result->body, true);

		if (\is_array($decoded) && isset($decoded['message'])) {
			$message = (string) $decoded['message'];
		}

		throw new GithubException(
			\sprintf('GitHub answered %d to %s %s%s', $result->status, $method, $url, $message === '' ? '' : ': ' . $message),
			$result->status
		);
	}

	/**
	 * @return  list<array<string, mixed>>
	 */
	private function decodeList(HttpResult $result): array
	{
		$decoded = json_decode($result->body, true);

		if (!\is_array($decoded) || !array_is_list($decoded)) {
			throw new GithubException('GitHub sent something that is not a list: ' . substr($result->body, 0, 200));
		}

		return array_values(array_filter($decoded, 'is_array'));
	}

	/**
	 * Whether the Link header points at a next page.
	 */
	private function hasNextPage(HttpResult $result): bool
	{
		return str_contains((string) $result->header('link'), 'rel="next"');
	}
}
