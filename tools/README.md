# Tools

Development, migration, validation, and release utilities belong here.

## Deployment preflight

After installing a candidate in staging, validate the public health/readiness contract and exact deployed version:

```shell
php tools/deployment-preflight.php --url=https://staging.example.test --expected-version=1.3.0-dev
```

Use `--json` for machine-readable evidence. Plain HTTP is rejected except for an explicit loopback-only development invocation with `--allow-http-localhost`. The tool sends no credentials, follows no redirects, retains no response data, and keeps TLS certificate verification enabled.

## Reproducible plugin artifact

Build the production-only WordPress plugin archive from a clean Git commit and verify that two independent builds are byte-identical:

```shell
php tools/build-plugin-artifact.php --output=build/artifacts --verify-reproducible
php tools/verify-plugin-artifact.php --directory=build/artifacts
```

The archive contains only the explicitly allowlisted runtime surface, a dependency-free production autoloader, and an internal payload manifest. The adjacent external manifest records the source commit, deterministic timestamp, archive size, file count, and SHA-256 digest. The build fails closed if Composer gains a production dependency, the source tree is dirty, an input is missing, a symlink is encountered, or reproducibility fails.
