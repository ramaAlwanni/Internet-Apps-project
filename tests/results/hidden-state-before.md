# Hidden state verification - before

- Time: 2026-11-26 00:19:04
- Branch: razan-inventory-local
- Expected result for this phase: FAIL
- Failover: not requested (use -Failover)

| Check | Result | Evidence |
|---|---|---|
| source code is baked, not bind-mounted | FAIL | backend -> C:\Users\DELL\Internet-Apps-project \| rabbitmq-worker -> C:\Users\DELL\Internet-Apps-project \| reverb -> C:\Users\DELL\Internet-Apps-project \| order-email-outbox-publisher -> C:\Users\DELL\Internet-Apps-project |
| .env is mounted read-only | FAIL | backend=False, rabbitmq-worker=False, reverb=False, order-email-outbox-publisher=False, migrate=False |
| running replicas have no writable source mount | FAIL | 732f31cd27b0042ffed2bb5ea07f6d69e9b4961f11050700ed52603cb7689b91 rw \| 1ffb2f4644524edd5e03eed074edf407067c3768befa99f9724d2c55d87508bf rw \| 7eed5e43a0f425a2161dae7f882e6f74d9b03cdbd38e74a6d1b54b72d86ca84a rw |
| CORS on /instance allows SPA, blocks other origins, exposes instance header | FAIL | allowed: ACAO=[] expose=[]; evil: ACAO=[] |
| backend healthcheck configured and healthy | FAIL | configured=False; statuses=none,none,none; curl=/usr/bin/curl |
| nginx least_conn + max_fails + timeouts | FAIL | directives=False; nginx -t: nginx: the configuration file /etc/nginx/nginx.conf syntax is ok nginx: configuration file /etc/nginx/nginx.conf test is successful |

Reproduce with:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\verify-hidden-state.ps1 -Phase before
```
