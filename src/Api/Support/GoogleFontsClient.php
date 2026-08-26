<?php

declare (strict_types=1);
namespace JooosiFon\Api\Support;

final class GoogleFontsClient
{
    public function fetchCss(string $url, string $userAgent): string
    {
        $host = strtolower((string) wp_parse_url($url, \PHP_URL_HOST));
        if (wp_parse_url($url, \PHP_URL_SCHEME) !== 'https' || $host !== 'fonts.googleapis.com') {
            throw new \InvalidArgumentException('The Google Fonts stylesheet URL is invalid.');
        }
        $response = wp_safe_remote_get($url, ['headers' => ['User-Agent' => $userAgent], 'redirection' => 2, 'timeout' => 15, 'limit_response_size' => \MB_IN_BYTES]);
        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }
        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            throw new \RuntimeException(sprintf('Google Fonts returned HTTP %d.', $status));
        }
        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || trim($body) === '') {
            throw new \RuntimeException('Google Fonts returned an empty stylesheet.');
        }
        return $body;
    }
}
