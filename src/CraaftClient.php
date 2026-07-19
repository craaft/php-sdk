<?php

declare(strict_types=1);

namespace Craaft;

use Craaft\Exceptions\CraaftError;
use Craaft\Http\RetryConfig;
use Craaft\Http\Transport;
use Craaft\Resources\AttachmentsResource;
use Craaft\Resources\CardsResource;
use Craaft\Resources\ChecklistResource;
use Craaft\Resources\ColumnsResource;
use Craaft\Resources\CommentsResource;
use Craaft\Resources\MembersResource;
use Craaft\Resources\MeResource;
use Craaft\Resources\MilestonesResource;
use Craaft\Resources\ProjectsResource;

/**
 * Top-level synchronous client for the Craaft API.
 *
 * Wires the HTTP transport, applies token resolution and defaults, and
 * exposes resource sub-clients as readonly properties.
 */
final class CraaftClient
{
    public const DEFAULT_BASE_URL = 'https://craaft.io/api/v1';
    public const DEFAULT_TIMEOUT = 30.0;
    public const ENV_TOKEN = 'CRAAFT_API_TOKEN';
    public const ENV_BASE_URL = 'CRAAFT_BASE_URL';
    public const VERSION = '0.2.0';

    /** Hosts allowed to use plain http:// (development only). */
    private const PLAINTEXT_HOSTS = ['localhost', '127.0.0.1', '::1'];

    /** Matches the documented token format cra_<chars>. */
    private const TOKEN_RE = '/\Acra_[A-Za-z0-9_\-]+\z/';

    public readonly Transport $transport;
    public readonly MeResource $me;
    public readonly ProjectsResource $projects;
    public readonly CardsResource $cards;
    public readonly AttachmentsResource $attachments;
    public readonly CommentsResource $comments;
    public readonly ColumnsResource $columns;
    public readonly MembersResource $members;
    public readonly ChecklistResource $checklist;
    public readonly MilestonesResource $milestones;

    /**
     * @param ?RetryConfig $retry Pass null to use the default policy. Pass
     *                           `new RetryConfig(maxAttempts: 1)` to disable retries.
     * @param ?object      $http Internal hook: an alternative HTTP executor
     *                          (must implement `execute(method, url, headers, body, timeout, bodyLimit): HttpAttempt`).
     *                          Used by tests; production callers should leave this null.
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        float|array $timeout = self::DEFAULT_TIMEOUT,
        ?RetryConfig $retry = null,
        ?string $userAgent = null,
        private readonly ?object $http = null,
    ) {
        $envToken = getenv(self::ENV_TOKEN);
        $envToken = ($envToken === false || $envToken === '') ? null : $envToken;
        $rawToken = $apiKey ?? $envToken;
        if ($rawToken === null || $rawToken === '') {
            throw new CraaftError(
                'API key required: pass $apiKey or set the ' . self::ENV_TOKEN . ' environment variable.',
            );
        }
        $token = self::validateToken($rawToken);

        $envBaseUrl = getenv(self::ENV_BASE_URL);
        $envBaseUrl = ($envBaseUrl === false || $envBaseUrl === '') ? null : $envBaseUrl;
        $resolvedBaseUrl = self::resolveBaseUrl($baseUrl ?? $envBaseUrl);
        $ua = self::validateUserAgent($userAgent ?? ('craaft-php/' . self::VERSION));

        $retryConfig = $retry ?? new RetryConfig();

        $this->transport = new Transport(
            apiKey: $token,
            baseUrl: $resolvedBaseUrl,
            timeout: $timeout,
            retry: $retryConfig,
            userAgent: $ua,
            http: $this->http,
        );
        $this->me = new MeResource($this->transport);
        $this->projects = new ProjectsResource($this->transport);
        $this->cards = new CardsResource($this->transport);
        $this->attachments = new AttachmentsResource($this->transport);
        $this->comments = new CommentsResource($this->transport);
        $this->columns = new ColumnsResource($this->transport);
        $this->members = new MembersResource($this->transport);
        $this->checklist = new ChecklistResource($this->transport);
        $this->milestones = new MilestonesResource($this->transport);
    }

    public function close(): void
    {
        $this->transport->close();
    }

    public function __destruct()
    {
        $this->close();
    }

    private static function resolveBaseUrl(?string $baseUrl): string
    {
        $resolved = $baseUrl ?? self::DEFAULT_BASE_URL;
        $parsed = parse_url($resolved);
        if (!is_array($parsed) || empty($parsed['host'])) {
            throw new CraaftError("base_url must include a hostname: '{$resolved}'");
        }
        $scheme = $parsed['scheme'] ?? '';
        $host = $parsed['host'];
        if ($scheme === 'https') {
            return $resolved;
        }
        if ($scheme === 'http' && in_array($host, self::PLAINTEXT_HOSTS, true)) {
            return $resolved;
        }
        throw new CraaftError(
            'base_url must use https:// (got ' . ($scheme !== '' ? "'{$scheme}'" : 'none') . '). '
            . 'Plain http:// is only allowed for localhost during development.',
        );
    }

    private static function validateToken(string $token): string
    {
        $stripped = trim($token);
        if ($stripped === '') {
            throw new CraaftError('API key is empty.');
        }
        if (preg_match(self::TOKEN_RE, $stripped) !== 1) {
            throw new CraaftError(
                'API key does not match the expected format `cra_<chars>`. '
                . 'Check for stray whitespace or a copy-paste error.',
            );
        }
        return $stripped;
    }

    private static function validateUserAgent(string $userAgent): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $userAgent)) {
            throw new CraaftError('user_agent must not contain control characters.');
        }
        return $userAgent;
    }
}
