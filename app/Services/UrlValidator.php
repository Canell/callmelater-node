<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class UrlValidator
{
    /**
     * Validate a URL for safe HTTP calls.
     *
     * @throws \InvalidArgumentException if URL is not safe
     */
    public function validate(string $url): void
    {
        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['host'])) {
            throw new \InvalidArgumentException('Invalid URL format');
        }

        $host = $parsed['host'];
        $scheme = $parsed['scheme'] ?? 'http';

        // Only allow HTTP and HTTPS
        if (! in_array($scheme, ['http', 'https'])) {
            throw new \InvalidArgumentException('Only HTTP and HTTPS protocols are allowed');
        }

        // Check blocked hostnames
        if ($this->isBlockedHost($host)) {
            throw new \InvalidArgumentException('This hostname is not allowed');
        }

        // Resolve hostname to IP and check
        if (config('callmelater.http.block_private_ips', true)) {
            $this->validateHostIp($host);
        }
    }

    /**
     * Check if a hostname is in the blocked list.
     */
    private function isBlockedHost(string $host): bool
    {
        $blockedHosts = config('callmelater.http.blocked_hosts', []);

        foreach ($blockedHosts as $blocked) {
            // Exact match
            if ($host === $blocked) {
                return true;
            }

            // Wildcard match (*.local)
            if (str_starts_with($blocked, '*.')) {
                $suffix = substr($blocked, 1); // .local
                if (str_ends_with($host, $suffix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolve hostname and validate the IP address.
     *
     * @throws \InvalidArgumentException if IP is private/blocked
     */
    private function validateHostIp(string $host): void
    {
        // Check if host is already an IP
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isBlockedIp($host)) {
                throw new \InvalidArgumentException('Requests to private IP addresses are not allowed');
            }
            return;
        }

        // Resolve hostname
        $ips = gethostbynamel($host);

        if ($ips === false || empty($ips)) {
            throw new \InvalidArgumentException('Unable to resolve hostname');
        }

        foreach ($ips as $ip) {
            if ($this->isBlockedIp($ip)) {
                Log::warning('Blocked HTTP call to private IP', [
                    'host' => $host,
                    'resolved_ip' => $ip,
                ]);
                throw new \InvalidArgumentException('Requests to private IP addresses are not allowed');
            }
        }
    }

    /**
     * Check if an IP address is in a blocked range.
     */
    private function isBlockedIp(string $ip): bool
    {
        $blockedRanges = config('callmelater.http.blocked_ranges', []);

        foreach ($blockedRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP is within a CIDR range.
     */
    private function ipInRange(string $ip, string $cidr): bool
    {
        // Handle IPv6
        if (str_contains($cidr, ':')) {
            return $this->ipv6InRange($ip, $cidr);
        }

        // IPv4
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$range, $netmask] = explode('/', $cidr, 2);
        $netmask = (int) $netmask;

        $rangeDecimal = ip2long($range);
        $ipDecimal = ip2long($ip);

        if ($rangeDecimal === false || $ipDecimal === false) {
            return false;
        }

        $wildcardDecimal = (1 << (32 - $netmask)) - 1;
        $netmaskDecimal = ~$wildcardDecimal;

        return ($ipDecimal & $netmaskDecimal) === ($rangeDecimal & $netmaskDecimal);
    }

    /**
     * Check if an IPv6 address is within a CIDR range.
     */
    private function ipv6InRange(string $ip, string $cidr): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false;
        }

        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$range, $netmask] = explode('/', $cidr, 2);
        $netmask = (int) $netmask;

        $ipBin = inet_pton($ip);
        $rangeBin = inet_pton($range);

        if ($ipBin === false || $rangeBin === false) {
            return false;
        }

        // Compare the first $netmask bits
        $fullBytes = intdiv($netmask, 8);
        $remainingBits = $netmask % 8;

        // Compare full bytes
        for ($i = 0; $i < $fullBytes; $i++) {
            if ($ipBin[$i] !== $rangeBin[$i]) {
                return false;
            }
        }

        // Compare remaining bits
        if ($remainingBits > 0 && $fullBytes < strlen($ipBin)) {
            $mask = 0xFF << (8 - $remainingBits);
            if ((ord($ipBin[$fullBytes]) & $mask) !== (ord($rangeBin[$fullBytes]) & $mask)) {
                return false;
            }
        }

        return true;
    }
}
