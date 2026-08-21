# Tools

Development, migration, validation, and release utilities belong here.

## Deployment preflight

After installing a candidate in staging, validate the public health/readiness contract and exact deployed version:

```shell
php tools/deployment-preflight.php --url=https://staging.example.test --expected-version=1.3.0-dev
```

Use `--json` for machine-readable evidence. Plain HTTP is rejected except for an explicit loopback-only development invocation with `--allow-http-localhost`. The tool sends no credentials, follows no redirects, retains no response data, and keeps TLS certificate verification enabled.
