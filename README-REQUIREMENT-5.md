# المتطلب الخامس: التوسع وتوزيع الأحمال (Scalability & Load Distribution)

> دليل المقابلة + تقرير مراجعة هندسية لـ **PR #27**
> المستودع: `ramaAlwanni/Internet-Apps-project`
> PR: https://github.com/ramaAlwanni/Internet-Apps-project/pull/27/commits
> الـ PR المُراجَع: `origin/Razan` عند `1b82fb4` (Base: `origin/main` @ `10dd5fd`)
> **الفرع الحالي بعد العمل: `yassmin` عند الكومت المحلي الأخير (HEAD) — محلياً فقط، بدون push.**
> نسخة احتياطية بنفس الكومت: `requirement-5-fix`.
> تاريخ التحديث: 2026-11-25

---

## 0) خريطة سريعة — اقرأها أولاً قبل المقابلة

| السؤال | الإجابة المختصرة |
|---|---|
| هل المتطلب تحقق؟ | نعم **بعد تعديلاتنا**؛ الـ PR كما وصل لم يكن كافياً. |
| كم نسخة تطبيق؟ | 3 حاويات `backend` خلف Nginx على المنفذ 8080. |
| كم عمّال طابور؟ | 3 حاويات `rabbitmq-worker` على نفس `order_emails_queue`. |
| لماذا `least_conn`؟ | لأن طلبات التطبيق غير متساوية التكلفة (بعضها يضرب MySQL/gRPC)، فالأقل اتصالاً يستلم التالي. |
| لماذا لا sticky sessions؟ | JWT + جلسات DB + كاش Redis مشتركة؛ أي نسخة تخدم أي مستخدم. |
| هل وقف نسخة يكسر الخدمة؟ | لا. أثبتنا 4463 طلباً بصفر فشل أثناء إيقاف نسخة واحدة. |
| هل الإثبات يدوي من الواجهة؟ | **لا**. الإثبات المسجّل سكربتات k6 + أوامر Docker؛ الاختبار اليدوي بالمتصفح اختياري وموضّح في §5.4. |
| هل تم push؟ | **لا**. كل شيء محلي على `yassmin`. |

**جملة الافتتاح في المقابلة:**
> «راجعت PR رزان، وجدته أساساً جيداً للـ reverse proxy والـ observability لكنه لا ينشئ 3 نسخ فعلياً ولا يثبت الاستمرارية. دمجته في فرعي `yassmin`، أضفت `deploy.replicas` و`least_conn` والـ healthchecks و one-shot queue setup، وأثبتت التوزيع والـ failover بـ k6: 500 طلبٍ وُزّعت على 3 hostnames، و4463 طلب أثناء إيقاف نسخة بصفر فشل.»

---

## 1) من كان يعمل على ماذا؟ (للمقارنة)

### 1.1 رغد / `main` — الأساس الذي بُني عليه الـ PR

قبل PR #27 كان `origin/main` يحتوي بالفعل على:

| ما كان موجوداً | الملف | علاقته بالمتطلب الخامس |
|---|---|---|
| RabbitMQ + Reverb + outbox للبريد | `docker-compose.yml`, `config/broadcasting.php`, `Modules/Order/.../OrderEmailOutbox.php` (commit `59d641e`) | بنية تحتية موجودة، لكن بعامل واحد وبـ bind mount كامل. |
| مسار مصادقة البث للـ SPA | `routes/api.php` → `Route::post('/broadcasting/auth')->middleware('auth:api')` | حل مشكلة Bearer مقابل مسار Laravel الافتراضي (web/session). لا علاقة له بعدد النسخ. |
| Echo يقرأ الـ endpoint من env ويرسل التوكن | `frontend/src/lib/echo.js` | لا يوجد state محلي في نسخ التطبيق؛ لكن Reverb ما زال عقدة واحدة. |
| Rate limiting **للّوغن فقط** | `Modules/User/app/Providers/UserServiceProvider.php`: `RateLimiter::for('login')` → 5/15 دقيقة بالبريد+IP، و30/دقيقة بالـ IP، و40/ساعة بالحساب؛ مطبّق بـ `throttle:login` في `Modules/User/routes/api.php` | مهم للأمان، لكنه **ليس** channel limiter. يشارك نفس الـ cache store؛ لذلك وحّدنا `CACHE_STORE=redis`. |
| `config/channel_limits.php` | كان ملفاً يتيماً | **لا يقرأه أي كود** في `main` أو في فرع رزان؛ حُذف محلياً. |

> الخلاصة: معظم الفجوات التي ظهرت في الـ PR كانت موروثة من `main`، وليست أخطاء اخترعها رزان وحدها.

### 1.2 رزان / PR #27 — ماذا أنجزت فعلاً؟

PR #27 فيه 7 commits (منها 3 merges) و12 ملفاً (+231 / −56). الأعمال الفعلية في 4 commits:

| Commit | ما الذي فعله فعلاً | تقييمنا |
|---|---|---|
| `29a12d1` "rrrr" | تنسيق `echo.js`، وإضافة `broadcasting/*` و `exposed_headers` في `config/cors.php` | لا علاقة له بالتوسيع مباشرة؛ مسارات `broadcasting/*` كانت عديمة الجدوى لأن الحقيقي تحت `api/*`. |
| `1b58ad7` "feat: implement horizontal scaling and load balancing" | أضاف `docker/loadbalancer.conf`، خدمة `loadbalancer`، سحب `ports` من `backend`، route `/instance`، و k6 script بـ 500 طلب | **أساس جيد**، لكنه لم يضف نسخاً، ولا حدد استراتيجية توزيع، ولا أثبت التوزيع فعلياً. |
| `d57a548` "show backend instance for API requests" | `BackendInstanceHeader` + CORS expose + `console.log` في `frontend/src/services/api.js` | أداة observability ممتازة استخدمناها في الإثبات؛ أبقيناها كما هي. |
| `1b82fb4` "scale RabbitMQ consumers to three replicas" | **علّق** `container_name` فقط ليصبح `--scale` ممكناً يدوياً | العنوان أكبر من المحتوى: العدد الافتراضي ما زال 1، ولا يوجد `deploy.replicas`. |

**ملفات كتبتها رزان وأبقيناها كما هي (لم ننسبها لأنفسنا):**
`app/Http/Middleware/BackendInstanceHeader.php`, `bootstrap/app.php`, `routes/web.php` (`/instance`), `frontend/src/services/api.js` (console log), `frontend/src/lib/echo.js` (تنسيق فقط)، `composer.json`/`composer.lock` (إضافة `php-amqplib`).

### 1.3 نحن على `yassmin` — ماذا أضفنا فوق الـ PR؟

دمجنا `origin/Razan` بـ `git merge --ff-only` من `10dd5fd` إلى `1b82fb4`، ثم أضفنا كومتاً واحداً (HEAD) فيه 10 ملفات (+688 / −85). **بدون push.**

| الملف | قبل (PR / main) | بعد (إضافاتنا) | السبب |
|---|---|---|---|
| `docker-compose.yml` — `backend` | نسخة واحدة، `ports` معلّقة، لا healthcheck | `deploy.replicas: 3`, `restart: unless-stopped`, healthcheck بـ `/up`، بمنفذ 8080 للـ LB وحده | المتطلب يطلب 3 نسخ خلف Nginx؛ وبدون healthcheck لا يعرف LB ولا Compose أن النسخة ماتت. |
| `docker-compose.yml` — `loadbalancer` | يعتمد على `backend` بشكل عام، بلا healthcheck | `depends_on: backend: condition: service_healthy` + healthcheck + restart | لا نبدأ التوجيه إلا بعد نسخ صحية. |
| `docker/loadbalancer.conf` | `server backend:80 resolve;` وround robin افتراضي، بلا فحص فشل | `least_conn`, `zone`, `valid=5s`, `max_fails=2 fail_timeout=5s`, `proxy_next_upstream`, `proxy_next_upstream_tries 3`, `keepalive 32` | تبرير الاستراتيجية + استبعاد النسخة الفاشلة + تقليل handshake. |
| `Dockerfile` | بلا `curl`، وكان فيه كاش قديم من المضيف | ثبّت `curl`, `config:clear && route:clear && view:clear`, نسخ `zz-perf.ini` | الـ healthcheck يحتاج `curl`؛ والـ bind mount القديم كان يمرّر كاشاً قديمًا يعطي 404 لـ `/instance`. |
| `docker-compose.yml` — bind mounts | `.:/var/www/html` في backend/worker/reverb/publisher | حُذف؛ `.env` بس `ro`؛ الكود مخبوز في الصورة | كاش/لوجات/`bootstrap/cache` مشتركة على المضيف = state خفي يتضارب مع 3 نسخ. |
| `docker-compose.yml` — env | `CACHE_STORE: redis` موجود، لكن `APP_KEY` و`SESSION_DRIVER` غير صريحين | `${APP_KEY:?...}` fail-fast، `SESSION_DRIVER: database`, `CACHE_STORE: redis` صريح لكل خدمات PHP | منع تشفير مختلف بين النسخ، وجلسات/كاش محلية. |
| `docker-compose.yml` — RabbitMQ | `rabbitmq:setup && rabbitmq:consume` داخل كل عامل، و healthcheck بـ `ping` | خدمة `rabbitmq-setup` once + `replicas: 3` للعمّال + `prefetch-count=1` + healthcheck بـ `check_port_listener 5672` | منع سباق الـ declarations، وتحقيق 3 مستهلكين، وعدم إعلان RabbitMQ healthy قبل فتح 5672. |
| `docker-compose.yml` — reverb/frontend/k6 | يعتمدون على `backend` | صاروا يعتمدون على `loadbalancer`، وأُضيف fail-fast و restart لـ Reverb | كل عميل يدخل عبر نقطة واحدة صحيحة. |
| `config/cors.php` | origins ثابتة على 5173، ومسارات `broadcasting/*` عديمة الجدوى | env-driven (`CORS_ALLOWED_ORIGINS`) + `instance` + `X-Backend-Instance` في `exposed_headers` | يسمح بتغيير origin بدون تعديل كود، ويجعل رأس رزان مقروءاً من المتصفح. |
| `tests/k6/load-balancer-test.js` | يتحقق من 200 فقط؛ `hostname` من body؛ `fail()` | يقرأ الهيدر أيضاً، يضيف tag `test=distribution`، ويترك الحكم للسكربت | لو ذهبت الـ 500 كلها لنسخة واحدة كان الاختبار يمرّ خطأً. |
| `tests/k6/failover-test.js` | غير موجود | جديد: `constant-vus` لـ 45s مع `checks rate==1` و `http_req_failed rate==0` | إثبات الاستمرارية أثناء إيقاف نسخة. |
| `tests/k6/scalability-read.js` | BOM + URL ثابت + threshold متساهل | base من env + `checks==1` + `http_req_failed==0` | توحيد طريقة القياس وتشديد الشرط. |
| `scripts/verify-scalability.ps1` | غير موجود | جديد: يرفع 3/3، يرسل 500، يعدّ 3 hostnames، يوقف نسخة أثناء k6، يتحقق من صفر فشل، يعيدها ويطبع PASS | الإثبات لازم يكون آلياً وقابلاً لإعادة التشغيل. |
| `.gitignore` | لا يستبعد نتائج k6 | `/tests/k6/results/` | النتائج الخام دليل، لكنها لا تُرفع. |
| `config/channel_limits.php` | ملف غير متتبّع ولا مستخدم | حُذف محلياً | تنظيف فقط؛ لا يوجد channel limiter أصلاً. |

---

## 2) الحكم النهائي على PR كما وصل

| جزء المتطلب | حالة PR كما وصل | السبب |
|---|---|---|
| 3 نسخ تطبيق خلف Nginx | ❌ غير محقق | لا `deploy.replicas` ولا `--scale` ثابت. الخدمة تعمل نسخة واحدة. |
| تبرير استراتيجية التوزيع | ❌ غائب | `loadbalancer.conf` استخدم الافتراضي (round robin) بدون تبرير ولا فحص صحة. |
| 3 عمّال على نفس الطابور | ❌ غير محقق | تغيير الـ commit الأخير كان تعليق `container_name` فقط؛ العدد الافتراضي ما زال 1. |
| الاستمرارية عند إيقاف نسخة | ❌ غير مُثبت | لا توجد نسخة ثانية أصلاً، ولا healthcheck، ولا اختبار إيقاف. |
| المشاكل الخفية (local state) | ⚠️ جزئي | أضاف رأس `X-Backend-Instance` و CORS expose، لكنه لم يعالج الـ bind mounts، الـ cache/session المحلي، سباق `rabbitmq:setup`، ولا Reverb. |
| إثبات 500 طلب | ⚠️ سكربت فقط | `load-balancer-test.js` يتحقق من HTTP 200؛ كل الـ 500 يمكن أن تذهب لنسخة واحدة وتمرّ. لا نتائج محفوظة. |

**الخلاصة:** الـ PR أساس جيد (reverse proxy + observability)، لكن بالشكل المُرسَل **لا يحقق المتطلب**. التعديلات في §1.3 جعلته قابلاً للإثبات.

---

## 3) خريطة المتطلب → الملفات → ما عدّلناه → الإثبات

هذا الجدول هو الإجابة المباشرة على: «كل جزء من المتطلب بأي ملفات مطلوب الشغل عليها؟»

| جزء المتطلب | الملفات المطلوب العمل عليها | ما عدّلناه تحديداً | كيف نثبتها |
|---|---|---|---|
| **3 replicas خلف Nginx** | `docker-compose.yml`, `Dockerfile`, `docker/loadbalancer.conf` | `deploy.replicas: 3`، سحب `ports` من `backend`، `curl` + healthcheck، `server backend:80 resolve` | `docker compose ps` → 3 `backend` healthy؛ المنفذ 8080 مملوك للـ LB |
| **استراتيجية توزيع مبرّرة** | `docker/loadbalancer.conf`, هذا الملف | `least_conn` + `zone` + `valid=5s`؛ شرح لماذا لا `round_robin` ولا `ip_hash` | توزيع 171/169/160 + p95 ≈ 122ms؛ القياس لا يكفي وحده، التبرير موثّق هنا |
| **3 عمّال على طابور واحد** | `docker-compose.yml` (`rabbitmq-setup`, `rabbitmq-worker`) | one-shot setup + `replicas: 3` + `--prefetch-count=1` + انتظار `service_completed_successfully` | `docker compose ps -q rabbitmq-worker` → 3؛ سكربت الإثبات يفشل إن لم يجد 3 |
| **استمرارية عند إيقاف نسخة** | `docker/loadbalancer.conf`, `docker-compose.yml`, `tests/k6/failover-test.js`, `scripts/verify-scalability.ps1` | `max_fails/fail_timeout`, `proxy_next_upstream`, `restart: unless-stopped`, DNS re-resolve | إيقاف حاوية أثناء 45s ضغط → 4463 طلب، صفر فشل، نسختان تخدمّان ثم تعود الثالثة |
| **المشاكل الخفية / local state** | `docker-compose.yml`, `Dockerfile`, `config/cors.php`, `Modules/Product/app/Service/ProductCacheService.php` (كمرجع) | DB sessions، Redis cache/version/rate-limit، حذف bind mount، shared `product_images`، APP_KEY fail-fast، `.env` readonly | الجدول في §4 + إعادة بناء نظيفة عبر السكربت |
| **إثبات 500 طلب** | `tests/k6/load-balancer-test.js`, `scripts/verify-scalability.ps1` | تagged counter بالـ hostname + شرط وجود 3 hostnames | `171 / 169 / 160`، `http_req_failed=0/500`, `checks=1000/1000` |
| **ملاحظة النسخة / CORS** | `app/Http/Middleware/BackendInstanceHeader.php` (رزان), `routes/web.php` (رزان), `frontend/src/services/api.js` (رزان), `config/cors.php` (نحن) | `/instance`, `X-Backend-Instance`, console log، expose header، origins من env | k6 يقرأ الهيدر؛ المتصفح يراه في Console/Network (§5.4) |
| **التوثيق والمراجعة** | `README-REQUIREMENT-5.md`, `.gitignore` | هذا الملف + حفظ النتائج الخام خارج Git | الأمر: `powershell -ExecutionPolicy Bypass -File .\scripts\verify-scalability.ps1` |

---

## 4) المشاكل الخفية: كل ما يُحفظ محلياً داخل النسخة

هذا هو الجزء الأهم في المقابلة. الجدول التالي يشرح ما كان مخزّناً محلياً/mutable وما فعلناه:

| # | الحالة المحلية | الدليل في الكود | ماذا يحدث مع 3 نسخ قبل الإصلاح | بعد الإصلاح |
|---|---|---|---|---|
| 1 | **الجلسات** | `config/session.php:21` → `SESSION_DRIVER=database` (افتراضي)، و `config/session.php:63` → `storage/framework/sessions` لو استُخدم `file` | مع `SESSION_DRIVER=file` تفقد كل نسخة جلسات النسخة الأخرى → تسجيل خروج عشوائي عند كل request | صراحةً `SESSION_DRIVER: database` في compose لكل خدمات PHP. الجلسات في جدول `sessions` المشترك. |
| 2 | **الكاش وعدّاد الإصدارات** | `config/cache.php:18`، `Modules/Product/app/Service/ProductCacheService.php:11,19,60` (`products:cache:version`) | مع `CACHE_STORE=file`، كل نسخة ترى إصداراً مختلفاً؛ تحديث منتج من نسخة لا يُبطل كاش النسختين الأخريين → بيانات قديمة | `CACHE_STORE: redis` صراحةً في compose. مفتاح الإصدار والـ TTL مشتركان بين كل النسخ. |
| 3 | **معدّل الطلبات / rate limiting** | Laravel `throttle` يستخدم نفس الـ cache store؛ رغد أضافت `RateLimiter::for('login')` | مع file cache يصبح حدّ كل نسخة مستقلاً (3 أضعاف الحد المقصود، وتجاوز الحماية) | نفس Redis مشترك → حدّ واحد فعّال عبر كل النسخ. لا يوجد channel limiter أصلاً. |
| 4 | **الصور المرفوعة** | `Modules/Product/app/Service/ProductService.php:42`، `Modules/Product/app/Models/Product.php:36`، `docker/nginx.conf:13` | لو كانت `storage/app/public` محلية لكل حاوية، صورة تُررفع عبر نسخة تعطي 404 من نسخة أخرى | حجم `product_images` مشترك صراحةً بين النسخ. (في الإنتاج يُستبدل بـ S3/object storage.) |
| 5 | **كود/مجلدات قابلة للكتابة** | كان في compose: `.:/var/www/html` | كل النسخ تكتب Blade views و logs و `bootstrap/cache` على نفس مجلد المضيف؛ كاش قديم في `bootstrap/cache` يجعل `/instance` يعطي 404 ويجعل env الخاص بـ Compose غير مُطبّق | حُذف الـ bind mount؛ الكود مخبوز داخل الصورة، و `config:clear && route:clear && view:clear` في الـ Dockerfile. كل نسخة لها framework views مستقلة (مشتقة وقابلة لإعادة التوليد). |
| 6 | **مفتاح التشفير APP_KEY** | `.env` | لو اختلف APP_KEY بين نسخة وأخرى، الكوكيز/التوقيعات/التشفير تفشل عشوائياً | `.env` يُركّب readonly في كل حاوية + `${APP_KEY:?...}` يفشل سريعاً لو غير موجود. `.env` مستبعد من الصورة عبر `.dockerignore`. |
| 7 | **إعداد RabbitMQ** | `routes/console.php:22-57` | `rabbitmq:setup` في كل عامل = سباق + `PRECONDITION_FAILED` يقتل عامل بصمت | خدمة `rabbitmq-setup` مرة واحدة، العمّال ينتظرون `service_completed_successfully`. |
| 8 | **WebSocket / Reverb** | `frontend/src/lib/echo.js:25`، `routes/api.php` (`/api/broadcasting/auth`) | اتصال WebSocket حالة خاصة بعقدة Reverb؛ لكن مصادقة القناة نفسها stateless JWT ولا تعتمد على نسخة التطبيق | ✅ لا يوجد state محلي في نسخ التطبيق نفسها. ⚠️ Reverb ما زال عقدة واحدة (نقص مُعلن في §7). |
| 9 | **رأس معرّف النسخة** | `app/Http/Middleware/BackendInstanceHeader.php` | الـ middleware يعمل بعد `$next()`؛ responses المولّدة من exception renderers في `bootstrap/app.php` قد لا تحمل الرأس | مقبول كأداة قياس فقط؛ لا يُستخدم في منطق أمني. يُنصح بإزالته على الحدّ الخارجي في الإنتاج (تسريب hostname داخلي). |
| 10 | **CORS** | `config/cors.php` في PR | مسارات `broadcasting/*` غير فعّالة (الحقيقي `/api/broadcasting/auth` مغطى بـ `api/*`)، والـ origins ثابتة على port 5173 | جعلناها env-driven، أضفنا `instance`، و أبقينا `X-Backend-Instance` في `exposed_headers`. |

**قاعدة ذهبية للمقابلة:** أي شيء يُكتب داخل الحاوية ولا يُشارك → إما أن يصبح مشتركاً (DB/Redis/Volume) أو يصبح derived/ephemeral (compiled views). لا تضع session ولا cache ولا عدّاداً في قرص الحاوية.

> تنظيف: `config/channel_limits.php` كان غير متتبّع ولا يقرأه أي كود، فحُذف محلياً. حل رغد في `main` هو `/api/broadcasting/auth` + rate limiting للّوغن فقط؛ **ما في أي channel limiter** في `main` أو في فرع رزان.

---

## 5) الإثبات (Proof)

### 5.1 الأوامر

```powershell
# إعادة بناء الصورة بعد تغييرات healthcheck / Dockerfile
docker compose down --remove-orphans
docker compose build backend

# الإثبات الكامل: 500 طلب + إيقاف نسخة + فحص العمّال
powershell -ExecutionPolicy Bypass -File .\scripts\verify-scalability.ps1
```

أو يدوياً:

```powershell
docker compose up -d --build --wait --scale backend=3 --scale rabbitmq-worker=3
docker compose ps

# توزيع 500 طلب
k6 run --out json=tests/k6/results/distribution.json `
  --env API_BASE_URL=http://localhost:8080 `
  --env TEST_ITERATIONS=500 --env TEST_VUS=10 `
  tests/k6/load-balancer-test.js

# أثناء تشغيل اختبار الاستمرارية، أوقف نسخة واحدة فقط (ليس كل الخدمة):
$id = (docker compose ps -q backend | Select-Object -First 1)
docker stop $id
docker start $id   # للرجوع بعد الاختبار
```

> ملاحظة: `deploy.replicas: 3` في compose و `--scale backend=3` كلاهما موجودان؛ عند التشغيل بالـ `--scale` يأخذ الأمر النهائي الأولوية، وكلاهما ينتهي إلى 3. لا تُعيّن `--scale backend=1` عن طريق الخطأ.

### 5.2 ما الذي يفحصه `scripts/verify-scalability.ps1` فعلياً

1. يرفع 3 نسخ `backend` و3 `rabbitmq-worker` ويتحقق من `docker compose ps`.
2. يرسل **500 طلب** عبر Nginx ويجمع `backend_requests` مع tag = hostname.
3. **يفشل** إذا ظهر أقل من 3 hostnames مختلفة (هذه هي المشكلة التي كان سكربت الـ PR يتجاهلها).
4. يبدأ `failover-test.js` (طلبات مستمرة 45s)، وبعد 8 ثوانٍ يوقف **حاوية واحدة** بـ `docker stop`، ثم:
   - يفشل إذا كان `ExitCode` الخاص بـ k6 ≠ 0 (Thresholds: `http_req_failed rate==0` و `checks rate==1`).
   - يفشل إذا وُجد أي Point للـ metric `http_req_failed` بقيمة 1.
   - يفشل إذا لم تظهر نسخة ثانية في الـ tags بعد الإيقاف.
5. يُعيد تشغيل الحاوية ويطبع الحالة النهائية.

نتائج k6 الخام تُحفظ (مستبعدة من Git) في `tests/k6/results/`.

> إصلاح تقني أثناء الإثبات: `Start-Process -NoNewWindow` قد يُرجع `ExitCode` فارغاً حتى مع k6 ناجح؛ السكربت يعتمد الآن على `Refresh()` + تحقق آمن من الصفر + الـ points/Thresholds، فلا يرفع استثناءً كاذباً.

### 5.3 النتائج الفعلية على هذه الآلة

> نُفّذ السكربت بعد إصلاح healthcheck الخاص بـ RabbitMQ (يفحص listener على 5672 بدل `ping`)،
> والمخرجات الخام محفوظة في `tests/k6/results/` (مستبعدة من Git).

| الفحص | النتيجة |
|---|---|
| `docker compose ps` → عدد `backend` | 3 (كلها healthy) |
| `docker compose ps` → عدد `rabbitmq-worker` | 3 (كلها healthy) |
| 500 طلب → توزيع على replicas | `2b1f8054b097 = 171`، `addd968d7167 = 169`، `e6324e747720 = 160` (المجموع 500، ثلاثة hostnames مختلفة) |
| فحص الـ 500 طلب | `http_req_failed = 0/500`، `checks = 1000/1000`، p95 ≈ 122ms |
| إيقاف نسخة أثناء الضغط (45s) | `http_req_failed = 0/4463`، `checks = 4463/4463`، p95 ≈ 66ms |
| بعد الإيقاف | نسختان بقيتا تخدمان الطلبات، ثم أُعيدت النسخة الثالثة |

**السطر الأخير من الإثبات:**
```
PASS: distribution across 3 replicas, 3 workers, and zero-downtime failover.
```

### 5.4 هل في اختبار يدوي من الواجهة ولا بس سكربتات؟

**الجواب الصادق: الإثبات المسجّل هنا سكربتات فقط + أوامر Docker. ما سجّلتُ جولة يدوية بالمتصفح.** والسبب أن الواجهة لا تستطيع إثبات ما يهم المتطلب: عدد الـ hostnames مختلفة، وصفر طلب فاشل أثناء إيقاف نسخة. هذه تُقاس بـ k6.

الواجهة تصلح كـ **demonstration** اختياري أمام الممتحن:

1. شغّل الـ stack: `powershell -ExecutionPolicy Bypass -File .\scripts\verify-scalability.ps1` (أو `docker compose up -d --build --scale backend=3 --scale rabbitmq-worker=3`).
2. افتح `http://localhost:5173` وسجّل دخول. افتح DevTools → Console: كل استدعاء API يسجّل `GET /products handled by: <hostname>` (هذا من middleware/rzan + `api.js`) — ترى أكثر من hostname واحد.
3. نفّذ عدة مرات `curl http://localhost:8080/instance` (أو افتح الرابط): كل مرة يعطي `hostname` مختلفاً غالباً. هذا هو مسار رزان `/instance`.
4. أوقف نسخة واحدة:
   ```powershell
   $id = (docker compose ps -q backend | Select-Object -First 1)
   docker stop $id
   ```
   حدّث قائمة المنتجات / أنشئ طلباً؛ الواجهة تكمل، والـ Console يعرض hostname من النسختين الباقيتين، وNetwork لا يُظهر 5xx. ثم `docker start $id`.
5. **حدود الاختبار اليدوي:** `frontend/src/services/api.js` ما زال يستخدم `baseURL: "http://localhost:8080/api"` (يشتغل محلياً لأنه يمرّ عبر LB)، و`echo.js` يستخدم `VITE_REVERB_*`/6001. في الإنتاج يجب أن تصبح هذه env-driven وتوضع خلف API gateway/LB. لا تعتمد على الـ console كدليل أمني ولا كبديل عن k6.

> الخلاصة للمقابلة: «الاختبار اليدوي يثبت أن المستخدم ما لاحظ الفشل؛ السكربت يثبت أن التوزيع والـ failover حصلا فعلاً بأرقام.»

---

## 6) قبل الإصلاح / بعد الإصلاح — ملخص للمقابلة

| المشكلة | قبل (main + PR) | بعد (yassmin) |
|---|---|---|
| عدد النسخ | 1 (رغم وجود Load Balancer) | 3 عبر `deploy.replicas` + `--scale` |
| استراتيجية التوزيع | default round robin بلا تبرير | `least_conn` + إعادة حل DNS + passive health checks |
| failure handling | لا يوجد هدف بديل | `proxy_next_upstream` + healthcheck + `restart: unless-stopped` |
| العمّال | 1 + `rabbitmq:setup` داخل كل عامل | 3 + setup مرة واحدة + prefetch=1 |
| بدء الخدمة | RabbitMQ يُعلن healthy قبل فتح 5672 → `Connection refused` | healthcheck يفحص `check_port_listener 5672` |
| إثبات 500 طلب | يتحقق من HTTP 200 فقط | يتحقق من 3 hostnames مختلفة + صفر فشل |
| إثبات الاستمرارية | غير موجود | إيقاف حاوية أثناء k6 مع threshold صفر فشل |
| local state | bind mount كامل + cache/session قابلة للتضارب | DB sessions + Redis cache + image code + shared volume للصور |
| APP_KEY | ضمن .env متعدد النسخ بدون فحص | fail-fast + readonly mount + خارج الصورة |
| CORS | ثابت وفيه مسارات عديمة الجدوى | env-driven + `instance` + expose header |
| التوثيق | لا يوجد | هذا الملف + سكربت الإثبات |

---

## 7) أسئلة مقابلة متوقعة وإجابات مختصرة

**س: لماذا لا تحتاج sticky sessions؟**
ج: لأننا لا نحفظ حالة المستخدم في ذاكرة/قرص النسخة. JWT يُتحقق منه في كل طلب، والجلسات في MySQL، والكاش/الـ rate limits في Redis. لذلك أي نسخة تخدم الطلب التالي بالتساوي.

**س: لماذا `least_conn` وليس `round_robin`؟**
ج: `round_robin` يوزّع بعدد الطلبات لا بحملها. قائمة المنتجات قد تصيب Redis (سريع) أو MySQL/gRPC (أبطأ)؛ `least_conn` يرسل التالي للأقل اتصالات نشطة فيمنع تراكم الطلبات خلف نسخة مشغولة.

**س: ماذا يحدث لو ماتت نسخة أثناء الطلب؟**
ج: Nginx يكتشف `error/timeout/5xx` عبر `proxy_next_upstream` ويعيد الطلب لنسخة أخرى خلال `proxy_next_upstream_tries 3`. الفحص السلبي يستبعدها بعد فشلين خلال 5 ثوانٍ، وDocker DNS يُحدّث القائمة خلال ≤5 ثوانٍ. Nginx OSS ما عنده active health checks؛ هذا موثّق وليس ادعاءً.

**س: إذا وقّفت المعيد (replica) قدامكم، السيرفر يفشل؟**
ج: يعتمد على **ماذا** أوقف بالضبط — وهذه هي الإجابة الكاملة:

| إجراء المعيد | ما الذي يحدث فعلياً | هل عالجناها؟ |
|---|---|---|
| `docker stop <backend-id>` لنسخة واحدة | الحاوية تبقى متوقفة (لأن `unless-stopped` لا يعيد ما أُوقف يدوياً). Docker DNS يشيلها، و`least_conn` + `proxy_next_upstream` يحوّلان للنسختين الباقيتين خلال ≤5s. إثباتنا: 4463 طلب بصفر فشل. للرجوع: `docker start $id` أو `docker compose up -d --scale backend=3`. | ✅ نعم، ومُثبتة في §5.3. |
| موت العملية داخل الحاوية (crash) | `restart: unless-stopped` يعيد الحاوية تلقائياً؛ الـ LB يتجاوزها لحين العودة. | ✅ نعم عبر الـ restart policy والفحص السلبي (لم نُجرب crash صريحاً، لكن سلوك Docker معروف). |
| `docker compose up -d --scale backend=2` | Compose يتوافق مع العدد الجديد؛ نسختان تخدمّان. | ✅ نعم، إعادة حل DNS تتكيف. |
| `docker compose stop backend` **أو** إيقاف الثلاث يدوياً | **انتبه:** أمر `compose stop` يوقف كل حاويات الخدمة، لا واحدة. يصير ما فيه أي upstream → Nginx يرجع **502**، و healthcheck الـ LB (`/up` المُ proxied) يصير unhealthy. `unless-stopped` لا يعيدها لأن الإيقاف صريح. | ❌ غير معالجة — حالة توقف كامل متعمدة ولا يوجد ما يخدم الطلب. تحتاج `docker compose up -d` أو orchestrator (Swarm/K8s) يعيد العدد المرغوب. |
| `docker compose up -d --scale backend=0` | نفس نتيجة إيقاف الكل: 0 خلفاء → 502. | ❌ غير معالجة (ما أضفنا حدّاً أدنى أو صفحة صيانة). |
| `docker rm -f <backend-id>` | الحاوية تختفي ولا يعيدها Compose تلقائياً؛ تحتاج `docker compose up -d` أو orchestrator. | ⚠️ جزئياً؛ سكربت الإثبات يعيد الـ stack كاملاً من الصفر. |
| إيقاف `loadbalancer` نفسه | المنفذ 8080 يموت حتى لو الـ backends سليمة. | ❌ SPoF منفصل خارج نطاق المتطلب، موثّق في سؤال HA أدناه. |

**الخلاصة للمقابلة:** «عالجنا فشل نسخة واحدة وأثبتناه بصفر فشل. أما إيقاف كل النسخ متعمداً فما في معالجة سحرية — التطبيق انعدم؛ الحل إما إعادة رفع الـ compose أو orchestrator يحافظ على desired state. لو الـ LB نفسه توقف فهذا SPoF آخر.»

**س: كيف تتأكد أن 500 طلب وزّعت فعلاً؟**
ج: `/instance` و `X-Backend-Instance` يُرجعان `gethostname()`؛ نضعه كـ tag في k6 ثم نعدّ الـ hostnames المختلفة في مخرجات k6 JSON. السكربت يفشل إن لم يجد 3. لا يكفي فحص status 200.

**س: كيف تتقاسم العمال الطابور بدون تكرار المعالجة؟**
ج: RabbitMQ يوزّع الرسائل على المستهلكين (competing consumers). `prefetch=1` يضمن أن العامل لا يأخذ أكثر من رسالة قبل ack، والـ ack/DLQ يمنع الفقد. لكن التسليم **at-least-once**؛ لذلك معالج الإيميل يجب أن يكون idempotent.

**س: ليش نقلت `rabbitmq:setup` خارج العمّال؟**
ج: كان كل عامل ينفّذ declarations عند البدء؛ مع 3 عمّال يصير سباق، وإذا كانت الطابور موجودة بوسائط DLQ مختلفة يرجع `PRECONDITION_FAILED` وبسبب `&&` لا يبدأ المستهلك → "3 عمال" تصبح 2 بصمت. صارت خدمة one-shot ينتظرونها.

**س: شو الفرق بين `deploy.replicas` و `--scale`؟**
ج: `deploy.replicas` يعلن العدد في الملف (يُطبّق مع `docker compose up` في الإصدارات الحديثة/Docker Compose)، و`--scale` يستبدلها وقت التشغيل. استخدمنا الاثنين متوافقين على 3. المهم أن الاسم `backend` يبقى دون `container_name` حتى تتولّد أسماء مختلفة.

**س: ماذا لو مات عامل طابور واحد؟**
ج: طلبات HTTP لا تتأثر؛ يتولى العاملان الباقيان خدمة الطابور (prefetch=1 يمنع حجز رسائل كثيرة من عامل ساقط). لو مات كل العمّال تتراكم الرسائل في الطابور حتى يعودوا — هذه backpressure، لا فقد.

**س: ما هي نقاط الضعف الأمنية التي راجعتها؟**
ج: (1) منع secrets داخل الصورة، (2) منع تضارب APP_KEY، (3) توحيد مخزن الجلسات/الكاش حتى لا يتجاوز المهاجم حدّ المعدل بتوزيع طلباته على 3 نسخ، (4) CORS env-driven وبدون credentials لأننا نستخدم Bearer، (5) رأس معرّف النسخة للقياس فقط وليس للقرارات الأمنية، ويُزال على الحدّ في الإنتاج.

**س: هل النظام كامل HA الآن؟**
ج: HTTP و workers نعم ضمن حدود الاختبار. ما زالت نقاط فشل مفردة (SPoF): `loadbalancer` واحد، `rabbitmq` واحد، `mysql-primary` واحد، `redis` واحد، `reverb` واحد، و `order-email-outbox-publisher` واحد. كذلك `frontend/src/services/api.js` ما زال يستخدم `http://localhost:8080/api` و `echo.js` يستخدم `localhost:6001`؛ في الإنتاج يجب أن تصبح هذه env-driven وتوضع خلف LB/API gateway.

**س: هل عملت اختباراً يدوياً من الواجهة؟**
ج: الإثبات الرسمي سكربتات، لأنها الوحيدة التي تقيس التوزيع والـ failover بأرقام. جولة المتصفح ممكنة وموضّحة في §5.4، لكن لم تُسجّل كخطوة إثبات.

---

## 8) معجم المفاهيم المطلوبة للمقابلة

| المفهوم | تعريف بصيغة مقابلة | أين ظهر في مشروعنا |
|---|---|---|
| **Horizontal scaling** | زيادة عدد النسخ/الحاويات لمعالجة طلبات أكثر. يقابلها **Vertical scaling** (زيادة CPU/RAM لنسخة واحدة). | `backend` و `rabbitmq-worker` → 3. |
| **Load balancer / Reverse proxy** | نقطة دخول واحدة تُوزّع الطلبات على خوادم خفية وتخفي عناوينها الداخلية. | Nginx على 8080 بدل تعريض `backend`. |
| **L4 vs L7** | L4 يوزّع على TCP/UDP؛ L7 يفهم HTTP (routes, headers). استخدمنا L7. | `proxy_set_header` و `/instance`. |
| **Upstream** | مجموعة السيرفرات التي يوجّه إليها Nginx. | `upstream store_backends`. |
| **DNS round-robin (Docker DNS)** | اسم الخدمة يُرجع عدة سجلات A؛ Docker يعيد ترتيبها/تحديثها عند تغيّر النسخ. | `server backend:80 resolve`. |
| **`least_conn`** | يختار العقدة الأقل اتصالات نشطة؛ مناسب حين تختلف تكلفة الطلب. | بدل الافتراضي في الـ PR. |
| **`round_robin`** | بالتناول بعدد الطلبات بغض النظر عن انشغال العقدة. | كان افتراضي الـ PR؛ تجنّبناه. |
| **`ip_hash` / Sticky sessions** | ربط العميل بنفس العقدة. يُستخدم عند وجود state محلي. | لا نحتاجه: JWT + DB + Redis. |
| **Stateless app** | لا يحتوي قرص/ذاكرة النسخة على حالة مستخدم حاسمة. | أي replica تخدم أي طلب. |
| **JWT Bearer** | توكن موقّع يحمل الهوية؛ يُتحقق منه بدون جلسة خادم. | `Authorization: Bearer` في `api.js` و `echo.js`. |
| **Shared session store** | الجلسات في قاعدة بيانات مشتركة بدل ملفات الحاوية. | `SESSION_DRIVER=database`. |
| **Shared cache store** | كاش واحد لكل النسخ (Redis) حتى لا تختلف النتائج. | `CACHE_STORE=redis` + `products:cache:version`. |
| **Rate limiter مركزي** | عدّاد المحاولات في مخزن مشترك، لا داخل كل عملية. | `RateLimiter::for('login')` من رغد + Redis موحّد. |
| **Cache invalidation / version key** | تغيير مفتاح الإصدار يُبطل كل كاش المنتجات دفعة واحدة. | `ProductCacheService`. |
| **Immutable image / Baked code** | الكود يُنسخ داخل الصورة عند البناء، لا يُركّب من المضيف وقت التشغيل. | حذفنا `.:/var/www/html`. |
| **Derived / ephemeral state** | ملفات يمكن إعادة توليدها (compiled views) ولا مشكلة لو فقدت. | `view:clear` + مجلدات framework داخل الحاوية. |
| **Shared volume vs Object storage** | Volume مشترك يكفي محلياً؛ S3 أفضل إنتاجاً للصور. | `product_images`. |
| **Fail-fast config** | إيقاف التشغيل فوراً لو غاب متغير ضروري بدل تشغيل نسخ بقيمة خاطئة. | `${APP_KEY:?...}`. |
| **12-factor config** | الإعدادات من env، لا من كود/ملفات مختلفة لكل نسخة. | `CORS_ALLOWED_ORIGINS`, `APP_KEY`, `REDIS_HOST`. |
| **Active health check** | LB يفحص النسخ بشكل استباقي (ميزة Nginx Plus غالباً). | غير متوفر في Nginx OSS. |
| **Passive health check** | LB يكتشف الفشل من الطلبات نفسها. | `max_fails=2 fail_timeout=5s`. |
| **`proxy_next_upstream`** | إعادة الطلب لعقدة أخرى عند error/timeout/5xx محدد. | أثناء إيقاف النسخة. |
| **`resolve` + `zone` + `valid`** | Nginx يعيد حلّ اسم الـ upstream كل 5 ثوانٍ بدون reload. | `resolver 127.0.0.11 ... valid=5s`. |
| **Keepalive upstream** | إبقاء اتصالات مفتوحة لتقليل تكلفة handshake. | `keepalive 32`. |
| **Competing consumers** | عدة عمّال يسحبون من نفس الطابور؛ كل رسالة تذهب لواحد فقط. | 3 `rabbitmq-worker`. |
| **`prefetch-count` / QoS** | أقصى عدد رسائل غير مُعترف بها لكل مستهلك. | `prefetch=1` للتوزيع العادل. |
| **ack / nack / requeue** | المستهلك يؤكد نجاح المعالجة أو يعيد الرسالة. | منع الفقد عند سقوط عامل. |
| **Dead Letter Queue (DLQ)** | طابور للرسائل التي فشلت عدة محاولات. | `--tries=3` + إعداد DLQ في `rabbitmq:setup`. |
| **At-least-once vs Exactly-once** | تسليم at-least-once قد يكرر الرسالة؛ exactly-once أصعب. المعالج يجب أن يكون idempotent. | إيميل الطلب. |
| **One-shot init service** | مهمة تهيئة تعمل مرة واحدة ثم تنتهي. | `rabbitmq-setup`. |
| **Race condition / PRECONDITION_FAILED** | تنفيذ declarations متوازٍ بوسائط مختلفة يرفضه RabbitMQ. | سبب نقل setup. |
| **Backpressure** | الطابور يمتصّ ذروة الطلبات بدل إسقاطها. | وظيفة RabbitMQ مع العمّال. |
| **SPoF (Single Point of Failure)** | مكوّن واحد لو توقف توقف النظام. | LB/RabbitMQ/MySQL/Redis/Reverb موثّقة. |
| **HA (High Availability)** | النظام يبقى متاحاً عند فشل جزء منه. | متحقق لنطاق HTTP+workers فقط. |
| **Failover vs Fault tolerance** | Failover = تحويل العمل لبديل؛ Fault tolerance = استمرار بدون توقف ملحوظ. | قياسنا 0 فشل ⇒ تجربة fault tolerance للعميل. |
| **p95 latency** | الزمن الذي لا يتجاوزه 95% من الطلبات؛ أفضل من المتوسط للذيل. | p95 ≈ 122ms / 66ms. |
| **Throughput / error rate / checks** | عدد الطلبات/ثانية، نسبة الفشل، ونسبة الشروط الناجحة. | 133 req/s، 0% failed، 100% checks. |
| **k6 VUs / iterations / thresholds** | مستخدمون افتراضيون، عدد التكرارات، وشروط نجاح/فشل تلقائية. | `TEST_VUS`, `TEST_ITERATIONS`, `rate==0`. |
| **Observability header** | رأس يُستخدم لقياس أي نسخة خدمت الطلب فقط. | `X-Backend-Instance` (رزان). |
| **CORS `exposed_headers`** | يسمح للـ JS بقراءة رأس مخصص من الاستجابة. | أضفناه لـ `X-Backend-Instance`. |
| **Synthetic vs Manual test** | Synthetic = حمل مبرمج قابل للقياس؛ Manual = جولة مستخدم بالواجهة. | إثباتنا synthetic؛ اليدوي موضّح في §5.4. |

---

## 9) الملفات النهائية

**كتبها رزان واعتمدناها:** `app/Http/Middleware/BackendInstanceHeader.php`, `bootstrap/app.php`, `routes/web.php`, `frontend/src/lib/echo.js`, `frontend/src/services/api.js`, `composer.json`, `composer.lock`.

**عدّلناها نحن (10 ملفات في الكومت الحالي):**
```
README-REQUIREMENT-5.md        # هذا الملف + المقارنة + المفاهيم
docker-compose.yml             # replicas, healthchecks, setup service, env hardening
Dockerfile                     # curl + clear stale caches + php perf ini
docker/loadbalancer.conf       # least_conn, DNS resolve, passive failure handling
config/cors.php                # env origins, /instance, exposed header
tests/k6/load-balancer-test.js # tagged counter + backend assertions
tests/k6/failover-test.js      # continuous traffic during replica stop
tests/k6/scalability-read.js   # env base URL + strict thresholds
scripts/verify-scalability.ps1 # end-to-end proof runner
.gitignore                     # ignore k6 raw results
```

**حُذف محلياً:** `config/channel_limits.php` (غير مستخدم).
**تركناه كما هو:** `frontend/src/services/authStorage.js` (ملفك غير المتتبّع).

---

## 10) حالة Git النهائية — بدون push

```powershell
git log --oneline --decorate -3
# <commit> (HEAD -> yassmin, requirement-5-fix) fix(requirement-5): enable 3 replicas and verify load balancing
# 1b82fb4 (origin/Razan) scale RabbitMQ consumers to three replicas
# d57a548 show backend instance for API requests

git status --short
# ?? frontend/src/services/authStorage.js   (ملفك، لم نلمسه)
```

- دمجنا الـ PR محلياً بـ `git merge --ff-only origin/Razan` ثم أضفنا إصلاحاتنا.
- **لا يوجد push، ولا تحديث للـ PR على GitHub.** أي إرسال لاحق لرزان أو لـ `main` تقرره أنت.
- إذا طُلب منك في المقابلة أن تشرح الفرق بجملة: «رزان بنت الطريق؛ أنا جعلت خلفه 3 نسخ و3 عمّال، ووحّدت الحالة المشتركة، وأثبتّ أنه ما فيه downtime بـ k6.»
