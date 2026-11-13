# gRPC في المشروع — الدليل الكامل

> هذا الملف يشرح كل ما يتعلق بـ **gRPC** في هذا المشروع: الفكرة، المعمارية، عقد Protocol Buffers، الإعداد، ملكية البيانات، تسلسل إنشاء الطلب، تفاصيل تنفيذ PHP وJava، آلة حالة الحجوزات، الأخطاء والمهل، الاختبارات، واستكشاف الأعطال.
>
> للمتطلب الأكاديمي المختصر راجع [README-REQUIREMENT-2.md](README-REQUIREMENT-2.md). هذا الملف هو المرجع التقني الكامل.

---

## المحتويات

1. [ما هو gRPC ولماذا استُخدم هنا؟](#1-ما-هو-grpc-ولماذا-استُخدم-هنا)
2. [الخريطة المعمارية](#2-الخريطة-المعمارية)
3. [خريطة الملفات](#3-خريطة-الملفات)
4. [عقد Protocol Buffers](#4-عقد-protocol-buffers)
5. [الإعداد والتشغيل](#5-الإعداد-والتشغيل)
6. [ملكية البيانات وقاعدة المخزون](#6-ملكية-البيانات-وقاعدة-المخزون)
7. [المسارات الكاملة من الواجهة إلى قاعدة البيانات](#7-المسارات-الكاملة-من-الواجهة-إلى-قاعدة-البيانات)
8. [الجانب PHP بالتفصيل](#8-الجانب-php-بالتفصيل)
9. [الجانب Java بالتفصيل](#9-الجانب-java-بالتفصيل)
10. [آلة حالة الحجوزات](#10-آلة-حالة-الحجوزات)
11. [الأخطاء والمهل ونقاط الفشل](#11-الأخطاء-والمهل-ونقاط-الفشل)
12. [الاختبارات](#12-الاختبارات)
13. [استكشاف الأخطاء](#13-استكشاف-الأخطاء)
14. [الأمان](#14-الأمان)
15. [القيود والتحسينات الممكنة](#15-القيود-والتحسينات-الممكنة)
16. [مسرد المصطلحات](#16-مسرد-المصطلحات)
17. [أوامر سريعة](#17-أوامر-سريعة)

---

## 1. ما هو gRPC ولماذا استُخدم هنا؟

**gRPC** إطار اتصال بين الخدمات (RPC = Remote Procedure Call). بدل أن يتصل Laravel بقاعدة بيانات خدمة المستودع مباشرة، يستدعي **دالة** عن بُعد عبر الشبكة، وكأنها دالة محلية:

```php
$inventory->reserveProducts($reservationId, $items);
```

خصائصه في هذا المشروع:

| الخاصية | الأثر العملي |
|---|---|
| **Protocol Buffers** | العقد مكتوب في `inventory.proto`، وتُولَّد منه أصناف PHP وJava. تغيير العقد يتطلب تحديث الطرفين. |
| **HTTP/2** | اتصال واحد متعدد الطلبات، ونقل ثنائي أكفأ من JSON/HTTP عادي. |
| **عقد صريح** | أسماء الحقول وأنواعها وأرقامها ثابتة؛ لا اعتماد على تخمين شكل JSON. |
| **مهل (Deadlines)** | كل نداء هنا له مهلة 5 ثوانٍ، فلا يعلّق الطلب إلى الأبد عند تعطل الخدمة. |
| **رموز حالة موحدة** | `NOT_FOUND`, `INVALID_ARGUMENT`, `DEADLINE_EXCEEDED`, `INTERNAL`... تُترجم إلى رسائل واضحة. |

### لماذا خدمة مستودع منفصلة؟

1. **فصل المسؤوليات:** Laravel يدير المستخدمين والكتالوج والطلبات؛ Java يدير الكميات والحجوزات.
2. **ملكية بيانات واضحة:** جداول المخزون في قاعدة `inventory_db` لا يقرأها Laravel إطلاقًا.
3. **ذرّية الحجز:** خدمة Java تملك المعاملة والأقفال، فتستطيع ضمان «إما كل العناصر أو لا شيء» في مكان واحد.
4. **قابلية الاستبدال:** يمكن استبدال تطبيق Java بخدمة أخرى ما دام يلتزم بـ `inventory.proto`.

> ملاحظة مهمة: gRPC هنا **ليس** بديلًا عن قاعدة بيانات Laravel. هو قناة بين خدمتين، لكل منهما قاعدة بياناتها.

---

## 2. الخريطة المعمارية

```text
┌──────────────────────┐
│  React frontend      │  :5173
│  (Vite)              │
└──────────┬───────────┘
           │ HTTP/JSON (REST)
           ▼
┌──────────────────────┐        ┌──────────────────────┐
│  Laravel backend     │        │  Redis               │
│  PHP-FPM + Nginx     │◄──────►│  (product cache)     │
│  :8080               │        └──────────────────────┘
└──────┬────────┬──────┘
       │        │
       │        │ gRPC (HTTP/2, protobuf)
       │        ▼
       │   ┌───────────────────────────┐
       │   │  inventory-service (Java) │  :9090 gRPC
       │   │  Spring Boot 3.2 / JDK 21 │  :8081 HTTP (داخلي)
       │   └─────────────┬─────────────┘
       │                 │ JDBC
       │                 ▼
       │   ┌───────────────────────────┐
       │   │  inventory-db (MySQL 8.4) │
       │   │  inventory_items          │
       │   │  reservations             │
       │   │  reservation_items        │
       │   └───────────────────────────┘
       │
       │ SQL (read/write split)
       ▼
┌──────────────────────┐        ┌──────────────────────┐
│  mysql-primary       │───────►│  mysql-replica       │
│  (Laravel writes)    │ binlog │  (Laravel reads)     │
└──────────────────────┘        └──────────────────────┘
```

### المكوّنات

| المكوّن | اللغة / التقنية | الدور | المنفذ |
|---|---|---|---|
| `backend` | Laravel 12 / PHP 8.2 | واجهة REST، إنشاء الطلبات، عميل gRPC | `8080` |
| `inventory-service` | Java 21 / Spring Boot 3.2 | خادم gRPC، منطق المخزون والحجوزات | `9090` (gRPC)، `8081` داخلي |
| `inventory-db` | MySQL 8.4 | مصدر الحقيقة للكميات والحجوزات | داخلي فقط |
| `mysql-primary` | MySQL 8.4 | بيانات Laravel (طلبات، منتجات، مستخدمون) | `3307` على المضيف |
| `mysql-replica` | MySQL 8.4 | قراءات Laravel | داخلي فقط |
| `redis` | Redis | كاش قائمة المنتجات | داخلي فقط |
| `frontend` | React 19 / Vite | واجهة المتجر | `5173` |

---

## 3. خريطة الملفات

| الملف | الدور |
|---|---|
| `inventory-service/src/main/proto/inventory.proto` | **العقد** الوحيد بين Laravel وJava. |
| `app/Grpc/Inventory/InventoryServiceClient.php` | عميل gRPC مولَّد (stub) — لا يُعدَّل يدويًا. |
| `app/Grpc/Inventory/*.php` | رسائل protobuf مولَّدة (`ReserveBatchRequest`, `StockItem`...). |
| `app/Grpc/GPBMetadata/Inventory.php` | بيانات وصفية مولَّدة تُسجّل الرسائل في descriptor pool. |
| `app/Contracts/Inventory/InventoryGateway.php` | الواجهة التي يعتمد عليها Laravel (فصل عن gRPC). |
| `app/Services/Inventory/GrpcInventoryGateway.php` | تنفيذ العميل: بناء الطلبات، المهلة، فحص الحالة. |
| `app/Providers/AppServiceProvider.php` | يربط `InventoryGateway` بـ `GrpcInventoryGateway` كـ singleton. |
| `Modules/Order/app/Contracts/StockManager.php` | واجهة إدارة الحجز (`reserve/commit/release`). |
| `Modules/Order/app/Services/GrpcStockManager.php` | تجميع العناصر، التحقق، الحجز الدفعي، التعويض. |
| `Modules/Order/app/Services/LocalStockManager.php` | البديل المحلي باستخدام جداول Laravel. |
| `Modules/Order/app/Providers/OrderServiceProvider.php` | يختار `local` أو `grpc` من الإعدادات **وقت الحل**. |
| `Modules/Order/app/Services/OrderService.php` | تسلسل: حجز ← حفظ الطلب ← تثبيت الحجز. |
| `Modules/Product/app/Services/ProductCatalogService.php` | قراءة المنتجات ودمج الكمية من خدمة المخزون. |
| `Modules/Order/config/config.php` | `order.stock_manager` و`order.grpc_inventory_host`. |
| `inventory-service/.../grpc/InventoryGrpcService.java` | محوّل gRPC: يستقبل الرسائل ويترجم الأخطاء. |
| `inventory-service/.../service/InventoryBusinessService.java` | منطق المخزون: معاملات، أقفال، حجوزات. |
| `inventory-service/src/main/resources/schema.sql` | إنشاء جداول المخزون عند الإقلاع. |
| `inventory-service/src/main/resources/application.properties` | منافذ واتصال قاعدة البيانات. |
| `docker-compose.yml` | الشبكة، المنافذ، متغيرات gRPC، والاعتماديات. |
| `Modules/Order/tests/Feature/InventoryGrpcTest.php` | اختبارات وحدة لـ `GrpcStockManager`. |
| `Modules/Order/tests/Feature/StockManagerBindingTest.php` | اختبارات اختيار `local`/`grpc`. |
| `Modules/Order/tests/Integration/InventoryGrpcIntegrationTest.php` | اختبارات حية ضد خدمة gRPC. |

---

## 4. عقد Protocol Buffers

الملف الكامل: [`inventory-service/src/main/proto/inventory.proto`](inventory-service/src/main/proto/inventory.proto)

```proto
syntax = "proto3";

package inventory;

option java_multiple_files = true;
option java_package = "com.example.inventory.grpc";
option java_outer_classname = "InventoryProto";

service InventoryService {
  rpc GetStockQuantity  (StockRequest)       returns (StockResponse);
  rpc EnsureProductStock(ReserveRequest)     returns (ReserveResponse);
  rpc ReserveStock      (ReserveRequest)     returns (ReserveResponse);
  rpc ReserveStockBatch (ReserveBatchRequest) returns (ReserveResponse);
  rpc CommitReservation (ReserveRequest)     returns (ReserveResponse);
  rpc ReleaseReservation(ReserveRequest)     returns (ReserveResponse);
  rpc RollbackStock     (RollbackRequest)    returns (RollbackResponse);
}
```

### 4.1 الدوال (RPCs)

| الدالة | المدخل | المخرج | تُستخدم من Laravel؟ | الوظيفة |
|---|---|---|---|---|
| `GetStockQuantity` | `StockRequest` | `StockResponse` | نعم | قراءة الكمية المتاحة. |
| `EnsureProductStock` | `ReserveRequest` | `ReserveResponse` | نعم | إنشاء سجل مخزون إن لم يوجد (لا يعدّل الموجود). |
| `ReserveStock` | `ReserveRequest` | `ReserveResponse` | نظريًا | حجز منتج واحد. |
| `ReserveStockBatch` | `ReserveBatchRequest` | `ReserveResponse` | **نعم — المسار الأساسي** | حجز كل عناصر الطلب في معاملة واحدة. |
| `CommitReservation` | `ReserveRequest` | `ReserveResponse` | نعم | تثبيت الحجز بعد حفظ الطلب. |
| `ReleaseReservation` | `ReserveRequest` | `ReserveResponse` | نعم | إرجاع الكميات وإلغاء الحجز. |
| `RollbackStock` | `RollbackRequest` | `RollbackResponse` | لا (متاح في العقد) | إرجاع عنصر واحد داخل حجز. |

### 4.2 الرسائل

| الرسالة | الحقول | ملاحظات |
|---|---|---|
| `StockRequest` | `int64 product_id = 1` | معرف المنتج. |
| `StockResponse` | `int64 product_id`, `int32 available_quantity` | الكمية الحالية. |
| `ReserveRequest` | `int64 product_id`, `int32 quantity`, `string order_ref_id` | في عمليات الحجز/التثبيت/التحرير يُستخدم `order_ref_id` كمعرّف الحجز. |
| `ReserveResponse` | `bool success`, `string message` | `success=false` يعني رفضًا منطقيًا (مثل نقص المخزون)، وليس بالضرورة خطأ نقل. |
| `StockItem` | `int64 product_id`, `int32 quantity` | عنصر واحد داخل الدفعة. |
| `ReserveBatchRequest` | `string order_ref_id`, `repeated StockItem items` | كل عناصر السلة. |
| `RollbackRequest` | `product_id`, `quantity`, `order_ref_id` | تراجع جزئي. |
| `RollbackResponse` | `bool success`, `string message` | نتيجة التراجع. |

### 4.3 ملاحظات مهمة على العقد

- `package inventory` يحدد namespace PHP المولَّد: `Inventory\`.
- أرقام الحقول (`= 1`, `= 2`) **لا يجوز تغييرها** بعد النشر؛ فهي هوية الحقل على الشبكة. أضف حقولًا جديدة بأرقام جديدة.
- لا يوجد `optional` أو `oneof` هنا؛ كل الحقول لها قيم افتراضية (`0` أو `""`).
- `repeated StockItem` تسمح بإرسال كل السلة في **نداء واحد**، وهو أساس الذرّية.
- `order_ref_id` في `EnsureProductStock` يُترك فارغًا؛ لأن العملية للتهيئة فقط.
- `ReserveStock` و`RollbackStock` موجودان في العقد لكن مسار الطلب الحالي لا يستخدمهما؛ المسار يستخدم `ReserveStockBatch` ثم `CommitReservation`/`ReleaseReservation`.

---

## 5. الإعداد والتشغيل

### 5.1 المتغيرات البيئية

| المتغير | القيمة الافتراضية | المعنى |
|---|---|---|
| `ORDER_STOCK_MANAGER` | `local` | أي تنفيذ لـ `StockManager`: `local` أو `grpc`. |
| `GRPC_INVENTORY_HOST` | `127.0.0.1:9090` | عنوان خدمة المخزون بصيغة `host:port`. |
| `GRPC_INVENTORY_TIMEOUT` | `5` | مهلة كل نداء gRPC بالثواني؛ تمنع انتظار المستخدم بلا نهاية. |
| `GRPC_FORWARD_PORT` | `9090` | منفذ gRPC المنشور على المضيف في Compose. |
| `INVENTORY_DB_DATABASE` | `inventory_db` | اسم قاعدة المخزون. |
| `INVENTORY_DB_USERNAME` | `inventory` | مستخدم قاعدة المخزون. |
| `INVENTORY_DB_PASSWORD` | `inventory_dev_password` | كلمة المرور. |
| `SPRING_DATASOURCE_URL` | `jdbc:mysql://inventory-db:3306/...` | اتصال Java بقاعدة المخزون. |

القيم الفعلية في Compose للـ backend:

```yaml
environment:
  ORDER_STOCK_MANAGER: grpc
  GRPC_INVENTORY_HOST: inventory-service:9090
```

أما `.env.example` فيستخدم `local` و`127.0.0.1:9090` للتشغيل المحلي.

### 5.2 الاختيار بين local وgrpc

الإعداد في [`Modules/Order/config/config.php`](Modules/Order/config/config.php):

```php
'stock_manager' => env('ORDER_STOCK_MANAGER', 'local'),
'grpc_inventory_host' => env('GRPC_INVENTORY_HOST', '127.0.0.1:9090'),
```

والربط في [`OrderServiceProvider.php`](Modules/Order/app/Providers/OrderServiceProvider.php):

```php
$this->app->bind(StockManager::class, function ($app): StockManager {
    return match (config('order.stock_manager', 'local')) {
        'local' => $app->make(LocalStockManager::class),
        'grpc'  => $app->make(GrpcStockManager::class),
        default => throw new InvalidArgumentException(
            'ORDER_STOCK_MANAGER must be either "local" or "grpc".'
        ),
    };
});
```

#### لماذا `bind` مع closure وليس ربطًا مباشرًا؟

هذا **إصلاح عطل حقيقي** في المشروع. في البداية كان الربط هكذا:

```php
// نسخة قديمة خاطئة
if (config('order.stock_manager') === 'grpc') {
    $this->app->singleton(StockManager::class, GrpcStockManager::class);
} else {
    $this->app->singleton(StockManager::class, LocalStockManager::class);
}
```

المشكلة: `register()` ينفّذ **قبل** أن يدمج Nwidart إعدادات الوحدة في `boot()`. لذلك كان `config('order.stock_manager')` يرى القيمة الافتراضية `local` دائمًا، حتى مع `ORDER_STOCK_MANAGER=grpc`. النتيجة: الطلبات تُخصم من `products.stock` في Laravel، بينما الواجهة تعرض الكمية من خدمة gRPC، فيظهر أن المخزون لا ينقص.

الحل الحالي يقرأ الإعداد **لحظة طلب الكائن** (lazy)، بعد اكتمال تحميل الإعدادات. اختبارات `StockManagerBindingTest` تحمي هذا السلوك.

### 5.3 التشغيل عبر Docker

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
docker compose build
docker compose run --rm --no-deps backend php artisan key:generate
docker compose up -d
docker compose ps
```

- `inventory-db` يبدأ أولًا ويصبح `healthy`.
- `inventory-service` ينتظر قاعدة البيانات، ثم ينشئ الجداول من `schema.sql` ويستمع على `9090`.
- `backend` ينتظر `migrate` و`inventory-service` و`redis` ويأخذ `ORDER_STOCK_MANAGER=grpc` من Compose.
- فحص صحة خدمة المخزون: `bash -c '</dev/tcp/127.0.0.1/9090'`.

### 5.4 التشغيل المحلي (بدون Docker)

1. أنشئ قاعدة `inventory_db` ومستخدمها.
2. شغّل خدمة Java:

   ```powershell
   $env:SPRING_DATASOURCE_URL = "jdbc:mysql://127.0.0.1:3306/inventory_db?useSSL=false&serverTimezone=UTC&allowPublicKeyRetrieval=true"
   $env:INVENTORY_DB_USERNAME = "inventory"
   $env:INVENTORY_DB_PASSWORD = "inventory_dev_password"
   Set-Location inventory-service
   .\mvnw.cmd spring-boot:run
   ```

3. في Laravel `.env`:

   ```dotenv
   ORDER_STOCK_MANAGER=grpc
   GRPC_INVENTORY_HOST=127.0.0.1:9090
   ```

4. تأكد من امتداد PHP gRPC:

   ```powershell
   php -m | Select-String grpc
   ```

   إن لم يظهر، فعّله في `php.ini` (`extension=grpc`) أو استخدم حاوية `backend` التي تثبّته عبر PECL.

5. بعد أي تغيير في `.env` مع كاش الإعدادات:

   ```powershell
   php artisan config:clear
   ```

### 5.5 توليد أصناف PHP من العقد

الأصناف المولَّدة **مُلتزَمة في المستودع** تحت `app/Grpc/` ومربوطة في `composer.json`:

```json
"autoload": {
  "psr-4": {
    "Inventory\\": "app/Grpc/Inventory/",
    "GPBMetadata\\": "app/Grpc/GPBMetadata/"
  }
}
```

عند تعديل `inventory.proto` يجب إعادة التوليد باستخدام `protoc` مع إضافة `grpc_php_plugin`، ثم:

```powershell
composer dump-autoload
```

لا يوجد سكربت توليد آلي في المشروع؛ هذا أحد التحسينات المقترحة. المهم أن يتطابق العقد مع نسخة Java المولَّدة عند البناء.

---

## 6. ملكية البيانات وقاعدة المخزون

### 6.1 من يملك ماذا؟

| البيانات | المالك | قاعدة البيانات |
|---|---|---|
| المستخدمون، الصلاحيات | Laravel | `internet_apps` |
| التصنيفات والمنتجات (الاسم، السعر، الصورة) | Laravel | `internet_apps` |
| `products.stock` | Laravel | `internet_apps` |
| الطلبات وعناصرها | Laravel | `internet_apps` |
| الكمية المتاحة الفعلية | **inventory-service** | `inventory_db` |
| الحجوزات وسجلاتها | **inventory-service** | `inventory_db` |

**قاعدة ذهبية:** بعد إنشاء سجل مخزون لمنتج، خدمة المخزون هي مصدر الحقيقة للكمية. Laravel لا يقرأ جداولها ولا يكتب فيها مباشرة.

### 6.2 المخطط

```sql
CREATE TABLE inventory_items (
    product_id BIGINT NOT NULL PRIMARY KEY,
    available_quantity INT NOT NULL,
    CONSTRAINT chk_inventory_items_quantity CHECK (available_quantity >= 0)
);

CREATE TABLE reservations (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    status VARCHAR(16) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_reservations_status CHECK (status IN ('RESERVED', 'COMMITTED', 'RELEASED'))
);

CREATE TABLE reservation_items (
    reservation_id VARCHAR(64) NOT NULL,
    product_id BIGINT NOT NULL,
    quantity INT NOT NULL,
    released BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (reservation_id, product_id),
    FOREIGN KEY (reservation_id) REFERENCES reservations (id),
    FOREIGN KEY (product_id) REFERENCES inventory_items (product_id)
);
```

دلالات المخطط:

- `PRIMARY KEY (reservation_id, product_id)` يعني صفًا واحدًا لكل منتج في الحجز؛ لذلك تُجمَّع الأسطر المتكررة قبل الإرسال.
- `CHECK (available_quantity >= 0)` يمنع المخزون السالب على مستوى قاعدة البيانات أيضًا.
- `released` يسمح بتحرير عنصر واحد (`RollbackStock`) دون إغلاق الحجز كله.

### 6.3 كيف تُهيَّأ الكميات أول مرة؟

`ProductCatalogService::snapshotWithInventory()` عند كل قراءة لمنتج (عند تفعيل gRPC):

```php
$this->inventory->ensureProductStock($productId, (int) $product->stock);
$snapshot['stock'] = $this->inventory->getAvailableQuantity($productId);
```

و`ensureProductStock` في Java تنفّذ:

```sql
INSERT IGNORE INTO inventory_items (product_id, available_quantity) VALUES (?, ?)
```

النتيجة:

- إن لم يوجد سجل → يُنشأ بالقيمة الموجودة في `products.stock` (أو الكمية المدخلة عند إنشاء المنتج).
- إن وُجد → **لا يتغير**؛ القراءة اللاحقة تعيد كمية خدمة المخزون.
- لذلك تعديل `products.stock` من لوحة الإدارة لا يغيّر المخزون الفعلي بعد التهيئة الأولى.

### 6.4 مثال حقيقي: منتج بـ 50 قطعة

1. إنشاء المنتج بـ `stock = 50`.
2. أول قراءة → `EnsureProductStock(50)` → لا سجل → يُنشأ بـ 50.
3. طلب 1 قطعة → حجز → 49 → تثبيت.
4. طلب 40 قطعة → حجز → 9 → تثبيت.
5. الواجهة تعرض 9 لأنها تقرأ من `GetStockQuantity`.

إذا ظهرت 50 بعد الطلب، فالمشكلة غالبًا في اختيار `LocalStockManager` بدل `GrpcStockManager`، أو في قراءة `products.stock` بدل خدمة المخزون. راجع [القسم 13](#13-استكشاف-الأخطاء).

---

## 7. المسارات الكاملة من الواجهة إلى قاعدة البيانات

### 7.1 قراءة منتج أو قائمة منتجات

```text
React → GET /api/v1/products
      → Laravel ProductCatalogService::findByIds()
      → لكل منتج:
          EnsureProductStock(productId, products.stock)   [gRPC]
          GetStockQuantity(productId)                    [gRPC]
      → دمج stock في الاستجابة وإرجاعها للواجهة
```

- تُستدعى `EnsureProductStock` قبل القراءة لضمان وجود سجل، وهي آمنة لأنها `INSERT IGNORE`.
- القائمة تُخزَّن في Redis، لكن الكمية تُقرأ من خدمة المخزون، لذلك تغيّر المخزون يمكن أن يغيّر ETag حتى مع كاش الكتالوج.
- ملاحظة أداء: كل منتج يُنتج نداءين gRPC. تحسين مستقبلي: RPC دفعي للقراءة.

### 7.2 إنشاء طلب من السلة (المسار الأساسي)

```text
React → POST /api/v1/orders/cart/checkout
      → CartController::checkout()
      → CartService::getCheckoutItems()          // عناصر السلة فقط
      → OrderService::createOrder()
          1. reservationId = UUID
          2. StockManager::reserve(id, items)     // gRPC ReserveStockBatch
          3. DB::transaction:
               - جلب المنتجات (findByIds)
               - حساب الإجمالي والـ subtotals
               - إنشاء Order + OrderItems
          4. StockManager::commit(id)             // gRPC CommitReservation
      → CartService::clearCart()
      → 201 مع الطلب
```

التفصيل الزمني:

| الخطوة | الطرف | الأثر على المخزون |
|---|---|---|
| 1 | Laravel | لا شيء (توليد UUID). |
| 2 | Java | فحص كل الكميات، خصمها، إنشاء `RESERVED` + عناصر الحجز. |
| 3 | Laravel | حفظ الطلب في MySQL. |
| 4 | Java | تحويل الحالة إلى `COMMITTED`. الكمية تبقى مخصومة. |
| 5 | Laravel | تفريغ السلة. |

### 7.3 لماذا الحجز قبل حفظ الطلب؟

لأنه لا يجوز إنشاء طلب لا يمكن الوفاء به. لو حُفظ الطلب أولًا ثم فشل الحجز، سنحتاج إلى حذف/إلغاء الطلب. الترتيب الحالي يضمن أن أي طلب محفوظ يملك مخزونًا محجوزًا.

### 7.4 التعويض عند فشل حفظ الطلب

إذا فشل `DB::transaction` (مثلًا مستخدم غير موجود، أو خطأ SQL):

```php
try {
    $order = DB::transaction(...);
} catch (\Throwable $exception) {
    $release = $this->stockManager->release($reservationId); // إرجاع الكميات
    throw $exception;
}
```

- إن فشل التحرير أيضًا، تُسجَّل `Log::critical` وتُضاف التفاصيل إلى رسالة الخطأ.
- لا يُعاد للمستخدم نجاح كاذب.

### 7.5 عند نتيجة gRPC غير مؤكدة

`GrpcStockManager::reserve()` يلفّ النداء في `try/catch`. عند `Throwable`:

1. يسجّل الخطأ.
2. يحاول `releaseReservation($reservationId)` بالمعرّف نفسه.
3. يعيد `success=false` مع سبب الفشل (بما فيه فشل التحرير إن حدث).

هذا مهم لأن انقطاع الشبكة قد يحدث **بعد** أن تكون Java نفّذت الحجز لكن قبل وصول الرد. التحرير بالمعرّف نفسه يعالج هذا الاحتمال (idempotent).

### 7.6 Idempotency (تكرار الطلب نفسه)

Java تتعامل مع معرّف الحجز كمفتاح تكرار:

- إذا وُجد حجز بالمعرّف نفسه وبنفس العناصر والكميات وحالته `RESERVED` أو `COMMITTED` → تُعيد `success=true` دون خصم جديد.
- إذا اختلفت العناصر أو الكميات → `success=false` مع `Reservation does not match the previous request.`
- إذا كان الحجز `RELEASED` → لا يُعاد استخدامه.

هذا يجعل إعادة المحاولة بعد مهلة آمنة إلى حد كبير.

### 7.7 إلغاء الطلب (قيد معروف)

`OrderService::updateStatus()` يغيّر حالة الطلب فقط. **لا يستدعي `releaseReservation`** عند الانتقال إلى `CANCELLED`. لذلك:

- الكمية تبقى مخصومة بعد الإلغاء.
- هذا سلوك غير مكتمل مقصود توثيقه، وليس مخفيًا. راجع [القسم 15](#15-القيود-والتحسينات-الممكنة).

---

## 8. الجانب PHP بالتفصيل

### 8.1 `InventoryGateway` (الواجهة)

```php
interface InventoryGateway
{
    public function ensureProductStock(int $productId, int $initialQuantity): void;
    public function getAvailableQuantity(int $productId): int;
    public function reserveProduct(string $reservationId, int $productId, int $quantity): array;
    public function reserveProducts(string $reservationId, array $items): array;
    public function commitReservation(string $reservationId): array;
    public function releaseReservation(string $reservationId): array;
}
```

الفائدة: `ProductCatalogService` و`GrpcStockManager` لا يعرفان شيئًا عن أصناف protobuf أو gRPC؛ يمكن استبدال التنفيذ في الاختبارات بـ Mock.

### 8.2 `GrpcInventoryGateway` (التنفيذ)

نقاط جوهرية:

```php
private const TIMEOUT_MICROSECONDS = 5_000_000; // 5 ثوانٍ

$host = config('order.grpc_inventory_host', '127.0.0.1:9090');
$client = new InventoryServiceClient($host, [
    'credentials' => ChannelCredentials::createInsecure(),
]);

[$response, $status] = $client->{$method}(
    $request,
    [],
    ['timeout' => self::TIMEOUT_MICROSECONDS]
)->wait();

if ($status->code !== 0) {
    throw new RuntimeException(
        "Inventory gRPC request failed ({$status->code}): "
        . ($status->details ?: 'Unknown error')
    );
}
```

- **عميل جديد لكل نداء:** بسيط وآمن، لكنه لا يستفيد من إعادة استخدام قناة HTTP/2. تحسين مستقبلي: عميل/قناة مشتركة.
- **`ChannelCredentials::createInsecure()`:** اتصال نصي صريح داخل شبكة خاصة. لا تستخدمه عبر شبكة غير موثوقة.
- **فحص `status->code`:** أي رمز غير صفر يُحوَّل إلى استثناء.
- **فحص نوع الاستجابة:** يمنع إرجاع كائن غير متوقع.
- **فحص وجود الامتداد:** رسالة واضحة إذا لم يكن `grpc` مثبتًا.

### 8.3 `GrpcStockManager` (منسّق الحجز)

المسؤوليات:

1. التحقق من `product_id > 0` و`quantity > 0`.
2. **تجميع الأسطر المتكررة** لنفس المنتج.
3. منع تجاوز حد `int32` (`2,147,483,647`).
4. رفض الدفعة الفارغة.
5. ترتيب المنتجات تصاعديًا (`ksort`) قبل الإرسال — يساعد على توحيد ترتيب الأقفال.
6. إرسال نداء واحد إلى `reserveProducts`.
7. التعويض عند الاستثناء.

مقتطف التجميع:

```php
$currentQuantity = $quantities[$productId] ?? 0;
if ($quantity > 2_147_483_647 - $currentQuantity) {
    return ['success' => false, 'message' => '...'];
}
$quantities[$productId] = $currentQuantity + $quantity;
...
ksort($quantities);
$reservation = $this->inventory->reserveProducts($reservationId, ...);
```

### 8.4 `OrderService` (تسلسل العمل)

التسلسل الفعلي في `createOrder()`:

```php
$reservationId = (string) Str::uuid();

$reservation = $this->stockManager->reserve($reservationId, $items);
if (!$reservation['success']) {
    throw new \RuntimeException($reservation['message']);
}

try {
    $order = DB::transaction(function () use ($userId, $items) {
        // جلب المنتجات، حساب الإجمالي، إنشاء Order + Items
    });
} catch (\Throwable $exception) {
    // release + تسجيل حرج + إعادة رمي
}

try {
    $commit = $this->stockManager->commit($reservationId);
    if (!$commit['success']) { /* رمي استثناء */ }
} catch (\Throwable $exception) {
    Log::critical('Order was saved but its inventory reservation could not be committed.', [...]);
    throw new \RuntimeException("Order {$order->id} was saved, but inventory reservation confirmation failed.");
}

return $order;
```

نقطة مهمة: **التثبيت بعد المعاملة**. إذا فشل `commit`:

- الطلب محفوظ في Laravel.
- الحجز في Java ما زال `RESERVED` (الكمية مخصومة أصلًا).
- يُعاد خطأ للمستخدم رغم وجود الطلب. هذا فجوة اتساق معروفة.

### 8.5 `ProductCatalogService` (دمج المخزون)

- `snapshot()` يقرأ بيانات Laravel فقط (`products.stock`).
- `snapshotWithInventory()`:
  - إذا كان المدير `local` → يعيد `products.stock` كما هو.
  - إذا كان `grpc` → يضمن السجل ثم يقرأ الكمية الفعلية ويستبدل `stock` في اللقطة.
- `reserveStock()` و`releaseStock()` تُستخدمان في المسار **المحلي** فقط (جداول Laravel).

### 8.6 `LocalStockManager` (البديل المحلي)

يستخدم جداول Laravel `stock_reservations` و`stock_reservation_items`، ويستدعي `ProductCatalogService::reserveStock()` التي تخصم `products.stock` داخل معاملة مع `lockForUpdate()`.

متى تستخدمه؟

- الاختبارات (مفروض في `phpunit.xml` و`tests/bootstrap.php`).
- التطوير بدون خدمة Java.
- ليس مناسبًا لتعدد الخدمات أو التزامن العالي؛ لا يوجد اتصال بـ gRPC.

---

## 9. الجانب Java بالتفصيل

### 9.1 `InventoryGrpcService` (طبقة gRPC)

- يرث `InventoryServiceGrpc.InventoryServiceImplBase` (مولَّد من proto).
- موسوم بـ `@GrpcService` من `grpc-spring-boot-starter`.
- يحوّل رسائل protobuf إلى أنواع Java داخلية، ثم يستدعي `InventoryBusinessService`.
- يترجم الاستثناءات إلى رموز gRPC:

| الاستثناء | رمز gRPC |
|---|---|
| `ProductNotFoundException` | `NOT_FOUND` |
| `IllegalArgumentException` | `INVALID_ARGUMENT` |
| `InsufficientStockException` (في الدفعة) | **ليس خطأ gRPC**؛ استجابة `success=false` |
| أي استثناء آخر | `INTERNAL` |

- `ReserveResponse.success=false` يعني رفضًا **متوقعًا** (نقص مخزون، حجز لم يعد نشطًا). أما رمز الحالة غير الصفري فيعني فشل نقل/تنفيذ.

### 9.2 `InventoryBusinessService` (المنطق)

#### `ensureProductStock`

```java
@Transactional
public int ensureProductStock(long productId, int initialQuantity) {
    validateProductId(productId);
    if (initialQuantity < 0) throw new IllegalArgumentException(...);
    jdbcTemplate.update(
        "INSERT IGNORE INTO inventory_items (product_id, available_quantity) VALUES (?, ?)",
        productId, initialQuantity
    );
    return getAvailableQuantity(productId);
}
```

#### `reserveStockBatch` — قلب الذرّية

```java
@Transactional
public ReservationResult reserveStockBatch(String reservationId,
                                           List<StockReservationItem> requestedItems) {
    // 1. تجميع وترتيب المنتجات
    Map<Long, Integer> quantities = new TreeMap<>();
    for (StockReservationItem item : requestedItems) {
        validateProductId(item.productId());
        if (item.quantity() <= 0) throw new IllegalArgumentException(...);
        quantities.merge(item.productId(), item.quantity(), Math::addExact);
    }

    // 2. إنشاء/قفل سجل الحجز
    jdbcTemplate.update("INSERT IGNORE INTO reservations (id, status) VALUES (?, 'RESERVED')", reservationId);
    String status = jdbcTemplate.queryForObject(
        "SELECT status FROM reservations WHERE id = ? FOR UPDATE", String.class, reservationId);

    // 3. فحص التكرار (idempotency)
    List<Map<String, Object>> existing = jdbcTemplate.queryForList(
        "SELECT product_id, quantity, released FROM reservation_items " +
        "WHERE reservation_id = ? ORDER BY product_id FOR UPDATE", reservationId);

    // 4. فحص كل الكميات أولًا (دون خصم)
    for (var item : quantities.entrySet()) {
        int available = jdbcTemplate.queryForObject(
            "SELECT available_quantity FROM inventory_items WHERE product_id = ? FOR UPDATE",
            Integer.class, item.getKey());
        if (available < item.getValue()) {
            throw new InsufficientStockException("Insufficient stock for product: " + item.getKey());
        }
    }

    // 5. الخصم ثم تسجيل العناصر
    for (var item : quantities.entrySet()) {
        jdbcTemplate.update(
            "UPDATE inventory_items SET available_quantity = available_quantity - ? WHERE product_id = ?",
            item.getValue(), item.getKey());
        jdbcTemplate.update(
            "INSERT INTO reservation_items (reservation_id, product_id, quantity) VALUES (?, ?, ?)",
            reservationId, item.getKey(), item.getValue());
    }

    return ReservationResult.succeeded();
}
```

ماذا يضمن هذا؟

| الضمان | كيف؟ |
|---|---|
| لا خصم جزئي | الفحص كله قبل أي `UPDATE`، وأي استثناء يتراجع عن المعاملة. |
| لا مخزون سالب | `FOR UPDATE` + فحص الكمية + `CHECK` في القاعدة. |
| لا جمود (deadlock) | `TreeMap` يرتب معرفات المنتجات، فتُقفل الصفوف بترتيب ثابت. |
| إعادة المحاولة آمنة | معرّف الحجز + مقارنة العناصر. |

#### `commitReservation`

- لا يوجد حجز → `success=false`.
- `COMMITTED` → `success=true` (idempotent).
- `RELEASED` → `success=false`.
- `RESERVED` → تصبح `COMMITTED`.

#### `releaseReservation`

- لا يوجد أو `RELEASED` → `success=true` (idempotent).
- `COMMITTED` → `success=false` (لا يجوز تحرير مخزون مثبَّت).
- `RESERVED` → تُعاد الكميات لكل عنصر غير محرَّر، تُعلَّم العناصر `released=TRUE`، وتصبح الحالة `RELEASED`.

#### `rollbackStock`

- تراجع عنصر واحد بشرط تطابق الكمية.
- إذا لم يبق عنصر غير محرَّر → تُغلق الحجز `RELEASED`.

### 9.3 الإقلاع والاتصال

`application.properties`:

```properties
server.port=8081
grpc.server.port=9090
spring.sql.init.mode=always
spring.datasource.url=${SPRING_DATASOURCE_URL:jdbc:mysql://localhost:3306/inventory_db?...}
spring.datasource.username=${INVENTORY_DB_USERNAME:inventory}
spring.datasource.password=${INVENTORY_DB_PASSWORD:inventory_dev_password}
```

- `spring.sql.init.mode=always` يشغّل `schema.sql` عند كل إقلاع؛ الأوامر `CREATE TABLE IF NOT EXISTS` آمنة.
- لا توجد مهاجرات Java (Flyway/Liquibase)؛ المخطط بسيط ومحمي بـ `IF NOT EXISTS`.
- خدمة HTTP على `8081` غير منشورة على المضيف في Compose؛ الواجهة الوحيدة هي gRPC.

### 9.4 الاعتماديات (من `pom.xml`)

| الاعتمادية | الإصدار |
|---|---|
| Java | 21 |
| Spring Boot | 3.2.2 |
| gRPC | 1.60.0 |
| protobuf | 3.25.1 |
| `grpc-spring-boot-starter` | 3.1.0.RELEASE |

---

## 10. آلة حالة الحجوزات

```text
                 ReserveStockBatch
                        │
                        ▼
                  ┌───────────┐
                  │  RESERVED │
                  └─────┬─────┘
             commit     │      release / rollback
                        ▼                ▼
                  ┌───────────┐    ┌───────────┐
                  │ COMMITTED │    │ RELEASED  │
                  └───────────┘    └───────────┘
```

| الانتقال | من | إلى | من يفعله | ملاحظات |
|---|---|---|---|---|
| حجز | — | `RESERVED` | `ReserveStockBatch` | يخصم الكميات. |
| تثبيت | `RESERVED` | `COMMITTED` | `CommitReservation` | لا يغيّر الكمية. |
| تثبيت مكرر | `COMMITTED` | `COMMITTED` | `CommitReservation` | يعيد `success=true`. |
| تحرير | `RESERVED` | `RELEASED` | `ReleaseReservation` | يعيد الكميات. |
| تحرير مكرر | `RELEASED` | `RELEASED` | `ReleaseReservation` | يعيد `success=true`. |
| تحرير مثبَّت | `COMMITTED` | — | `ReleaseReservation` | مرفوض. |
| تثبيت محرَّر | `RELEASED` | — | `CommitReservation` | مرفوض. |

**الحالة النهائية دائمًا `COMMITTED` أو `RELEASED`.** لا يوجد حجز معلّق إلى الأبد من منظور الخدمة؛ لكن إذا انقطع Laravel قبل `commit` أو `release`، يبقى `RESERVED` بلا تنظيف آلي. هذا أحد القيود.

---

## 11. الأخطاء والمهل ونقاط الفشل

### 11.1 رموز gRPC وكيف تظهر في Laravel

| رمز | الرقم | المعنى | ما يراه المستخدم |
|---|---|---|---|
| `OK` | 0 | نجاح | لا شيء. |
| `INVALID_ARGUMENT` | 3 | مدخل غير صالح (منتج ≤ 0، كمية ≤ 0، معرّف حجز فارغ) | رسالة التحقق. |
| `DEADLINE_EXCEEDED` | 4 | تجاوز المهلة المضبوطة | `Inventory service did not respond within 5 seconds. Please try again later.` مع HTTP **503** |
| `NOT_FOUND` | 5 | لا يوجد سجل مخزون للمنتج | رسالة `Product not found`. |
| `UNAVAILABLE` | 14 | الخدمة متوقفة/غير قابلة للوصول | `Inventory service is currently unavailable. Please try again later.` مع HTTP **503** |
| `INTERNAL` | 13 | خطأ غير متوقع في Java | رسالة فشل واضحة مع HTTP **503** |

### 11.2 المهلة (Deadline)

- القيمة الافتراضية: 5 ثوانٍ لكل نداء، ويمكن ضبطها عبر `GRPC_INVENTORY_TIMEOUT` (مثل `3` أو `2.5`).
- تُطبَّق على `GetStockQuantity` و`EnsureProductStock` و`ReserveStockBatch` و`Commit` و`Release`.
- عند فشل/انتهاء مهلة قراءة المنتجات، تتوقف القراءة عند أول منتج وتُعاد استجابة **503** برسالة واضحة بدل استهلاك مهلة جديدة لكل منتج أو إرجاع **500**.
- عند انتهاء المهلة، قد يكون الحجز قد نُفِّذ فعلًا؛ لذلك `GrpcStockManager` يحاول التحرير بالمعرّف نفسه.
- اختبار `test_slow_grpc_server_is_stopped_by_the_five_second_deadline` يتحقق من أن الطلب يتوقف بين 4 و6.5 ثوانٍ.

### 11.3 مصفوفة نقاط الفشل

| السيناريو | النتيجة |
|---|---|
| خدمة المخزون متوقفة قبل الحجز | فشل سريع (`UNAVAILABLE`) أو بعد المهلة؛ لا طلب جديد. |
| الخدمة بطيئة | `DEADLINE_EXCEEDED` بعد 5 ثوانٍ؛ لا نجاح كاذب. |
| نقص مخزون في عنصر واحد | `success=false` من Java؛ لا خصم لأي عنصر؛ لا طلب. |
| فشل حفظ الطلب بعد الحجز | Laravel يحرّر الحجز؛ الكمية تعود. |
| فشل `commit` بعد حفظ الطلب | الطلب موجود، الحجز `RESERVED`، خطأ للمستخدم. |
| فشل التحرير بعد فشل الحفظ | خطأ مركّب + `Log::critical`. |
| إعادة إرسال الحجز نفسه | idempotent إن تطابقت العناصر. |
| محاولة تحرير حجز مثبَّت | مرفوضة. |

### 11.4 أين تُسجَّل الأخطاء؟

- `GrpcStockManager`: `Log::error` لفشل الحجز، و`Log::critical` لفشل التحرير.
- `OrderService`: `Log::critical` لفشل التحرير بعد فشل الحفظ، ولفشل التثبيت.
- `InventoryGrpcService`: `LOGGER.error` لأخطاء الحجز غير المتوقعة.

---

## 12. الاختبارات

### 12.1 اختبارات الوحدة (لا تحتاج خدمة gRPC)

| الملف | ماذا يغطي |
|---|---|
| `Modules/Order/tests/Feature/InventoryGrpcTest.php` | نجاح الحجز، منتج غير موجود، نقص مخزون، دفعة واحدة، تحرير، سباق منطقي، مهلة، تثبيت، تجميع التكرار، رفض المدخلات غير الصالحة. يستخدم Mock لـ `InventoryGateway`. |
| `Modules/Order/tests/Feature/StockManagerBindingTest.php` | حل `local`، حل `grpc`، تغيير الاختيار وقت التشغيل، رفض قيمة غير معروفة. |

تشغيلها:

```powershell
php artisan test Modules/Order/tests/Feature/InventoryGrpcTest.php
php artisan test Modules/Order/tests/Feature/StockManagerBindingTest.php
```

### 12.2 اختبارات التكامل الحية

الملف: `Modules/Order/tests/Integration/InventoryGrpcIntegrationTest.php`

تتطلب:

- `RUN_INVENTORY_INTEGRATION_TESTS=1`
- امتداد PHP `grpc`
- خدمة `inventory-service` تعمل

التغطية:

1. قراءة، حجز، ثم تحرير كمية حقيقية.
2. ذرّية الدفعة عند نقص عنصر واحد (لا يُخصم الآخر).
3. سباق عمليتان متزامنتان على آخر قطعة: واحدة فقط تنجح (5 محاولات).
4. فشل حفظ الطلب → تحرير الحجز الحقيقي.
5. خدمة غير متاحة → فشل خلال المهلة.
6. خدمة لا تستجيب → `DEADLINE_EXCEEDED` بعد ~5 ثوانٍ.
7. الحجز المثبَّت لا يمكن تحريره.

الأمر (من `README.md`):

```powershell
docker compose up -d
docker compose run --rm --no-deps -e RUN_INVENTORY_INTEGRATION_TESTS=1 backend php artisan test --testsuite=Integration
```

ملاحظات:

- الاختبارات تُنشئ منتجات بأرقام عشوائية كبيرة (`1e15` تقريبًا) وقد تترك صفوفًا في `inventory_items`. ليست منتجات كتالوج.
- `RefreshDatabase` يعمل على SQLite في الذاكرة للجانب Laravel فقط؛ لا يمس قاعدة المخزون.
- `phpunit.xml` و`tests/bootstrap.php` يفرضان `ORDER_STOCK_MANAGER=local` وبطاقة SQLite، لحماية قاعدة التطوير من الاختبارات المدمِّرة.

### 12.3 الاختبار اليدوي من الواجهة

1. سجّل الدخول بحساب مدير وأنشئ منتجًا بمخزون معروف.
2. أضف كمية إلى السلة وأتمّ الطلب.
3. تحقق من نقص الكمية في صفحة المنتجات.
4. جرّب كمية أكبر من المتاح → يجب أن تفشل بلا طلب جديد.
5. أوقف الخدمة (`docker compose stop inventory-service`) ثم جرّب الطلب → يجب أن يفشل بعد المهلة، لا أن ينجح كذبًا.
6. أعد تشغيلها وجرّب مجددًا → ينجح الطلب.

---

## 13. استكشاف الأخطاء

### 13.1 جدول الأعراض السريع

| العرض | السبب المرجّح | الحل |
|---|---|---|
| المخزون لا ينقص بعد طلب ناجح | `LocalStockManager` يعمل بدل `grpc`، أو الواجهة تقرأ `products.stock` | تحقق من `config('order.stock_manager')` و`GetStockQuantity`. |
| `The PHP gRPC extension is required` | امتداد `grpc` غير مثبّت | فعّله أو استخدم حاوية `backend`. |
| `Inventory gRPC request failed (14)` | الخدمة متوقفة أو اسم المضيف خطأ | `docker compose ps`، وتأكد من `GRPC_INVENTORY_HOST`. |
| `failed (4) ... deadline` | الخدمة بطيئة/معلّقة أو الشبكة | افحص سجلات Java، وتأكد أن القفل لا ينتظر معاملة طويلة. |
| `failed (5) ... not found` | لا سجل مخزون للمنتج | اقرأ المنتج أولًا (Ensure) أو تحقق من `product_id`. |
| `Insufficient stock` | الكمية المطلوبة أكبر من المتاح | سلوك صحيح؛ عدّل الكمية أو المخزون. |
| `Reservation does not match the previous request` | إعادة استخدام معرّف حجز بعناصر مختلفة | لا تعِد استخدام المعرّف؛ استخدم UUID جديدًا. |
| `Committed reservations cannot be released` | محاولة تحرير بعد التثبيت | سلوك صحيح؛ استخدم مسار إرجاع مخصّص إن لزم. |
| الاختبارات تتصل بقاعدة خاطئة | متغيرات بيئة موروثة | لا تعطّل حماية `tests/bootstrap.php`. |

### 13.2 الحالة الأشهر: «طلبت 40 من 50 فبقي 50»

هذه هي المشكلة التي أُصلحت في هذا المشروع. التسلسل:

1. الطلب نجح، لكنه خُصم من `products.stock` في Laravel.
2. الواجهة تعرض الكمية من خدمة gRPC، ولم تتغير.
3. السبب: `OrderServiceProvider::register()` قرأ `config('order.stock_manager')` **قبل** دمج إعدادات الوحدة، فحصل على `local`.
4. الإصلاح: ربط lazy يقرأ الإعداد عند حل `StockManager`.
5. الحماية: `StockManagerBindingTest`.

كيف تتحقق الآن؟

```powershell
docker compose exec backend php artisan tinker --execute="
echo config('order.stock_manager'), PHP_EOL;
echo get_class(app(Modules\Order\Contracts\StockManager::class)), PHP_EOL;
"
```

المتوقع:

```text
grpc
Modules\Order\Services\GrpcStockManager
```

ثم تحقق من الكمية الفعلية:

```powershell
docker compose exec backend php artisan tinker --execute="
echo app(App\Contracts\Inventory\InventoryGateway::class)->getAvailableQuantity(1), PHP_EOL;
"
```

### 13.3 تغيير الإعدادات لا يؤثر

- إذا كنت تستخدم `php artisan config:cache` → نفّذ `php artisan config:clear`.
- في Docker، متغيرات `environment` في Compose تتقدم على `.env` للـ backend.
- بعد تعديل `.env` في Compose أعد إنشاء الحاوية:

  ```powershell
  docker compose up -d --force-recreate backend
  ```

### 13.4 عدم تطابق الكميات بين Laravel وJava

- لا تُصلح يدويًا قيمة `products.stock` وتتوقع تغيّر خدمة المخزون؛ القيمة تُستخدم للتهيئة الأولى فقط.
- لتصحيح كمية فعلية، عدّلها في `inventory_items` (أو عبر واجهة/أداة إدارية إن وُجدت)، مع مراعاة الحجوزات القائمة.
- لا تحذف صفًا من `inventory_items` لمنتج له حجوزات؛ قيد المفتاح الأجنبي سيمنع ذلك.

### 13.5 الخدمة تعمل لكن الاسم لا يُحل

- داخل Compose: `GRPC_INVENTORY_HOST=inventory-service:9090`.
- على المضيف: `127.0.0.1:9090` مع `GRPC_FORWARD_PORT`.
- لا تستخدم `localhost` داخل حاوية backend؛ فهو يشير إلى الحاوية نفسها.

### 13.6 فشل بناء خدمة Java

- تحقق من Java 21 في صورة البناء (`maven:3.9-eclipse-temurin-21`).
- راجع سجلات البناء: `docker compose build inventory-service`.
- إذا كان الميناء 9090 مستخدمًا على المضيف، غيّر `GRPC_FORWARD_PORT`.

---

## 14. الأمان

| الجانب | الوضع الحالي | التوصية للإنتاج |
|---|---|---|
| النقل | gRPC نصي (`createInsecure`) داخل شبكة Compose خاصة | TLS أو mTLS، وشهادات مُدارة. |
| المصادقة | لا توجد مصادقة على مستوى gRPC؛ من يصل للشبكة يستطيع النداء | mTLS أو اعتماد خدمة (token) + سياسات شبكة. |
| قاعدة المخزون | `inventory-db` بلا منفذ على المضيف | أبقِها خاصة، ولا تنشر 3306. |
| منفذ gRPC | `9090` منشور على المضيف افتراضيًا | لا تنشره خارج بيئة التطوير. |
| الأسرار | كلمات مرور افتراضية في `.env.example` | أسرار قوية عبر مدير أسرار. |
| سجل الحجوزات | معرّفات UUID عشوائية | كافية كمرجع، لكنها ليست تصريحًا أمنيًا. |
| التحقق من المدخلات | Laravel + Java يتحققان | أبقِ التحقق في الطرفين. |

نقطة مهمة: gRPC هنا **لا يعوّض** تفويض Laravel. المستخدم لا يستدعي خدمة المخزون مباشرة؛ Laravel هو من يقرر متى يحجز.

---

## 15. القيود والتحسينات الممكنة

### قيود حالية (موثّقة بوعي)

1. **إلغاء الطلب لا يحرّر المخزون.** `updateStatus(CANCELLED)` يغيّر الحالة فقط.
2. **فجوة بين حفظ الطلب وتثبيت الحجز.** فشل `commit` يترك طلبًا محفوظًا وحجزًا `RESERVED`.
3. **لا تنظيف تلقائي للحجوزات المعلّقة.** إذا مات Laravel بين الحجز والتثبيت/التحرير، يبقى `RESERVED`.
4. **نداءان gRPC لكل منتج عند القراءة** (`Ensure` + `Get`)؛ لا يوجد RPC قراءة دفعي.
5. **عميل gRPC جديد لكل نداء**؛ لا إعادة استخدام قناة.
6. **لا سكربت لتوليد أصناف PHP**؛ التوليد يدوي.
7. **لا مصادقة/TLS على gRPC**.
8. **اختبارات التكامل تترك صفوف مخزون تجريبية**.
9. **`RollbackStock` غير مستخدم** في مسار التطبيق الحالي.

### تحسينات مقترحة

- استدعاء `releaseReservation` عند إلغاء الطلب (مع تخزين `reservation_id` على الطلب).
- نمط Outbox أو حالة `PENDING_COMMIT` لمعالجة فجوة التثبيت.
- مهمة مجدولة لتحرير الحجوزات `RESERVED` الأقدم من مدة محددة.
- RPC دفعي للقراءة (`GetStockQuantities`) لتقليل عدد النداءات.
- إعادة استخدام قناة gRPC واحدة (singleton) مع إعادة اتصال.
- سكربت `protoc` موحّد في `composer`/`Makefile`.
- TLS/mTLS وسياسة شبكة تمنع الوصول إلا من backend.
- مقاييس (metrics) لعدد الحجوزات، معدل الفشل، وزمن النداء.

---

## 16. مسرد المصطلحات

| المصطلح | المعنى |
|---|---|
| **RPC** | استدعاء دالة في خدمة أخرى عبر الشبكة. |
| **Protocol Buffers** | صيغة تعريف الرسائل والأصناف المولَّدة. |
| **Stub** | صنف عميل مولَّد يحتوي دوال النداء. |
| **Deadline** | أقصى مدة انتظار للنداء. |
| **Idempotent** | إعادة العملية بنفس المدخلات لا تُحدث أثرًا إضافيًا. |
| **Reservation** | حجز كمية: تُخصم من المتاح لكنها قابلة للتحرير. |
| **Commit** | تثبيت الحجز؛ الكمية تبقى مخصومة نهائيًا. |
| **Release** | إلغاء الحجز وإرجاع الكمية. |
| **Compensation** | إجراء تعويضي عند فشل خطوة لاحقة (تحرير الحجز بعد فشل الحفظ). |
| **`FOR UPDATE`** | قفل صف في MySQL حتى نهاية المعاملة. |
| **`INSERT IGNORE`** | إدخال لا يفشل عند وجود المفتاح؛ يُستخدم للتهيئة. |
| **Deadlock** | جمود بين معاملتين تنتظر كل منهما قفل الأخرى. |
| **Source of truth** | مصدر الحقيقة المعتمد للبيانات. |

---

## 17. أوامر سريعة

```powershell
# حالة الخدمات
docker compose ps

# سجلات خدمة المخزون
docker compose logs -f inventory-service

# سجلات Laravel
docker compose logs -f backend

# التحقق من مدير المخزون داخل الحاوية
docker compose exec backend php artisan tinker --execute="echo config('order.stock_manager'), PHP_EOL; echo get_class(app(Modules\Order\Contracts\StockManager::class)), PHP_EOL;"

# قراءة كمية منتج من خدمة المخزون
docker compose exec backend php artisan tinker --execute="echo app(App\Contracts\Inventory\InventoryGateway::class)->getAvailableQuantity(1), PHP_EOL;"

# اختبارات الوحدة
php artisan test Modules/Order/tests/Feature/InventoryGrpcTest.php
php artisan test Modules/Order/tests/Feature/StockManagerBindingTest.php

# اختبارات التكامل الحية
docker compose run --rm --no-deps -e RUN_INVENTORY_INTEGRATION_TESTS=1 backend php artisan test --testsuite=Integration

# إيقاف/تشغيل خدمة المخزون لاختبار الفشل
docker compose stop inventory-service
docker compose start inventory-service

# مسح كاش الإعدادات بعد تغيير .env
php artisan config:clear

# الدخول إلى قاعدة المخزون
docker compose exec inventory-db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
```

---

## خلاصة في سطرين

Laravel يرسل عناصر السلة كلها في نداء `ReserveStockBatch` واحد؛ خدمة Java تنفّذ الحجز داخل معاملة واحدة بأقفال مرتبة وتخصم كل العناصر أو لا شيء، ثم يثبّت Laravel الحجز بعد حفظ الطلب ويحرّره إن فشل الحفظ. المخزون بعد التهيئة ملك خدمة Java وحدها، وLaravel يقرأه عبر gRPC فقط.
