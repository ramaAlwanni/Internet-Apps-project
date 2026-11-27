# Hidden state verification - after

- Time: 2026-11-27 16:11:47
- Branch: requirement-5-fix
- Expected result for this phase: PASS
- Failover: stopped 71a11c974b3c: during ok=119 fail=0 (served by e51a0f2bf2f0,16b6f32d1145); after restore ok=60 fail=0

| Check | Result | Evidence |
|---|---|---|
| source code is baked, not bind-mounted | PASS | no /var/www/html bind mount in any PHP service |
| .env is mounted read-only | PASS | backend=True, rabbitmq-worker=True, reverb=True, order-email-outbox-publisher=True, migrate=True |
| running replicas have no writable source mount | PASS | live inspect: 0 source mounts |
| CORS on /instance allows SPA, blocks other origins, exposes instance header | PASS | allowed: ACAO=[http://localhost:5173] expose=[X-Backend-Instance]; evil: ACAO=[] |
| backend healthcheck configured and healthy | PASS | configured=True; statuses=healthy,healthy,healthy; curl=/usr/bin/curl |
| nginx least_conn + max_fails + timeouts | PASS | directives=True; nginx -t: nginx: the configuration file /etc/nginx/nginx.conf syntax is ok nginx: configuration file /etc/nginx/nginx.conf test is successful |
| zero-downtime failover with 3 replicas | PASS | stopped 71a11c974b3c: during ok=119 fail=0 (served by e51a0f2bf2f0,16b6f32d1145); after restore ok=60 fail=0 |

Reproduce with:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\verify-hidden-state.ps1 -Phase after -Failover
```
