# EventFlow IMP-093 — Deterministic production artifact gate

IMP-093 makes the EventFlow WordPress plugin artifact a reproducible, independently verifiable build output. A clean committed source tree is mandatory. The builder packages only `eventflow.php`, Composer metadata, application source, admin/guest assets, migrations, and the frozen schema baseline under a single `eventflow/` archive root.

EventFlow currently has no production Composer package dependency. The build therefore creates a minimal deterministic PSR-4 runtime autoloader and fails closed if production dependencies or autoload rules change; future dependency bundling must be reviewed explicitly rather than copied accidentally from a developer `vendor/` directory.

Every payload file is normalized where appropriate and recorded with byte count and SHA-256 in the archive's internal manifest. An adjacent external manifest binds the archive to its version, source commit, source timestamp, size, file count, and SHA-256. The ZIP writer uses ordered entries, fixed modes, UTC source timestamps, no platform-specific ZIP extension, and atomic output replacement. CI builds twice, requires byte identity, then verifies the resulting archive from its manifests.

Build and verify from a clean commit:

```shell
php tools/build-plugin-artifact.php --output=build/artifacts --verify-reproducible
php tools/verify-plugin-artifact.php --directory=build/artifacts
```

This gate proves package composition, provenance, integrity, and reproducibility. It does not replace the later Sprint 12 staging installation, migration, backup/restore, provider/worker, browser, accessibility, load, or launch gates.
