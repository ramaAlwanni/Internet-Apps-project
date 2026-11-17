# توثيق Pull Request #25 — RabbitMQ & Async Email Reliability

> ملف توثيقي مستقل تم إعداده بناءً على محتوى الـ PR المباشر من GitHub وعلى فحص فعلي لشجرة Git محلياً (بما في ذلك محاكاة الدمج لمعرفة التعارضات).
> لا يقوم هذا الملف بتعديل أو استبدال `README.md` أو `README-REQUIREMENT-2.md` الموجودين.

---

## 1) معلومات الـ PR

| العنصر | القيمة |
|---|---|
| رقم الـ PR | `#25` |
| العنوان | `feat(rabbitmq): implement event publishing, background worker, and DLQ reliability for async emails` |
| الحالة | مفتوح (Open)، لم يُدمج بعد |
| الفرع المصدر | `Rama` |
| الفرع الهدف | `main` |
| الـ commit | `f5e508095c16b63004db74a493476ed2d18ab4f0` |
|عدد الـ commits | 1 |
| التغييرات | `+1,303` / `-70` عبر 15 ملفاً |
| تاريخ الـ commit | 17 نوفمبر 2026 |

رابط الـ PR: https://github.com/ramaAlwanni/Internet-Apps-project/pull/25

---

## 2) الهدف من الـ PR

تحويل إرسال رسالة تأكيد الطلب من عملية متزامنة (داخل طلب HTTP) إلى **معالجة غير متزامنة عبر RabbitMQ**، مع إضافة آليات موثوقية:

- نشر مهمة إرسال البريد عند إنشاء الطلب بدلاً من إرساله مباشرة.
- عامل (Worker) يستهلك الرسائل من الطابور.
- إعادة المحاولة (Retries) عند الفشل.
- Dead Letter Queue (DLQ) للرسائل التي فشلت نهائياً.
- "Parking Lot" كمرحلة أخيرة قبل تدخل المطوّر.
- أوامر Artisan لاستعادة الرسائل وإعادتها للطابور.
- جدولة (Scheduler) لمحاولة الاستعادة تلقائياً يومياً.
- دليل تشغيل عربي مفصّل (`RABBITMQ_QUEUE_GUIDE.md`).

> ملاحظة رغم عنوان الـ PR يذكر "event publishing"، التنفيذ الفعلي يعتمد على **Laravel Job** اسمه `SendOrderEmailJob` وليس على Event، باستثناء أن فرع `yassmin` هو الذي يضيف الأحداث (`OrderCreatedEvent` / `OrderStatusUpdatedEvent`).

---

## 3) قائمة الملفات المعدّلة بالتفصيل

### 3.1 ملفات التهيئة والإعداد

| الملف | التغيير | التفاصيل |
|---|---|---|
| `.env.example` | `+18 / -8` | تغيير `QUEUE_CONNECTION` من `database` إلى `rabbitmq`؛ إضافة `RABBITMQ_HOST`, `PORT`, `USER`, `PASSWORD`, `VHOST`, `QUEUE`؛ تغيير `MAIL_MAILER` من `log` إلى `smtp` مع إعدادات Gmail؛ إضافة `DEVELOPER_ALERT_EMAIL`. |
| `composer.json` | `+2 / -1` | إضافة الحزمة `"vladimir-yuldashev/laravel-queue-rabbitmq": "^15.0"` (مشغّل RabbitMQ الخاص بـ Laravel Queue). |
| `composer.lock` | `+411 / -1` | تحديث `content-hash` وإضافة حزم: `php-amqplib/php-amqplib`, `phpseclib/phpseclib`, `paragonie/constant_time_encoding`, `symfony/polyfill-php82`, `vladimir-yuldashev/laravel-queue-rabbitmq`. |
| `config/queue.php` | `+64` | إضافة connection جديد باسم `rabbitmq` مع `hosts`, `ssl_options`, وثلاثة طوابير معلنة: `order_emails_queue`, `order_emails_dlq`, `order_emails_parking_lot` مع وسيطات `x-dead-letter-exchange` / `x-dead-letter-routing-key`. |
| `phpunit.xml` | `+1 / -1` | تعليق سطر `<env name="QUEUE_CONNECTION" value="sync" force="true"/>` حتى لا تُجبر الاختبارات على الـ sync (أي أن الاختبار الفعلي يمر عبر RabbitMQ). |

### 3.2 منطق التطبيق (Backend)

| الملف | التغيير | التفاصيل |
|---|---|---|
| `Modules/Order/app/Services/OrderService.php` | `+12` | استيراد `App\Jobs\SendOrderEmailJob` و `Illuminate\Support\Facades\Auth`؛ داخل `DB::transaction` وبعد إنشاء عناصر الطلب يتم تنفيذ: `SendOrderEmailJob::dispatch([...])->onQueue('order_emails_queue')` مع البيانات: `order_id`, `user_email`, `user_name`, `total_price`, `items`. |
| `Modules/Order/app/Http/Controllers/OrderController.php` | `+42 / -59` | تنظيم الـ imports (إزالة استيراد مكرر لـ `Controller`)، تكثيف قواعد التحقق (validation) في سطر واحد، استخدام `successResponse()` في `store()` برسالة «Order created successfully. A confirmation email will be sent shortly.»، ضبط مسافة `updateStatus()` وترجمة التعليقات من العربية إلى الإنجليزية وإزالة قوس زائد. **لا تغيير وظيفي في الصلاحيات/الـ Policy.** |

### 3.3 الوظائف والرسائل الجديدة (Files Added)

| الملف | الحجم | الوظيفة |
|---|---|---|
| `app/Jobs/SendOrderEmailJob.php` | `+66` | Job يطبّق `ShouldQueue`؛ `tries = 3`؛ `backoff = [1,2,3]`؛ في `handle()` يرسل `OrderConfirmationMail` ويسجّل في اللوج؛ في `failed()` يسجّل `critical` ويرسل `ParkingLotAlertMail` إلى `DEVELOPER_ALERT_EMAIL`. |
| `app/Mail/OrderConfirmationMail.php` | `+25` | Mailable بعنوان `Order Receipt Confirmation #<id>` ومحتوى HTML بسيط (الاسم، رقم الطلب، المبلغ). |
| `app/Mail/ParkingLotAlertMail.php` | `+46` | Mailable تنبيه للمطوّر يحتوي رقم الطلب واسم الـ Job وسبب الفشل. |
| `app/Console/Commands/RecoverFailedEmailsCommand.php` | `+30` | أمر `emails:recover-dlq`: يستدعي `rabbitmq:consume --queue=order_emails_dlq --tries=3 --stop-when-empty`. |
| `app/Console/Commands/ReplayParkingLotEmailsCommand.php` | `+30` | أمر `emails:replay-parking-lot`: يستدعي `rabbitmq:consume --queue=order_emails_parking_lot --tries=3 --stop-when-empty`. |
| `tests/Feature/OrderEmailQueueTest.php` | `+37` | اختبار واحد `test_1_dispatch_real_message_to_rabbitmq` يرسل payload تجريبي for الطلب `#1001` إلى `order_emails_queue` ثم يتحقق بـ `assertTrue(true)`. |
| `RABBITMQ_QUEUE_GUIDE.md` | `+515` | دليل عربي من 10 أقسام: المفاهيم، سيناريو الشراء، سيناريو توقف الـ worker، سيناريو الفشل والـ DLQ والـ Parking Lot، شرح الملفات، إعداد `.env`، Docker Compose مع خدمة `rabbitmq` و `rabbitmq-worker`، سيناريوهات العرض (Demo)، أوامر سريعة، وخلاصة. |

### 3.4 الجدولة

| الملف | التغيير | التفاصيل |
|---|---|---|
| `routes/console.php` | `+4` | استيراد `Illuminate\Support\Facades\Schedule` وإضافة `Schedule::command('emails:recover-dlq')->dailyAt('00:00');` (السطر الذي أشرت إليه في رابط الـ diff). |

> لتفعيل الجدولة يحتاج الخادم إلى cron واحد: `* * * * * php artisan schedule:run >> /dev/null 2>&1`.

---

## 4) المخطط التشغيلي (Flow)

```
POST /orders
   │
   ├─ OrderService::createOrder()  (داخل DB::transaction)
   │       └─ SendOrderEmailJob::dispatch()->onQueue('order_emails_queue')
   │
   ▼
order_emails_queue  ──(worker: rabbitmq:consume --tries=3 --prefetch-count=1)──►  إرسال SMTP
   │                                                                              │
   │ فشل 3 مرات                                                                   └─ نجاح → Log::info
   ▼
order_emails_dlq  ──(emails:recover-dlq / Scheduler 00:00)──► إعادة المحاولة
   │
   │ فشل نهائي
   ▼
order_emails_parking_lot  ──(emails:replay-parking-lot)──► رسالة تنبيه للمطوّر + إعادة التشغيل بعد الإصلاح
```

أسماء الطوابير/الـ exchange:

- الطابور الرئيسي: `order_emails_queue`
- الـ Dead Letter: `order_emails_dlq` (عبر `orders_dlx`)
- Parking Lot: `order_emails_parking_lot` (عبر `parking_lot_exchange`)
- لوحة التحكم: `http://localhost:15672` (guest / guest)

---

## 5) طريقة التشغيل بعد الدمج

```bash
# 1) تثبيت الحزمة الجديدة
composer install

# 2) نسخ الإعدادات وضبطها
cp .env.example .env
php artisan config:clear

# 3) تشغيل RabbitMQ (إن كنت تستخدم Docker)
docker compose up -d rabbitmq
docker compose logs -f rabbitmq

# 4) تشغيل العامل
php artisan rabbitmq:consume --queue=order_emails_queue --tries=3 --prefetch-count=1

# 5) اختبار سريع
php artisan test tests/Feature/OrderEmailQueueTest.php

# 6) استعادة الرسائل الفاشلة
php artisan emails:recover-dlq
php artisan emails:replay-parking-lot

# 7) تفعيل الجدولة (مرة واحدة على السيرفر)
* * * * * php artisan schedule:run >> /dev/null 2>&1
```

داخل Docker يصبح `RABBITMQ_HOST=rabbitmq` بدل `127.0.0.1`، والدليل يوفّر خدمة `rabbitmq-worker` جاهزة.

---

## 6) تحليل الدمج مع فرع `yassmin` (مهم)

### 6.1 حالة الفروع كما تم فحصها فعلياً

| الفرع | آخر commit | ملاحظة |
|---|---|---|
| `origin/main` | `9b159d5` | أساس الـ PR #25 (دمج PR #23 سابقاً). |
| `origin/Rama` | `f5e5080` | رأس الـ PR #25. |
| `origin/yassmin` | `1252cd4` | عمل WebSocket/Reverb والإشعارات (قبل الـ commit المحلي غير المحفوظ). |
| merge-base بين `Rama` و `yassmin` | `88a5872` | أي أن الفرعين تفرّعا قبل عمل الـ websocket في `yassmin`. |

### 6.2 النتيجة

| الهدف من الدمج | النتيجة |
|---|---|
| دمج PR #25 في `main` (الهدف الرسمي على GitHub) | ✅ **نظيف تماماً** — `main` هو سلف مباشر لـ `Rama` (Fast-forward)، لا توجد تعارضات. |
| دمج PR #25 في `yassmin` مباشرة | ⚠️ **يوجد 3 تعارضات فعلية** (تم التحقق عبر `git merge-tree` ومحاكاة دمج حقيقية في worktree منفصل). |

### 6.3 الملفات التي تتحرك تلقائياً بلا تعارض

`.env.example`، `composer.json`، وكل الملفات الجديدة التسعة (`RABBITMQ_QUEUE_GUIDE.md`, الأوامر, الـ Jobs, الـ Mails, الاختبار).

### 6.4 التعارضات الثلاثة بالتفصيل

#### أ) `Modules/Order/app/Http/Controllers/OrderController.php` — مقطعان

1. **مقطع الـ imports (الأسطر 5–9 في ملف الدمج):**
   - `yassmin` يضيف `use App\Exceptions\BaseException;`
   - `Rama` يزيل الاستيراد المكرر `use App\Http\Controllers\Controller;` ويضيف `use App\Jobs\SendOrderEmailJob;`
   - ⚠️ نتيجة الدمج التلقائي تحتوي على استيرادين لـ `Controller` — يجب حذف أحدهما وإلا حدث خطأ PHP.
   - **الحل:** أبقِ `BaseException` (من `yassmin`) + `SendOrderEmailJob` (من `Rama`) + نسخة واحدة فقط من `Controller`.

2. **مقطع `store()` (الأسطر 70–85):**
   - `yassmin` يحتفظ بـ `return response()->json([...], 201);` بعد `successResponse()` (سطر غير قابل للوصول) ويضيف `catch (BaseException $e)` قبل `catch (Throwable $e)`.
   - `Rama` يحذف ذلك الـ return ويكتفي بـ `catch (Throwable)` مع رسالة «...A confirmation email will be sent shortly.»
   - **الحل:** احتفظ برسالة `Rama` و`successResponse()`، واحتفظ بـ `catch (BaseException)` قبل `catch (Throwable)` (ترتيبهما مهم)، واحذف الـ `return` القديم غير القابل للوصول.

#### ب) `Modules/Order/app/Services/OrderService.php` — مقطع واحد

- تعارض في الـ imports فقط: `yassmin` يضيف `App\Exceptions\InventoryUnavailableException`، و`Rama` يضيف `App\Jobs\SendOrderEmailJob` و `Auth`.
- **الحل:** احتفظ بالثلاثة معاً. بقية التغييرات (معالجة نفاد المخزون، `findSnapshotsByIds`، الأحداث، الـ pagination) تدمج تلقائياً دون تعارض.

#### ج) `composer.lock` — تعارض محتوى

- `yassmin` أضاف `laravel/reverb` و `pusher/pusher-php-server` (وتبعيات مثل `clue/redis-protocol`) وغيّر `content-hash`.
- `Rama` أضاف حزم RabbitMQ وغيّر `content-hash` ALSO.
- Git لا يستطيع دمج `content-hash` ولا مجموعتين مختلفتين من الحزم تلقائياً.
- **الحل الموصى به:** لا تحرّر الملف يدوياً. بعد حل `composer.json` (أبقِ **كل** الحزم من الطرفين)، نفّذ:

```bash
composer update --no-install
composer install
```

ليتم إعادة توليد الـ lock وhash بشكل متسق.

### 6.5 تحذير قبل الدمج: تعديلاتك المحلية غير المحفوظة

على فرع `yassmin` توجد حالياً **15 ملفاً معدّلاً و7 ملفات غير متتبعة** (من ضمنها تعديل على `OrderService.php` الخاص بمنطق انتقال الحالات وإرسال الحدث فقط عند التغيير، وعلى `bootstrap/app.php`, `docker-compose.yml`, وواجهة الإشعارات).

- لا يمكن بدء الدمج مع وجود تعديلات محلية؛ Git سيرفض بـ: *"Your local changes to the following files would be overwritten by merge: Modules/Order/app/Services/OrderService.php"*.
- قبل الدمج: إمّا `git add && git commit` لتلك التعديلات، أو `git stash push -u` ثم استرجاعها بعد الدمج.
- تعديلك المحلي في `updateStatus()` لا يتعارض مع الـ PR (الـ PR لم يلمس تلك الدالة)، لكن انتبه عند حل تعارض الـ imports إلى عدم فقدان منطق `$changed` و `Order::statuses()`.

### 6.6 خطوات دمج آمنة

```bash
git fetch origin
git checkout yassmin
git pull --ff-only

# احفظ عملك المحلي أولاً
git add . && git commit -m "feat(websocket): admin notifications and status guards"

# ابدأ الدمج
git merge origin/Rama --no-commit --no-ff

# حلّ التعارضات الثلاثة كما ورد أعلاه
git status
git add Modules/Order/app/Http/Controllers/OrderController.php \
        Modules/Order/app/Services/OrderService.php

composer update --no-install
git add composer.json composer.lock

php artisan test
git commit -m "Merge PR #25 (RabbitMQ async emails) into yassmin"
```

---

## 7) ملاحظات ومخاطر يجب مراجعتها قبل الدمج

1. 🔴 **بيانات اعتماد حقيقية في المستودع:** `.env.example` و `RABBITMQ_QUEUE_GUIDE.md` والقيمة الافتراضية في `SendOrderEmailJob` تحتوي على بريد/كلمة مرور Gmail حقيقية. يجب تدوير كلمة المرور فوراً واستبدالها بـ placeholders واستخدام متغيرات بيئة سرية (Secrets).
2. 🟠 `phpunit.xml` يعطّل `QUEUE_CONNECTION=sync`، ما يعني أن الاختبارات تحتاج RabbitMQ حيّاً وقد تفشل في CI. الأفضل استخدام `QUEUE_CONNECTION=array|sync` في بيئة الاختبار واختبار الـ dispatch منفصلاً.
3. 🟠 الدليل غير متطابق مع الكود في 3 نقاط: يذكر `dailyAt('10:55')` بينما الكود `00:00`؛ يذكر `--tries=1` بينما الكود `3`؛ يذكر مسار `/test-queue` غير موجود في هذا الـ PR.
4. 🟠 الـ Job يُرسل **داخل** `DB::transaction`؛ إذا فشل الـ commit لاحقاً قد يُرسل بريد لطلب غير موجود. الأفضل الإرسال بعد الـ commit أو استخدام `after_commit => true`.
5. 🟠 اختبار `OrderEmailQueueTest` يتحقق بـ `assertTrue(true)` فقط؛ لا يؤكد وصول الرسالة فعلياً. يُنصح بفحص الطابور عبر `Queue::assertPushed` أو عبر RabbitMQ API.
6. 🟡 `Auth::user()` قد يكون `null` خارج سياق HTTP؛ أضف تحققاً أو تمرير المستخدم صراحةً.
7. 🟡 استيراد `use App\Jobs\SendOrderEmailJob;` داخل `OrderController.php` غير مستخدم (الإرسال يتم في الـ Service) — يمكن حذفه لتقليل الالتباس.
8. 🟡 تأكد فيRabbitMQ UI من إنشاء الـ exchanges (`orders_dlx`, `parking_lot_exchange`) وربطها بالطوابير الثلاثة، لأن الإعداد يعلن الـ queues بوسائط DLX فقط.

---

## 8) خلاصة

- الـ PR #25 يضيف طبقة إرسال بريد غير متزامنة موثوقة تعتمد على RabbitMQ مع DLQ وParking Lot واستعادة مجدولة، إضافةً إلى دليل عربي شامل.
- دمجه في `main` آمن بلا تعارضات.
- دمجه في `yassmin` ممكن لكنه يحتاج حل 3 تعارضات: `OrderController.php` (مقطعان)، `OrderService.php` (مقطع واحد)، و `composer.lock` (يُعاد توليده بـ Composer).
- انتبه للتعديلات المحلية غير المحفوظة على `yassmin` ولتسريب بيانات Gmail قبل أي دمج/نشر.
