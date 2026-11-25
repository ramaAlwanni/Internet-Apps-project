# المتطلب الخامس: التوسع وتوزيع الأحمال     



## 0) خريطة سريعة: المتطلب ← الملفات ← الإثبات

| # | جزء المتطلب | الملفات التي اشتغلنا عليها | حالة الاختبار | أمر الإثبات المختصر |
|---|---|---|---|---|
| 1 | 3 نسخ من التطبيق خلف Nginx | `docker-compose.yml`, `Dockerfile`, `docker/loadbalancer.conf` | TC-1.1 … TC-1.6 | `docker compose ps backend` + `k6 run tests/k6/load-balancer-test.js` |
| 2 | تبرير استراتيجية التوزيع `least_conn` | `docker/loadbalancer.conf`, هذا الملف | TC-2.1 … TC-2.3 | توزيع 500 طلب على 3 hostnames + شرح §2 |
| 3 | 3 عمّال يتقاسمون نفس الطابور | `docker-compose.yml`, `routes/console.php`, `config/queue.php`, `app/Jobs/SendOrderEmailJob.php` | TC-3.1 … TC-3.6 | `docker compose ps rabbitmq-worker` + إدارة RabbitMQ + أوامر tinker |
| 4 | استمرارية النظام عند إيقاف نسخة | `docker/loadbalancer.conf`, `docker-compose.yml`, `tests/k6/failover-test.js`, `scripts/verify-scalability.ps1` | TC-4.1 … TC-4.5 | `powershell -ExecutionPolicy Bypass -File .\scripts\verify-scalability.ps1` |
| 5 | المشاكل الخفية (كل ما يُحفظ محلياً داخل النسخة) | `docker-compose.yml`, `Dockerfile`, `config/session.php`, `config/cache.php`, `config/cors.php`, `Modules/Product/app/Service/ProductCacheService.php` | TC-5.1 … TC-5.8 | أوامر `docker compose exec` في §5 + `Test-CacheAndETag.ps1` |
| 6 | إثبات التوزيع تحت ضغط (500 طلب) | `tests/k6/load-balancer-test.js`, `scripts/verify-scalability.ps1` | TC-1.5 | انظر §6 لكل سكربتات الاختبار |

**النتيجة النهائية المتوقعة بعد تشغيل الإثبات الكامل:**
`PASS: distribution across 3 replicas, 3 workers, and zero-downtime failover.`

---

## 1) الجزء الأول: النسخ الثلاث، عزل نقطة الدخول، وتتبّع النسخة

### 1.1 ما طُبّق فعلياً

| ما طُبّق | الملف | التفاصيل |
|---|---|---|
| 3 نسخ من `backend` | `docker-compose.yml` | `deploy.replicas: 3` تحت خدمة `backend`، دون `container_name`، حتى يولّد Compose اسماً مختلفاً لكل حاوية → 3 سجلات A في DNS الداخلي باسم `backend`. |
| منفذ خارجي واحد فقط | `docker-compose.yml` | حُذفت `ports` من `backend`؛ المنفذ `8080:80` معلن فقط على `loadbalancer` داخل `app-network`. لا يمكن تجاوز Nginx. |
| فحص صحة | `docker-compose.yml` + `Dockerfile` | healthcheck بـ `curl -fsS http://127.0.0.1/up` كل 5s؛ ثُبّت `curl` في الصورة؛ LB ينتظر `backend: condition: service_healthy`. |
| حالة مشتركة بين النسخ | `docker-compose.yml` | `SESSION_DRIVER=database`, `CACHE_STORE=redis`, `REDIS_HOST=redis`, `DB_READ_HOST=mysql-replica`, `DB_WRITE_HOST=mysql-primary`, `APP_KEY` إلزامي. |
| صور المنتجات | `docker-compose.yml` | حجم (volume) مشترك `product_images` على `storage/app/public/products` حتى تراها كل نسخة. |
| منع state قديم من المضيف | `docker-compose.yml` + `Dockerfile` | حُذف bind mount `.:/var/www/html`؛ `.env` يُركّب `ro` فقط؛ الكود مخبوز في الصورة مع `config:clear && route:clear && view:clear`. |
| توزيع ذكي | `docker/loadbalancer.conf` | `least_conn` + `zone` + `resolve` + `valid=5s` + `max_fails/fail_timeout` + `proxy_next_upstream`. |
| تتبّع النسخة | `routes/web.php`, `app/Http/Middleware/BackendInstanceHeader.php`, `bootstrap/app.php`, `config/cors.php`, `frontend/src/services/api.js` | مسار `/instance` يعيد `hostname`، وكل استجابة تحمل `X-Backend-Instance`، والواجهة تسجّلها في Console. |

### 1.2 شرح الكود

#### أ) `docker-compose.yml` — خدمة `backend` (الأسطر 156‑213)

```yaml
backend:
  image: internet-apps-backend:local
  deploy:
    replicas: 3                       # 3 حاويات مستقلة خلف الاسم backend
  # لا يوجد ports هنا: المنفذ الوحيد هو 8080 على loadbalancer
  environment:
    APP_KEY: "${APP_KEY:?APP_KEY must be set in .env}"   # فشل سريع لو غاب المفتاح
    SESSION_DRIVER: database          # جلسات في MySQL مشتركة، لا ملفات داخل الحاوية
    DB_WRITE_HOST: "${DB_WRITE_HOST:-mysql-primary}"
    DB_READ_HOST: "${DB_READ_HOST:-mysql-replica}"
    CACHE_STORE: redis                # كاش/عدّادات مشتركة
    REDIS_HOST: redis
    ORDER_STOCK_MANAGER: grpc
    GRPC_INVENTORY_HOST: inventory-service:9090
  volumes:
    - ./.env:/var/www/html/.env:ro    # قراءة فقط، لا كتابة من النسخة
    - product_images:/var/www/html/storage/app/public/products  # صور مشتركة
  healthcheck:
    test: ["CMD-SHELL", "curl -fsS http://127.0.0.1/up >/dev/null || exit 1"]
    interval: 5s
    timeout: 3s
    retries: 12
    start_period: 20s
  restart: unless-stopped
  depends_on:
    migrate: { condition: service_completed_successfully }
    mysql-replica: { condition: service_healthy }
    inventory-service: { condition: service_healthy }
    redis: { condition: service_healthy }
```

**لماذا كل سطر مهم؟**

- `deploy.replicas: 3` يجعل عدد النسخ معلناً في الملف. عند التشغيل نضيف أيضاً `--scale backend=3`؛ كلاهما ينتهي إلى 3 (الأمر وقت التشغيل يأخذ الأولوية).
- غياب `ports` يعني أن طلباً خارجياً لا يستطيع الوصول إلى حاوية بعينها؛ حتى لو عُرف hostname لا يوجد منفذ مكشوف.
- `APP_KEY` موحّد وإلزامي: لو اختلف بين نسختين، الكوكيز الموقّعة والقيم المشفّرة تفشل عشوائياً.
- `/up` هو مسار صحة Laravel المدمج (`bootstrap/app.php` → `health: '/up'`)؛ يعود 200 عندما التطبيق قابل لخدمة الطلبات.
- `start_period: 20s` يمنع وسم النسخة غير صحية أثناء الإقلاع الأول (composer/مigrations/ربط gRPC).

خدمة الموزّع:

```yaml
loadbalancer:
  image: nginx:1.28-alpine
  ports:
    - "${BACKEND_PORT:-8080}:80"      # نقطة الدخول الوحيدة
  volumes:
    - ./docker/loadbalancer.conf:/etc/nginx/conf.d/default.conf:ro
  healthcheck:
    test: ["CMD-SHELL", "wget -q -O - http://127.0.0.1/up >/dev/null || exit 1"]
  depends_on:
    backend:
      condition: service_healthy     # لا يبدأ التوجيه قبل نسخة سليمة
  restart: unless-stopped
```

#### ب) `docker/loadbalancer.conf` — شرح سطر بسطر

```nginx
resolver 127.0.0.11 ipv6=off valid=5s;          # DNS المدمج في Docker؛ لا نtrusted إلا 5 ثوانٍ

upstream store_backends {
    zone store_backends 64k;                    # منطقة مشتركة بين worker processes (مطلوبة لـ resolve)
    least_conn;                                 # الطلب التالي → النسخة الأقل اتصالات نشطة
    server backend:80 resolve max_fails=2 fail_timeout=5s;  # كل A record من Docker DNS
    keepalive 32;                               # إبقاء اتصالات upstream لتقليل handshake
}

server {
    listen 80;
    location / {
        proxy_pass http://store_backends;
        proxy_http_version 1.1;
        proxy_set_header Connection "";         # ضروري مع keepalive
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        proxy_connect_timeout 2s;               # اكتشاف سريع لنسخة ماتت
        proxy_send_timeout 15s;
        proxy_read_timeout 15s;
        proxy_next_upstream error timeout http_502 http_503 http_504;  # جرّب نسخة أخرى
        proxy_next_upstream_tries 3;            # حد أقصى 3 محاولات
    }
}
```

| التوجيه | ماذا يفعل؟ | لماذا اخترناه؟ |
|---|---|---|
| `resolver 127.0.0.11 valid=5s` | يعيد حل اسم `backend` كل ≤5s | عند إيقاف/إعادة نسخة يتغيّر سجل DNS؛ بدون `resolve` يبقى Nginx على عناوين قديمة. |
| `zone store_backends 64k` | حالة الـ upstream مشتركة بين كل Nginx workers | شرط استخدام `resolve` دون إعادة تحميل manual. |
| `least_conn` | يختار أقل عدد اتصالات نشطة | الطلبات غير متساوية التكلفة (قارئ Redis سريع مقابل استعلام MySQL/gRPC بطيء). |
| `server backend:80` | كل نسخة هي upstream | لا نعرف عناوين IP مسبقاً؛ Docker DNS يعطي واحدة لكل حاوية. |
| `max_fails=2 fail_timeout=5s` | فشلان خلال 5s ⇒ استبعاد مؤقت للنسخة | فحص سلبي (passive) لأن Nginx OSS لا يملك active health checks. |
| `proxy_next_upstream ... tries 3` | إعادة الطلب لنسخة أخرى عند line error/timeout/5xx | يخفي لحظة سقوط النسخة عن المستخدم. |
| `proxy_*_timeout` | حدود زمنية صارمة | يمنع بقاء الطلب معلّقاً خلف نسخة نصف ميتة. |
| `keepalive 32` | إعادة استخدام اتصالات TCP | يقلل زمن الاتصال تحت 500 طلب متزامنة. |
| `proxy_set_header X-Forwarded-*` | يمرّر IP والبروتوكول الأصليين | التطبيق يرى عنوان العميل الحقيقي رغم وجود proxy. |

#### ج) تتبّع النسخة: `/instance` + Middleware + CORS + الواجهة

`routes/web.php`:

```php
Route::get('/instance', function () {
    return response()->json([
        'hostname' => gethostname(),   // معرّف الحاوية التي خدمت الطلب
    ]);
});
```

`app/Http/Middleware/BackendInstanceHeader.php` (مسجّل ** globally ** في `bootstrap/app.php`:

```php
$middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
$middleware->append(\App\Http\Middleware\SecurityHeaders::class);
$middleware->append(\App\Http\Middleware\BackendInstanceHeader::class);
```

داخل الـ middleware:

```php
$response = $next($request);                        // ينفّذ الطلب أولاً
$response->headers->set('X-Backend-Instance', gethostname());
return $response;
```

> تنبيه: لأن الرأس يُضاف **بعد** `$next()`، بعض استجابات معالجات الاستثناءات في `bootstrap/app.php` قد لا تحمله. لذلك k6 يقرأ الهيدر **أو** حقل `hostname` من JSON؛ الرأس أداة قياس فقط وليس للقرارات الأمنية، ويُزال على الحدّ الخارجي في الإنتاج حتى لا نكشف أسماء الحاويات الداخلية.

`config/cors.php`:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie', 'instance'],
'allowed_origins' => array_values(array_filter(array_map(
    'trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173'))
))),
'exposed_headers' => ['X-Backend-Instance'],  // يسمح لـ JS بقراءته
'supports_credentials' => false,              // نستخدم Bearer، لا كوكيز جلسة
```

`frontend/src/services/api.js`:

```js
API.interceptors.response.use(
  (response) => {
    console.log(`${response.config.method?.toUpperCase()} ${response.config.url}`,
                "handled by:", response.headers["x-backend-instance"]);
    return response;
  },
  (error) => Promise.reject(error),
);
```

### 1.3 حالات الاختبار — الجزء الأول

| ID | الحالة | Command | النتيجة المتوقعة |
|---|---|---|---|
| TC‑1.1 | بناء ظريف ورفع 3 نسخ | `docker compose up -d --build --wait --wait-timeout 180 --scale backend=3 --scale rabbitmq-worker=3` | خروج 0؛ كل الخدمات جاهزة (`--wait`). |
| TC‑1.2 | عدد النسخ وصحتها | `docker compose ps backend --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"` | 3 صفوف، كلها `Up (healthy)`، عمود `PORTS` فارغ. |
| TC‑1.2b | عدّ آلي | `(docker compose ps -q backend).Count` | `3`. |
| TC‑1.3 | المنفذ الخارجي للموزّع فقط | `docker compose ps --format "table {{.Service}}\t{{.Ports}}"` | `loadbalancer` فقط يظهر `0.0.0.0:8080->80/tcp`؛ `backend` بلا منافذ. |
| TC‑1.4 | مسار `/instance` | `curl -s http://localhost:8080/instance` | JSON: `{"hostname":"<container-id>"}`. |
| TC‑1.4b | تكرار المسار يعطي أكثر من hostname | `1..10 \| ForEach-Object { (Invoke-RestMethod http://localhost:8080/instance).hostname } \| Group-Object \| Select-Object Name,Count` | أكثر من hostname واحد (غالباً 3). |
| TC‑1.5 | الرأس في الاستجابة | `curl -s -D - -o NUL http://localhost:8080/instance \| Select-String 'X-Backend-Instance'` | يظهر الرأس بقيمة hostname الحاوية. |
| TC‑1.6 | CORS يكشف الرأس للمتصفح | `curl -s -D - -o NUL -H "Origin: http://localhost:5173" http://localhost:8080/instance \| Select-String -Pattern 'access-control-allow-origin\|access-control-expose-headers'` | `access-control-expose-headers: X-Backend-Instance`. |
| TC‑1.7 | توزيع 500 طلب | انظر أمر §6 (`load-balancer-test.js`) | 3 hostnames مختلفة، `http_req_failed=0/500`, `checks=100%`. |

أوامر جاهزة للنسخ:

```powershell
# 1) رفع النسخ
docker compose up -d --build --wait --wait-timeout 180 --scale backend=3 --scale rabbitmq-worker=3

# 2) فحص النسخ والمنافذ
docker compose ps backend --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"
docker compose ps --format "table {{.Service}}\t{{.Ports}}"
(docker compose ps -q backend).Count

# 3) فحص /instance والرأس
curl -s http://localhost:8080/instance
curl -s -D - -o NUL http://localhost:8080/instance | Select-String 'X-Backend-Instance'
curl -s -D - -o NUL -H "Origin: http://localhost:5173" http://localhost:8080/instance | Select-String -Pattern 'access-control-'

# 4) توزيع 500 طلب (k6 محلي)
k6 run --env API_BASE_URL=http://localhost:8080 --env TEST_ITERATIONS=500 --env TEST_VUS=10 tests/k6/load-balancer-test.js

# بديل بدون تثبيت k6 على المضيف (صورة k6 في compose)
docker compose --profile load-test run --rm `
  -e API_BASE_URL=http://loadbalancer -e TEST_ITERATIONS=500 -e TEST_VUS=10 `
  k6 run /tests/load-balancer-test.js
```

---

## 2) الجزء الثاني: لماذا `least_conn`؟ (التبرير المنطقي)

### 2.1 التبرير

اخترنا `least_conn` (توجيه الطلب للنسخة ذات أقل عدد اتصالات نشطة) بدلاً من `round_robin` الافتراضي، واستغنينا عن `ip_hash` (sticky sessions) للأسباب التالية:

1. **تفاوت تكلفة الطلبات.** ليست كل الطلبات متساوية: بعضها سريع (قراءة من Redis/كاش)، وبعضها أثقل (استعلام MySQL، أو تواصل مع خدمة المخزون عبر gRPC). `round_robin` يوزّع **بعدد الطلبات** لا **بحملها**، فيمكن أن يرسل عدة طلبات ثقيلة متتالية لنسخة بينما نسخة أخرى أقل انشغالاً. `least_conn` يرسل الطلب الجديد تلقائياً إلى النسخة الأقل اتصالات نشطة → زمن استجابة أفضل وتقليل خطر اختناق نسخة واحدة.
2. **لا حاجة إلى `ip_hash`.** المصادقة تعتمد على **Bearer JWT** يُتحقق منه في كل طلب، والجلسات مخزّنة في **MySQL**، والكاش/معدّدات التحديد في **Redis**. لذلك أي نسخة من الثلاث قادرة على خدمة أي طلب لأي مستخدم؛ لا يوجد state يربط المستخدم بنسخة معيّنة. لو استخدمنا `ip_hash` لتقليل الخطر، لخلقنا مشكلة توزيع غير متساوٍ عند وجود مستخدمين خلف NAT واحد دون داعٍ تقني.
3. **التعامل مع الأعطال.** `least_conn` وحده لا يعني failover؛ لذلك ضممناه مع `max_fails=2 fail_timeout=5s` (استبعاد سلبي)، و`proxy_next_upstream error timeout http_502 http_503 http_504` مع `proxy_next_upstream_tries 3` (إعادة الطلب لنسخة بديلة)، و`resolve valid=5s` (تحديث قائمة النسخ بعد إيقافها). النتيجة: توجيه ذكي + تحويل تلقائي عند الفشل.
4. **ما ادّعيناه بوضوح.** Nginx OSS يملك **passive health checks فقط**؛ لا يوجد active health check استباقي (ميزة Nginx Plus). الاكتشاف يتم من الطلبات نفسها + healthcheck الخاص بـ Compose/Docker.

### 2.2 حالات الاختبار — الاستراتيجية

| ID | الحالة | Command | النتيجة المتوقعة |
|---|---|---|---|
| TC‑2.1 | التوزيع فعلي على 3 لا على نسخة واحدة | `k6 run --env API_BASE_URL=http://localhost:8080 --env TEST_ITERATIONS=500 --env TEST_VUS=10 tests/k6/load-balancer-test.js` ثم §7 لقراءة الـ tags | 3 hostnames؛ لو ذهبت الـ 500 ل hostname واحد يكون الاختبار قد فشل (السكربت يتحقق من suppress هذا الفشل عبر `verify-scalability.ps1`). |
| TC‑2.2 | لا sticky sessions: التوكن نفسه يعمل عبر نسخ مختلفة | `k6 run --env API_BASE_URL=http://localhost:8080 --env TEST_EMAIL=<admin-email> --env TEST_PASSWORD=<password> --env PRODUCT_ID=1 --env TEST_ITERATIONS=20 --env TEST_VUS=5 tests/k6/read-write-verification.js` | كل POST order ينجح (201) والـ GET الفوري يرى الطلب رغم أن الطلبين قد يخدمهما نسختان مختلفتان (`X-Backend-Instance`). يتطلب حساب admin ومخزوناً متاحاً عبر gRPC. |
| TC‑2.3 | سلوك عند الفشل | `powershell -ExecutionPolicy Bypass -File .\scripts\verify-scalability.ps1` | إيقاف نسخة أثناء الضغط لا ينتج 5xx؛ انظر §4. |

> ملاحظة: لم نُعد تشغيل الاختبار بـ `round_robin` للمقارنة؛ التبرير معماري (تكلفة الطلبات) والقياس المتاح هو توزيع `least_conn` تحت 500 طلب. هذا موثّق صراحةً حتى لا يُفهم القياس كدليل حصري على تفوق الاستراتيجية.

---

## 3) الجزء الثالث: 3 عمّال يتقاسمون نفس الطابور

### 3.1 الملفات والكود

#### أ) `docker-compose.yml` — خدمة إعداد الطابور مرّة واحدة (الأسطر 316‑335)

```yaml
rabbitmq-setup:
  image: internet-apps-backend:local
  command: ["php", "artisan", "rabbitmq:setup"]
  depends_on:
    rabbitmq: { condition: service_healthy }
  restart: "no"        # ينتهي بعد الإعداد ولا يعود
```

#### ب) `docker-compose.yml` — العمّال الثلاثة (الأسطر 337‑389)

```yaml
rabbitmq-worker:
  image: internet-apps-backend:local
  # لا container_name: Compose يولّد اسماً لكل نسخة
  deploy:
    replicas: 3
  command:
    [
      "php", "artisan", "rabbitmq:consume",
      "--queue=order_emails_queue",
      "--tries=3",
      "--prefetch-count=1",
    ]
  environment:
    QUEUE_CONNECTION: rabbitmq
    RABBITMQ_HOST: rabbitmq
    RABBITMQ_QUEUE: order_emails_queue
    CACHE_STORE: redis
    SESSION_DRIVER: database
  depends_on:
    rabbitmq: { condition: service_healthy }
    rabbitmq-setup: { condition: service_completed_successfully }
    migrate: { condition: service_completed_successfully }
  restart: unless-stopped
```

**لماذا `--prefetch-count=1`؟**
الخيار يمرّر إلى `basic.qos` داخل حزمة `vladimir-yuldashev/laravel-queue-rabbitmq` (انظر `vendor/.../src/Console/ConsumeCommand.php`، السطر 65‑66: `setPrefetchCount(...)`). القيمة الافتراضية في الحزمة هي 1000؛ لو تركناها، عامل واحد قد يحجز دفعة كبيرة من الرسائل (unacked) بينما العاملان الآخران جائعان، و لو مات العامل الحاجز تأخرت رسائله حتى انتهاء `retry_after`. بقيمة 1 نحصل على **fair dispatch**: كل عامل لا يحمل أكثر من رسالة واحدة غير مُعترف بها، فيتقاسم الثلاثة العمل فعلياً.

#### ج) `routes/console.php` — تعريف الطوبولوجيا (الأسطر 22‑57)

```php
Artisan::command('rabbitmq:setup', function () {
    $channel->exchange_declare('orders_dlx', 'direct', false, true, false);
    $channel->exchange_declare('parking_lot_exchange', 'direct', false, true, false);
    $channel->queue_declare('order_emails_dlq', false, true, false, false, false,
        new AMQPTable(['x-dead-letter-exchange' => 'parking_lot_exchange',
                       'x-dead-letter-routing-key' => 'parking.key']));
    $channel->queue_declare('order_emails_queue', false, true, false, false, false,
        new AMQPTable(['x-dead-letter-exchange' => 'orders_dlx',
                       'x-dead-letter-routing-key' => 'order_emails_dlq']));
    $channel->queue_bind('order_emails_dlq', 'orders_dlx', 'order_emails_dlq');
    ...
});
```

**لماذا خدمة منفصلة؟** لو نفّذ كل عامل `rabbitmq:setup` عند الإقلاع (كما كان سابقاً `setup && consume`)، يحدث سباق declarations بين 3 عمال؛ وإذا وُجد الطابور بوسائط DLQ مختلفة يرجع RabbitMQ `PRECONDITION_FAILED`، وبسبب `&&` لا يبدأ المستهلك → نحصل على عاملين بدلثلاثة بصمت. الآن الإعداد يعمل مرة واحدة والعمّال ينتظرون `service_completed_successfully`.

#### د) `config/queue.php` + `app/Jobs/SendOrderEmailJob.php`

- `config/queue.php` (الأسطر 76‑122) يعرّف اتصال `rabbitmq` والطابور `order_emails_queue` مع وسيطات `x-dead-letter-*`، أي أن الرسالة الفاشلة لا تُسقط بل تذهب إلى `orders_dlx` ثم `order_emails_dlq` ثم `parking_lot`.
- `SendOrderEmailJob` يملك `public int $tries = 3` و`$backoff = [1,2,3]` و`failed()` يرسل تنبيه parking lot. **التسليم at-least-once**، لذلك يجب أن يبقى معالج الإيميل idempotent (الرسالة قد تُعاد بعد سقوط عامل).

### 3.2 حالات الاختبار — العمّال

| ID | الحالة | Command | النتيجة المتوقعة |
|---|---|---|---|
| TC‑3.1 | وجود 3 حاويات عامل | `docker compose ps rabbitmq-worker --format "table {{.Name}}\t{{.Status}}"` | 3 صفوف، `Up`. |
| TC‑3.1b | عدّ آلي (نفس فحص السكربت) | `(docker compose ps -q rabbitmq-worker).Count` | `3`؛ السكربت يفشل إن لم يجد 3. |
| TC‑3.2 | كل العمّال على نفس الطابور | `docker compose ps -q rabbitmq-worker \| ForEach-Object { docker inspect --format '{{range .Config.Env}}{{println .}}{{end}}' $_ \| Select-String '^RABBITMQ_QUEUE=' }` | 3 أسطر متطابقة: `RABBITMQ_QUEUE=order_emails_queue`. |
| TC‑3.3 | 3 مستهلكين فعليين عند RabbitMQ | `curl -s -u guest:guest http://localhost:15672/api/queues/%2F/order_emails_queue \| ConvertFrom-Json \| Select-Object name,consumers,messages` | `consumers: 3` (واجهة الإدارة على 15672). |
| TC‑3.4 | الإعداد يعمل مرة واحدة | `docker compose ps rabbitmq-setup`; `docker compose logs --no-color rabbitmq-setup` | الحالة `Exited (0)`؛ السطر `RabbitMQ topology declared: order_emails_queue -> ...`. |
| TC‑3.5 | competing consumers: 20 رسالة تُعالج مرة واحدة لكل رسالة | انظر أمر tinker أدناه، ثم `curl -s -u guest:guest http://localhost:15672/api/queues/%2F/order_emails_queue \| ConvertFrom-Json \| Select-Object messages,consumers` و`docker compose logs --no-color rabbitmq-worker \| Select-String 'Order confirmation email sent successfully' \| Measure-Object \| Select-Object Count` | `messages: 0` بعد المعالجة؛ عدد سطور النجاح = 20 (بعد طرح أي خط أساس موجود). توزّع السطور على أكثر من عامل. |
| TC‑3.6 | استمرار الطابور عند سقوط عامل | `$id=(docker compose ps -q rabbitmq-worker \| Select-Object -First 1); docker stop $id` ثم أعد TC‑3.5 بـ 10 رسائل ثم `docker start $id` | الرسائل تُعالج من العاملين الباقيين (prefetch=1 يمنع حجزها من العامل الساقط)؛ لا فقد. |
| TC‑3.7 | اختبارات PHPUnit للطابور | `docker compose exec -T backend php artisan test tests/Feature/OrderEmailQueueTest.php` | PASS: الرسالة تصل إلى `order_emails_queue`. |
| TC‑3.8 | بقاء الرسالة عند توقف العامل + DLQ | `docker compose exec -T backend php artisan test --filter test_message_survives_when_worker_is_stopped tests/Feature/OrderEmailQueueResilienceTest.php` (عامل متوقف) ثم `docker compose exec -T backend php artisan test --filter test_failed_job_reaches_dlq_and_recovery_drains_it tests/Feature/OrderEmailQueueResilienceTest.php` (عامل يعمل) | PASS في الحالتين: `queue after > before`، والرسالة الفاشلة تصل DLQ ثم `emails:recover-dlq` يفرّغها. راجع ملاحظة الملف: الاختبار الأول يحتاج عاملاً متوقفاً والثاني عاملاً يعمل. |

أمر حقن 20 رسالة (يُرسل بريداً حقيقياً؛ استخدم حساب اختبار أو بيئة staging):

```powershell
docker compose exec -T backend php artisan tinker --execute='for ($i=1; $i<=20; $i++) { \App\Jobs\SendOrderEmailJob::dispatch(["order_id"=>"REQ5-$i","user_email"=>"ramaalwanni83@gmail.com","user_name"=>"Req5","total_price"=>1,"items"=>[]])->onQueue("order_emails_queue"); } echo "queued 20 messages\n";'
```

---

## 4) الجزء الرابع: استمرارية النظام عند إيقاف نسخة

### 4.1 الآلية موزّعة على الملفات

| الآلية | الملف | السطر/التوجيه | الأثر عند إيقاف نسخة |
|---|---|---|---|
| DNS ديناميكي | `docker-compose.yml` + `docker/loadbalancer.conf` | `deploy.replicas: 3`, `server backend:80 resolve`, `valid=5s` | Docker يشيل سجل الحاوية المتوقفة؛ Nginx يعيد الحل خلال ≤5s. |
| فحص سلبي | `docker/loadbalancer.conf` | `max_fails=2 fail_timeout=5s` | فشلان ⇒ استبعاد النسخة 5 ثوانٍ. |
| إعادة المحاولة | `docker/loadbalancer.conf` | `proxy_next_upstream error timeout http_502 http_503 http_504` + `tries 3` | الطلب الذي كان ذاهباً للنسخة المتوقفة يُعاد لنسخة سليمة. |
| اكتشاف صحة الحاوية | `docker-compose.yml` | healthcheck `/up` كل 5s + `depends_on: service_healthy` | LB لا يوجّه لنسخة غير سليمة عند الإقلاع. |
| إعادة تشغيل تلقائية | `docker-compose.yml` | `restart: unless-stopped` | لو ماتت العملية داخل الحاوية (crash) تعود تلقائياً؛ الإيقاف اليدوي بـ `docker stop` يبقى متوقفاً حتى `docker start`. |

> حدود صريحة: إيقاف **كل** النسخ أو `--scale backend=0` يعني عدم وجود upstream ⇒ Nginx يعيد **502**؛ لا يوجد maintenance page. وإيقاف `loadbalancer` نفسه يقتل المنفذ 8080 (نقطة فشل مفردة منفصلة عن نطاق المتطلب). `unless-stopped` لا يُعيد ما أُوقف يدوياً.

### 4.2 حالات الاختبار — الاستمرارية

| ID | الحالة | Command | النتيجة المتوقعة |
|---|---|---|---|
| TC‑4.1 | الإثبات الكامل الآلي | `powershell -ExecutionPolicy Bypass -File .\scripts\verify-scalability.ps1` | 5 خطوات: رفع 3+3، 500 طلب، تحقق 3 hostnames، إيقاف نسخة أثناء k6، إعادة تشغيلها ثم `PASS`. |
| TC‑4.2 | إيقاف يدوي أثناء ضغط مستمر | انظر كتل الأوامر أدناه | k6 ينهي بـ thresholds ناجحة وصفر `http_req_failed`. |
| TC‑4.3 | نسختان تخدمّان بعد الإيقاف | أمر قراءة `failover.json` أدناه | ≥2 hostname بعد الإيقاف (القياس المسجّل: 3 ظهروا لأن الأولى خدمت قبل الإيقاف). |
| TC‑4.4 | عودة النسخة | `docker start $target`; `docker compose ps backend --format "table {{.Name}}\t{{.Status}}"` | 3 نسخ healthy من جديد. |
| TC‑4.5 | حالة سلبية: إيقاف كل النسخ | `docker compose stop backend`; `curl -s -o NUL -w "%{http_code}" http://localhost:8080/instance`; ثم `docker compose start backend` | المتوقع **502** أثناء التوقف (حد معروف، ليس خللاً في الإثبات). |

أمر TC‑4.1 مع خيارات صريحة:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\verify-scalability.ps1 `
  -BackendPort 8080 -FailoverDuration 45s -K6Bin k6
```

أمر TC‑4.2 يدوياً (لاحظ أن `docker stop` يستهدف **حاوية واحدة** لا الخدمة كلها):

```powershell
# 1) شغّل ضغطاً مستمراً في الخلفية
$k6Args = @('run','--quiet','--out=json=tests/k6/results/failover-manual.json',
            '--env=API_BASE_URL=http://localhost:8080',
            '--env=TEST_DURATION=45s','--env=TEST_VUS=5',
            'tests/k6/failover-test.js')
$proc = Start-Process -FilePath k6 -ArgumentList $k6Args -PassThru -NoNewWindow

# 2) بعد 8 ثوانٍ أوقف نسخة واحدة
Start-Sleep -Seconds 8
$id = (docker compose ps -q backend | Select-Object -First 1)
docker stop $id

# 3) انتظر نهاية الاختبار ثم أعد النسخة
$proc.WaitForExit(); $proc.Refresh()
if ($null -ne $proc.ExitCode -and [int]$proc.ExitCode -ne 0) { throw "k6 failed: $($proc.ExitCode)" }
docker start $id
```

أمر TC‑4.3 لقراءة النتائج:

```powershell
$f = Get-Content tests\k6\results\failover.json | ForEach-Object { $_ | ConvertFrom-Json }
$f | Where-Object { $_.type -eq 'Point' -and $_.metric -eq 'backend_requests' } |
  ForEach-Object { $_.data.tags.backend } | Group-Object | Sort-Object Name |
  Select-Object Name,Count
$f | Where-Object { $_.type -eq 'Point' -and $_.metric -eq 'http_req_failed' } |
  ForEach-Object { $_.data.value } | Measure-Object -Sum
```

> ملاحظة تقنية في السكربت: مع `Start-Process -NoNewWindow` قد يعود `ExitCode` فارغاً حتى مع k6 ناجح؛ لذلك يعتمد `verify-scalability.ps1` على `Refresh()` + فحص آمن للصفر + قراءة نقاط `http_req_failed` + thresholds، فلا يرفع استثناءً كاذباً.

---

## 5) الجزء الخامس: المشاكل الخفية — كل ما يُحفظ محلياً داخل النسخة

> **القاعدة الذهبية:** أي شيء يُكتب داخل الحاوية ولا يُشارك ⇒ إما يُنقل إلى مخزن مشترك (DB/Redis/Volume) أو يصبح `derived/ephemeral` يمكن إعادة توليده. لا نضع session ولا cache ولا عدّاداً على قرص النسخة.

| # | الحالة المحلية | الدليل في الكود | قبل الإصلاح (مع 3 نسخ) | بعد الإصلاح في `yassmin` | اختبار |
|---|---|---|---|---|---|
| 1 | **الجلسات** | `config/session.php:21` (`SESSION_DRIVER`), `:63` (`storage/framework/sessions`) | مع `file` تفقد النسخة جلسات غيرها ⇒ تسجيل خروج عشوائي | `SESSION_DRIVER: database` صريح في compose لكل خدمات PHP؛ الجلسات في جدول `sessions` المشترك | TC‑5.1 |
| 2 | **الكاش وعدّاد الإصدارات** | `config/cache.php:18`, `Modules/Product/app/Service/ProductCacheService.php:11,19,60` | مع `file` ترى كل نسخة إصداراً مختلفاً؛ تحديث منتج لا يُبطل كاش الباقي ⇒ بيانات قديمة | `CACHE_STORE: redis`؛ مفتاح `products:cache:version` وTTL مشتركان | TC‑5.2 |
| 3 | **معدّد الطلبات / rate limiting** | Laravel `throttle` يستخدم نفس الـ cache store؛ `RateLimiter::for('login')` في `Modules/User` | مع file cache يصبح الحد 3 أضعاف ويتجاوز المهاجم الحماية بتوزيع طلباته | Redis واحد ⇒ حد فعّال واحد عبر النسخ | TC‑5.3 |
| 4 | **الصور المرفوعة** | `Modules/Product/app/Service/ProductService.php`, `docker/nginx.conf:13` (`/storage/`) | صورة تُررفع عبر نسخة ⇒ 404 من نسخة أخرى | volume `product_images` مشترك (في الإنتاج S3/object storage) | TC‑5.4 |
| 5 | **كود/مجلدات قابلة للكتابة** | كان يوجد `.:/var/www/html` في compose؛ `Dockerfile:44-47` | كاش Blade/logs/`bootstrap/cache` مشتركة على المضيف؛ كاش قديم ⇒ 404 لـ `/instance` وتجاهل env الخاص بـ Compose | حُذف الـ bind mount؛ `.env` ro؛ الكود مخبوز في الصورة؛ `config:clear && route:clear && view:clear` | TC‑5.5 |
| 6 | **مفتاح التشفير `APP_KEY`** | `.env`, compose `${APP_KEY:?...}` | اختلاف المفتاح بين نسخ ⇒ فشل عشوائي للكوكيز/التوقيعات | fail-fast + `.env` readonly + مستبعد من الصورة عبر `.dockerignore` | TC‑5.6 |
| 7 | **تهيئة RabbitMQ** | `routes/console.php:22-57` | `rabbitmq:setup` داخل كل عامل ⇒ سباق + `PRECONDITION_FAILED` يقتل عامل بصمت | خدمة `rabbitmq-setup` مرة واحدة + انتظار `service_completed_successfully` | TC‑3.4 / TC‑5.7 |
| 8 | **WebSocket / Reverb** | `frontend/src/lib/echo.js`, `routes/api.php` (`/api/broadcasting/auth`) | اتصال WS مرتبط بعقدة Reverb؛ لكن مصادقة القناة JWT stateless | لا state محلي في نسخ التطبيق؛ ⚠️ Reverb ما زال عقدة واحدة (محدودية معلنة) | TC‑5.8 |
| 9 | **رأس معرّف النسخة** | `app/Http/Middleware/BackendInstanceHeader.php` | يُضاف بعد `$next()`؛ بعض استجابات الاستثناءات قد لا تحمله | أداة قياس فقط؛ k6 يقرأ الهيدر أو JSON؛ يُزال على الحدّ في الإنتاج | TC‑1.5 |
| 10 | **CORS** | `config/cors.php` | origins ثابتة + مسارات `broadcasting/*` غير فعّالة | env-driven + `instance` + `exposed_headers` | TC‑1.6 |

### 5.1 شرح أكواد الإصلاح

**الكاش وإبطاله دفعة واحدة** (`ProductCacheService.php`):

```php
protected const VERSION_KEY = 'products:cache:version';
protected const TTL = 3600;

protected function getVersion(): int {
    return Cache::rememberForever(self::VERSION_KEY, fn() => 1);
}
$cacheKey = "products:v{$version}:cat_{$categoryId}:page_{$page}:per_{$perPage}";
return Cache::remember($cacheKey, self::TTL, fn() => Product::with('category')...->paginate($perPage));

public function invalidateCache(): void {
    if (!Cache::has(self::VERSION_KEY)) { Cache::forever(self::VERSION_KEY, 1); }
    else { Cache::increment(self::VERSION_KEY); }
}
```

بما أن المفتاح مشترك في Redis، زيادة الإصدار تجعل كل النسخ تستخدم مفاتيح جديدة ⇒ إبطال فوري دون مسح آلاف المفاتيح. لو كان الكاش `file` لكل نسخة إصدارها الخاص ⇒ نسخة تعطي بيانات قديمة.

**الفصل بين القراءة والكتابة + الحالة المشتركة** (`docker-compose.yml`):

```yaml
DB_WRITE_HOST: mysql-primary        # الكتابة
DB_READ_HOST: mysql-replica         # القراءة
SESSION_DRIVER: database            # جلسات مشتركة
CACHE_STORE: redis                  # كاش/عدّادات مشتركة
APP_KEY: "${APP_KEY:?APP_KEY must be set in .env}"
volumes:
  - ./.env:/var/www/html/.env:ro
  - product_images:/var/www/html/storage/app/public/products
```

**حذف bind mount وبناء الصورة** (`Dockerfile`):

```dockerfile
COPY . .
RUN composer clear-cache \
 && composer install --no-interaction --prefer-dist --optimize-autoloader \
 && mkdir -p storage/... bootstrap/cache \
 && php artisan config:clear && php artisan route:clear && php artisan view:clear \
 && rm -f bootstrap/cache/*.tmp \
 && php artisan storage:link \
 && chown -R www-data:www-data storage bootstrap/cache
```

`curl` ثُبّت في نفس الملف حتى يعمل healthcheck.

### 5.2 حالات الاختبار — المشاكل الخفية

| ID | الحالة | Command | النتيجة المتوقعة |
|---|---|---|---|
| TC‑5.1 | الجلسات في DB وليس ملفات | `docker compose ps -q backend \| ForEach-Object { docker exec $_ php artisan tinker --execute="echo config('session.driver');" }` ثم `docker compose exec -T mysql-primary sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" internet_apps -Nse "SELECT COUNT(*) FROM sessions"'` | 3 أسطر `database`؛ العدّاد يزيد بعد تسجيل دخول واحد عبر LB (بغض النظر عن النسخة التي خدمت الطلب). |
| TC‑5.2 | إصدار الكاش مشترك في Redis | `docker compose exec -T redis redis-cli GET products:cache:version`; `docker compose exec -T redis redis-cli --scan --pattern 'products:*'` | قيمة واحدة؛ مفاتيح `products:v<N>:...` واحدة تراها كل النسخ. |
| TC‑5.2b | كاش/ETag عبر LB | `powershell -ExecutionPolicy Bypass -File .\tests\scripts\Test-CacheAndETag.ps1 -ApiBaseUrl http://localhost:8080` | 200 + ETag، تكرار 200 بنفس ETag، `If-None-Match` ⇒ 304، و`[CACHE HIT] ... products:v...` في اللوج. \*) |
| TC‑5.3 | حدّ التحديد مركزي | `1..6 \| ForEach-Object { try { Invoke-RestMethod -Method Post -Uri http://localhost:8080/api/v1/users/sign-in -Body (@{email='nobody@example.com';password='wrong'} \| ConvertTo-Json) -ContentType 'application/json' } catch { $_.Exception.Response.StatusCode.value__ } }` ثم `docker compose exec -T redis redis-cli --scan --pattern '*throttle*'` | بعد 5 محاولات يظهر 429 ولا يتضاعف الحد إلى 15 بمجرد تبديل النسخة (عدّاد Redis واحد). \*) |
| TC‑5.4 | الصور مشتركة | `docker compose ps -q backend \| ForEach-Object { docker exec $_ sh -c 'hostname; ls -la storage/app/public/products \| head -3' }` | نفس قائمة الملفات في الثلاث حاويات؛ `docker inspect` يُظهر volume `product_images` لكل حاوية. |
| TC‑5.5 | لا bind mount لكامل الكود | `docker compose config \| Select-String -Pattern '\.:/var/www/html'` (لا نتيجة) و`docker compose exec -T backend sh -c 'mount \| grep /var/www/html'` | لا يوجد سطر يركّب `.` على `/var/www/html`؛ يظهر فقط المسار الفرعي للصور. |
| TC‑5.6 | `APP_KEY` واحد | `docker compose ps -q backend \| ForEach-Object { docker exec $_ printenv APP_KEY } \| Sort-Object -Unique \| Measure-Object \| Select-Object -ExpandProperty Count` | `1`. ولو حُذف المتغير يفشل `docker compose config` برسالة `APP_KEY must be set in .env`. |
| TC‑5.7 | لا سباق في إعداد RabbitMQ | `docker compose up -d --force-recreate rabbitmq-worker`; `docker compose logs --no-color rabbitmq-setup`; `docker compose logs --no-color rabbitmq-worker \| Select-String 'PRECONDITION_FAILED'` | الإعداد يعمل مرة؛ لا `PRECONDITION_FAILED`؛ 3 consumers. |
| TC‑5.8 | حدود Reverb | `docker compose ps reverb --format "table {{.Name}}\t{{.Status}}"` + `curl -s -o NUL -w "%{http_code}" http://localhost:6001/` | عقدة واحدة تعمل؛ موثّق كـ SPoF، لا يُعد إثبات HA للبث اللحظي. |

\*) يعتمد شكل مفتاح rate limit على إصدار Laravel وتوقيع `RateLimiter`؛ الفحص الجوهري هو ظهور 429 بعد الحد وليس شكل المفتاح.

**أمر مفيد لمراقبة التغيّر بعد تحديث منتج (TC‑5.2):**

```powershell
# قبل التحديث
docker compose exec -T redis redis-cli GET products:cache:version
# بعد تعديل منتج من الواجهة/API (يستدعي invalidateCache)
docker compose exec -T redis redis-cli GET products:cache:version
docker compose exec -T redis redis-cli --scan --pattern 'products:*'
```

الإصدار يجب أن يزيد، والمفاتيح القديمة (`products:v<N-1>:...`) تصبح غير مستخدمة.

---

## 6) كل سكربتات الاختبار الموجودة — الأوامر وماذا يثبت كل واحد

### 6.1 السكربت الرئيسي (الإثبات الكامل)

| السكربت | الأمر | ماذا يثبت؟ |
|---|---|---|
| `scripts/verify-scalability.ps1` | `powershell -ExecutionPolicy Bypass -File .\scripts\verify-scalability.ps1` | يرفع 3 backend + 3 workers، يرسل 500 طلب ويتحقق من 3 hostnames، يوقف نسخة أثناء 45s ضغط ويتحقق من صفر فشل ووجود نسخة ثانية، يعيد النسخة ويطبع `PASS`. النتائج الخام في `tests/k6/results/`. خياراته: `-BackendPort`, `-FailoverDuration`, `-K6Bin`. |

ما يفحصه السكربت داخلياً بالترتيب:

1. `docker compose down --remove-orphans` ثم `up -d --build --wait --scale backend=3 --scale rabbitmq-worker=3`.
2. انتظار `/instance` على `http://localhost:8080` (60 محاولة×2s).
3. k6 `load-balancer-test.js` بـ 500 طلب/10 VUs، ثم تجميع `backend_requests` حسب tag `backend` والفشل إن قلّ العدد عن 3.
4. فحص `(docker compose ps -q rabbitmq-worker).Count -eq 3`.
5. تشغيل `failover-test.js` (45s/5 VUs)، بعد 8s إيقاف حاوية واحدة، ثم فحص `ExitCode` ونقاط `http_req_failed` ووجود ≥2 hostname.
6. `docker start $target` وطباعة الحالة النهائية.

### 6.2 سكربتات k6

| السكربت | الأمر (k6 محلي) | البديل داخل Docker | النتيجة المتوقعة |
|---|---|---|---|
| `tests/k6/load-balancer-test.js` | `k6 run --env API_BASE_URL=http://localhost:8080 --env TEST_ITERATIONS=500 --env TEST_VUS=10 tests/k6/load-balancer-test.js` | `docker compose --profile load-test run --rm -e API_BASE_URL=http://loadbalancer -e TEST_ITERATIONS=500 -e TEST_VUS=10 k6 run /tests/load-balancer-test.js` | 500 طلب `/instance`؛ `backend_requests` بـ 3 tags؛ `checks=100%`؛ `http_req_failed=0`. |
| `tests/k6/failover-test.js` | `k6 run --env API_BASE_URL=http://localhost:8080 --env TEST_DURATION=45s --env TEST_VUS=5 tests/k6/failover-test.js` | `docker compose --profile load-test run --rm -e API_BASE_URL=http://loadbalancer -e TEST_DURATION=45s -e TEST_VUS=5 k6 run /tests/failover-test.js` | طلبات مستمرة 45s؛ أثناءها أوقف نسخة؛ صفر 5xx. **لوحده لا يوقف نسخة**؛ أوقفها يدوياً أو استخدم السكربت الرئيسي. |
| `tests/k6/scalability-read.js` | `k6 run --env API_BASE_URL=http://localhost:8080 --env TEST_ITERATIONS=500 --env TEST_VUS=20 tests/k6/scalability-read.js` | `docker compose --profile load-test run --rm -e API_BASE_URL=http://loadbalancer -e TEST_ITERATIONS=500 -e TEST_VUS=20 k6 run /tests/scalability-read.js` | 500 قراءة عبر LB؛ `checks=100%`، `http_req_failed=0`، `p(95)<3000ms`. يقيس الجذر `/` (صفحة 200) وليس `/instance`. |
| `tests/k6/read-write-verification.js` | `k6 run --env API_BASE_URL=http://localhost:8080 --env TEST_EMAIL=<admin> --env TEST_PASSWORD=<pass> --env PRODUCT_ID=1 --env TEST_ITERATIONS=20 --env TEST_VUS=5 tests/k6/read-write-verification.js` | `docker compose --profile load-test run --rm -e API_BASE_URL=http://loadbalancer -e TEST_EMAIL=<admin> -e TEST_PASSWORD=<pass> -e PRODUCT_ID=1 -e TEST_ITERATIONS=20 -e TEST_VUS=5 k6 run /tests/read-write-verification.js` | كل طلب ينشئ order (201) ثم GET فوري يراه؛ يثبت أن JWT والقراءة بعد الكتابة لا يعتمدان على نسخة بعينها. يحتاج حساب admin ومخزوناً. |
| `tests/k6/cache-test.js` | `k6 run tests/k6/cache-test.js` | غير مُهيّأ في compose (الرابط فيها ثابت `http://127.0.0.1:8000`) | 200/304 وp95<500ms؛ **سكربت قديم** يعمل فقط مع خادم Laravel على 8000؛ عدّله إلى `http://localhost:8080/api/v1/products` لقياس التوسّع عبر LB. |
| `tests/k6/load-test.js` | `k6 run tests/k6/load-test.js` | غير مُهيّأ في compose (رابط `http://backend/api/...` + توكن ثابت منتهٍ) | سكربت قديم؛ استبدل التوكن والرابط بـ `http://localhost:8080` قبل الاستخدام. لا يُستخدم كإثبات للمتطلب الخامس. |

### 6.3 سكربتات PowerShell واختبارات PHP

| السكربت | الأمر | النتيجة المتوقعة |
|---|---|---|
| `tests/scripts/Test-CacheAndETag.ps1` | `powershell -ExecutionPolicy Bypass -File .\tests\scripts\Test-CacheAndETag.ps1 -ApiBaseUrl http://localhost:8080` (أو `-Token <jwt> -PerPage 7`) | يسجّل دخولاً تفاعلياً، يتحقق من 200 + ETag، تكرار بنفس ETag، 304 مع `If-None-Match`، ويبحث في `storage/logs/laravel.log` عن `[CACHE HIT] ... products:v<N>:...` ⇒ دليل على أن الكاش في Redis وليس ملفاً محلياً. |
| `tests/Feature/OrderEmailQueueTest.php` | `docker compose exec -T backend php artisan test tests/Feature/OrderEmailQueueTest.php` | PASS: `SendOrderEmailJob` يُدفع إلى `order_emails_queue`. |
| `tests/Feature/OrderEmailQueueResilienceTest.php` | `docker compose exec -T backend php artisan test --filter test_message_survives_when_worker_is_stopped tests/Feature/OrderEmailQueueResilienceTest.php` (عامل متوقف) / `docker compose exec -T backend php artisan test --filter test_failed_job_reaches_dlq_and_recovery_drains_it tests/Feature/OrderEmailQueueResilienceTest.php` (عامل يعمل) | PASS: الرسالة تبقى في الطابور عند توقف العامل؛ الفاشلة تصل DLQ ثم يُفرّغها `emails:recover-dlq`. |
| باقي اختبارات PHP (`php artisan test`) | `docker compose exec -T backend php artisan test` | تغطي بقية المتطلبات؛ ليست إثباتاً للمتطلب الخامس. ملاحظة: `phpunit.xml` يجبر `CACHE_STORE=array` و`SESSION_DRIVER=array` داخل الاختبارات، لذلك لا تستخدم نتائجه وحدها كدليل على مخزن الإنتاج المشترك. |

### 6.4 قراءة نتائج k6 الخام (بعد أي تشغيل)

```powershell
# توزيع 500 طلب
$d = Get-Content tests\k6\results\distribution.json | ForEach-Object { $_ | ConvertFrom-Json }
$d | Where-Object { $_.type -eq 'Point' -and $_.metric -eq 'backend_requests' } |
  ForEach-Object { $_.data.tags.backend } | Group-Object | Sort-Object Name |
  Select-Object Name,Count
$d | Where-Object { $_.type -eq 'Point' -and $_.metric -eq 'http_req_failed' } |
  ForEach-Object { $_.data.value } | Measure-Object -Sum

# فشل أثناء الـ failover
$f = Get-Content tests\k6\results\failover.json | ForEach-Object { $_ | ConvertFrom-Json }
$f | Where-Object { $_.type -eq 'Point' -and $_.metric -eq 'http_req_failed' } |
  ForEach-Object { $_.data.value } | Measure-Object -Sum
```

---

## 7) النتائج المسجّلة على هذه الآلة (من `tests/k6/results/`)

> الملفات الخام موجودة محلياً ومستبعدة من Git عبر `.gitignore` (`/tests/k6/results/`).
> الأرقام أدناه مُستخرجة مباشرة من هاتين المجموعتين باستخدام أوامر §6.4.

| الفحص | النتيجة |
|---|---|
| عدد حاويات `backend` | 3، كلها healthy |
| عدد حاويات `rabbitmq-worker` | 3، كلها تعمل على `order_emails_queue` |
| توزيع 500 طلب حسب hostname | `2b1f8054b097 = 171`، `addd968d7167 = 169`، `e6324e747720 = 160` (المجموع 500، 3 hostnames) |
| فحص الـ 500 طلب | `http_req_failed = 0/500`، `checks = 100%`، p95 ≈ **122.5ms** |
| إيقاف نسخة أثناء 45s ضغط | `http_req_failed = 0/4463`، p95 ≈ **65.7ms** |
| hostnames ظهرت أثناء الـ failover | `2b1f8054b097 = 279` (قبل الإيقاف)، `addd968d7167 = 2081`، `e6324e747720 = 2103` |
| بعد الإيقاف | نسختان تخدمّان الطلبات، ثم أُعيدت الثالثة بـ `docker start` |
| السطر الختامي | `PASS: distribution across 3 replicas, 3 workers, and zero-downtime failover.` |

---

## 7.1 حماية إضافية: تنظيف الحجوزات المعلّقة

المشكلة: لو مات الـ caller (أو انقطع gRPC) **بعد** ما خدمة المخزون تحجز الكمية وقبل ما يوصلها `commit`/`release`، يبقى الصف `RESERVED` والكمية محجوزة بلا طلب مقابل. المسار الطبيعي كان يحرّر بنفس `reservation_id`، لكن ما كانت فيه شبكة أمان للحالة النادرة.

### ما طُبّق

| الملف | التعديل | السبب |
|---|---|---|
| `inventory-service/.../service/InventoryBusinessService.java` | `releaseStaleReservations(staleAfterMinutes, batchSize)` | يختار الحجوزات `RESERVED` الأقدم من العتبة بـ `FOR UPDATE`، ثم يستدعي `releaseReservation(نفس المعرّف)` فيرجع الكمية ويحوّل الحالة إلى `RELEASED`. القفل يمنع `commit` متزامن من النجاح بعد قرار التحرير. |
| `inventory-service/.../scheduler/StaleReservationCleanup.java` (جديد) | `@Scheduled` كل 60s، أول تشغيل بعد 10s | شبكة أمان دورية؛ أي فشل في دورة لا يوقف الدورات التالية. |
| `inventory-service/.../InventoryServiceApplication.java` | `@EnableScheduling` | تفعيل مهمة Spring. |
| `inventory-service/src/main/resources/application.properties` | إعدادات قابلة للحقن من البيئة | 10 دقائق عتبة، دفعة 200، فترة 60s. العتبة أكبر بكثير من `GRPC_INVENTORY_TIMEOUT=5s` فلا تُحرَّر طلبات حيّة. |
| `docker-compose.yml` (خدمة `inventory-service`) | `INVENTORY_STALE_RESERVATION_MINUTES`، `INVENTORY_STALE_CLEANUP_INITIAL_DELAY_MS`، `INVENTORY_STALE_CLEANUP_INTERVAL_MS`، `INVENTORY_STALE_CLEANUP_BATCH_SIZE` | كل الإعدادات تأتي من Docker Compose. |

### 7.1.1 اختبار فعلي على قاعدة حيّة

```powershell
$rid = "stale-proof-$(Get-Date -Format yyyyMMddHHmmss)"
$sql = "INSERT INTO reservations (id,status,created_at,updated_at) VALUES ('$rid','RESERVED', NOW() - INTERVAL 20 MINUTE, NOW() - INTERVAL 20 MINUTE); INSERT INTO reservation_items (reservation_id,product_id,quantity,released) VALUES ('$rid',1,2,FALSE); UPDATE inventory_items SET available_quantity = available_quantity - 2 WHERE product_id=1;"
docker compose exec -T inventory-db mysql -uinventory -pinventory_dev_password inventory_db -e $sql
docker compose up -d --build inventory-service
Start-Sleep -Seconds 12
docker compose exec -T inventory-db mysql -uinventory -pinventory_dev_password inventory_db -Nse "SELECT id,status FROM reservations WHERE id='$rid';"
docker compose exec -T inventory-db mysql -uinventory -pinventory_dev_password inventory_db -Nse "SELECT COUNT(*) FROM reservations WHERE status='RESERVED';"
docker compose exec -T inventory-db mysql -uinventory -pinventory_dev_password inventory_db -Nse "SELECT available_quantity FROM inventory_items WHERE product_id=1;"
docker compose logs --no-color inventory-service | Select-String -Pattern 'stale'
```

الخرج المسجّل:

| القياس | قبل | بعد |
|---|---|---|
| Reserve تجريبي | `RESERVED` | `RELEASED` (updated_at `12:05:41`) |
| عدد `RESERVED` | 15 (14 قديمة + 1 تجريبية) | **0** |
| مخزون المنتج 1 | 21 بعد الحجز الاصطناعي | **23** (رجع زي ما كان) |
| لوج الخدمة | — | `Released 15 stale RESERVED reservation(s) older than 10 minute(s).` |


| الحالة | الخرج الحقيقي | الحكم |
|---|---|---|
| RabbitMQ موقوف | `ORDER_OK id=1010`، المخزون `24 → 23`، outbox `pending` ثم `published` بعد الرجوع | الخصم **مشروع** لأن الطلب نجح؛ الإيميل فقط تأخر |
| gRPC موقوف قبل الطلب | `EXPECTED_FAIL 503`، المخزون `23 → 23`، آخر order بقي `1010` | الطلب فشل، **ما فيه خصم** ولا طلب جديد |
| بطء gRPC (deadline 5s) | `PASS ... 5.29s — 1 passed (5 assertions)` | ينقطع خلال 5s بلا خصم |
| فشل حفظ الطلب بعد الحجز | `PASS ... 0.38s — 1 passed (4 assertions)` | التحرير يرجع المخزون (`orders=0`, `available=1`) |
| موت الـ caller بعد الحجز | 15 صفاً `RESERVED` → `0`، والمخزون رجع | شبكة الأمان الجديدة أغلقت الثغرة |
| طلب عادي بعد التنظيف | `SMOKE_OK id=1011`، المخزون `23 → 22`، أحدث حجز `COMMITTED`، outbox `published` | المهمة ما أثرت على المسار الصحيح |

---

## 8) تشغيل سريع من الصفر (Checklist)

```powershell
# 1) تأكد من APP_KEY في .env ثم ابنِ وارفع
docker compose down --remove-orphans
docker compose up -d --build --wait --wait-timeout 180 --scale backend=3 --scale rabbitmq-worker=3

# 2) فحص سريع
docker compose ps backend --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"
(docker compose ps -q rabbitmq-worker).Count
curl -s http://localhost:8080/instance

# 3) الإثبات الكامل (500 طلب + failover + workers)
powershell -ExecutionPolicy Bypass -File .\scripts\verify-scalability.ps1

# 4) إصلاحات شائعة
docker compose logs --no-color loadbalancer        # تأكد من resolve/least_conn محمّلين
docker compose logs --no-color backend            # تأكد من /up وعدم 404 لـ /instance
docker compose logs --no-color rabbitmq-setup     # تأكد من إعلان الطوبولوجيا مرة واحدة
docker compose restart loadbalancer                # بعد تعديل docker/loadbalancer.conf
```

**مشاكل متوقعة وحلولها:**

| المشكلة | السبب المحتمل | الحل |
|---|---|---|
| 404 من `/instance` | كاش قديم في `bootstrap/cache` أو bind mount قديم | أعد البناء: `docker compose up -d --build backend`; تحقق من `config:clear/route:clear/view:clear` في Dockerfile. |
| `APP_KEY must be set in .env` | المتغير غائب | أضف `APP_KEY=base64:...` إلى `.env` الساري (موجود أصلاً محلياً). |
| `PRECONDITION_FAILED` في worker | declarations متزامنة بوسائط مختلفة | تأكد أن `rabbitmq-setup` يعمل مرة وأن العمّال ينتظرونه؛ أعد `docker compose up -d --force-recreate rabbitmq-worker`. |
| k6 غير موجود | لم يُثبّت على المضيف | استخدم صورة `k6` عبر `--profile load-test` كما في §6.2. |
| منفذ 8080 مشغول | خدمة أخرى تستخدمه | غيّر `BACKEND_PORT` في `.env` ومرّر `-BackendPort <الجديد>` للسكربت. |
| `Connection refused` عند بدء worker | RabbitMQ أعلن healthy قبل فتح 5672 | healthcheck الآن يفحص `check_port_listener 5672`؛ انتظر `--wait` ثم أعد المحاولة. |

---

## 9) حدود معروفة (للصدق في العرض)

1. **HA جزئي:** HTTP وعمّال الطابور متعددون؛ لكن `loadbalancer`, `rabbitmq`, `mysql-primary`, `redis`, `reverb`, و`order-email-outbox-publisher` ما زالت عُقداً مفردة (SPoF). المتطلب طلب 3 نسخ من **التطبيق** و3 **عمّال**، وهذا متحقق؛ نظام HA كامل يحتاج Swarm/K8s أو عُقد إضافية.
2. **Nginx OSS:** فحص سلبي فقط؛ لا active health checks.
3. **رأس `X-Backend-Instance`:** للقياس/الاختبار فقط؛ يُزال على الحدّ الخارجي في الإنتاج (تسريب أسماء الحاويات الداخلية).
4. **الواجهة:** `frontend/src/services/api.js` ما زال يستخدم `http://localhost:8080/api` و`echo.js` يستخدم `localhost:6001` ; في الإنتاج يجب أن تصبح env-driven وتوضع خلف gateway/LB.
5. **الصور:** volume محلي `product_images` يكفي للتجربة؛ الإنتاج يستخدم S3/object storage.
6. **`docker compose stop backend`** يوقف كل النسخ (لا واحدة) ⇒ 502؛ للاستمرارية أوقف **حاوية واحدة** بـ `docker stop <id>` كما في §4.
7. **تنظيف الحجوزات المعلّقة:** العتبة الافتراضية 10 دقائق (أطول بكثير من مهلة gRPC البالغة 5s). أي طلب يبقى قبل `commit` أكثر من العتبة يُعتبر مهجوراً ويُحرَّر؛ في بيئات بطيئة جداً ارفع `INVENTORY_STALE_RESERVATION_MINUTES` من Docker Compose.

---

### خلاصة جاهزة للعرض

> «طبّقنا 3 نسخ من Laravel خلف Nginx بمنفذ خارجي واحد 8080، واخترنا `least_conn` لأن الطلبات تختلف تكلفةً بين Redis وMySQL وgRPC، ولا نحتاج sticky sessions لأن JWT والجلسات والكاش مشتركة. الطابور له 3 عمّال بـ `prefetch=1` وإعدادRabbitMQ مرة واحدة. أثبتنا 500 طلب موزّعة على 3 hostnames، واستمرار الخدمة بصفر فشل أثناء إيقاف نسخة (4463 طلباً). المشاكل الخفية عالجناها بنقل الجلسات إلى DB والكاش إلى Redis ومشاركة الصور بحجم مشترك ومنع bind mounts القديمة وتوحيد `APP_KEY».»
