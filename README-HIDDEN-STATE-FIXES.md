# README - المشاكل الخفية عند التوسع (Scalability & Load Distribution)

هذا الملف يشرح بالتفصيل شغلنا على **المشاكل الخفية** اللي بتظهر لما نروح من نسخة واحدة إلى 3 نسخ من التطبيق + 3 عمّال، وكل تعديل سوناه، وسبب التعديل، والكود المهم، وطريقة اختباره من التيرمنال.

> **مهم:** هذا ملف مستقل. **ما عدّلنا** `README-REQUIREMENT-5.md` ولا `README-REQUIREMENT-5-GUIDE.md`.
> كل الشغل **لوكالي** على الفرع `razan-inventory-local`، مأخوذ من `origin/Razan` عند الكومت `94b51fa`. **ما فيه commit ولا push.**
> تعديلات المخزون اللي طلبتها قبل كذا محفوظة كما هي، وهذا الملف يوثّقها كمان عشان المعيد يشوف الصورة كاملة.

---

## 0) حالة الفرع الحالية

```powershell
git branch --show-current
# razan-inventory-local

git status -sb
# ## razan-inventory-local...origin/Razan
#  M .dockerignore
#  M Dockerfile
#  M config/cors.php
#  M docker-compose.yml
#  M docker/loadbalancer.conf
#  M inventory-service/src/main/java/com/example/inventory_service/InventoryServiceApplication.java
#  M inventory-service/src/main/java/com/example/inventory_service/service/InventoryBusinessService.java
#  M inventory-service/src/main/resources/application.properties
# ?? inventory-service/src/main/java/com/example/inventory_service/scheduler/
# ?? scripts/
# ?? tests/k6/results/
# ?? tests/results/
```

`tests/k6/results/` كان موجود أصلاً كـ untracked على فرع رزان و**تركناه كما هو**.

الفرق الإجمالي:

```powershell
git diff --stat origin/Razan
# 8 files changed, 131 insertions(+), 30 deletions(-)
```

---

## 1) ليش سمّيناها «مشاكل خفية»؟

لما التطبيق كان نسخة واحدة، أي حالة محلية جوه الحاوية كانت مقبولة عملياً. لما صار عندنا 3 نسخ خلف Nginx، صار أي شي محفوظ **جوه النسخة** يختلف من نسخة لأخرى، فالطلبات صارت تعطي نتائج مختلفة حسب أي replica خدمها.

المشاكل اللي اكتشفناها ونعالجها هنا:

| # | المشكلة | قبل الإصلاح | بعد الإصلاح |
|---|---|---|---|
| 1 | الكود والـ `.env` كانوا bind mount من المضيف | 4 خدمات تقرأ وتكتب نفس مسار المضيف `rw` | الكود مدمج بالصورة، و`.env` `ro` |
| 2 | CORS ثابت وبدون `/instance` | `/instance` ما بيرجّع ACAO أصلاً | origins من env + `/instance` + expose header |
| 3 | ما فيه healthcheck للباك | `statuses=none,none,none` | الثلاث نسخ `healthy` |
| 4 | Nginx بدون `max_fails`/timeouts | فشل لحظي مؤقت عند إيقاف نسخة | `0` فشل أثناء وبعد الإيقاف |

والمشاكل اللي كانت معالجة أصلاً على فرع رزان وبقينا نتأكد منها: الجلسات في DB، الكاش في Redis، صور المنتجات في named volume، الـ rate limit مركزي، والعمّال الثلاثة يتقاسموا نفس الطابور.

---

## 2) خريطة الملفات (وين كل شي)

| الملف | شو سوناه فيه | ليه |
|---|---|---|
| `docker-compose.yml` | شيلت `.:/var/www/html`، حوّلت `.env` إلى `ro`، أضفت healthcheck + `CORS_ALLOWED_ORIGINS`، وأضفت 5 أسطر env للمخزون | منع الحالة المحلية + دعم الفحص والـ CORS وتنظيف الحجوزات |
| `Dockerfile` | أضفت `curl` + `rm -f bootstrap/cache/*.php bootstrap/cache/*.tmp` | لازم نفحص الصحة من جوه الحاوية، وما ندمج ملفات كاش قديمة بالصورة |
| `.dockerignore` | أضفت `bootstrap/cache/*.php` و`*.tmp` و`storage/framework/views/*` | نفس السبب: ما ندخل حالة محلية إلى الـ image |
| `config/cors.php` | origins من env، أضفت `instance`، شيلت `broadcasting/*` | `/instance` هو دليل توزيع الأحمال، لازم الـ SPA يقرأ هيدره |
| `docker/loadbalancer.conf` | `valid=1s`, `max_fails=1`, `fail_timeout=3s`, timeouts, `proxy_next_upstream_tries`, مجموعة `@fallback` | استبعاد النسخة الميتة بسرعة وصفر فشل عند الإيقاف |
| `scripts/verify-hidden-state.ps1` | سكربت إثبات قبل/بعد بالإنكليزي | يطبع PASS/FAIL ويكتب تقريرين |
| `tests/results/hidden-state-before.md` | حالة ما قبل الإصلاح | دليل للمعيد |
| `tests/results/hidden-state-after.md` | حالة ما بعد الإصلاح + الاستمرارية | دليل للمعيد |
| `inventory-service/.../InventoryServiceApplication.java` | `@EnableScheduling` | تفعيل الـ scheduler |
| `inventory-service/.../scheduler/StaleReservationCleanup.java` | ملف جديد | تنظيف الحجوزات المعلّقة |
| `inventory-service/.../service/InventoryBusinessService.java` | `releaseStaleReservations(...)` | التنفيذ الآمن مع `FOR UPDATE` |
| `inventory-service/src/main/resources/application.properties` | 4 مفاتيح env | ضبط العتبة والدورة وحجم الدفعة |

---

## 3) التفاصيل المملة: كل تعديل وسببه

### 3.1 الـ bind mount — الأخطر

**قبل (في `docker-compose.yml` كان موجود في 4 خدمات):**

```yaml
    volumes:
      - .:/var/www/html
      - ./.env:/var/www/html/.env
```

**بعد (backend كمثال، وبقية الخدمات بنفس الشكل):**

```yaml
    volumes:
      - ./.env:/var/www/html/.env:ro
      - ./docker/nginx.conf:/etc/nginx/sites-available/default:ro
      - ./docker/php/zz-perf.ini:/usr/local/etc/php/conf.d/zz-perf.ini:ro
      - product_images:/var/www/html/storage/app/public/products
```

**ليه كان مشكلة؟**
لأن `.:/var/www/html` كان يركّب `C:\Users\DELL\Internet-Apps-project` بصلاحية `rw` جوّه كل replica. يعني:

- أي ملف ينشأ جوه نسخة يظهر فوراً في كل النسخ التانية والمضيف.
- ملفات مثل `bootstrap/cache/services.php` و`modules.php` و`pacE22A.tmp` كانت موجودة فعلياً على المضيف (شفتها بـ `docker compose exec backend sh -c 'ls -la /var/www/html/bootstrap/cache'`).
- لو مطوّر عدّل ملف على المضيف، النسخ الثلاث تغيّروا في نفس اللحظة بدون إعادة بناء صورة — فما نقدر نثبت إن النسخ متطابقة.
- `.env` كان `rw`، يعني أي نسخة تقدر تكتب فيه وتغيّر سلوك الباقي.

**الإصلاح الإضافي في `Dockerfile`:**

```dockerfile
        nginx \
        supervisor \
        curl \
        unzip \
...
COPY . .
RUN composer clear-cache \
    && composer install --no-interaction --prefer-dist --optimize-autoloader \
    && rm -f bootstrap/cache/*.php bootstrap/cache/*.tmp \
    && mkdir -p storage/app/public/products storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache
```

`curl` صار جوه الصورة عشان الـ healthcheck، و`rm` شال أي كاش قديم كان ممكن يتنسخ بالصورة عبر `COPY . .`.

**وفي `.dockerignore`:**

```gitignore
storage/app/public/**
storage/logs/*
storage/framework/views/*
bootstrap/cache/*.php
bootstrap/cache/*.tmp
```

**كيف تختبرها من التيرمنال:**

```powershell
# 1) ما فيه source mount في أي خدمة PHP
$cfg = docker compose config --format json | ConvertFrom-Json
$names = @('backend','rabbitmq-worker','reverb','order-email-outbox-publisher','migrate')
foreach ($n in $names) {
  $cfg.services.$n.volumes | Where-Object { $_.target -eq '/var/www/html' } | Format-List
}
# المتوقع: ولا شي

# 2) .env ro في كل الخدمات
foreach ($n in $names) {
  $cfg.services.$n.volumes | Where-Object { $_.target -eq '/var/www/html/.env' } |
    Select-Object @{n='Service';e={$n}},read_only | Format-Table -AutoSize
}
# المتوقع: read_only=True لكل الخدمات

# 3) فحص حي على replica شغالة
$cid = @(docker compose ps -q backend)[0]
docker inspect --format '{{range .Mounts}}{{.Destination}} {{.Mode}}{{println}}{{end}}' $cid
# المتوقع: /var/www/html/.env ro + product_images rw، وبدون سطر /var/www/html
```

---

### 3.2 الـ CORS على `/instance`

**قبل (`config/cors.php`):**

```php
'paths' => [
    'api/*',
    'broadcasting/auth',
    'broadcasting/*',
    'sanctum/csrf-cookie',
],
'allowed_origins' => [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
],
'exposed_headers' => [
    'X-Backend-Instance',
],
```

المشكلة: `/instance` مش داخل `paths`، فالـ `HandleCors` middleware ما كان يضيف أي هيدر CORS عليه.uint اختبارات k6 كانت تشوف الهيدر لأنها طلب مباشر، بس المتصفح (الـ SPA) ما كان يقدر يقرأ `X-Backend-Instance`. وكمان `broadcasting/*` كان أوسع من اللزوم.

**بعد (الملف كامل):**

```php
<?php

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'))
)));

return [
    /*
     * /instance is the endpoint the k6 tests call to prove which replica
     * served a request, so it must be CORS-enabled for the SPA as well.
     */
    'paths' => [
        'api/*',
        'instance',
        'sanctum/csrf-cookie',
        'broadcasting/auth',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-Backend-Instance',
    ],

    'max_age' => 0,

    'supports_credentials' => false,
];
```

**ليه env؟** عشان كل replica تقرأ نفس القائمة من `docker-compose.yml` بدل ما نعدّل ملف كود ونعيد البناء:

```yaml
CORS_ALLOWED_ORIGINS: "${CORS_ALLOWED_ORIGINS:-http://localhost:5173,http://127.0.0.1:5173}"
```

**كيف تختبرها:**

```powershell
$r = Invoke-WebRequest 'http://localhost:8080/instance' -Headers @{ Origin='http://localhost:5173' } -UseBasicParsing
$r.StatusCode                                  # 200
$r.Headers['Access-Control-Allow-Origin']      # http://localhost:5173
$r.Headers['Access-Control-Expose-Headers']    # X-Backend-Instance

$e = Invoke-WebRequest 'http://localhost:8080/instance' -Headers @{ Origin='http://evil.example.com' } -UseBasicParsing
$e.Headers['Access-Control-Allow-Origin']      # فاضي / غير موجود
```

---

### 3.3 الـ healthcheck

**قبل:** ما فيه `healthcheck` تحت `backend` في Compose، و`Dockerfile` أصلاً ما فيه `curl` (الصورة القديمة كانت مبنية من فرع ثاني فكان فيها curl بالصدفة).

**بعد:**

```yaml
    healthcheck:
      test: ["CMD-SHELL", "curl -fsS http://127.0.0.1/instance >/dev/null || exit 1"]
      interval: 5s
      timeout: 3s
      retries: 20
      start_period: 30s
```

**ليه `/instance`؟** لأنه route خفيف وما يحتاج توثيق، فالفحص ما يستهلك DB ولا يزعج الطلبات الحقيقية.

**كيف تختبرها:**

```powershell
docker compose ps backend
# المتوقع: Up (healthy) لكل النسخ الثلاث

foreach ($cid in @(docker compose ps -q backend)) {
  docker inspect --format '{{.State.Health.Status}}' $cid
}
# healthy healthy healthy

docker compose exec -T backend sh -c 'command -v curl'
# /usr/bin/curl
```

---

### 3.4 موازن الحمل و `max_fails`

**قبل (`docker/loadbalancer.conf`):**

```nginx
resolver 127.0.0.11 ipv6=off valid=5s;

upstream store_backends {
    least_conn;
    zone store_backends 64k;
    server backend:80 resolve;
}
...
proxy_next_upstream error timeout http_502 http_503 http_504;
```

**المشكلة:** أي فشل في النسخة الموقوفة يضيع حتى ينتهي الـ DNS cache (`valid=5s`)، وبدون timeouts كان الطلب يعلق، وظهر عندنا فشلان `ERR WebException` أثناء الإيقاف.

**بعد (الملف كامل):**

```nginx
resolver 127.0.0.11 ipv6=off valid=1s;

upstream store_backends {
    least_conn;
    zone store_backends 64k;
    # Primary group: a replica that fails twice is taken out for 3 seconds.
    server backend:80 resolve max_fails=1 fail_timeout=3s;
}

# Safety net for nginx open source: with "resolve", a single DNS hostname can
# be treated as one peer, so max_fails can disable the whole group for a moment.
# This second group never disables peers; proxy_next_upstream simply tries the
# next replica. It is only used when the primary group returns 502/503/504.
upstream store_backends_fallback {
    least_conn;
    zone store_backends_fallback 64k;
    server backend:80 resolve max_fails=0;
}

server {
    listen 80;

    error_page 502 503 504 = @fallback;

    location / {
        proxy_pass http://store_backends;
        proxy_http_version 1.1;

        proxy_connect_timeout 1s;
        proxy_send_timeout 3s;
        proxy_read_timeout 3s;
        proxy_next_upstream error timeout http_502 http_503 http_504;
        proxy_next_upstream_tries 3;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location @fallback {
        proxy_pass http://store_backends_fallback;
        proxy_http_version 1.1;

        proxy_connect_timeout 1s;
        proxy_send_timeout 3s;
        proxy_read_timeout 3s;
        proxy_next_upstream error timeout;
        proxy_next_upstream_tries 3;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**شرح كل سطر للمقابلة:**

- `least_conn`: طلباتنا مختلفة الوزن (some requests hit DB/gRPC)، فأقل عدد اتصالات أفضل من round-robin اللي بيفترض إن كل طلب متساوي.
- `valid=1s`: Docker DNS يحدّث عناوين النسخ بسرعة بعد الإيقاف.
- `max_fails=1 fail_timeout=3s`: أول فشل يستبعد النسخة 3 ثواني بدل ما ننتظر 5 ثواني DNS.
- `proxy_connect_timeout 1s`: ما ننتظر على IP ميت أكثر من ثانية.
- `proxy_next_upstream ...`: يعيد المحاولة على نسخة ثانية قبل ما يرجّع خطأ للعميل.
- `@fallback`: في nginx OSS مع `resolve`، الـ hostname أحياناً يُعامل كـ peer واحد، فـ `max_fails` يعطّل المجموعة كلها لحظياً وطلع عندنا `502` واحد. المجموعة الاحتياطية ما تعطّل أحد، فالطلب يكمل على نسخة حيّة. هذا اللي خلى الفشل `0`.

**كيف تختبرها:**

```powershell
# صحة الكونفيغ
docker compose exec -T loadbalancer sh -c 'nginx -t 2>&1'

# التوزيع على 3 نسخ
$h = @{}
1..30 | ForEach-Object {
  try {
    $r = Invoke-WebRequest 'http://localhost:8080/instance' -UseBasicParsing -TimeoutSec 3
    $id = $r.Headers['X-Backend-Instance']; $h[$id] = 1 + [int]$h[$id]
  } catch { $h['FAIL'] = 1 + [int]$h['FAIL'] }
}
$h.GetEnumerator() | Sort-Object Name | Format-Table -AutoSize
# المتوقع: 3 معرّفات تقريباً 10/10/10 وبدون FAIL

# الإيقاف أثناء ضغط (السكربت يعمل هذا تلقائياً)
$target = @(docker compose ps -q backend)[0]
docker stop $target | Out-Null
$ok=0; $fail=0
$deadline = (Get-Date).AddSeconds(20)
while ((Get-Date) -lt $deadline) {
  try { $r=Invoke-WebRequest 'http://localhost:8080/instance' -UseBasicParsing -TimeoutSec 3; if($r.StatusCode -eq 200){$ok++} } catch { $fail++ }
  Start-Sleep -Milliseconds 100
}
docker start $target | Out-Null
"ok=$ok fail=$fail"
# المتوقع: fail=0
```

> **تنبيه مهم:** أي أمر `docker compose up` لازم يتكتب معه `--scale backend=3 --scale rabbitmq-worker=3`. لو كتبت `docker compose up -d --force-recreate loadbalancer` بدون الـ scale، Compose يرجّع الباك لنسخة واحدة وتفشل تجربة الاستمرارية. كل الأوامر في هذا الملف مكتوبة مع الـ scale.

---

### 3.5 تنظيف الحجوزات المعلّقة (شغل المخزون اللي طلبته)

المشكلة اللي أثارها Raghad: Laravel أو رابط gRPC يموت بعد ما المخزون يحجز الكمية وقبل ما يوصل `release`/`commit`. الحجز يبقى `RESERVED` والكمية مخصومة من المخزون للأبد.

**الملفات:**

- `inventory-service/src/main/java/com/example/inventory_service/InventoryServiceApplication.java`

```java
import org.springframework.scheduling.annotation.EnableScheduling;

@SpringBootApplication
@EnableScheduling
public class InventoryServiceApplication { ... }
```

- `inventory-service/src/main/java/com/example/inventory_service/scheduler/StaleReservationCleanup.java` (جديد)

```java
@Component
public class StaleReservationCleanup {
    @Value("${inventory.reservations.stale-after-minutes:10}")
    private int staleAfterMinutes;

    @Value("${inventory.reservations.cleanup-batch-size:200}")
    private int batchSize;

    @Scheduled(
            initialDelayString = "${inventory.reservations.cleanup-initial-delay-ms:10000}",
            fixedDelayString = "${inventory.reservations.cleanup-interval-ms:60000}"
    )
    public void releaseStaleReservations() {
        int released = inventoryBusinessService.releaseStaleReservations(staleAfterMinutes, batchSize);
        if (released > 0) {
            LOGGER.info("Released {} stale RESERVED reservation(s) older than {} minute(s).",
                        released, staleAfterMinutes);
        }
    }
}
```

- `inventory-service/src/main/java/com/example/inventory_service/service/InventoryBusinessService.java`

```java
@Transactional
public int releaseStaleReservations(int staleAfterMinutes, int batchSize) {
    List<String> staleIds = jdbcTemplate.query(
        "SELECT id FROM reservations WHERE status = 'RESERVED' "
      + "AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) >= ? "
      + "ORDER BY created_at LIMIT ? FOR UPDATE",
        (rs, n) -> rs.getString("id"), staleAfterMinutes, batchSize);
    int released = 0;
    for (String reservationId : staleIds) {
        ReservationResult result = releaseReservation(reservationId);
        if (result.success()) { released++; }
    }
    return released;
}
```

`FOR UPDATE` مهم لأنه يمنع سباق مع طلب إصداري ما زال شغّال على نفس الصف.

- `inventory-service/src/main/resources/application.properties`

```properties
inventory.reservations.stale-after-minutes=${INVENTORY_STALE_RESERVATION_MINUTES:10}
inventory.reservations.cleanup-initial-delay-ms=${INVENTORY_STALE_CLEANUP_INITIAL_DELAY_MS:10000}
inventory.reservations.cleanup-interval-ms=${INVENTORY_STALE_CLEANUP_INTERVAL_MS:60000}
inventory.reservations.cleanup-batch-size=${INVENTORY_STALE_CLEANUP_BATCH_SIZE:200}
```

- `docker-compose.yml` تحت `inventory-service`:

```yaml
      INVENTORY_STALE_RESERVATION_MINUTES: "${INVENTORY_STALE_RESERVATION_MINUTES:-10}"
      INVENTORY_STALE_CLEANUP_INITIAL_DELAY_MS: "${INVENTORY_STALE_CLEANUP_INITIAL_DELAY_MS:-10000}"
      INVENTORY_STALE_CLEANUP_INTERVAL_MS: "${INVENTORY_STALE_CLEANUP_INTERVAL_MS:-60000}"
      INVENTORY_STALE_CLEANUP_BATCH_SIZE: "${INVENTORY_STALE_CLEANUP_BATCH_SIZE:-200}"
```

**ليه 10 دقائق؟** لأن مهلة gRPC عندنا 5 ثواني، فالطلب الصحي ما يُظنّ متروك أبداً.

**كيف تختبرها (أعد التشغيل أولاً عشان أول دورة تبدأ من الصفر):**

```powershell
docker compose restart inventory-service | Out-Null
$rid = "stale-proof-$(Get-Date -Format yyyyMMddHHmmss)"
$sql = "INSERT INTO reservations (id,status,created_at,updated_at) VALUES ('$rid','RESERVED', NOW() - INTERVAL 20 MINUTE, NOW() - INTERVAL 20 MINUTE); INSERT INTO reservation_items (reservation_id,product_id,quantity,released) VALUES ('$rid',1,2,FALSE); UPDATE inventory_items SET available_quantity = available_quantity - 2 WHERE product_id=1;"
docker compose exec -T inventory-db mysql -uinventory -pinventory_dev_password inventory_db -e $sql
Start-Sleep -Seconds 20
docker compose exec -T inventory-db mysql -uinventory -pinventory_dev_password inventory_db -Nse "SELECT CONCAT('ROW=',id,':',status) FROM reservations WHERE id='$rid'; SELECT CONCAT('STOCK_P1=',available_quantity) FROM inventory_items WHERE product_id=1; SELECT CONCAT('RESERVED_COUNT=',COUNT(*)) FROM reservations WHERE status='RESERVED';"
docker compose logs --no-color inventory-service 2>&1 | Select-String 'Released .* stale'
```

**النتيجة المتوقعة:** `ROW=...:RELEASED`، `RESERVED_COUNT=0`، المخزون رجع (مثلاً `18 → 22`)، واللوج فيه `Released ... stale RESERVED reservation(s) older than 10 minute(s).`

---

### 3.6 مشاكل خفية كانت معالجة أصلاً (نتأكد منها بس)

| المشكلة | الحل الموجود |.command للتحقق |
|---|---|---|
| الجلسات محلية | `SESSION_DRIVER=database` على كل نسخة | `docker compose ps -q backend \| % { docker exec $_ php artisan tinker --execute="echo config('session.driver');" }` |
| الكاش محلي | `CACHE_STORE: redis` | `docker compose exec -T redis redis-cli --scan --pattern 'products:*'` |
| الصور محلية | named volume `product_images` | `docker inspect --format '{{range .Mounts}}{{.Destination}} {{.Name}}{{println}}{{end}}' @(docker compose ps -q backend)[0]` |
| Rate limit محلي | العدّاد في Redis مشترك بين النسخ | 6 محاولات تسجيل فاشلة ⇒ `HTTP 429` |
| سباق RabbitMQ setup | 3 عمّال + طابور مشترك | `docker compose exec -T rabbitmq rabbitmqctl list_consumers -p /` ⇒ 3 consumers على `order_emails_queue` |
| توزيع الإيميلات | outbox + worker | `docker compose exec -T mysql-primary mysql -uroot -plocal_root_password --database=Internet-app -Nse "SELECT order_id,status,attempts FROM order_email_outbox ORDER BY order_id DESC LIMIT 6;"` |

---

## 4) سكربت الإثبات `scripts\verify-hidden-state.ps1`

السكربت يفحص 6 أشياء، ومع `-Failover` يفحص سابعاً:

1. الكود مدمج بالصورة (بدون bind mount).
2. `.env` `ro`.
3. النسخ الشغّالة ما عندها mount للكود.
4. CORS على `/instance` يسمح للـ SPA ويمنع origin خارجي ويعرّض `X-Backend-Instance`.
5. healthcheck مُعرّف والنسخ `healthy`.
6. Nginx فيه `least_conn` + `max_fails` + timeouts و`nginx -t` ناجح.
7. إيقاف نسخة أثناء طلبات مستمرة ⇒ صفر فشل، وبعد الإرجاع صفر فشل.

**الأوامر:**

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\verify-hidden-state.ps1 -Phase before
powershell -ExecutionPolicy Bypass -File .\scripts\verify-hidden-state.ps1 -Phase after -Failover
```

**النتائج المسجّلة:**

| التقرير | النتيجة |
|---|---|
| `tests\results\hidden-state-before.md` | `0 PASS / 6 FAIL` |
| `tests\results\hidden-state-after.md` | `7 PASS / 0 FAIL` |

من تقرير «بعد» (آخر تشغيل):

```text
stopped 6923783f6cda: during ok=95 fail=0 (served by 244116c26957,8e20b3bd9e52); after restore ok=60 fail=0
```

يعني الاستمرارية صارت فعلياً **بدون أي طلب فاشل**، بينما قبل الإصلاح كان فيها فشلان عند لحظة الإيقاف.

---

## 5) التشغيل الكامل من الصفر (Copy / Paste)

```powershell
# 1) تأكد من الفرع
git branch --show-current          # razan-inventory-local

# 2) تحقق صحة Compose
docker compose config --quiet
if ($LASTEXITCODE -eq 0) { 'compose config OK' }

# 3) ابنِ الصورتين (الباك فيه curl والكود مدمج، والمخزون فيه الـ scheduler)
docker compose build backend
docker compose build --no-cache inventory-service

# 4) شغّل 3 نسخ + 3 عمّال (لازم الـ scale في كل up)
docker compose up -d --wait --wait-timeout 300 --scale backend=3 --scale rabbitmq-worker=3

# 5) الإثبات الكامل
powershell -ExecutionPolicy Bypass -File .\scripts\verify-hidden-state.ps1 -Phase after -Failover

# 6) تأكد إن الحالة ما تغيّرت
git status -sb
```

لإعادة إنتاج حالة «قبل» تحتاج ترجع الملفات الأصلية من `origin/Razan` في مجلد مؤقت أو فرع ثاني، تشغّل السكربت بـ `-Phase before`، ثم ترجع لفرعنا. **لا تعمل push.**

---

## 6) ملخص للمقابلة (بند بند)

- **سؤال: ليش least_conn؟** لأن تكلفة الطلبات مختلفة (بعضها يروح DB وبعضها gRPC للمخزون)، فأقل اتصالات أعدل من round-robin.
- **سؤال: كيف عرفت إن الحالة كانت محلية؟** `docker inspect` أظهر `/var/www/html rw` من `C:\...` على الثلاث نسخ، ولقيت `bootstrap/cache/services.php` و`pacE22A.tmp` على المضيف.
- **سؤال: ليش CORS على `/instance`؟** لأنه الهيدر اللي بنثبت فيه توزيع الطلبات؛ المتصفح ما كان يقرأه ل absence of CORS.
- **سؤال: كيف منعت الفشل عند إيقاف نسخة؟** healthcheck + DNS `valid=1s` + `max_fails=1 fail_timeout=3s` + timeouts + `proxy_next_upstream` + مجموعة `@fallback` تحل مشكلة تعطيل المجموعة في nginx OSS.
- **سؤال: وش عملت للمخزون؟** scheduled safety net كل دقيقة يطلق الحجوزات `RESERVED` الأقدم من 10 دقائق بـ `FOR UPDATE` عشان ما يتزاحم مع طلب حي.

هذا كل شي، وكل رقم مذكور فوق قابل للتحقق بالأوامر الموجودة في نفس الملف.
