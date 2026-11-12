<?php
declare(strict_types=1);

/**
 * JSON POST helper for AI providers.
 * Uses the cURL extension when it loads. This XAMPP Apache cannot load
 * php_curl.dll, so the request is handed to curl.exe when that program exists.
 * Otherwise HTTPS falls back to PHP streams.
 */
final class HttpClient
{
    /**
     * @param list<string> $headers
     * @return array{body:string,http_code:int,error:string}
     */
    public static function post(string $url, string $body, array $headers, int $timeout): array
    {
        $timeout = max(1, $timeout);
        // Apache's default 30s limit was killing Gemini course-plan calls and
        // dumping an HTML fatal error into the JSON response.
        if (function_exists('set_time_limit')) {
            @set_time_limit($timeout + 20);
        }
        @ini_set('default_socket_timeout', (string)$timeout);
        if (function_exists('curl_init')) {
            return self::postCurl($url, $body, $headers, $timeout);
        }
        $curlExe = self::curlExecutable();
        if ($curlExe !== '') {
            return self::postCurlExe($curlExe, $url, $body, $headers, $timeout);
        }
        return self::postStream($url, $body, $headers, $timeout);
    }

    /**
     * @param list<string> $headers
     * @return array{body:string,http_code:int,error:string}
     */
    private static function postCurl(string $url, string $body, array $headers, int $timeout): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['body' => '', 'http_code' => 0, 'error' => 'Could not start the HTTP request.'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array_merge($headers, ['Expect:']),
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $ca = self::caBundle();
        if ($ca !== '') {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        }
        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno) {
            return [
                'body' => '',
                'http_code' => $code,
                'error' => $err !== '' ? $err : 'Network error',
            ];
        }
        return ['body' => (string)$resp, 'http_code' => $code, 'error' => ''];
    }

    /**
     * @param list<string> $headers
     * @return array{body:string,http_code:int,error:string}
     */
    private static function postStream(string $url, string $body, array $headers, int $timeout): array
    {
        if (!ini_get('allow_url_fopen')) {
            return [
                'body' => '',
                'http_code' => 0,
                'error' => 'PHP cURL is not enabled, and allow_url_fopen is off, so the AI request cannot be sent.',
            ];
        }

        $headerLines = array_values(array_filter($headers, static fn($h) => trim((string)$h) !== ''));
        $hasLength = false;
        foreach ($headerLines as $line) {
            if (stripos($line, 'Content-Length:') === 0) {
                $hasLength = true;
                break;
            }
        }
        if (!$hasLength) {
            $headerLines[] = 'Content-Length: ' . strlen($body);
        }
        // Empty Expect stops PHP from waiting on 100-continue, which shows up
        // as "HTTP request failed!" on Windows when the provider never sends it.
        $headerLines[] = 'Expect:';
        $headerLines[] = 'Connection: close';

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines) . "\r\n",
                'content' => $body,
                'timeout' => max(1, $timeout),
                'ignore_errors' => true,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $started = hrtime(true);
        $resp = @file_get_contents($url, false, $context);
        $elapsed = (hrtime(true) - $started) / 1e9;
        // Retry only a fast connection drop. A long timeout should not be sent twice.
        if ($resp === false && $elapsed < 8) {
            $resp = @file_get_contents($url, false, $context);
        }
        $code = 0;
        if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
            if (preg_match('#\s(\d{3})\s#', (string)$http_response_header[0], $match)) {
                $code = (int)$match[1];
            }
        }
        if ($resp === false) {
            $last = error_get_last();
            $detail = is_array($last) ? (string)($last['message'] ?? '') : '';
            if ($detail !== '') {
                error_log('AI provider stream failed: ' . preg_replace('/https?:\/\/\S+/i', '[url]', $detail));
            }
            return [
                'body' => '',
                'http_code' => $code,
                'error' => 'The AI provider could not be reached. Please try again.',
            ];
        }
        return ['body' => $resp, 'http_code' => $code, 'error' => ''];
    }

    private static function curlExecutable(): string
    {
        $candidates = [];
        $env = getenv('PPAI_CURL');
        if (is_string($env) && $env !== '') {
            $candidates[] = $env;
        }
        $candidates[] = 'C:\\xampp\\apache\\bin\\curl.exe';
        $candidates[] = '/usr/bin/curl';
        $candidates[] = '/usr/local/bin/curl';
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return '';
    }

    private static function caBundle(): string
    {
        $candidates = [
            (string)ini_get('curl.cainfo'),
            (string)ini_get('openssl.cafile'),
            'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
        ];
        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }
        return '';
    }

    /**
     * Apache on this XAMPP install cannot load php_curl.dll. curl.exe can.
     *
     * @param list<string> $headers
     * @return array{body:string,http_code:int,error:string}
     */
    private static function postCurlExe(string $curlExe, string $url, string $body, array $headers, int $timeout): array
    {
        // An empty Expect header stops curl from waiting on 100-continue.
        // Bodies over 1024 bytes were stalling until max-time with 0 bytes received.
        $command = [$curlExe, '--silent', '--show-error', '--http1.1', '--header', 'Expect:', '--max-time', (string)$timeout];
        $ca = self::caBundle();
        if ($ca !== '') {
            $command[] = '--cacert';
            $command[] = $ca;
        }
        foreach ($headers as $header) {
            $header = trim($header);
            if ($header === '') {
                continue;
            }
            $command[] = '--header';
            $command[] = $header;
        }
        $bodyFile = tempnam(sys_get_temp_dir(), 'ppai');
        if ($bodyFile === false) {
            return ['body' => '', 'http_code' => 0, 'error' => 'The AI provider could not be reached. Please try again.'];
        }
        file_put_contents($bodyFile, $body);
        $command[] = '--data-binary';
        $command[] = '@' . str_replace('\\', '/', $bodyFile);
        $command[] = '--write-out';
        $command[] = "\n%{http_code}";
        $command[] = $url;

        $pipes = [];
        $proc = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            @unlink($bodyFile);
            return ['body' => '', 'http_code' => 0, 'error' => 'The AI provider could not be reached. Please try again.'];
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $status = ['exitcode' => 1, 'running' => true];
        $deadline = microtime(true) + $timeout + 5;
        while (microtime(true) < $deadline) {
            $stdout .= (string)stream_get_contents($pipes[1]);
            $stderr .= (string)stream_get_contents($pipes[2]);
            $status = proc_get_status($proc);
            if (!$status['running']) {
                $stdout .= (string)stream_get_contents($pipes[1]);
                $stderr .= (string)stream_get_contents($pipes[2]);
                break;
            }
            usleep(20000);
        }
        if ($status['running']) {
            proc_terminate($proc);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $exit = (int)$status['exitcode'];
        @unlink($bodyFile);

        $code = 0;
        if (preg_match('/\n(\d{3})\s*$/', $stdout, $match)) {
            $code = (int)$match[1];
            $stdout = substr($stdout, 0, -strlen($match[0]));
        }
        if ($exit !== 0 && $stdout === '') {
            $detail = trim(preg_replace('/https?:\/\/\S+/i', '[url]', $stderr) ?? '');
            error_log('AI provider curl failed: exit ' . $exit . ($detail !== '' ? ' ' . $detail : ''));
            $timedOut = $exit === 28 || str_contains(strtolower($detail), 'timed out');
            return [
                'body' => '',
                'http_code' => $code,
                'error' => $timedOut
                    ? 'The AI provider request timed out. Please try again.'
                    : 'The AI provider could not be reached. Please try again.',
            ];
        }
        return ['body' => $stdout, 'http_code' => $code, 'error' => ''];
    }
}
