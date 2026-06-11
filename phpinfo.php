<?php
/**
 * FakePHPInfo
 * Copyright (C) 2026 Managed Server Srl
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 * ---------------------------------------------------------------------------
 *
 * FakePHPInfo - Honeypot-redirecting phpinfo() wrapper
 *
 * WHAT IT DOES
 * ------------
 * This script renders a fully functional phpinfo() page that is identical
 * to the real one in every detail, except that all occurrences of the
 * server's real hostname and IP addresses are replaced with fake values
 * that you configure. The result is a page that looks completely authentic
 * to an attacker performing reconnaissance, but silently redirects them
 * toward a honeypot or decoy system of your choice.
 *
 * WHY IT EXISTS
 * -------------
 * Attackers routinely look for exposed phpinfo() pages to gather critical
 * intelligence about a target: the server's real IP address (useful to
 * bypass CDN/WAF protections), the hostname (useful for lateral movement
 * and DNS enumeration), and other infrastructure details. By serving a
 * phpinfo() page where these values point to a honeypot, you can:
 *
 *   1. Divert attackers away from your real infrastructure.
 *   2. Lure them into a monitored honeypot where their techniques and
 *      tools can be studied.
 *   3. Waste their time and resources on a decoy target.
 *   4. Collect threat intelligence (IPs, user agents, attack patterns).
 *
 * HOW IT WORKS
 * ------------
 * 1. The script captures the full HTML output of the real phpinfo()
 *    function using PHP's output buffering (ob_start / ob_get_clean).
 *
 * 2. It automatically detects all real identifying values:
 *    - System hostname via gethostname() and php_uname('n')
 *    - Server IP via $_SERVER['SERVER_ADDR']
 *    - IPv6-mapped representation (::ffff:x.x.x.x)
 *    - SERVER_NAME and HTTP_HOST values
 *
 * 3. It builds a replacement map and performs a global string substitution,
 *    replacing every occurrence of the real values with the configured
 *    fake (honeypot) values. Longer strings are replaced first to avoid
 *    partial-match corruption (e.g., an IPv6-mapped address won't be
 *    partially replaced by the shorter IPv4 rule).
 *
 * 4. The modified HTML is sent to the browser. Everything else in the
 *    phpinfo() output (PHP version, loaded modules, configuration
 *    directives, environment variables, file paths, etc.) remains
 *    completely real and untouched.
 *
 * WHAT GETS REPLACED
 * ------------------
 *   - The "System" line at the top (kernel hostname)
 *   - $_SERVER['SERVER_ADDR']     (server IP)
 *   - $_SERVER['SERVER_NAME']     (virtual host name)
 *   - $_SERVER['HTTP_HOST']       (Host header value)
 *   - $_SERVER['SERVER_PORT']     (optional, if $fake_port is set)
 *   - Any other occurrence of the real hostname or IP anywhere in the
 *     phpinfo() output (e.g., in loaded configuration paths if the
 *     hostname appears there)
 *
 * WHAT STAYS REAL
 * ---------------
 *   - PHP version and build information
 *   - All loaded extensions and their configuration
 *   - php.ini directives (local and master values)
 *   - File paths (DOCUMENT_ROOT, include_path, error_log, etc.)
 *   - Environment variables (except hostname/IP occurrences)
 *   - HTTP request headers (except Host)
 *   - Everything else
 *
 * HOW TO USE
 * ----------
 * 1. Edit the CONFIGURATION section below. Set $fake_hostname and
 *    $fake_ip to the values of your honeypot system.
 *
 * 2. Place this file on your web server as "phpinfo.php" (or any name
 *    you prefer) in a location where attackers are likely to find it
 *    (e.g., web root, /info/, /debug/, etc.).
 *
 * 3. Optionally, restrict access to this file via .htaccess or your
 *    web server configuration so that only specific conditions trigger
 *    the fake version (e.g., non-whitelisted IPs get the fake page,
 *    while your team sees the real one).
 *
 * CONFIGURATION VARIABLES
 * -----------------------
 *   $fake_hostname  - The hostname that will replace the real one.
 *                     Set this to your honeypot's FQDN.
 *                     Example: 'honeypot.example.com'
 *
 *   $fake_ip        - The IPv4 address that will replace the real one.
 *                     Set this to your honeypot's IP address.
 *                     Example: '192.168.100.50'
 *
 *   $fake_ipv6      - The IPv6-mapped representation of the fake IP.
 *                     Normally this should be '::ffff:' followed by
 *                     $fake_ip. Only change it if your honeypot has
 *                     a different IPv6 address.
 *                     Example: '::ffff:192.168.100.50'
 *
 *   $fake_port      - (Optional) A fake server port number. Set to null
 *                     to keep the real port visible (recommended in most
 *                     cases since changing the port may look suspicious).
 *                     Example: 8080 or null
 *
 * @package    FakePHPInfo
 * @author     Managed Server Srl
 * @copyright  2026 Managed Server Srl
 * @license    GPL-2.0-or-later
 * @version    1.0.0
 */

// ============================================================
// CONFIGURATION - Set your fake (honeypot) values here
// ============================================================

$fake_hostname = 'honeypot.example.com';
$fake_ip       = '192.168.100.50';
$fake_ipv6     = '::ffff:192.168.100.50';

// Optional: fake server port (leave null to keep the real one)
$fake_port     = null;

// ============================================================
// END CONFIGURATION
// ============================================================

$real_hostname = gethostname();
$real_ip       = $_SERVER['SERVER_ADDR'] ?? gethostbyname($real_hostname);
$real_ipv6     = '::ffff:' . $real_ip;
$real_server_name = $_SERVER['SERVER_NAME'] ?? $real_hostname;
$real_http_host   = $_SERVER['HTTP_HOST'] ?? $real_hostname;

// Strip port from HTTP_HOST for replacement
$real_http_host_no_port = preg_replace('/:\d+$/', '', $real_http_host);

ob_start();
phpinfo();
$output = ob_get_clean();

// Build replacement pairs: real => fake (order matters - longest/most specific first)
$replacements = [];

// IPv6-mapped addresses first (more specific)
if ($real_ipv6 !== $fake_ipv6) {
    $replacements[$real_ipv6] = $fake_ipv6;
}

// Real IP address
if ($real_ip !== $fake_ip) {
    $replacements[$real_ip] = $fake_ip;
}

// Hostnames
if ($real_hostname !== $fake_hostname) {
    $replacements[$real_hostname] = $fake_hostname;
}

if ($real_server_name !== $fake_hostname && $real_server_name !== $real_hostname) {
    $replacements[$real_server_name] = $fake_hostname;
}

if ($real_http_host_no_port !== $fake_hostname
    && $real_http_host_no_port !== $real_hostname
    && $real_http_host_no_port !== $real_server_name) {
    $replacements[$real_http_host_no_port] = $fake_hostname;
}

// Also catch the FQDN that php_uname('n') might return (can differ from gethostname)
$uname_hostname = php_uname('n');
if ($uname_hostname !== $real_hostname && $uname_hostname !== $fake_hostname) {
    $replacements[$uname_hostname] = $fake_hostname;
}

// Replace port if configured
if ($fake_port !== null) {
    $real_port = $_SERVER['SERVER_PORT'] ?? '80';
    if ($real_port !== (string)$fake_port) {
        $replacements['"SERVER_PORT"' ] = '"SERVER_PORT"';
        $output = preg_replace(
            '/(SERVER_PORT[^>]*>)' . preg_quote($real_port, '/') . '(<)/',
            '${1}' . $fake_port . '${2}',
            $output
        );
    }
}

// Sort by key length descending so longer strings get replaced first
uksort($replacements, function ($a, $b) {
    return strlen($b) - strlen($a);
});

$output = str_replace(
    array_keys($replacements),
    array_values($replacements),
    $output
);

echo $output;
