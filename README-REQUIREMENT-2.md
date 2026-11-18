# المتطلب الثاني: خدمة المستودع واختبارها من الواجهات

## الخلاصة

التنفيذ يستخدم خدمة مخزون Java وقاعدة بيانات مستقلة عن Laravel، ويتواصل معها عبر gRPC بعقد Protocol Buffers. حجز عناصر الطلب الآن يتم بنداء gRPC واحد، وتتحقق خدمة المستودع من كل العناصر وتخصم الكميات ضمن معاملة قاعدة بيانات واحدة مع أقفال مرتبة. إذا لم يتوفر أي عنصر، تتراجع المعاملة كلها ولا يُنشأ الطلب. توجد اختبارات تكامل حية لهذه الحالة وللتنافس على آخر قطعة.

## مقارنة المتطلب بالتنفيذ

| المطلوب | ما يفعله المشروع | التقييم |
|---|---|---|
| خدمة مستودع منفصلة، بلغة وقاعدة بيانات مستقلتين | خدمة Spring Boot/Java وقاعدة `inventory-db` منفصلتان عن Laravel. التطبيق يتواصل معها عبر gRPC ولا يقرأ قاعدة بياناتها مباشرة. | متحقق |
| حجز المخزون قبل حفظ الطلب | `OrderService` يحجز أولًا، ثم يحفظ الطلب؛ وعند فشل حفظه يحاول تحرير الحجز. | متحقق في المسار المعتاد |
| حجز كل منتجات الطلب معًا أو عدم حجز شيء | Laravel يرسل `ReserveStockBatch` مرة واحدة؛ خدمة Java تتحقق من كل الكميات وتقفل صفوف المخزون وتخصمها داخل معاملة واحدة. أي نقص يرمي نتيجة رفض فتتراجع المعاملة، بما فيها سجل الحجز. | متحقق |
| عدم تجاوز المخزون عند طلبات متزامنة على آخر قطعة | خدمة Java تستخدم معاملة وقفل صف المخزون (`FOR UPDATE`). يوجد اختبار تكامل يرسل محاولتي حجز متزامنتين ويتوقع نجاح واحدة فقط. | متحقق حسب التنفيذ والاختبار |
| التعامل مع توقف الخدمة أو تأخرها | كل نداء gRPC له مهلة قابلة للضبط (`GRPC_INVENTORY_TIMEOUT`، افتراضيًا خمس ثوانٍ). عند التوقف أو انتهاء المهلة يُعاد **503** برسالة واضحة بدل **500**، وتتوقف قراءة المنتجات عند أول فشل بدل تكرار المهلة لكل منتج. | متحقق؛ اختبارات التكامل تغطي الاتصال غير المتاح والمهلة |
| أن يرى المستخدم نتيجة الطلب من الواجهة | React تعرض نجاحًا أو رسالة خطأ، وتعرض الطلب ضمن «طلباتي» بعد نجاحه. | متحقق للمسارات المعتادة |

### ماذا يحدث تقنيًا عند إنشاء الطلب؟

1. واجهة React ترسل عناصر السلة إلى Laravel.
2. Laravel ينشئ معرّف حجز، ويرسل كل العناصر في **نداء gRPC واحد** من نوع `ReserveStockBatch`.
3. خدمة Java ترتب معرّفات المنتجات لتأخذ أقفال صفوف المخزون بترتيب ثابت، ثم تتحقق من الكميات كلها قبل الخصم. الحجز وسجل عناصره ضمن معاملة واحدة في قاعدة بيانات المستودع.
4. عند وجود نقص في أي عنصر، تتراجع معاملة Java بالكامل؛ لا يُستهلك مخزون أي عنصر آخر ولا يُحفظ طلب Laravel. عند توفر العناصر كلها، تُخصم كلها ثم يحفظ Laravel الطلب ويثبّت الحجز.
5. إذا تعذر حفظ الطلب بعد الحجز، يحاول Laravel تحرير الحجز. وإذا كانت نتيجة نداء الشبكة غير مؤكدة، يحاول تحرير الحجز بالمعرّف نفسه، وتُسجل حالة فشل التحرير صراحة.

الخدمة الخلفية لا تقرأ قاعدة بيانات المستودع من Laravel؛ عقد الاتصال موجود في [inventory.proto](./inventory-service/src/main/proto/inventory.proto)، والمعاملة في [InventoryBusinessService.java](./inventory-service/src/main/java/com/example/inventory_service/service/InventoryBusinessService.java). هذا يحقق شرط «إما الكل أو لا شيء» داخل خدمة المستودع.

## الأكواد المهمة وكيف أشرحها

المقاطع التالية هي الأجزاء الجوهرية التي يُستحسن عرضها للمعيد. هي مقتطفات من ملفات المشروع لتسهيل الشرح؛ التنفيذ الكامل موجود في الملفات المرتبطة.

### 1. عقد الطلب الدفعي بين Laravel وJava

في [inventory.proto](./inventory-service/src/main/proto/inventory.proto)، نعرّف نداءً واحدًا يستقبل معرّف الحجز وكل عناصر السلة:

```proto
rpc ReserveStockBatch (ReserveBatchRequest) returns (ReserveResponse);

message StockItem {
  int64 product_id = 1;
  int32 quantity = 2;
}

message ReserveBatchRequest {
  string order_ref_id = 1;
  repeated StockItem items = 2;
}
```

`repeated StockItem` تعني أن الطلب يحمل قائمة المنتجات كلها، بدل إرسال نداء حجز منفصل لكل منتج.

### 2. Laravel يجمع العناصر ويرسل دفعة واحدة

في [GrpcStockManager.php](./Modules/Order/app/Services/GrpcStockManager.php)، تُجمع الكميات إذا تكرر المنتج، ثم تُرتب العناصر ويُرسل الحجز مرة واحدة:

```php
ksort($quantities);
$reservation = $this->inventory->reserveProducts(
    $reservationId,
    array_map(
        fn (int $productId, int $quantity) => [
            'product_id' => $productId,
            'quantity' => $quantity,
        ],
        array_keys($quantities),
        array_values($quantities)
    )
);
```

أما [GrpcInventoryGateway.php](./app/Services/Inventory/GrpcInventoryGateway.php) فيحوّل العناصر إلى رسائل Protocol Buffers وينفّذ نداء gRPC:

```php
$request = new ReserveBatchRequest();
$request->setOrderRefId($reservationId);

foreach ($items as $item) {
    $stockItem = new StockItem();
    $stockItem->setProductId((int) $item['product_id']);
    $stockItem->setQuantity((int) $item['quantity']);
    $request->getItems()[] = $stockItem;
}

$response = $this->call('ReserveStockBatch', $request, ReserveResponse::class);
```

### 3. خدمة Java تستقبل النداء

في [InventoryGrpcService.java](./inventory-service/src/main/java/com/example/inventory_service/grpc/InventoryGrpcService.java)، تُحوّل الخدمة العناصر إلى نماذج داخلية وتستدعي منطق المستودع:

```java
var items = request.getItemsList().stream()
        .map(item -> new InventoryBusinessService.StockReservationItem(
                item.getProductId(),
                item.getQuantity()
        ))
        .toList();

InventoryBusinessService.ReservationResult result =
        inventoryBusinessService.reserveStockBatch(request.getOrderRefId(), items);
respond(responseObserver, result.success(), result.message());
```

### 4. موضع الذرّية: معاملة قاعدة بيانات واحدة

هذا هو الجزء الأهم في [InventoryBusinessService.java](./inventory-service/src/main/java/com/example/inventory_service/service/InventoryBusinessService.java). `@Transactional` تجعل تحديث المخزون وسجلات الحجز ضمن معاملة واحدة. `TreeMap` يرتب معرّفات المنتجات؛ ثم تُقفل الصفوف وتُفحص كل الكميات قبل بدء أي خصم:

```java
@Transactional
public ReservationResult reserveStockBatch(
        String reservationId,
        List<StockReservationItem> requestedItems
) {
    Map<Long, Integer> quantities = new TreeMap<>();
    for (StockReservationItem item : requestedItems) {
        quantities.merge(item.productId(), item.quantity(), Math::addExact);
    }

    for (Map.Entry<Long, Integer> item : quantities.entrySet()) {
        int availableQuantity = jdbcTemplate.queryForObject(
                "SELECT available_quantity FROM inventory_items WHERE product_id = ? FOR UPDATE",
                Integer.class,
                item.getKey()
        );
        if (availableQuantity < item.getValue()) {
            throw new InsufficientStockException(
                    "Insufficient stock for product: " + item.getKey()
            );
        }
    }

    for (Map.Entry<Long, Integer> item : quantities.entrySet()) {
        jdbcTemplate.update(
                "UPDATE inventory_items SET available_quantity = available_quantity - ? WHERE product_id = ?",
                item.getValue(),
                item.getKey()
        );
        jdbcTemplate.update(
                "INSERT INTO reservation_items (reservation_id, product_id, quantity) VALUES (?, ?, ?)",
                reservationId,
                item.getKey(),
                item.getValue()
        );
    }
    return ReservationResult.succeeded();
}
```

إذا رمى فحص أي منتج `InsufficientStockException`، لا تصل العملية إلى الخصم؛ وتتراجع المعاملة بما فيها سجل الحجز. وإذا وقع خطأ أثناء التحديث، تتراجع تحديثات المعاملة كذلك. لذلك لا يُخصم A عندما يفشل حجز B.

### تسلسل الملفات عند العرض

1. ابدأ بعقد `ReserveStockBatch` في [inventory.proto](./inventory-service/src/main/proto/inventory.proto).
2. اعرض تجميع السلة وإرسالها مرة واحدة في [GrpcStockManager.php](./Modules/Order/app/Services/GrpcStockManager.php).
3. اعرض تحويل العناصر إلى رسائل gRPC في [GrpcInventoryGateway.php](./app/Services/Inventory/GrpcInventoryGateway.php).
4. اختم بمعاملة `reserveStockBatch` والقفل `FOR UPDATE` في [InventoryBusinessService.java](./inventory-service/src/main/java/com/example/inventory_service/service/InventoryBusinessService.java)، ثم نفّذ اختبار الواجهة رقم 4 أدناه.

## اختبار الواجهة

### تجهيز البيئة

استخدم Docker Compose لكي يكون إعداد Laravel على `ORDER_STOCK_MANAGER=grpc` ويصل إلى خدمة المستودع داخل شبكة Compose. بعد جلب التعديلات، أعد بناء خدمتي Laravel والمستودع ثم شغلهما:

```powershell
docker compose up -d --build inventory-service backend
```

إذا كانت البيئة لم تُشغّل من قبل، اتبع خطوات الإعداد الكاملة في [README.md](./README.md). تحقق من الحالة:

```powershell
docker compose ps
```

يجب أن تكون `frontend` و`backend` و`inventory-service` وقاعدة بيانات المخزون بحالة تشغيل/جاهزية. افتح:

- المتجر: <http://localhost:5173>
- الواجهة الخلفية: <http://localhost:8080> (للتأكد من أن الخدمة تعمل، وليست واجهة التسوق)

سجّل الدخول من صفحة المتجر بحساب زبون. استخدم منتجات تملك مخزونًا في خدمة المستودع. لإنشاء بيانات اختبار، سجّل دخولًا بحساب مدير، وافتح إدارة المنتجات وأنشئ منتجات اختبار بمخزون `1` لكل منها. إنشاء المنتج يهيئ سجل مخزونه في الخدمة، وبعد ذلك مخزون خدمة المستودع هو المرجع. لا تعدّل مخزون منتج موجود من لوحة الإدارة وتتوقع أن تتغير كمية خدمة المستودع، لأن قيمة الكتالوج تستخدم لتهيئة سجل جديد فقط.

### 1. طلب ناجح من السلة

1. من صفحة **Products** افتح تفاصيل منتج متوفر، واختر كمية لا تتجاوز المتاح، ثم **Add to Cart**.
2. افتح **Cart** وتأكد من ظهور المنتج والكمية.
3. اضغط **Create Order**.
4. المتوقع: تظهر رسالة نجاح، تصبح السلة فارغة، ويظهر الطلب في **My Orders** مع تفاصيله.
5. ارجع إلى **Products** وحدّث الصفحة؛ يجب أن تعكس قيمة التوفر الجديدة مخزون خدمة المستودع.

### 2. السلة الفارغة

1. أضف منتجًا إلى السلة.
2. من صفحة **Cart** استخدم أيقونة سلة المهملات لإزالة المنتج.
3. المتوقع: تظهر السلة فارغة ويكون **Create Order** معطّلًا.

> توجد واجهة حذف لكل عنصر، لكن لا يظهر حاليًا زر «إفراغ السلة» مستقلًا؛ أزل العناصر واحدًا واحدًا. عند حذف عنصر مع بقاء عناصر أخرى، يعيد الخادم تحميل تفاصيل المنتجات وقد يتصل بخدمة المستودع أثناء ذلك؛ نفّذ هذا الاختبار والخدمة تعمل.

### 3. مخزون غير كافٍ

1. أضف منتجًا إلى السلة والكمية المتاحة أكبر من صفر.
2. في جلسة زبون ثانية، اطلب آخر وحدة من المنتج نفسه قبل إتمام الطلب الأول.
3. حاول إتمام الطلب من الجلسة الأولى.
4. المتوقع: يفشل الطلب برسالة مخزون غير كافٍ، ولا يظهر طلب جديد في **My Orders** للجلسة الفاشلة، وتبقى عناصر سلتها موجودة.

قد لا تمنع الواجهة إضافة العنصر مسبقًا، لأن الحسم النهائي للمخزون يحصل عند إنشاء الطلب؛ المهم ألا يُنشأ طلب غير قابل للحجز.

### 4. إثبات «الكل أو لا شيء» من واجهتين

هذا هو الاختبار الأهم لشرح الملاحظة:

1. أنشئ منتجين اختباريين، **A** و**B**، ومخزون كل منهما `1`.
2. سجّل الدخول بحساب الزبون الأول، وأضف A وB إلى السلة.
3. في نافذة/ملف متصفح ثانٍ، سجّل الدخول بحساب زبون آخر، وأضف B فقط إلى سلته، ثم اضغط **Create Order** ليستهلك القطعة الوحيدة من B.
4. ارجع إلى حساب الزبون الأول واضغط **Create Order** على السلة التي تحتوي A وB.
5. المتوقع: يفشل الطلب برسالة مخزون غير كافٍ، وتبقى السلة كما هي، ولا يظهر طلب جديد لهذا الحساب.
6. حدّث صفحة المنتجات: يجب أن يكون مخزون A ما زال `1` ومخزون B يساوي `0`. بقاء A كاملة يثبت أن فشل حجز B لم يترك حجزًا جزئيًا على A.

### 5. طلبان متزامنان على آخر قطعة

1. جهّز منتجًا لا يتوفر منه إلا `1`.
2. افتح نافذتين مستقلتين أو ملفي متصفح مختلفين وسجّل الدخول بحسابي زبون مختلفين.
3. أضف القطعة نفسها إلى سلة كل حساب بينما ما زالت متاحة.
4. اضغط **Create Order** في النافذتين بفارق زمني صغير.
5. المتوقع: ينجح طلب واحد فقط؛ يفشل الآخر برسالة مخزون غير كافٍ. بعد تحديث صفحة المنتجات، يجب ألا يصبح المخزون سالبًا.

هذا عرض يدوي مفيد، لكنه لا يضمن توليد ضغط متزامن كافٍ. توجد تغطية أدق للحجز المتزامن في اختبار التكامل `test_only_one_live_grpc_reservation_wins_a_race_for_the_last_item` في [InventoryGrpcIntegrationTest.php](./Modules/Order/tests/Integration/InventoryGrpcIntegrationTest.php).

### 6. إيقاف خدمة المستودع أثناء محاولة الشراء

1. بينما الخدمة تعمل، أضف منتجًا إلى السلة وتأكد من ظهوره في **Cart**.
2. من طرفية المشروع أوقف خدمة المستودع فقط:

   ```powershell
   docker compose stop inventory-service
   ```

3. ارجع إلى السلة واضغط **Create Order**. انتظر نتيجة الطلب؛ مهلة كل نداء gRPC خمس ثوانٍ. إذا كان الطلب بطيئًا وغير مستجيب فقد تستغرق محاولة تحرير الحجز غير المؤكد مهلة أخرى.
4. المتوقع: تظهر رسالة خطأ واضحة بعد انتهاء المهلة، **لا يظهر نجاح ولا يُنشأ الطلب**، وتبقى عناصر السلة في الجلسة الحالية. صفحة المنتجات والسلة تعرضان رسالة **503** مفهومة (خدمة المستودع غير متاحة مؤقتًا) بدل خطأ **500**. عند محاولة الحجز ثم فشل حفظ الطلب يحاول Laravel تحرير الكمية المحجوزة فورًا بالمعرّف نفسه.
5. أعد تشغيل الخدمة وانتظر جاهزيتها:

   ```powershell
   docker compose start inventory-service
   docker compose ps
   ```

6. بعد عودة `inventory-service` إلى الحالة السليمة، أعد محاولة **Create Order**. المتوقع نجاح الطلب وإفراغ السلة.

أوقف الحاوية وأعد تشغيلها بهذا الأسلوب؛ لا تستخدم `docker compose down --volumes` لهذا الاختبار، لأنه يحذف بيانات قواعد البيانات.

## اختبارات آلية موجودة

اختبارات gRPC والتكامل في:

- [InventoryGrpcTest.php](./Modules/Order/tests/Feature/InventoryGrpcTest.php): تحقق أن Laravel يرسل كل العناصر في نداء واحد، يجمع تكرار المنتج، ويرفض الدخل غير الصالح.
- [InventoryGrpcIntegrationTest.php](./Modules/Order/tests/Integration/InventoryGrpcIntegrationTest.php): قراءة المخزون وحجزه وتحريره، رفض دفعة متعددة دون خصم أي عنصر، التزامن، تعويض فشل حفظ الطلب، توقف الخدمة، والمهلة.

اختبارات التكامل الحية لا تعمل افتراضيًا؛ تفعيلها يتطلب `RUN_INVENTORY_INTEGRATION_TESTS=1` وامتداد PHP gRPC وخدمات Compose شغالة. انظر قسم الاختبارات في [README.md](./README.md) قبل تشغيلها.

## ماذا أشرح للمعيد؟

يمكنك شرحها بهذا التسلسل، ثم تنفيذ الاختبار الرابع أعلاه أمامه:

> «خدمة المستودع خدمة مستقلة مكتوبة بـJava ولها قاعدة بياناتها الخاصة. تطبيق Laravel لا يصل إلى جداولها؛ بينهما عقد gRPC موصوف في ملف proto. عند إتمام السلة، يرسل Laravel عناصرها كلها في نداء `ReserveStockBatch` واحد. خدمة المستودع تبدأ معاملة، وتقفل صفوف المنتجات بترتيب ثابت، وتتحقق من الكميات كلها قبل أن تخصم. إذا كانت قطعة واحدة غير متوفرة، تفشل المعاملة وتُرجع كل التغييرات، لذلك لا يُحفظ الطلب ولا ينقص مخزون بقية المنتجات. إذا نجح الحجز، يحفظ Laravel الطلب ثم يثبت الحجز. اختبرت أيضًا سباق زبونين على آخر قطعة؛ واحد فقط ينجح. وعند توقف الخدمة، يظهر للمستخدم فشل بعد انتهاء المهلة بدل رسالة نجاح أو طلب محفوظ.»

أظهر له ملف proto، ثم تنفيذ `reserveStockBatch` في Java، ثم اختبر السلة متعددة المنتجات مع نفاد B. هذه الخطوات تبيّن العقد، مكان المعاملة، ونتيجة الذرّية من الواجهة دون Postman.
