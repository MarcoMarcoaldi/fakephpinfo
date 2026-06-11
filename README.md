# FakePHPInfo

<img width="1536" height="1024" alt="image" src="https://github.com/user-attachments/assets/465ede86-0618-448b-9510-126ecb34a16d" />


> A drop-in `phpinfo()` wrapper that serves an **authentic-looking** PHP information
> page in which the server's real hostname and IP addresses are silently swapped
> for **honeypot/decoy values**.

[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
![PHP](https://img.shields.io/badge/PHP-%3E%3D5.4-777BB4.svg)

---

## What it does

`phpinfo.php` renders a fully functional `phpinfo()` page that is **identical to the
real one in every detail**, except that every occurrence of the server's real
hostname and IP addresses is replaced with fake values you configure.

The result looks completely authentic to an attacker performing reconnaissance,
but every identifying value points toward a **honeypot or decoy system** of your
choice instead of your real infrastructure.

## Why it exists

Attackers routinely hunt for exposed `phpinfo()` pages to harvest critical
intelligence about a target:

- the **real IP address** — useful to bypass CDN/WAF protections;
- the **hostname** — useful for lateral movement and DNS enumeration;
- other infrastructure details.

By serving a `phpinfo()` page where these values point to a honeypot, you can:

1. **Divert** attackers away from your real infrastructure.
2. **Lure** them into a monitored honeypot where their tools and techniques can be studied.
3. **Waste** their time and resources on a decoy target.
4. **Collect** threat intelligence (IPs, user agents, attack patterns).

## How it works

1. The script captures the full HTML output of the real `phpinfo()` function using
   PHP output buffering (`ob_start()` / `ob_get_clean()`).
2. It automatically detects all real identifying values:
   - system hostname via `gethostname()` and `php_uname('n')`;
   - server IP via `$_SERVER['SERVER_ADDR']`;
   - the IPv6-mapped representation (`::ffff:x.x.x.x`);
   - `SERVER_NAME` and `HTTP_HOST` values.
3. It builds a replacement map and performs a global string substitution. **Longer,
   more specific strings are replaced first** (sorted by length, descending) so that,
   for example, an IPv6-mapped address is not partially corrupted by the shorter
   IPv4 rule.
4. The modified HTML is sent to the browser. **Everything else in the `phpinfo()`
   output stays real and untouched.**

## What gets replaced

- The **"System"** line at the top (kernel hostname).
- `$_SERVER['SERVER_ADDR']` — server IP.
- `$_SERVER['SERVER_NAME']` — virtual host name.
- `$_SERVER['HTTP_HOST']` — Host header value.
- `$_SERVER['SERVER_PORT']` — optional, only if `$fake_port` is set.
- Any other occurrence of the real hostname or IP anywhere in the output.

## What stays real

- PHP version and build information.
- All loaded extensions and their configuration.
- `php.ini` directives (local and master values).
- File paths (`DOCUMENT_ROOT`, `include_path`, `error_log`, …).
- Environment variables (except hostname/IP occurrences).
- HTTP request headers (except `Host`).
- Everything else.

## Installation & usage

1. **Configure** the values at the top of `phpinfo.php`:

   ```php
   $fake_hostname = 'honeypot.example.com';
   $fake_ip       = '192.168.100.50';
   $fake_ipv6     = '::ffff:192.168.100.50';
   $fake_port     = null; // leave null to keep the real port
   ```

2. **Deploy** the file on your web server as `phpinfo.php` (or any name you prefer)
   in a location where attackers are likely to probe it (web root, `/info/`,
   `/debug/`, …).

3. *(Optional)* **Restrict** access via `.htaccess` or your web server config so that
   only specific conditions trigger the fake page — e.g. non-whitelisted IPs get the
   decoy, while your team sees the real `phpinfo()`.

### Configuration reference

| Variable         | Description                                                                 | Example                     |
|------------------|-----------------------------------------------------------------------------|-----------------------------|
| `$fake_hostname` | Hostname that replaces the real one. Set it to your honeypot's FQDN.         | `'honeypot.example.com'`    |
| `$fake_ip`       | IPv4 address that replaces the real one. Your honeypot's IP.                 | `'192.168.100.50'`          |
| `$fake_ipv6`     | IPv6-mapped form of the fake IP. Normally `::ffff:` + `$fake_ip`.            | `'::ffff:192.168.100.50'`   |
| `$fake_port`     | *(Optional)* Fake server port. Leave `null` to keep the real one (recommended). | `8080` or `null`        |

## Requirements

- PHP (works with PHP 5.4+ through 8.x).
- The `phpinfo()` function must not be disabled via `disable_functions`.

## Security considerations

- This tool is a **deception / threat-intelligence aid**, not a substitute for proper
  hardening. The single most effective measure is still **not exposing `phpinfo()`
  at all**. Use this only as an intentional, monitored decoy.
- All non-identifying data (PHP version, extensions, paths, directives) remains
  **genuine** and is still disclosed. Make sure that is acceptable in your threat model.
- Replacement is a plain string substitution. If your real hostname is a very short
  or common substring, review the output to ensure no unrelated text is altered.

## License

Released under the **GNU General Public License v2.0 or later** (GPL-2.0-or-later).
See the license header in `phpinfo.php` for details.

## Author

**Managed Server Srl** — © 2026
