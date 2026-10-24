<?php
declare(strict_types=1);

namespace Mova\AI;

/**
 * Minimal HTTP client for the AI gateway. Prefers ext-curl (own CA bundle handling, proxy env,
 * reliable timeouts) and falls back to PHP streams when curl is not installed, so shared hosts
 * work either way. TLS verification is always on.
 */
final class AiHttp
{
    /**
     * @param list<string> $headers "Name: value" lines
     * @return array{status:int, body:string|false, error:string, headers:array<string,string>}
     */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 30): array
    {
        $timeout = max(1, $timeout);
        if (function_exists('curl_init')) {
            return self::viaCurl($method, $url, $headers, $body, $timeout);
        }
        return self::viaStream($method, $url, $headers, $body, $timeout);
    }

    private static function viaCurl(string $method, string $url, array $headers, ?string $body, int $timeout): array
    {
        $respHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$respHeaders): int {
                if (preg_match('#^HTTP/\S+\s+\d{3}#', $line)) {
                    $respHeaders = [];
                } elseif (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $respHeaders[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = $raw === false ? (curl_error($ch) ?: 'connection failed') : '';
        curl_close($ch);
        return ['status' => $status, 'body' => $raw === false ? false : (string) $raw, 'error' => $err, 'headers' => $respHeaders];
    }

    private static function viaStream(string $method, string $url, array $headers, ?string $body, int $timeout): array
    {
        $opts = [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headers),
            'timeout' => $timeout,
            'ignore_errors' => true,
        ];
        if ($body !== null) {
            $opts['content'] = $body;
        }
        $ctx = stream_context_create(['http' => $opts, 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $http_response_header = [];
        $raw = @file_get_contents($url, false, $ctx);
        $status = 0;
        $hdrs = [];
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $mm)) {
                $status = (int) $mm[1];
                $hdrs = [];
            } elseif (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $hdrs[strtolower(trim($k))] = trim($v);
            }
        }
        $err = '';
        if ($raw === false) {
            $e = error_get_last();
            $err = is_array($e) && !empty($e['message'])
                ? (string) preg_replace('/^file_get_contents\([^)]*\):\s*/', '', $e['message'])
                : 'connection failed';
        }
        return ['status' => $status, 'body' => $raw, 'error' => $err, 'headers' => $hdrs];
    }
}
