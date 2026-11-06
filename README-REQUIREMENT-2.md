# المتطلب الثاني: التكامل بين Laravel وخدمة المخزون عبر gRPC

يوثّق هذا الملف البنية التي يعتمدها المشروع لتنفيذ التواصل بين خدمتين مستقلتين، ومسار قراءة المخزون وحجزه وإرجاعه، وأماكن الكود والاختبارات.

## 1. الفكرة المعمارية

يتكوّن الحل من خدمتين لكل منهما مسؤولية وقاعدة بيانات مستقلة:

```text
المتصفح
   |
   v
واجهة React  --->  Laravel API
                       | \
                       |  \-- MySQL الخاصة بـ Laravel
                       |      المنتجات، المستخدمون، السلة، الطلبات
                       |
                       | gRPC / Protocol Buffers
                       v
                  Java Spring Boot
                       |
                       \-- MySQL الخاصة بالمخزون
                           الكميات والحجوزات
```

- تملك Laravel بيانات الكتالوج والمستخدمين والطلبات.
- تملك خدمة Java الكمية المتاحة وسجل الحجوزات، وهي المصدر المعتمد للمخزون عند تفعيل `ORDER_STOCK_MANAGER=grpc`.
- تتشارك الخدمتان `product_id` بوصفه معرّفًا مرجعيًا؛ خدمة المخزون لا تقرأ قاعدة Laravel ولا تعتمد على اتصال بها.
- يعرّف ملف Protocol Buffers الرسائل والعمليات المشتركة. تتواصل Laravel مع Java عبر عميل gRPC، وليس عبر استعلامات SQL مشتركة.

## 2. مواقع الملفات الأساسية

```text
docker-compose.yml
├── mysql                         قاعدة Laravel
├── inventory-db                  قاعدة المخزون المستقلة
├── backend                       Laravel API وعميل gRPC
└── inventory-service             خدمة Java/gRPC

app/
├── Contracts/Inventory/
│   └── InventoryGateway.php      عقد بوابة المخزون في Laravel
└── Services/Inventory/
    └── GrpcInventoryGateway.php  استدعاءات gRPC ومهلة الاتصال

Modules/
├── Product/app/Services/
│   └── ProductCatalogService.php قراءة الكتالوج وتهيئة/قراءة المخزون
└── Order/
    ├── app/Services/
    │   ├── GrpcStockManager.php  حجز المنتجات وتحرير الحجز الجزئي
    │   └── OrderService.php      حفظ الطلب ثم تثبيت الحجز أو تعويضه
    └── tests/
        ├── Feature/              اختبارات الوحدة والتكامل الوهمي
        └── Integration/          اختبارات حية لخدمة gRPC

inventory-service/src/main/
├── proto/inventory.proto         عقد gRPC
├── java/.../grpc/
│   └── InventoryGrpcService.java تنفيذ نقاط gRPC
├── java/.../service/
│   └── InventoryBusinessService.java منطق المخزون والمعاملات
└── resources/schema.sql          جداول قاعدة المخزون

tests/
├── bootstrap.php                 حاجز أمان لقاعدة الاختبار
└── Feature/TestingDatabaseIsolationTest.php
```

## 3. عقد gRPC

العقد موجود في `inventory-service/src/main/proto/inventory.proto`. العمليات الأساسية هي:

| العملية | الغرض |
|---|---|
| `GetStockQuantity` | قراءة الكمية المتاحة لمنتج |
| `EnsureProductStock` | إنشاء سجل مخزون ابتدائي إن لم يكن موجودًا |
| `ReserveStock` | حجز كمية وربطها بمعرّف حجز |
| `CommitReservation` | تثبيت الحجز بعد نجاح حفظ الطلب |
| `ReleaseReservation` | إلغاء الحجز وإعادة كمياته المتاحة |
| `RollbackStock` | تعويض كمية محددة عند الحاجة |

مقتطف من العقد:

```protobuf
service InventoryService {
  rpc GetStockQuantity (StockRequest) returns (StockResponse);
  rpc EnsureProductStock (ReserveRequest) returns (ReserveResponse);
  rpc ReserveStock (ReserveRequest) returns (ReserveResponse);
  rpc CommitReservation (ReserveRequest) returns (ReserveResponse);
  rpc ReleaseReservation (ReserveRequest) returns (ReserveResponse);
  rpc RollbackStock (RollbackRequest) returns (RollbackResponse);
}
```

يحمل `ReserveRequest.order_ref_id` معرّف الحجز. ينشئ Laravel هذا المعرّف لكل طلب، ويستخدمه في الحجز والتثبيت أو التحرير، ما يجعل دورة الحجز قابلة للتتبع في قاعدة المخزون.

## 4. فصل قواعد البيانات

يعرّف `docker-compose.yml` خدمتي MySQL وvolume مستقلًا لكل واحدة:

- `mysql` تستخدمه Laravel لبيانات التطبيق.
- `inventory-db` تستخدمه Java للمخزون والحجوزات.
- يتصل `backend` بعنوان gRPC الداخلي `inventory-service:9090`.
- تتصل خدمة Java بـ `inventory-db:3306` فقط.
- volume قاعدة المخزون هو `inventory_mysql_data`، وvolume قاعدة Laravel هو `mysql_data`.

تحتوي `schema.sql` على:

- `inventory_items`: الكمية المتاحة لكل `product_id`.
- `reservations`: حالة الحجز (`RESERVED` أو `COMMITTED` أو `RELEASED`).
- `reservation_items`: المنتجات والكميات المرتبطة بكل حجز.

عند إنشاء منتج أو عرضه، تستدعي Laravel `EnsureProductStock`. تنشئ هذه العملية سجلًا ابتدائيًا عند غيابه ولا تستبدل كمية سجل المخزون الموجود؛ لذلك يبقى المخزون المخفّض أو المحجوز ملكًا لخدمة Java.

## 5. قراءة المخزون من الكتالوج

تقرأ `ProductCatalogService` بيانات المنتج وسعره من قاعدة Laravel، ثم تستعلم عن الكمية من خدمة المخزون عندما يكون مدير المخزون مضبوطًا على gRPC:

```php
$this->inventory->ensureProductStock($productId, (int) $product->stock);
$snapshot['stock'] = $this->inventory->getAvailableQuantity($productId);
```

قيمة `products.stock` في Laravel هي كمية ابتدائية لتهيئة سجل غير موجود. بعد ذلك تكون كمية المخزون المعروضة هي الكمية التي تعيدها خدمة المخزون.

## 6. دورة إنشاء الطلب

ينفذ `OrderService::createOrder` الخطوات التالية:

1. ينشئ معرّف حجز فريدًا.
2. يحجز جميع كميات الطلب عبر `GrpcStockManager`.
3. يحفظ الطلب وبنوده داخل transaction في قاعدة Laravel.
4. إذا فشل الحفظ، يطلب تحرير الحجز من خدمة المخزون؛ وإذا فشل التحرير أيضًا، يسجل خطأً حرجًا ظاهرًا.
5. إذا نجح الحفظ، يثبت الحجز عبر `CommitReservation`.

مقتطف مبسط من منطق التعويض في Laravel:

```php
try {
    $order = DB::transaction(function () use ($userId, $items) {
        // قراءة بيانات المنتجات وحفظ الطلب وبنوده في قاعدة Laravel.
    });
} catch (\Throwable $exception) {
    $this->stockManager->release($reservationId);
    throw $exception;
}

$this->stockManager->commit($reservationId);
```

هذه معاملة موزعة بتعويض (Saga): قاعدة Laravel وقاعدة المخزون لا تدخلان في transaction مشتركة. لذلك يحجز النظام المخزون أولًا، ثم يحفظ الطلب، ثم يثبت الحجز؛ ويحرر الحجز إذا فشل حفظ الطلب.

## 7. التزامن والمهلات والأخطاء

- يقفل Java سجل كمية المنتج داخل transaction قبل فحص المتاح وتخفيضه. يمنع هذا تجاوز الكمية عند وصول حجوزات متزامنة على آخر قطعة.
- معاملات قاعدة المخزون تحفظ الحجز وبنوده في `inventory-db` بدل تخزينها في ذاكرة Laravel.
- لكل استدعاء gRPC مهلة **5 ثوانٍ** في `GrpcInventoryGateway`.
- أخطاء gRPC ترجع إلى التطبيق كأخطاء صريحة؛ لا تُحوّل إلى نجاح وهمي.
- يحرر `GrpcStockManager` الحجز إذا فشل حجز أحد المنتجات بعد نجاح حجز منتجات أخرى.
- يحتفظ الحجز بحالة تمنع تحرير الحجز المثبّت أو خصم الكمية نفسها مرتين.

## 8. الاختبارات

### الاختبارات العادية

```powershell
composer test
```

يضمن `tests/bootstrap.php` تشغيل الاختبارات على SQLite داخل الذاكرة، مع `ORDER_STOCK_MANAGER=local`. يوقف PHPUnit التشغيل قبل إقلاع Laravel إذا لم تتوفر SQLite أو كانت متغيرات قاعدة الاختبار غير آمنة. لا تشغّل الاختبارات على قاعدة MySQL الحية.

### اختبارات gRPC الحية

شغّل خدمات Docker أولًا:

```powershell
docker compose up -d
docker compose ps
```

ثم نفّذ:

```powershell
docker compose run --rm --no-deps `
  -e RUN_INVENTORY_INTEGRATION_TESTS=1 `
  backend php artisan test --testsuite=Integration
```

تختبر المجموعة الحية:

- قراءة المخزون وحجزه وتحريره عبر gRPC.
- طلبين متوازيين على آخر قطعة؛ تكرر الاختبار السباق خمس مرات، ويتوقع فائزًا واحدًا في كل مرة.
- إعادة المخزون إذا فشل حفظ الطلب في Laravel.
- فشل الاتصال بخدمة غير متاحة.
- انتهاء مهلة فعلية لخدمة لا تستجيب، ضمن حد الخمس ثواني.
- تثبيت الحجز ومنع تحريره بعد تثبيته.

تستخدم اختبارات التكامل معرّفات منتجات اصطناعية عشوائية ولا تنشئ منتجات في كتالوج Laravel. تبقى سجلات الاختبار الاصطناعية في قاعدة المخزون حتى لا تعبث الاختبارات ببيانات التطبيق.

## 9. الإعداد والتشغيل

في Docker يضبط Compose تلقائيًا:

```text
ORDER_STOCK_MANAGER=grpc
GRPC_INVENTORY_HOST=inventory-service:9090
```

أما التشغيل المحلي، فيحتاج أن تكون قاعدة خدمة المخزون مستقلة وأن تعمل خدمة Java على المنفذ `9090`، ثم تضبط Laravel:

```dotenv
ORDER_STOCK_MANAGER=grpc
GRPC_INVENTORY_HOST=127.0.0.1:9090
```

بعدها يمكن تشغيل Laravel وواجهة React بالطريقة المعتادة للمشروع.

## 10. دليل التنفيذ والاختبارات

- عقد الاتصال: `inventory-service/src/main/proto/inventory.proto`.
- إعداد قواعد البيانات والخدمات: `docker-compose.yml`.
- إعداد الجداول: `inventory-service/src/main/resources/schema.sql`.
- بوابة Laravel إلى gRPC: `app/Services/Inventory/GrpcInventoryGateway.php`.
- منطق الحجز في Laravel: `Modules/Order/app/Services/GrpcStockManager.php`.
- تدفق إنشاء الطلب والتعويض: `Modules/Order/app/Services/OrderService.php`.
- التنفيذ والمعاملات في Java: `inventory-service/src/main/java/com/example/inventory_service/service/InventoryBusinessService.java`.
- اختبارات العميل المعزولة: `Modules/Order/tests/Feature/InventoryGrpcTest.php`.
- اختبارات الخدمة الحية: `Modules/Order/tests/Integration/InventoryGrpcIntegrationTest.php`.
