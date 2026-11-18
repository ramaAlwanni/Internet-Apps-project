# المتطلب الرابع: المعالجة غير المتزامنة والتحديثات اللحظية

هذا الملف يوثّق تنفيذ **المتطلب الرابع (Asynchronous Queues & Real-Time)** على فرع `yassmin`، ويشرح:

- المطلوب وما الذي نُفّذ لكل جزء.
- الملفات الأساسية المرتبطة بكل جزء.
- شرح لأهم الأكواد.
- **التحديثات الجديدة (آخر تعديلات) وسببها.**
- كيفية الاختبار والتشغيل باستخدام Docker مع إثباتات القبول.

> ملاحظة: هذا الفرع يحتوي أيضاً على متطلبات سابقة (مثل gRPC/المخزون، القراءة/الكتابة، الكاش...). هذا التوثيق يركّز على ما يخص المتطلب الرابع.

---

## 0) التحديثات الجديدة وسببها

تمت إضافة/تعديل النقاط التالية لتحسين الاعتمادية ومنع فقدان الرسائل أو فشل الطلبات بسبب أعطال الخدمات المساعدة:

| التحديث | الملفات المتأثرة | ما الذي تغير؟ | السبب |
|---|---|---|---|
| تهيئة طوبولوجيا RabbitMQ صراحةً (Exchanges/Queues/Bindings) قبل الاستهلاك | `routes/console.php`, `docker-compose.yml` | إضافة أمر Artisan `rabbitmq:setup` يقوم بتعريف `orders_dlx` و`parking_lot_exchange` والطوابير `order_emails_queue`, `order_emails_dlq`, `order_emails_parking_lot` مع روابط الـ dead-letter، وتشغيله تلقائياً في `rabbitmq-worker` قبل `rabbitmq:consume` (`php artisan rabbitmq:setup && php artisan rabbitmq:consume ...`) | الإعدادات في `config/queue.php` تشير إلى DLX/مسارات dead-letter، لكن Laravel/المستهلك لا يعلن الـ Exchanges والربط بينها تلقائياً في هذا السيناريو؛ بدون هذا الإعلان قد تُسقط الرسائل الفاشلة بدلاً من وصولها إلى `order_emails_dlq` (أي فقدان مسار DLQ) |
| جعل فشل البث اللحظي (Reverb) غير مُسقِط لعملية الطلب/تحديث الحالة بعد الالتزام | `Modules/Order/app/Services/OrderService.php` | تغليف `event(new OrderCreatedEvent(...))` و`event(new OrderStatusUpdatedEvent(...))` داخل `try/catch` مع تسجيل `Log::warning` عند الفشل | الطلب/تحديث الحالة يكون قد تم حفظه والالتزام به في قاعدة البيانات؛ لذلك لا يجب أن يؤدي انقطاع Reverb أو فشل الإرسال اللحظي إلى إرجاع خطأ 500 للمستخدم أو اعتبار العملية فاشلة، مع الاكتفاء بتسجيل فقدان التحديث اللحظي |
| تحسين بناء صورة Docker لامتداد `sockets` | `Dockerfile` | تثبيت `ext-sockets` (المطلوب لـ php-amqplib/RabbitMQ) في طبقة `RUN` منفصلة مع تعليق يوضح السبب، مع إزالة `# syntax=docker/dockerfile:1` من بداية الملف | فصل تثبيت `sockets` عن طبقة بناء `grpc` المكلفة يحافظ على كاش Docker لتلك الطبقة عند إعادة البناء، مع ضمان توفر الامتداد اللازم لاتصال AMQP |
| إضافة اختبار مرونة طابور البريد | `tests/Feature/OrderEmailQueueResilienceTest.php` (ملف جديد/غير متعقّب حالياً) | اختباران: (1) التأكد من بقاء الرسالة في `order_emails_queue` عند توقف العامل، (2) وصول المهمة الفاشلة إلى `order_emails_dlq` ثم تفريغها عبر `emails:recover-dlq` | توفير إثبات آلي/قابل للتكرار لسلوك عدم الضياع ومسار DLQ والاسترجاع، بما يدعم إثباتات المتطلب الرابع (مع ملاحظة شروط تشغيل كل اختبار كما هو موضح داخل الملف) |

---

## 1) نظرة عامة على ما تم تنفيذه

| الجزء | المطلوب | كيف تم تحقيقه |
|---|---|---|
| نشر حدث عند إنشاء الطلب | عند إنشاء الطلب يُنشر حدث في RabbitMQ ويرد التطبيق فوراً دون انتظار إرسال البريد | `OrderService` ينشئ الطلب ثم يرسل `SendOrderEmailJob` إلى طابور `order_emails_queue` عبر `dispatch(...)->onQueue(...)` |
| عامل إرسال البريد | عامل (Worker) مستقل يرسل بريد تأكيد حقيقياً عبر خدمة بريد خارجية | خدمة `rabbitmq-worker` (أو أمر `rabbitmq:consume`) تشغّل `SendOrderEmailJob` الذي يستخدم `Mail::to(...)->send(new OrderConfirmationMail(...))` عبر SMTP |
| عدم ضياع الرسائل + إعادة المحاولة + DLQ | الرسالة لا تضيع عند تعطل العامل، مع محاولات إعادة وDLQ للفشل الدائم | RabbitMQ بطوابير durable، وتهيئة `x-dead-letter-*` في `config/queue.php`، و`tries/backoff` في الـ Job، وأمر استرجاع `emails:recover-dlq` |
| صفحة متابعة الطلب للزبون (لحظي) | يرى الزبون حالة طلبه تتغير فوراً دون تحديث الصفحة، ولا يستطيع غيره متابعته | حدث `OrderStatusUpdatedEvent` يُبث على قنوات خاصة `private-order.{id}` و`private-user.{userId}`، والواجهة تشترك عبر Laravel Echo/Reverb وتحدّث Redux/UI |
| لوحة المسؤول (لحظي) | يظهر الطلب الجديد فوراً في لوحة المسؤول دون تحديث الصفحة، ولا يدخلها إلا مسؤول | حدث `OrderCreatedEvent` يُبث على `private-admin.orders`، والواجهة تشترك وتضيف الطلب للجدول فوراً |
| الصلاحيات قبل الاشتراك | التحقق من هوية المستخدم وصلاحيته قبل الاشتراك في أي قناة | `/api/broadcasting/auth` محمي بـ `auth:api`، وتفويض القنوات في `routes/channels.php` (admin role / مالك الطلب / مالك المستخدم) |
| الإثباتات | إيقاف العامل لا يضيع بريداً، وظهور الطلب/تحديث الحالة فوراً | خطوات اختبار Docker مفصّلة في قسم "الاختبار مع Docker" |

---

## 2) هيكلية المكوّنات (Docker)

الخدمات المرتبطة مباشرة بالمتطلب الرابع في `docker-compose.yml`:

- **`rabbitmq`**: وسيط الرسائل (AMQP) + واجهة إدارة.
  - المنافذ: `5672` (AMQP)، `15672` (Management UI).
- **`rabbitmq-worker`**: عامل مستقل يستهلك رسائل البريد من RabbitMQ.
  - الأمر: `sh -c "php artisan rabbitmq:setup && php artisan rabbitmq:consume --queue=order_emails_queue --tries=3 --prefetch-count=1"`
  - **لماذا `rabbitmq:setup` أولاً؟** لتعريف Exchanges والـ Queues والـ Bindings (بما فيها مسارات DLX/DLQ والـ parking lot) بشكل صريح قبل بدء الاستهلاك، حتى لا تُسقط الرسائل الفاشلة خارج مسار DLQ.
  - يعتمد على جاهزية `rabbitmq` ونجاح `migrate`.
- **`reverb`**: خادم WebSocket (بروتوكول Pusher) للبث اللحظي.
  - الأمر: `php artisan reverb:start --host=0.0.0.0 --port=6001`
  - المنفذ: `6001`.
- **`backend`**: تطبيق Laravel (API) الذي ينشئ الطلبات، يرسل Jobs/Events، ويخدم تفويض البث `/api/broadcasting/auth`.
- **`frontend`**: تطبيق React/Vite الذي يتصل بـ Reverb عبر Laravel Echo ويعرض التحديثات اللحظية.

شبكة مشتركة: `app-network`، وحجم دائم لبيانات RabbitMQ: `rabbitmq_data`.

---

## 3) الملفات المطلوبة لكل جزء من المتطلب (مع الشرح)

### 3.1 نشر حدث/رسالة عند إنشاء الطلب والرد دون انتظار إرسال البريد

**الفكرة:** عند نجاح إنشاء الطلب وحفظه وتأكيد حجز المخزون، يتم:
- دفع مهمة إرسال البريد إلى RabbitMQ (`SendOrderEmailJob`) لتُعالَج لاحقاً بواسطة العامل.
- بث حدث `OrderCreatedEvent` لإشعار لوحة المسؤول فوراً.

**الملفات:**
- `Modules/Order/app/Services/OrderService.php`
- `Modules/Order/app/Http/Controllers/OrderController.php`
- `Modules/Order/Events/OrderCreatedEvent.php`

**أهم الأكواد:**

- في `OrderService::createOrder()`:
  - داخل المعاملة وبعد إنشاء الطلب وعناصره يتم إرسال Job إلى الطابور:
    ```php
    SendOrderEmailJob::dispatch([
        'order_id' => $order->id,
        'user_email' => $user->email,
        'user_name' => $user->name,
        'total_price' => $order->total_amount,
        'items' => $orderItems,
    ])->onQueue('order_emails_queue');
    ```
  - بعد اكتمال العملية وتأكيد (commit) حجز المخزون بنجاح يتم بث حدث الإنشاء، مع عزل فشل البث اللحظي حتى لا يؤثر على استجابة الطلب الناجح:
    ```php
    try {
        event(new OrderCreatedEvent($order));
    } catch (\Throwable $exception) {
        Log::warning('Order created but realtime broadcast failed.', [
            'order_id' => $order->id,
            'error' => $exception->getMessage(),
        ]);
    }
    ```
- في `OrderController::store()` يتم إرجاع استجابة نجاح مباشرة بعد إنشاء الطلب برسالة تفيد بأن بريد التأكيد سيُرسل لاحقاً:
  ```php
  return $this->successResponse(
      'Order created successfully. A confirmation email will be sent shortly.',
      $order,
      201
  );
  ```

**النتيجة:** المستخدم يحصل على استجابة إنشاء الطلب فوراً، بينما إرسال البريد الفعلي يحدث بشكل غير متزامن في العامل.

---

### 3.2 العامل (Worker) وإرسال بريد تأكيد حقيقي عبر خدمة خارجية

**الفكرة:** عامل مستقل يستهلك الرسائل من `order_emails_queue` ويرسل بريداً حقيقياً عبر SMTP (خدمة بريد خارجية مُهيأة في `.env`).

**الملفات:**
- `app/Jobs/SendOrderEmailJob.php`
- `app/Mail/OrderConfirmationMail.php` (قالب/بريد تأكيد الطلب)
- `app/Mail/ParkingLotAlertMail.php` (تنبيه المطور عند الفشل النهائي/التنبيه)
- `config/mail.php` و`.env` / `.env.example` (إعدادات SMTP)
- `docker-compose.yml` (خدمة `rabbitmq-worker`)
- `Dockerfile` (يثبّت `ext-sockets` المطلوبة لـ php-amqplib/AMQP في طبقة منفصلة للحفاظ على كاش بناء `grpc`)

**أهم الأكواد:**

- `SendOrderEmailJob`:
  - ينفّذ `ShouldQueue` ويحدّد محاولات إعادة الإرسال والتأخير بين المحاولات:
    ```php
    public int $tries = 3;
    public array $backoff = [1, 2, 3];
    ```
  - في `handle()` يرسل البريد الحقيقي:
    ```php
    Mail::to($this->orderData['user_email'])
        ->send(new OrderConfirmationMail($this->orderData));
    ```
  - في `failed()` يسجّل تنبيهاً حرجاً ويرسل بريداً تنبيهياً للمطور (للمتابعة عند استنفاد المحاولات/وصول الحالة الحرجة):
    ```php
    Log::critical("PARKING LOT ALERT: Order #{$orderId} ...");
    Mail::to($developerEmail)->send(new ParkingLotAlertMail(...));
    ```
- عامل Docker (`rabbitmq-worker`) يشغّل الأمر:
  ```yaml
  command: ["php", "artisan", "rabbitmq:consume", "--queue=order_emails_queue", "--tries=3", "--prefetch-count=1"]
  ```
  مع `QUEUE_CONNECTION=rabbitmq` و`RABBITMQ_HOST=rabbitmq`.

> ملاحظة عملية: لإرسال بريد حقيقي يجب ضبط إعدادات SMTP صحيحة في `.env` (مثل `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_*`). بدون ذلك قد تفشل المهمة وتنتقل لمحاولات إعادة/DLQ حسب الحالة.

---

### 3.3 عدم ضياع الرسائل، إعادة المحاولة، وDLQ

**الفكرة:** RabbitMQ يحتفظ بالرسائل بشكل دائم (durable) في الطابور، والعامل يستهلكها ويؤكدها عند النجاح. عند الفشل تُعاد المحاولة، وعند استنفاد المحاولات/فشل المسار تُوجَّه الرسالة إلى طوابير الرسائل الميتة حسب تهيئة الطابور.

**الملفات:**
- `config/queue.php` (اتصال `rabbitmq` وتعريف الطوابير ووسائط DLX/DLQ)
- `app/Jobs/SendOrderEmailJob.php` (`tries`, `backoff`, `failed`)
- `app/Console/Commands/RecoverFailedEmailsCommand.php` (استرجاع من DLQ)
- `routes/console.php` (جدولة الاسترجاع + أمر `rabbitmq:setup` لتعريف الطوبولوجيا)
- `docker-compose.yml` (خدمة `rabbitmq` بحجم `rabbitmq_data` وخدمة `rabbitmq-worker` مع `restart: unless-stopped`)
- `tests/Feature/OrderEmailQueueResilienceTest.php` (اختبارات مرونة/إثبات سلوك الطابور وDLQ)

**أهم الأكواد:**

- `config/queue.php` (مقتطفات مهمة):
  - اتصال RabbitMQ ومضيفه داخل Docker هو اسم الخدمة `rabbitmq`:
    ```php
    'driver' => 'rabbitmq',
    'queue' => env('RABBITMQ_QUEUE', 'order_emails_queue'),
    'hosts' => [[
        'host' => env('RABBITMQ_HOST', '127.0.0.1'),
        'port' => env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
    ]],
    ```
  - الطابور الرئيسي `order_emails_queue` (durable) مع Dead Letter Exchange/Routing Key:
    ```php
    'order_emails_queue' => [
        'declare' => true,
        'bind' => true,
        'durable' => true,
        'arguments' => json_encode([
            'x-dead-letter-exchange' => 'orders_dlx',
            'x-dead-letter-routing-key' => 'order_emails_dlq',
        ]),
    ],
    ```
  - طابور DLQ `order_emails_dlq` مُعرّف أيضاً (durable) مع توجيه لاحق مُهيأ في الوسائط، وطابور `order_emails_parking_lot` مُعرّف.
- أمر تهيئة الطوبولوجيا `rabbitmq:setup` في `routes/console.php` (مهم جداً لمسار DLQ):
  - يعلن Exchanges: `orders_dlx`, `parking_lot_exchange`
  - يعلن الطوابير: `order_emails_queue`, `order_emails_dlq`, `order_emails_parking_lot`
  - يضبط وسائط dead-letter (`x-dead-letter-exchange`, `x-dead-letter-routing-key`) ويقوم بربط الطوابير بالـ Exchanges (`queue_bind`)
  - **السبب:** `config/queue.php` يحدد مسارات DLX/DLQ، لكن exchanges والـ bindings قد لا تُعلن تلقائياً في هذا الإعداد؛ بدونها قد لا تصل الرسائل الفاشلة إلى `order_emails_dlq` كما هو متوقع. لذلك يقوم `rabbitmq-worker` في Docker بتنفيذ `rabbitmq:setup` قبل بدء الاستهلاك.
- أمر الاسترجاع `app/Console/Commands/RecoverFailedEmailsCommand.php`:
  ```php
  Artisan::call('rabbitmq:consume', [
      '--queue' => 'order_emails_dlq',
      '--tries' => 3,
      '--stop-when-empty' => true,
  ], $this->output);
  ```
- الجدولة في `routes/console.php`:
  ```php
  Schedule::command('emails:recover-dlq')->dailyAt('00:00');
  ```
- Docker يضمن استمرارية بيانات RabbitMQ عبر:
  ```yaml
  volumes:
    - rabbitmq_data:/var/lib/rabbitmq
  ```

**كيف يتحقق "عدم الضياع عند توقف العامل"؟** الرسالة تبقى محفوظة في RabbitMQ (الطابور durable والبيانات على volume) إلى أن يستهلكها عامل ويؤكد نجاحها؛ لذلك إيقاف `rabbitmq-worker` ثم إعادة تشغيله لا يحذف الرسائل غير المستهلكة. كما أن تعريف الطوبولوجيا مسبقاً يضمن وجود مسار DLQ الصحيح عند الفشل.

---

### 3.4 صفحة متابعة الطلب للزبون (تحديث لحظي + خصوصية)

**الفكرة:** عندما يغيّر المسؤول حالة الطلب، يُبث حدث الحالة الجديدة على قنوات خاصة تخص مالك الطلب/المستخدم، فتتحدّث صفحة "طلباتي" وتفاصيل الطلب فوراً دون إعادة تحميل.

**الملفات (Backend):**
- `Modules/Order/Events/OrderStatusUpdatedEvent.php`
- `Modules/Order/app/Services/OrderService.php` (`updateStatus` يبث بعد commit)
- `Modules/Order/app/Http/Controllers/OrderController.php` (`updateStatus`)
- `Modules/Order/app/Http/Requests/UpdateOrderStatusRequest.php`
- `Modules/Order/app/Policies/OrderPolicy.php` (تفويض تغيير الحالة عبر `authorize('updateStatus', $order)` في الكنترولر)
- `routes/channels.php` (تفويض قنوات `user.{userId}` و`order.{orderId}`)
- `routes/api.php` (تفويض البث `/api/broadcasting/auth` بـ `auth:api`)
- `bootstrap/app.php` (تسجيل `channels: routes/channels.php`)

**الملفات (Frontend):**
- `frontend/src/lib/echo.js` (تهيئة Laravel Echo + Reverb + authorizer مع Bearer token)
- `frontend/src/hooks/useEchoPrivateChannel.js` (اشتراك/إلغاء اشتراك آمن متعدد المكونات)
- `frontend/src/features/notifications/hooks/useCustomerOrderNotifications.js` (استماع على `user.{id}` وتحديث Redux + Toast)
- `frontend/src/features/orders/hooks/useOrderStatusRealtime.js` (استماع على `order.{id}` للطلب المفتوح/المتابَع)
- `frontend/src/features/orders/pages/OrdersPage.jsx`
- `frontend/src/features/orders/components/OrdersList.jsx`
- `frontend/src/features/orders/components/OrderDetailsModal.jsx`
- `frontend/src/redux/slices/orderSlice.js` (تخزين الحالة اللحظية `statusById` وتحديثها)

**أهم الأكواد:**

- حدث الحالة `OrderStatusUpdatedEvent`:
  - يُبث فورياً (`ShouldBroadcastNow`) على ثلاث قنوات خاصة:
    ```php
    return [
        new PrivateChannel('admin.orders'),
        new PrivateChannel('order.'.$this->order->id),
        new PrivateChannel('user.'.$this->order->user_id),
    ];
    ```
  - اسم الحدث للبث: `order.status.updated`.
- تفويض القنوات في `routes/channels.php`:
  ```php
  Broadcast::channel('user.{userId}', function (User $user, $userId) {
      return (int) $user->getKey() === (int) $userId;
  });

  Broadcast::channel('order.{orderId}', function (User $user, $orderId) {
      return \Modules\Order\Models\Order::query()
          ->whereKey($orderId)
          ->where('user_id', $user->getKey())
          ->exists();
  });
  ```
  بهذا لا يستطيع مستخدم آخر الاشتراك لقناة طلب/مستخدم لا يملكه.
- `OrderService::updateStatus()` يبث الحدث فقط بعد commit وفي حال تغيّرت الحالة فعلاً، مع عزل فشل Reverb عن نجاح العملية:
  ```php
  if ($changed) {
      try {
          event(new OrderStatusUpdatedEvent($order));
      } catch (\Throwable $exception) {
          Log::warning('Order status updated but realtime broadcast failed.', [
              'order_id' => $order->id,
              'status' => $order->status,
              'error' => $exception->getMessage(),
          ]);
      }
  }
  ```
- الواجهة:
  - `useCustomerOrderNotifications(userId)` يشترك في `private-user.{userId}` ويحدّث حالة الطلب في Redux ويُظهر إشعاراً.
  - `useOrderStatusRealtime(orderId, initialStatus)` يشترك في `private-order.{id}` ويعرض أحدث حالة في نافذة تفاصيل الطلب/المكوّنات المشتركة.
  - `OrdersList` و`OrderDetailsModal` يعرضان الحالة من `statusById` (اللحظي) مع fallback لقيمة الطلب.

---

### 3.5 لوحة المسؤول: ظهور الطلب الجديد فوراً (لحظي + صلاحية مسؤول)

**الفكرة:** عند إنشاء طلب جديد يُبث حدث إلى قناة خاصة بالمسؤولين، فتحدّث لوحة الطلبات الجدول فوراً دون إعادة تحميل الصفحة.

**الملفات (Backend):**
- `Modules/Order/Events/OrderCreatedEvent.php`
- `Modules/Order/app/Services/OrderService.php` (`event(new OrderCreatedEvent($order))` بعد نجاح الالتزام)
- `routes/channels.php` (تفويض قناة `admin.orders`)
- `Modules/Order/routes/api.php` (مسارات الإدارة تحت `permission:view-all-orders,api` و`prefix('admin')`)

**الملفات (Frontend):**
- `frontend/src/features/adminOrders/hooks/useAdminOrdersRealtime.js`
- `frontend/src/features/adminOrders/pages/AdminOrdersPage.jsx`
- `frontend/src/features/adminOrders/components/AdminOrdersTable.jsx`
- `frontend/src/features/adminOrders/components/AdminOrderDetailsModal.jsx`
- `frontend/src/redux/slices/adminOrderSlice.js`
- `frontend/src/services/api.js` / `app/store.js` (جلب/تخزين طلبات الأدمن حسب بنية المشروع)

**أهم الأكواد:**

- حدث الإنشاء `OrderCreatedEvent`:
  ```php
  public function broadcastOn(): array
  {
      return [new PrivateChannel('admin.orders')];
  }

  public function broadcastAs(): string
  {
      return 'order.created';
  }
  ```
  ويرسل حمولة تحتوي الطلب (مع المستخدم والعناصر) لاستهلاك الجدول مباشرة.
- تفويض القناة في `routes/channels.php`:
  ```php
  Broadcast::channel('admin.orders', function (User $user) {
      return $user->hasRole('admin');
  });
  ```
- الواجهة `useAdminOrdersRealtime()` تشترك في `admin.orders` وتستمع للأحداث:
  - `.order.created` → إضافة/دمج الطلب في `adminOrderSlice`.
  - `.order.status.updated` → تحديث حالة الطلب في الجدول.
- مسارات API الإدارية محمية بصلاحية `view-all-orders` (إضافة إلى حماية المصادقة على مجموعة الطلبات).

---

### 3.6 التحقق من هوية المستخدم وصلاحيته قبل الاشتراك في القنوات

**آلية الحماية (طبقات):**

1. **مصادقة المستخدم قبل تفويض البث:**
   - `routes/api.php` يعرّف نقطة تفويض البث:
     ```php
     Route::post('/broadcasting/auth', function (Request $request) {
         return Broadcast::auth($request);
     })->middleware('auth:api');
     ```
   - `frontend/src/lib/echo.js` يرسل طلب التفويض إلى `VITE_BROADCAST_AUTH_ENDPOINT` (افتراضياً `/api/broadcasting/auth`) مع:
     - `Authorization: Bearer <token>` عند وجود JWT في `localStorage`.
     - أو `credentials: include` كبديل لجلسات Sanctum (حسب الإعداد).

2. **تفويض القناة (Authorization callback) بعد المصادقة:**
   - `routes/channels.php` يحدد من يحق له الاستماع لكل قناة:
     - `admin.orders`: فقط من لديه دور `admin`.
     - `user.{userId}`: فقط المستخدم نفسه (`id` مطابق).
     - `order.{orderId}`: فقط مالك الطلب (تحقق من `orders.user_id`).

3. **حماية واجهات/مسارات البيانات:**
   - مسارات الطلبات محمية بـ `auth:api`.
   - مسارات عرض/إدارة كل الطلبات محمية بـ `permission:view-all-orders,api`.
   - تغيير حالة الطلب يمر عبر `authorize('updateStatus', $order)` وPolicy.

بهذا لا يمكن لمستخدم غير مصادق أو غير مخوّل الاشتراك في القنوات الخاصة أو استهلاك بياناتها.

---

## 4) إعدادات البيئة المهمة (Docker)

### 4.1 RabbitMQ والبريد (Backend)

في `.env` (أو عبر متغيرات البيئة في docker-compose للخدمات المعنية):

```env
QUEUE_CONNECTION=rabbitmq

RABBITMQ_HOST=rabbitmq         
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_QUEUE=order_emails_queue

# إعدادات SMTP لإرسال بريد حقيقي 
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@example.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="your_email@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

> ملاحظات:
> - عند التشغيل المحلي خارج Docker يكون `RABBITMQ_HOST=127.0.0.1` (إذا كان RabbitMQ منشوراً على الجهاز).
> - حاوية `rabbitmq-worker` في `docker-compose.yml` تضبط `QUEUE_CONNECTION=rabbitmq` و`RABBITMQ_HOST=rabbitmq` صراحة.

### 4.2 Reverb والبث اللحظي (Backend + Reverb)

المتغيرات الأساسية (تأكد من تطابقها بين `backend` و`reverb` و`frontend`):

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=100100
REVERB_APP_KEY=local-reverb-key
REVERB_APP_SECRET=local-reverb-secret
REVERB_HOST=localhost          # من منظور المتصفح/المضيف
REVERB_PORT=6001
REVERB_SCHEME=http
```

> خدمة `reverb` في `docker-compose.yml` تعمل داخل الحاوية على `0.0.0.0:6001` وتنشر المنفذ للمضيف، مع `BROADCAST_CONNECTION=reverb`.

### 4.3 متغيرات الواجهة (Frontend / Vite)

في `frontend/.env` (أو `frontend/.env.example` كأساس):

```env
VITE_REVERB_APP_KEY=local-reverb-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=6001
VITE_REVERB_SCHEME=http
VITE_BROADCAST_AUTH_ENDPOINT=http://localhost:8080/api/broadcasting/auth
```

> عند التشغيل عبر Docker في هذا المشروع يكون الـ API على `http://localhost:8080` والواجهة على `http://localhost:5173` (افتراضياً)، لذلك يجب أن يشير `VITE_BROADCAST_AUTH_ENDPOINT` إلى الـ backend الصحيح.

---

## 5) كيفية التشغيل والاختبار مع Docker

### 5.1 التحضير والتشغيل

1. تأكد من وجود ملف `.env` في جذر المشروع وملف `frontend/.env` (يمكن النسخ من الأمثلة وتعديل القيم):
   - فعّل/اضبط: `QUEUE_CONNECTION=rabbitmq`, `BROADCAST_CONNECTION=reverb`, إعدادات `RABBITMQ_*`, إعدادات `REVERB_*`, وإعدادات SMTP للبريد.
   - اضبط متغيرات `VITE_REVERB_*` و`VITE_BROADCAST_AUTH_ENDPOINT` في `frontend/.env`.

2. ابنِ وشغّل الخدمات:
   ```bash
   docker compose up -d --build
   ```

3. (اختياري) راقب السجلات أثناء الاختبار:
   ```bash
   docker compose logs -f backend reverb rabbitmq-worker rabbitmq
   ```

4. عناوين مفيدة (افتراضياً):
   - الواجهة: `http://localhost:5173`
   - الـ API (Laravel): `http://localhost:8080`
   - Reverb (WebSocket): `ws://localhost:6001`
   - RabbitMQ Management: `http://localhost:15672` (المستخدم/كلمة المرور الافتراضية غالباً `guest/guest` ما لم تُغيّر)

> ملاحظة: إذا كانت الحاويات تعمل بالفعل بعد تعديل `.env`، قد تحتاج لإعادة إنشاء/تشغيل الخدمات المتأثرة (`backend`, `reverb`, `rabbitmq-worker`, `frontend`) لالتقاط القيم الجديدة.

### 5.2 إثبات 1: إيقاف العامل ثم تشغيله لا يضيع أي بريد

**الهدف:** إثبات أن الرسالة تبقى في RabbitMQ عند توقف العامل وتُعالج بعد إعادة تشغيله.

**الخطوات:**
1. تأكد أن `rabbitmq` يعمل:
   ```bash
   docker compose ps rabbitmq
   ```
2. أوقف العامل فقط:
   ```bash
   docker compose stop rabbitmq-worker
   ```
3. أنشئ طلباً جديداً (من واجهة الزبون أو عبر API إنشاء الطلب) بحيث يتم إرسال `SendOrderEmailJob` إلى `order_emails_queue`.
4. (اختياري للتأكيد) افتح RabbitMQ Management على `http://localhost:15672` وتحقق من وجود رسائل/رسالة في الطابور `order_emails_queue` (Ready/Unacked حسب الحالة).
5. أعد تشغيل العامل:
   ```bash
   docker compose start rabbitmq-worker
   ```
6. راقب سجل العامل:
   ```bash
   docker compose logs -f rabbitmq-worker
   ```

**النتيجة المتوقعة:**
- ظهور معالجة الطلب وإرسال البريد بنجاح (مثلاً رسائل مثل `Processing Order #...` ثم `Email sent successfully...`) بعد إعادة التشغيل.
- عدم فقدان الرسالة رغم توقف العامل في الفترة بين الإنشاء وإعادة التشغيل.

> إذا كانت إعدادات SMTP غير صحيحة، قد تفشل المهمة وتُعاد المحاولة ثم تنتقل لمسار الفشل/DLQ؛ هذا يثبت آلية إعادة المحاولة/عدم الفقدان لكن لن يثبت "إرسال بريد ناجح" إلا بإعداد SMTP صالح.

### 5.3 إثبات 2: الطلب الجديد يظهر فوراً في لوحة المسؤول (بدون تحديث الصفحة)

**الهدف:** إثبات البث اللحظي لحدث `order.created` إلى `private-admin.orders`.

**المتطلبات المسبقة:**
- `reverb` يعمل، و`BROADCAST_CONNECTION=reverb`.
- `frontend/.env` يحتوي قيم `VITE_REVERB_*` الصحيحة و`VITE_BROADCAST_AUTH_ENDPOINT=http://localhost:8080/api/broadcasting/auth`.
- حساب مسؤول يملك الدور/الصلاحية المطلوبة (الوصول لقناة `admin.orders` ومسار الإدارة).

**الخطوات:**
1. افتح نافذة/متصفح (جلسة مسؤول) وانتقل إلى صفحة إدارة الطلبات: `http://localhost:5173/admin/orders`.
2. افتح نافذة أخرى (جلسة زبون) وقم بإنشاء طلب جديد.
3. راقب لوحة المسؤول دون عمل Refresh.

**النتيجة المتوقعة:**
- ظهور الطلب الجديد مباشرة في جدول الطلبات (بفضل اشتراك `useAdminOrdersRealtime` في `admin.orders` واستقبال حدث `.order.created`).

**في حال عدم ظهور التحديث:** تحقق من اتصال WebSocket بـ Reverb، ومن تطابق مفاتيح Reverb، ومن نجاح طلب تفويض البث `/api/broadcasting/auth` (يجب أن يكون المستخدم مسؤولاً).

### 5.4 إثبات 3: موافقة/تغيير حالة الطلب من المسؤول تظهر فوراً في صفحة الزبون (بدون تحديث)

**الهدف:** إثبات البث اللحظي لحدث `order.status.updated` إلى قنوات الزبون (`private-user.{id}` و/أو `private-order.{id}`).

**المتطلبات المسبقة:** نفس متطلبات Reverb/Echo أعلاه، مع حساب زبون يملك الطلب.

**الخطوات:**
1. افتح كزبون صفحة "طلباتي": `http://localhost:5173/orders` (وافتح تفاصيل الطلب إن رغبت).
2. افتح كمسؤول صفحة إدارة الطلبات `http://localhost:5173/admin/orders`.
3. غيّر حالة الطلب (مثلاً إلى `confirmed` أو `processing` أو `shipped`) من لوحة المسؤول.
4. راقب صفحة الزبون دون Refresh.

**النتيجة المتوقعة:**
- تتغير حالة الطلب فوراً في قائمة طلبات الزبون و/أو نافذة التفاصيل (تحديث Redux عبر `useCustomerOrderNotifications` و`useOrderStatusRealtime`).
- قد يظهر إشعار/Toast للزبون بتغير الحالة.

### 5.5 (إضافي) التحقق من الطوابير وDLQ عبر واجهة RabbitMQ

1. افتح `http://localhost:15672` وسجّل الدخول.
2. من تبويب **Queues** تحقق من وجود:
   - `order_emails_queue` (الطابور الرئيسي)
   - `order_emails_dlq` (طابور الرسائل الميتة)
   - `order_emails_parking_lot` (الطابور النهائي/parking lot حسب الطوبولوجيا المُعلنة)
3. من تبويب **Exchanges** يمكنك أيضاً التحقق من وجود:
   - `orders_dlx`
   - `parking_lot_exchange`
4. يمكنك مراقبة معدلات الرسائل (Ready/Unacked/Ack) أثناء تشغيل العامل وإيقافه لإثبات بقاء الرسائل ومسار نقلها إلى DLQ.

> عند تشغيل `rabbitmq-worker` عبر Docker يتم تنفيذ `php artisan rabbitmq:setup` تلقائياً قبل الاستهلاك، لذلك من المفترض أن تظهر هذه exchanges/queues/bindings. يمكن تشغيله يدوياً أيضاً عند الحاجة: `docker compose exec rabbitmq-worker php artisan rabbitmq:setup`.

### 5.6 (إضافي) تشغيل استرجاع DLQ يدوياً داخل Docker

لاختبار مسار الاسترجاع يدوياً:

```bash
docker compose exec rabbitmq-worker php artisan emails:recover-dlq
```

(أو عبر خدمة `backend` إذا كانت البيئة مهيأة بنفس إعدادات RabbitMQ: `docker compose exec backend php artisan emails:recover-dlq`)

### 5.7 (إضافي) اختبار مرونة الطابور الآلي (Feature Test)

أُضيف الملف `tests/Feature/OrderEmailQueueResilienceTest.php` لتغطية سيناريوهات المرونة برمجياً:

- `test_message_survives_when_worker_is_stopped`: يتحقق من زيادة عدد الرسائل في `order_emails_queue` بعد الإرسال (يُشغّل بينما `rabbitmq-worker` متوقف).
- `test_failed_job_reaches_dlq_and_recovery_drains_it`: يرسل مهمة تفشل، ينتظر وصولها إلى `order_emails_dlq`، ثم ينفّذ `emails:recover-dlq` ويتحقق من تفريغ DLQ (يتطلب عاملاً قيد التشغيل لمعالجة المحاولات/النقل).

**ملاحظات تشغيل مهمة:**
- الاختباران لهما متطلبات تشغيل مختلفة لحالة العامل كما هو موضح في تعليق أعلى الملف؛ لذلك يُفضّل تشغيلهما بشكل منفصل/يدوي بحسب السيناريو وليس كتشغيل أعمى لكامل الملف في كل مرة.
- يجب أن تكون إعدادات اتصال RabbitMQ متاحة للبيئة التي ينفذ فيها الاختبار (داخل حاوية backend/worker أو محلياً مع نفس الإعدادات). مثال:
  ```bash
  # مع إيقاف العامل أولاً لهذا الاختبار
  docker compose stop rabbitmq-worker
  docker compose exec backend php artisan test --filter=test_message_survives_when_worker_is_stopped
  docker compose start rabbitmq-worker

  # مع تشغيل العامل لهذا الاختبار
  docker compose exec backend php artisan test --filter=test_failed_job_reaches_dlq_and_recovery_drains_it
  ```
- قد تحتاج لضبط بيانات SMTP/سلوك المهمة في بيئة الاختبار لأن السيناريو الثاني يعتمد على فشل الإرسال فعلياً بعد المحاولات.

---

## 6) ملاحظات تنفيذية مهمة (مطابقة للكود الحالي)

- **ترتيب أحداث إنشاء الطلب:** يتم بث `OrderCreatedEvent` بعد نجاح حفظ الطلب وتأكيد حجز المخزون (`commit`) لتجنب بث طلب قد يُلغى لاحقاً.
- **مرونة البث اللحظي بعد الالتزام:** تم تغليف بث أحداث `OrderCreatedEvent` و`OrderStatusUpdatedEvent` في `OrderService` داخل `try/catch` مع تسجيل `Log::warning` عند الفشل، حتى لا يحول انقطاع Reverb عملية ناجحة ومُلتزمة في قاعدة البيانات إلى استجابة خطأ.
- **ترتيب تحديث الحالة:** يتم بث `OrderStatusUpdatedEvent` فقط عند تغيّر فعلي للحالة وبعد نجاح المعاملة (`commit`).
- **قناة المستخدم `user.{id}`:** مستخدمة لإيصال تحديثات الحالة للزبون على أي صفحة (ليس فقط عند فتح نافذة طلب محدد).
- **تفويض البث في هذا المشروع:** نقطة التفويض المستخدمة من الواجهة هي `POST /api/broadcasting/auth` مع حارس `auth:api` (JWT)، وتفويض القنوات يتم في `routes/channels.php`.
- **تهيئة طوبولوجيا RabbitMQ بشكل صريح:** تمت إضافة `php artisan rabbitmq:setup` (في `routes/console.php`) لتعريف Exchanges والـ Queues والـ Bindings الخاصة بالـ DLQ/parking lot، ويشغّلها `rabbitmq-worker` تلقائياً قبل الاستهلاك. السبب: منع ضياع الرسائل الفاشلة بسبب غياب تعريف الـ exchanges/bindings اللازمة لمسار dead-letter.
- **إعدادات صف DLQ في `config/queue.php`:** يحدد الطابور الرئيسي مسار dead-letter إلى `orders_dlx`/`order_emails_dlq`، و`order_emails_dlq` مهيأ بمسار لاحق نحو `parking_lot_exchange`/`order_emails_parking_lot`. مع أمر `rabbitmq:setup` تصبح الطوبولوجيا مطابقة ومعلنة صراحةً، لكن يبقى التحقق العملي عبر RabbitMQ UI والسجلات والاختبارات هو أفضل إثبات لسلوك المسار في بيئة التشغيل.

---

## 7) ملخص سريع للملفات الأكثر أهمية

- **RabbitMQ/Queue/Email:** `config/queue.php`, `app/Jobs/SendOrderEmailJob.php`, `app/Console/Commands/RecoverFailedEmailsCommand.php`, `routes/console.php` (جدولة الاسترجاع + `rabbitmq:setup`), `docker-compose.yml` (`rabbitmq`, `rabbitmq-worker`), `Dockerfile`
- **إنشاء الطلب ودفع المهمة والبث الآمن:** `Modules/Order/app/Services/OrderService.php`, `Modules/Order/app/Http/Controllers/OrderController.php`
- **البث اللحظي (Backend):** `Modules/Order/Events/OrderCreatedEvent.php`, `Modules/Order/Events/OrderStatusUpdatedEvent.php`, `routes/channels.php`, `routes/api.php`, `bootstrap/app.php`, `docker-compose.yml` (`reverb`)
- **البث اللحظي (Frontend):** `frontend/src/lib/echo.js`, `frontend/src/hooks/useEchoPrivateChannel.js`, `frontend/src/features/adminOrders/hooks/useAdminOrdersRealtime.js`, `frontend/src/features/notifications/hooks/useCustomerOrderNotifications.js`, `frontend/src/features/orders/hooks/useOrderStatusRealtime.js`, `frontend/src/redux/slices/adminOrderSlice.js`, `frontend/src/redux/slices/orderSlice.js`
- **اختبارات/إثباتات إضافية:** `tests/Feature/OrderEmailQueueResilienceTest.php` (مرونة الطابور وDLQ والاسترجاع، مع شروط تشغيل مذكورة في الملف)

هذا الملف يغطي ما هو مطلوب في المتطلب الرابع: التنفيذ، الملفات، شرح أهم الأكواد، التحديثات الجديدة وأسبابها، وخطوات الاختبار مع Docker والإثباتات المطلوبة.
