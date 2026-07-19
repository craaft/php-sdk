<?php

declare(strict_types=1);

namespace Craaft\Http;

use Craaft\Exceptions\ConnectionError;
use Craaft\Exceptions\TimeoutError;

/**
 * Default HTTP executor backed by ext-curl.
 *
 * Returns an {@see HttpAttempt} on any HTTP response (including 5xx). Throws
 * {@see TimeoutError} / {@see ConnectionError} on transport-level failures.
 */
final class CurlExecutor
{
    /** @var callable(int<0,max>): void */
    private $sleeper;

    /**
     * @param callable(int<0,max>): void|null $sleeper Unused here, kept for parity.
     */
    public function __construct(?callable $sleeper = null)
    {
        $this->sleeper = $sleeper ?? static fn(int $us): int => usleep($us) ?? 0;
    }

    /**
     * @param list<string>          $headers
     * @param float|array{0:int,1:float} $timeout
     */
    public function execute(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float|array $timeout,
        int $bodyLimit,
    ): HttpAttempt {
        $ch = curl_init();
        $respHeaders = '';
        $respBody = '';
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADERFUNCTION => static function ($_, string $header) use (&$respHeaders): int {
                $respHeaders .= $header;
                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => function ($_, string $chunk) use (&$respBody, $bodyLimit): int {
                if (strlen($respBody) + strlen($chunk) > $bodyLimit) {
                    return 0; // aborts with CURLE_WRITE_ERROR
                }
                $respBody .= $chunk;
                return strlen($chunk);
            },
            CURLOPT_CONNECTTIMEOUT => is_array($timeout) ? (int) $timeout[0] : (float) $timeout,
            CURLOPT_TIMEOUT => is_array($timeout) ? (float) $timeout[1] : (float) $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($errno === CURLE_WRITE_ERROR && strlen($respBody) >= $bodyLimit) {
            throw new \Craaft\Exceptions\CraaftError("response body exceeded maximum size of {$bodyLimit} bytes");
        }
        if ($errno !== 0) {
            $message = $error !== '' ? $error : "curl errno {$errno}";
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw new TimeoutError($message);
            }
            throw new ConnectionError($message);
        }

        return new HttpAttempt((int) $info['http_code'], $respHeaders, $respBody);
    }
}
