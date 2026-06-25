<?php

declare(strict_types=1);

namespace Indieinabox;

class HttpSignature
{
    /**
     * Signs a request for ActivityPub.
     *
     * @param string $keyId The URL to the public key (Actor profile ID#main-key)
     * @param string $privateKey The PEM formatted RSA private key
     * @param string $method HTTP Method (POST, GET)
     * @param string $url Target URL
     * @param string $body Request body (if any)
     * @param array $additionalHeaders Associative array of additional headers to include in signature
     * @return array Array containing headers to add to the request (Date, Host, Digest, Signature)
     */
    public static function sign(
        string $keyId,
        string $privateKey,
        string $method,
        string $url,
        string $body = '',
        array $additionalHeaders = []
    ): array {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'];
        $path = $parsedUrl['path'] ?? '/';
        if (isset($parsedUrl['query'])) {
            $path .= '?' . $parsedUrl['query'];
        }

        $date = gmdate('D, d M Y H:i:s T');
        
        $headers = [
            '(request-target)' => strtolower($method) . ' ' . $path,
            'host' => $host,
            'date' => $date,
        ];

        if ($body !== '') {
            $digest = 'SHA-256=' . base64_encode(hash('sha256', $body, true));
            $headers['digest'] = $digest;
        }

        foreach ($additionalHeaders as $k => $v) {
            $headers[strtolower($k)] = $v;
        }

        $signedHeaders = implode(' ', array_keys($headers));
        
        $signatureString = '';
        foreach ($headers as $k => $v) {
            if ($signatureString !== '') {
                $signatureString .= "\n";
            }
            $signatureString .= "$k: $v";
        }

        openssl_sign($signatureString, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $signatureBase64 = base64_encode($signature);

        $sigHeader = sprintf(
            'keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
            $keyId,
            $signedHeaders,
            $signatureBase64
        );

        $resultHeaders = [
            'Host' => $host,
            'Date' => $date,
            'Signature' => $sigHeader
        ];
        
        if (isset($headers['digest'])) {
            $resultHeaders['Digest'] = $headers['digest'];
        }

        return $resultHeaders;
    }

    /**
     * Verifies an incoming HTTP Signature.
     *
     * @param array $headers The incoming HTTP headers
     * @param string $method Request method
     * @param string $path Request path with query string
     * @param string $publicKey PEM formatted RSA public key
     * @return bool True if valid
     */
    public static function verify(array $headers, string $method, string $path, string $publicKey): bool
    {
        $signatureHeader = null;
        // Find Signature header case-insensitively
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'signature') {
                $signatureHeader = is_array($v) ? $v[0] : $v;
                break;
            }
        }

        if (!$signatureHeader) {
            return false;
        }

        preg_match_all('/([a-zA-Z0-9]+)="([^"]+)"/', $signatureHeader, $matches);
        $sigParts = array_combine($matches[1], $matches[2]);

        if (!isset($sigParts['signature']) || !isset($sigParts['headers'])) {
            return false;
        }

        $signedHeaders = explode(' ', $sigParts['headers']);
        $signatureString = '';

        foreach ($signedHeaders as $h) {
            if ($h === '(request-target)') {
                $v = strtolower($method) . ' ' . $path;
            } else {
                // Find header value case-insensitively
                $v = '';
                foreach ($headers as $hk => $hv) {
                    if (strtolower($hk) === strtolower($h)) {
                        $v = is_array($hv) ? $hv[0] : $hv;
                        break;
                    }
                }
                if ($v === '') {
                    return false; // required header missing
                }
            }

            if ($signatureString !== '') {
                $signatureString .= "\n";
            }
            $signatureString .= "$h: $v";
        }

        $signature = base64_decode($sigParts['signature']);
        $valid = openssl_verify($signatureString, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        return $valid === 1;
    }
}
