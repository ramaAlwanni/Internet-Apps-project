# Test matrix - requirement-5-fix

Branch: `requirement-5-fix` (based on `origin/pr-27` = `origin/Razan` @ `ac352f8`).
No push was performed. All changes stay local.

## Summary

| Test | Result | Evidence |
|---|---|---|
| `docker compose config --quiet` | PASS | exit code 0 |
| PHPUnit (full suite, workers running) | 53 tests / 171 assertions / 1 environment-dependent failure / 7 skipped | The single failure is `test_message_survives_when_worker_is_stopped`, which is documented to require stopped workers. |
| PHPUnit (`--filter test_message_survives_when_worker_is_stopped`, workers stopped) | PASS | OK (1 test, 1 assertion) |
| `scripts/verify-hidden-state.ps1 -Phase after -Failover` | PASS | 7 PASS / 0 FAIL |
| Inventory stale `RESERVED` cleanup | PASS | `RESERVED` -> `RELEASED`, `RESERVED_COUNT=0`, log: `Released 1 stale RESERVED reservation(s) older than 10 minute(s).` |
| `tests/k6/load-balancer-test.js` | PASS | 200 requests, 0 failed, p95 105.48 ms, 3 distinct backends |
| `tests/k6/scalability-read.js` | PASS | 500 requests, 0 failed, p95 164.45 ms |
| `tests/k6/authenticated-products.js` | PASS | 500 requests, 0 failed, p95 423.51 ms |
| `tests/k6/read-write-verification.js` | PASS | 3/3 checks, 0 failed, p95 354.9 ms |
| `tests/k6/failover-test.js` | PASS | 3130 requests, 0 failed, 3 backends observed, zero downtime while one replica was stopped |
| `tests/k6/cache-test.js` (temp copy with auth header and load-balancer URL) | PASS | 1312 requests, 0 failed, p95 124.77 ms |
| `tests/k6/load-test.js` (temp copy with fresh JWT and load-balancer URL) | PASS | 1481 requests, 0 failed, p95 140.85 ms |
| `tests/scripts/Test-CacheAndETag.ps1` | PASS | 7 checks passed, including Redis CACHE HIT and HTTP 304 |

## Notes

- `tests/k6/failover-test.js` was restored from the local `requirement-5-fix-backup` branch because `scripts/verify-scalability.ps1` references it. The verification script itself was not run because it calls `docker compose down --remove-orphans`, which would remove the pre-existing orphan container; its steps were executed manually instead.
- `cache-test.js` and `load-test.js` hardcode `127.0.0.1:8000` / `http://backend` and a stale JWT. Temporary copies were used for verification; the tracked test files were left unchanged.
- `Test-CacheAndETag.ps1` was adapted to read Laravel logs from the backend replicas, because the source bind mount was intentionally removed by the bind-mount fix.
- PHPUnit now forces the `array` cache store in `tests/bootstrap.php`; previously `$_SERVER['CACHE_STORE']=redis` from Compose caused sqlite test fixtures to be written into the shared Redis cache.
- `laravel.log` is pre-created with `www-data` ownership in the image so PHP-FPM can write after the source bind mount was removed.

## Reproduction

```powershell
# PHPUnit (run the worker-dependent case separately with workers stopped)
docker compose exec -T backend sh -c 'cd /var/www/html && ./vendor/bin/phpunit --testdox'
docker compose stop rabbitmq-worker
docker compose exec -T backend sh -c 'cd /var/www/html && ./vendor/bin/phpunit --filter test_message_survives_when_worker_is_stopped --testdox'
docker compose start rabbitmq-worker

powershell -ExecutionPolicy Bypass -File .\scripts\verify-hidden-state.ps1 -Phase after -Failover
```
