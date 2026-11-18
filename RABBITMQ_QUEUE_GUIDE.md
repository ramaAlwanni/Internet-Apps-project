# دليل RabbitMQ في المشروع - السيناريوهات الثلاث + شرح الملفات + Docker

## 1) الهدف من المشروع

تم تنفيذ نظام إرسال البريد الإلكتروني بشكل غير متزامن عبر RabbitMQ بحيث:

- يتم فصل عملية إنشاء الطلب عن عملية إرسال البريد الإلكتروني.
- لا يتم فقدان الرسائل عند توقف الـ Worker.
- إذا فشل الإرسال في الـ Worker، يتم إعادة المحاولة 3 مرات.
- إذا استنفدت المحاولات، يتم نقل الرسالة تلقائياً إلى Dead Letter Queue (DLQ).
- في الساعة المحددة يتم تشغيل استرجاع تلقائي للـ DLQ ومراجعة الرسائل المفقودة.

الحالة الأساسية في المشروع:

- الطابور الرئيسي: `order_emails_queue`
- الطابور الميت: `order_emails_dlq`
- الـ Exchange: `orders_dlx`

---

## 2) السيناريو الأول: الإرسال الناجح

### الفكرة

نرسل طلب إنشاء Order أو نستخدم كائن payload صحيح، ثم يذهب الطلب إلى RabbitMQ، ويقوم الـ Worker بسحب الرسالة ومعالجتها وتأكيدها، وبالتالي يتم إرسال البريد الإلكتروني بنجاح.

### الخطوات العملية

1. تأكد أن RabbitMQ يعمل محلياً أو عبر Docker.
2. تأكد أن هذا في ملف `.env`:

```env
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_QUEUE=order_emails_queue
```

3. نفّذ الاختبار:

```bash
php artisan test tests/Feature/OrderEmailQueueTest.php
```

4. النتيجة المتوقعة:

```text
[✔] Test 1: Message for Order #1001 pushed to RabbitMQ (order_emails_queue).
PASS  Tests\Feature\OrderEmailQueueTest
✓ 1 dispatch real message to rabbitmq
```

5. تأكد أيضاً من تشغيل الـ Worker:

```bash
php artisan rabbitmq:consume --queue=order_emails_queue --tries=3 --prefetch-count=1
```

6. في هذه الحالة يجب أن تظهر رسالة مشابهة لـ:

```text
-> Processing Order #1001 for (ramaalwanni83@gmail.com)...
✓ Email sent successfully for Order #1001
```

### ما الذي يثبت النجاح؟

- تم إدخال الرسالة فعلياً في الطابور الرئيسي.
- الـ Worker استلم الرسالة.
- تم إرسال البريد الحقيقي.
- تم ACK بنجاح، وبالتالي لا تبقى الرسالة في الطابور.

---

## 3) السيناريو الثاني: إيقاف Worker قبل الإرسال ثم إعادة التشغيل

### الفكرة

هذا السيناريو يثبت أن RabbitMQ لا يضيع الرسالة عندما يتوقف العامل. إذا تم إيقاف الـ Worker قبل إرسال الإيميل، لا يتم فقدان الطلب؛ عند إعادة التشغيل، يقوم الـ Worker بجلب الرسالة المتبقية من الطابور واستكمالها.

### الخطوات العملية

1. أرسل رسالة إلى `order_emails_queue`.
2. اذهب إلى الـ Worker وقفه قبل أن يتم إرسال البريد الإلكتروني.
3. تأكد أن الرسالة لا تزال موجودة داخل الطابور.
4. أعد تشغيل الـ Worker:

```bash
php artisan rabbitmq:consume --queue=order_emails_queue --tries=3 --prefetch-count=1
```

5. ستلاحظ أن الرسالة يتم سحبها بعد إعادة التشغيل ومعالجتها، بدون فقدانها.

### مثال عملي من المشروع

تمت ملاحظة هذه الحالة في السجل:

```text
php artisan rabbitmq:consume --queue=order_emails_queue --tries=3 --prefetch-count=1

-> Processing Order #1001 for (ramaalwanni83@gmail.com)...
✓ Email sent successfully for Order #1001
```

حتى لو تم إيقاف العامل سابقاً، يتم إعادة استرجاع الرسالة عند إعادة التشغيل لأن RabbitMQ يحفظ الرسالة في الطابور ويمكن أن يتم استلامها لاحقاً.

### لماذا هذا مهم؟

- لأن الرسالة ليست مخزّنة داخل ذاكرة التطبيق فقط.
- RabbitMQ يضمن استمرار الرسالة ما دامت موجودة في Queue.
- هذا يختلف عن `async` العادي في الذاكرة، الذي قد يضيع إذا توقف التطبيق.

---

## 4) السيناريو الثالث: فشل الاتصال + DLQ + Recovery

### الفكرة

في هذه الحالة يتم محاكاة مشكلة اتصال أو خطأ برمجي، ما يؤدي إلى فشل الإرسال. بعد استنفاد المحاولات الثلاث، يتحرك الطلب من الطابور الرئيسي إلى الطابور الميت `order_emails_dlq`.

### خطوات المحاكاة

1. نغيّر قيمة الـ Port أو نوقف الخدمة الخارجية التي يحتاجها الإيميل.
2. نرسل طلب Order جديد.
3. يبدأ الـ Worker في معالجة الطلب.
4. عند فشل الإرسال، يقوم المشروع بإعادة المحاولة 3 مرات.
5. بعد المحاولة الثالثة، يخرج الطلب من الطابور الرئيسي ويتحرك إلى DLQ.

### مثال تاريخي من المشروع

في الاختبارات/المراجعة تم ملاحظة الشكل التالي:

```text
php artisan rabbitmq:consume --queue=order_emails_queue --tries=3 --prefetch-count=1

-> Processing Order #1001 for (ramaalwanni83@gmail.com)...
  [FAIL after retry]
```

ثم يذهب الطلب إلى `order_emails_dlq`.

### ماذا يحدث بعد DLQ؟

النظام جاهز لإعادة المحاولة الدوري في الساعة المحددة، عبر الأمر:

```bash
php artisan emails:recover-dlq
```

هذا الأمر:

- يبدأ استرجاع الرسائل من `order_emails_dlq`
- يمررها إلى الـ Worker مرة أخرى
- يحاول إرسالها مرة أخرى
- إذا نجحت، يحذفها من DLQ، وإذا فشلت، يكتب log خاصاً للمطور

### مثال كان موجوداً في المشروع

```text
php artisan emails:recover-dlq
Starting midnight DLQ recovery for order_emails_dlq...

✓ Email sent successfully for Order #1001
DLQ recovery completed.
```

### لماذا هذا مهم؟

- لا يتم فقدان الرسائل نهائياً.
- الرسائل الميتة تنتظر في DLQ حتى يتم مراجعتها.
- إذا كان هناك خطأ في الكود أو اتصال خارجي، يظل المطور على علم بالإشكال من خلال الـ Logs.

---

## 5) شرح المشروع: الملفات المهمة التي تم تعديلها / ترتيبها

### 1. `config/queue.php`

هذا الملف هو أهم ملف في RabbitMQ داخل Laravel.

ما تم فيه:

- تم تفعيل محرك الطوابير `rabbitmq`.
- تم تعريف الـ Queue الرئيسي باسم `order_emails_queue`.
- تم إضافة إعدادات الاتصال بـ RabbitMQ:
  - Host
  - Port
  - User
  - Password
  - Vhost
- تم تفعيل خاصية `durable` حتى تبقى الرسائل موجودة عند إعادة التشغيل.
- تم تفعيل `x-dead-letter-exchange` و `x-dead-letter-routing-key` لتوجيه الرسائل الفاشلة إلى `orders_dlx` و `order_emails_dlq`.

الدور العملي:

- كل رسالة فاشلة تُنقل تلقائياً إلى DLQ.
- يتم إبعادها عن الطابور الرئيسي حتى لا تعطل باقي الرسائل السليمة.

---

### 2. `app/Jobs/SendOrderEmailJob.php`

هذا الملف يملك منطق الـ Worker الفعلي.

ما تم فيه:

- تنفيذ `ShouldQueue` ليصبح Job في قائمة الطابور.
- تحديد `tries = 3` لعدد محاولات إعادة الإرسال.
- إضافة `backoff` (فترات إعادة المحاولة) لوقت بين المحاولات.
- في `handle()` يتم:
  - قراءة order_data
  - طباعة معلومات العملية في الـ Console
  - إرسال البريد الحقيقي عبر `Mail::to(...)->send(...)`
  - تسجيل نجاح العملية في `Log::info`
- في `failed()` يتم:
  - التمييز بين الرسالة التي فشلت في الطابور الرئيسي وبين الرسالة التي فشلت في DLQ
  - في حالة DLQ يتم تسجيل `Log::critical` لأنه يحتاج تدخل المطور
  - في حالة الطابور الرئيسي يتم تسجيل `Log::error` لأن الرسالة انتقلت إلى DLQ

الدور العملي:

- يضمن وجود Retry mechanism.
- يوضح للمطور ما الذي فعله النظام عند الفشل.
- يتيح تتبع الرسائل المفقودة أو الفاشلة.

---

### 3. `app/Console/Commands/RecoverFailedEmailsCommand.php`

هذا الملف يضيف أمر Laravel خاص لاسترجاع الرسائل الميتة.

ما تم فيه:

- تعريف الأمر: `emails:recover-dlq`
- عند تنفيذه، يبدأ عملية استرجاع الرسائل من `order_emails_dlq`.
- يقوم باستدعاء:

```php
Artisan::call('rabbitmq:consume', [
    '--queue' => 'order_emails_dlq',
    '--tries' => 1,
    '--stop-when-empty' => true,
]);
```

هذا يعني أن النظام يحاول استرداد الرسائل الميتة مرة واحدة في كل تشغيل للأمر.

الدور العملي:

- يحاكي Recovery عند الساعة 12:00 (أو أي وقت محدد في الجدولة).
- يساعد على إعادة إرسال الرسائل التي تعثرت بسبب مشكلة مؤقتة.

---

### 4. `routes/console.php`

هذا الملف هو مكان جدولة الأوامر.

تمت إضافة:

```php
Schedule::command('emails:recover-dlq')->dailyAt('10:55');
```

هذا يعني:

- يتم تنفيذ استرجاع DLQ بشكل دوري على الساعة 10:55.
- يمكن تغييره لاحقاً إلى الوقت الذي يريده المشروع.

الدور العملي:

- يضمن أن الرسائل الفاشلة لا تبقى في DLQ إلى الأبد.
- يدعم التشغيل التلقائي في جدول Laravel Scheduler.

---

### 5. `tests/Feature/OrderEmailQueueTest.php`

هذا الملف يختبر الحالة الأساسية:

- أن الرسالة يتم دفعها فعلياً إلى `order_emails_queue`
- أن العملية لا تتعطل في الـ Test
- أن وجود RabbitMQ و Queue صحيح

الناتج الظاهر:

```text
php artisan test tests/Feature/OrderEmailQueueTest.php

PASS  Tests\Feature\OrderEmailQueueTest
✓ 1 dispatch real message to rabbitmq
```

هذا الملف يثبت أن المشروع قادر على إرسال رسالة حقيقية إلى RabbitMQ بشكل صحيح.

---

### 6. `Modules/Order/routes/api.php`

هذا الملف يحتوي Route لاختبار طابور الرسائل، وهو مفيد في التشغيل اليدوي.

```php
Route::get('/test-queue', function () {
    SendOrderEmailJob::dispatch([...])->onQueue('order_emails_queue');
    return "Job dispatched successfully to order_emails_queue!";
});
```

الدور العملي:

- يسمح بسرعة اختبار الدفع إلى الطابور.
- مناسب للمطور لاختبار التحميل/الإرسال بدون الحاجة إلى إنشاء طلب كامل.

---

### 7. `Modules/Order/app/Services/OrderService.php`

هذا الملف يضيف فعلياً الـ Job عند إنشاء Order جديد.

```php
SendOrderEmailJob::dispatch([
    'order_id' => $order->id,
    'user_email' => $user->email,
    'user_name' => $user->name,
    'total_price' => $order->total_amount,
    'items' => $orderItems,
])->onQueue('order_emails_queue');
```

الدور العملي:

- عند إنشاء الطلب، ستتولد رسالة بريد تلقائياً في الطابور.
- يتم فصل أتمتة الطلب عن إرسال البريد الإلكتروني.

---

## 6) نسخة من إعدادات `.env` المستخدمة في المشروع

تم تحديث ملف `.env.example` ليكون نسخة قابلة للنسخ من الإعدادات المستخدمة في RabbitMQ.

### نسخة جاهزة للنسخ

```env
QUEUE_CONNECTION=rabbitmq

RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_QUEUE=order_emails_queue

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=ramaalwanni83@gmail.com
MAIL_PASSWORD=rmjcukilimdpqekl
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="ramaalwanni83@gmail.com"
MAIL_FROM_NAME="Internet-Apps-Project"
```

> ملاحظة: هذه القيمة عبارة عن نسخة من إعدادات المشروع الحالية المستخدمة في البيئة المحلية. في المشاريع الحقيقية يفضل استخدام متغيرات آمنة ولا تضع كلمات المرور في Git.

---

## 7) ملاحظات Docker وLinux/Windows للـ RabbitMQ

### 1. لماذا نحتاج Docker؟

لأن RabbitMQ يعمل كخدمة منفصلة عن Laravel، ومن الأفضل تشغيله داخل حاوية خاصة لتقليل التداخل مع النظام المحلي.

### 2. المطلوب في Docker Compose

أضف خدمة RabbitMQ في ملف `docker-compose.yml` مثل هذا:

```yaml
services:
  rabbitmq:
    image: rabbitmq:3-management-alpine
    container_name: internet-apps-rabbitmq
    ports:
      - "5672:5672"
      - "15672:15672"
    environment:
      RABBITMQ_DEFAULT_USER: guest
      RABBITMQ_DEFAULT_PASS: guest
    volumes:
      - rabbitmq_data:/var/lib/rabbitmq
    healthcheck:
      test: ["CMD", "rabbitmq-diagnostics", "-q", "ping"]
      interval: 10s
      timeout: 5s
      retries: 10
    networks:
      - app-network
```

### 3. أضف Worker داخل Docker

```yaml
  rabbitmq-worker:
    image: internet-apps-backend:local
    build:
      context: .
      dockerfile: Dockerfile
    command: ["php", "artisan", "rabbitmq:consume", "--queue=order_emails_queue", "--tries=3", "--prefetch-count=1"]
    environment:
      QUEUE_CONNECTION: rabbitmq
      RABBITMQ_HOST: rabbitmq
      RABBITMQ_PORT: "5672"
      RABBITMQ_USER: guest
      RABBITMQ_PASSWORD: guest
      RABBITMQ_VHOST: "/"
      RABBITMQ_QUEUE: order_emails_queue
    depends_on:
      rabbitmq:
        condition: service_healthy
    networks:
      - app-network
```

### 4. ملاحظات مهمة

- داخل شبكة Docker، لا تستخدم `127.0.0.1` داخل الـ Container.
- استخدم اسم الخدمة: `rabbitmq`.
- لذلك في `.env` داخل Docker يجب أن يكون:

```env
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_QUEUE=order_emails_queue
```

- لوحة الإدارة تفتح عادة على:

```text
http://localhost:15672
```

- المستخدم الافتراضي هو:

```text
guest / guest
```

### 5. التحقق من التشغيل في Docker

```bash
docker compose up -d rabbitmq

docker compose logs -f rabbitmq
```

ثم:

```bash
docker compose up -d rabbitmq-worker
```

### 6. تحقق من queues في RabbitMQ UI

افتح الواجهة:

```text
http://localhost:15672
```

ثم:

- Queue Name: `order_emails_queue`
- DLQ: `order_emails_dlq`
- Exchange: `orders_dlx`

---

## 8) الخلاصة النهائية

تم تنفيذ 3 سيناريوهات رئيسية بشكل عملي ومفهوم:

1. الإرسال الناجح إلى RabbitMQ.
2. عدم فقدان الرسالة عند توقف العامل ثم إعادة التشغيل.
3. انتقال الرسالة إلى DLQ عند الفشل وإعادة الاسترجاع في الجدولة.

كل هذه السيناريوهات تُستخدم كأدلة اختبار جاهزة للـ Demo، للـ Presentation، وللـ Viva مع المعيد أو المشرف.

---

## 9) أوامر سريعة للتدريب والاستعراض

```bash
php artisan test tests/Feature/OrderEmailQueueTest.php
php artisan rabbitmq:consume --queue=order_emails_queue --tries=3 --prefetch-count=1
php artisan emails:recover-dlq
php artisan config:clear
```

---

## 10) ملاحظات للعرض أمام المعيد

إذا طلب منك المعيد شرحاً سريعا:

- قل إن المشروع قام بفصل إرسال البريد عن إنشاء الطلب.
- قل إن RabbitMQ يحفظ الرسالة حتى لو توقف الـ Worker.
- قل إن DLQ يضمن عدم ضياع الرسائل في حالة الفشل المستمر.
- قل إن Scheduler أو Command Recovery يراجع الرسائل الميتة لإعادة محاولتها.
- قل إن `config/queue.php` + `SendOrderEmailJob.php` + `RecoverFailedEmailsCommand.php` هي الملفات الأساسية للمشكلة.

هذا هو المنطق الذي تم تطبيقه فعلياً في المشروع.
