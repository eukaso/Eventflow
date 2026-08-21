# Sprint 12 staging environment and WordPress composition

## Required target

Use a production-like, non-public staging WordPress installation owned by the deployment team. Install the exact approved artifact and verify its adjacent IMP-093 manifest before activation. Use synthetic or separately approved minimized data only.

The target must provide:

- PHP 8.2 or newer and WordPress 6.5 or newer;
- MySQL 8.0+ or MariaDB 10.11+, default InnoDB, and `utf8mb4`;
- verified HTTPS at the WordPress home URL;
- readable immutable plugin files;
- an existing writable, non-symlink protected export directory outside `ABSPATH`;
- WordPress cron, or a separately configured external scheduler when `DISABLE_WP_CRON` is true;
- secrets injected outside Git and browser-localized configuration.

## Non-secret configuration declarations

Set these declarations in deployment-managed configuration outside the plugin repository. Values below are examples; do not commit host paths or secrets:

```php
define('EVENTFLOW_ENV', 'staging');
define('EVENTFLOW_DEBUG', false);
define('EVENTFLOW_PROTECTED_EXPORT_DIR', '/deployment-managed/path/outside/web-root');
define('EVENTFLOW_SECRETS_EXTERNAL', true);
```

When WordPress cron is deliberately disabled, also declare `EVENTFLOW_EXTERNAL_CRON` as `true` only after the external scheduler is configured. This declaration proves a prerequisite, not successful worker execution; worker leases/cadence are tested in IMP-097.

`EVENTFLOW_SECRETS_EXTERNAL` is a non-secret operator attestation that all required secret values are injected outside Git and never localized to browser configuration. It must never contain a credential.

## Execution

Keep the acceptance tool in a controlled release-tools directory outside the installed plugin. Run it within the target WordPress process:

```shell
wp --path=/srv/www/wordpress eval-file /secure/release-tools/staging-environment-acceptance.php -- --expected-version=1.3.0-dev --json
```

The tool exits `0` only when every check passes. Any failure or inability to observe a prerequisite exits nonzero. Correct the target and rerun; do not edit the evidence or downgrade a failure to a warning.

## WordPress composition proof

The command confirms:

- the plugin is active and its application bootstrap is healthy and ready;
- EventFlow owns its admin menu and asset hooks;
- the `eventflow_rsvp` shortcode is registered;
- health/readiness and representative Event, Venue, Invitation, Attendee, Seating, Reception, Communication, Import, Export, Privacy, Audit, Diagnostic, and guest-bootstrap REST route families are present.

## Evidence handling

Store the sanitized JSON with the deployment record, artifact SHA-256, commit, operator, UTC execution time, and target identifier in the approved evidence system. Do not commit environment output: even minimized deployment evidence belongs outside Git. IMP-094 remains blocked until that live record exists and reports `status: pass`.
