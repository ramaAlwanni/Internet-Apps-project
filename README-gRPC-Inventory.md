# توثيق وحدة المخزون عبر gRPC

## نظرة عامة

هذا المشروع يتكون من خدمتين تعملان معًا:

1. تطبيق Laravel: يستقبل الطلبات الوظيفية ويستدعي خدمة المخزون عبر gRPC.
2. خدمة Java باستخدام Spring Boot: تحتفظ بالمنطق الفعلي للمخزون وتعرض أساليب RPC الخاصة بـ gRPC.

المسؤولية الأساسية في الجانب الخاص بـ Laravel هي التنسيق والتحقق وسلوك التراجع (Rollback).

---

## الملفات المعدلة

### 1) عقد خدمة Laravel
الملف: [Modules/Order/app/Contracts/StockManager.php](Modules/Order/app/Contracts/StockManager.php)

الهدف:
- يعرّف التجريد المستخدم داخل وحدة الطلبات.
- يفتح الطرق التالية:
  - `reserve(string $reservationId, array $items): array`
  - `commit(string $reservationId): array`
  - `release(string $reservationId): array`

لماذا مهم:
- يحافظ على فصل منطق الأعمال عن التنفيذ الفعلي.
- يسهل استبدال التخزين المحلي أو gRPC أو الـ mock في الاختبارات.

---

### 2) تنفيذ gRPC في Laravel
الملف: [Modules/Order/app/Services/GrpcStockManager.php](Modules/Order/app/Services/GrpcStockManager.php)

الهدف:
- يربط الخدمة مع خدمة المخزون في Java عبر gRPC.
- يرسل `ReserveRequest` و `RollbackRequest` إلى `inventory.InventoryService`.
- يتعامل مع الاستجابات الناجحة والفاشلة.
- ينفذ التراجع عند فشل الحجز في منتصف العملية.

السلوك المهم:
- `reserve()` يمرّ على كل عنصر في الطلب.
- إذا فشل أحد العناصر، يتم التراجع عن أي حجز ناجح تم قبل ذلك.
- إذا كان خادم gRPC غير متاح، يرجع خطأ بدل أن يتسبب في انهيار التطبيق.

الطرق الرئيسية:
- `__construct()`
  - يقرأ قيمة `GRPC_INVENTORY_HOST` مع قيمة افتراضية `localhost:9090`.
- `getClient()`
  - ينشئ قناة اتصال غير آمنة لخادم Java عبر gRPC.
- `reserve()`
  - يرسل الطلبات إلى `/inventory.InventoryService/ReserveStock`.
  - يتحقق من `status->code` ومن حقل الاستجابة `success` في البروتوبف.
- `release()`
  - يستدعي عملية التراجع عند الإلغاء أو التحرير.
- `rollbackPartial()`
  - ينفذ التراجع في خدمة المخزون.

---

### 3) ربط Laravel
الملف: [Modules/Order/app/Providers/OrderServiceProvider.php](Modules/Order/app/Providers/OrderServiceProvider.php)

الهدف:
- يربط `StockManager` بـ `GrpcStockManager`.

لماذا مهم:
- أصبحت وحدة الطلبات تستخدم إدارة المخزون عبر gRPC افتراضيًا.

---

### 4) تدفق طلبات الطلبات
الملف: [Modules/Order/app/Services/OrderService.php](Modules/Order/app/Services/OrderService.php)

الهدف:
- يستخدم `StockManager` داخل مسار الطلب الحقيقي.

التدفق:
1. إنشاء معرف حجز (reservation UUID)
2. استدعاء `reserve()`
3. إذا فشل الحجز، يتوقف إنشاء الطلب
4. إنشاء الطلب داخل معاملة قاعدة البيانات
5. استدعاء `commit()` عند قبول الطلب
6. إذا حدث أي خطأ، يتم استدعاء `release()`

هذا هو السلوك الأساسي للمعاملات داخل نظام الطلبات.

---

### 5) منطق المخزون في Java
الملف: [inventory-service/src/main/java/com/example/inventory_service/service/InventoryBusinessService.java](inventory-service/src/main/java/com/example/inventory_service/service/InventoryBusinessService.java)

الهدف:
- يطبق قواعد المخزون الفعلية في Java.

الطرق:
- `getAvailableQuantity(Long productId)`
  - يعيد الكمية المتاحة الحالية
- `reserveStock(Long productId, int quantity)`
  - يستخدم قفلًا متحفظًا (pessimistic locking)
  - يتحقق من توفر الكمية المطلوبة
  - يخصم المخزون إذا توافرت الكمية
- `rollbackStock(Long productId, int quantity)`
  - يعيد الكمية عند إلغاء الطلب أو فشله

لماذا هذا مهم:
- يمنع مشاكل race condition عن طريق قفل صف المنتج أثناء المعاملة.

---

### 6) تنفيذ خدمة gRPC في Java
الملف: [inventory-service/src/main/java/com/example/inventory_service/grpc/InventoryGrpcService.java](inventory-service/src/main/java/com/example/inventory_service/grpc/InventoryGrpcService.java)

الهدف:
- يعرض طرق gRPC المستخدمة في عمليات المخزون.

الطرق المطبقة:
- `getStockQuantity(...)`
- `reserveStock(...)`
- `rollbackStock(...)`

كل طريقة:
- تقرأ بيانات الطلب
- تستدعي خدمة الأعمال
- ترجع رسالة استجابة من البروتوبف
- ترسل أخطاء gRPC إذا فشل الطلب

---

### 7) عقد البروتوكول في Java
الملف: [inventory-service/src/main/proto/inventory.proto](inventory-service/src/main/proto/inventory.proto)

الهدف:
- يعرّف عقد API الخاص بـ gRPC الذي يتفق عليه الجانبان.

الخدمة:
- `InventoryService`
- الأساليب:
  - `GetStockQuantity`
  - `ReserveStock`
  - `RollbackStock`

الرسائل:
- `StockRequest`
- `StockResponse`
- `ReserveRequest`
- `ReserveResponse`
- `RollbackRequest`
- `RollbackResponse`

هذا الملف هو العقد الحقيقي بين Laravel و Java.

---

### 8) إعدادات Java
الملف: [inventory-service/src/main/resources/application.properties](inventory-service/src/main/resources/application.properties)

القيم المهمة:
- `server.port=8081`
- `grpc.server.port=9090`

المعنى:
- خدمة Java تستمع على منفذ gRPC رقم `9090`.
- يجب أن يتصل Laravel إلى `localhost:9090` افتراضيًا.

---

### 9) ملف الاختبارات
الملف: [Modules/Order/tests/Feature/InventoryGrpcTest.php](Modules/Order/tests/Feature/InventoryGrpcTest.php)

الهدف:
- يختبر الحالات الناجحة والحالات الخطأ بدون الحاجة إلى خادم Java فعلي.

الحالات المغطاة:
- الحجز الناجح
- المنتج غير موجود
- الكمية غير كافية
- gRPC غير متاح
- التراجع عند فشل جزئي
- استدعاء الإطلاق/الإلغاء
- حالة race condition

هذا الملف يستخدم استجابات fake gRPC لتجعل اختبارات المشروع مستقرة وقابلة للتكرار.

---

## كيف يعمل النظام من البداية إلى النهاية

1. تستقبل Laravel طلب إنشاء الطلب من خلال `OrderService`.
2. تستدعي `StockManager->reserve($reservationId, $items)`.
3. يرسل عميل gRPC طلب `ReserveRequest` إلى خادم Java.
4. يتحقق Java من وجود المنتج وتوفر الكمية.
5. يقوم Java بتحديث المخزون باستخدام قفل متحفظ (pessimistic locking).
6. يرجع Java `ReserveResponse` يحتوي على `success` و `message`.
7. إذا كان هناك أكثر من عنصر في الطلب وفشل أحدهم، فسيقوم Laravel بتنفيذ rollback.
8. إذا فشل الطلب بالكامل، يستدعي Laravel `release()` وتعيد Java الكمية إلى المخزون.

---

## البيئة المحلية المطلوبة

### طريقة التنزيل والتثبيت

####  تحميل ملف gRPC الخاص بـ PHP بشكل صحيح
1. أولًا، تأكد من نسخة PHP الحالية:
   ```bash
   php -v
   ```
2. إذا كنت تستخدم XAMPP، فغالبًا نسخة PHP هي 8.2.x، لذلك تحتاج ملف gRPC مطابق لـ:
   - PHP 8.2
   - Windows x64
   - TS إذا كنت تستخدم XAMPP
3. اذهب إلى الموقع الرسمي:
   https://windows.php.net/downloads/pecl/releases/grpc/
4. اختر الإصدار المناسب لـ PHP 8.2 و x64 و TS.
5. حمل الملف الذي يحتوي على اسم مشابه لـ:
   ```text
   php_grpc.dll
   ```
6. بعد التحميل، فك الضغط ووضع الملف داخل:
   ```text
   C:\xampp\php\ext
   ```
7. افتح ملف إعداد PHP:
   ```text
   C:\xampp\php\php.ini
   ```
8. أضف السطر التالي:
   ```ini
   extension=grpc
   ```
9. احفظ الملف، ثم أعد تشغيل Apache من XAMPP Control Panel.
10. تحقق من أن gRPC محمل:
    ```bash
    php -m | findstr /I grpc
    ```

ملاحظات مهمة:
- إذا لم يظهر `grpc`، فهذا يعني أن ملف gRPC لا يطابق نسخة PHP لديك.
- إذا ظهر خطأ في تحميل الـ DLL، فمن المرجح أن يكون السبب اختلاف النسخة مثل: PHP 8.1 بدل 8.2، أو x86 بدل x64، أو TS بدل NTS.
- الملف الفعلي المطلوب هو `php_grpc.dll` وليس مجرد كتابة `extension=grpc` فقط.

---

#### 3) Java 21
لأن خدمة المخزون مكتوبة بـ Java/Spring Boot، تحتاج JDK 21.

1. حمل OpenJDK 21 من الموقع الرسمي:
   https://adoptium.net/
2. اختر نسخة Temurin JDK 21 لـ Windows.
3. بعد التثبيت، أضف متغير البيئة `JAVA_HOME` مثل:
   ```text
   JAVA_HOME=C:\Program Files\Eclipse Adoptium\jdk-21.x.x
   ```
4. أضف إلى `Path`:
   ```text
   %JAVA_HOME%\bin
   ```
5. تحقق:
   ```bash
   java -version
   ```

---


#### 5) Maven
المشروع يحتوي على Maven Wrapper في مجلد `inventory-service`, لذلك لا تحتاج تثبيت Maven دائمًا، لكنه يساعد في بعض الحالات.
هو أداة تأتي مع المشروع وتحتوي على نسخة محددة وثابتة من برنامج Maven (أداة بناء وإدارة مشاريع جافا) ضمن مجلد المشروع نفسه.

1. حمل Maven من:
   https://maven.apache.org/download.cgi
2. ثبت Maven ثم أضف مساره إلى `Path`.
3. تحقق:
   ```bash
   mvn -v
   ```
4. أو داخل الخدمة:
   ```bash
   ./mvnw -v
   ```

---

#### 6) MySQL
إذا كنت تستخدم XAMPP، فـ MySQL موجود داخل الحزمة.

1. افتح XAMPP Control Panel.
2. شغّل خدمة MySQL.
3. تأكد أن المنفذ يعمل على `3306`.
4. تأكد من وجود قاعدة البيانات المطلوبة مثل `inventory_db`.
5. يمكنك الدخول عبر phpMyAdmin أو عبر MySQL CLI.

للتحقق:
```bash
mysql --version
```

---

### الجانب الخاص بـ PHP
- PHP 8.2
- Composer
- تثبيت ملحق gRPC الخاص بـ PHP
- تطبيق Laravel مع تثبيت جميع التبعيات

للتحقق:
```bash
php -v
php -m | findstr /I grpc
```

إذا لم يظهر `grpc` فهذا يعني يجب تثبيت ملحق gRPC الخاص بـ PHP.

### الجانب الخاص بـ Java
- Java 21
- Maven wrapper
- MySQL قيد التشغيل محليًا

للتحقق:
```bash
java -version
./mvnw -v
```

---

## خطوات التشغيل محليًا

### 1) تشغيل MySQL
تأكد أن قاعدة البيانات `inventory_db` موجودة وقابلة للوصول.

### 2) تشغيل خدمة المخزون في Java
من مسار المشروع:
```bash
cd inventory-service
./mvnw spring-boot:run
```

هذا يجب أن يبدأ خادم gRPC على المنفذ `9090`.

### 3) تشغيل Laravel
من مسار المشروع:
```bash
php artisan serve
```

### 4) تشغيل الاختبارات
```bash
php artisan test Modules/Order/tests/Feature/InventoryGrpcTest.php
```

---

## خيار Docker

المشروع يحتوي فعليًا على ملفات مرتبطة بـ Docker مثل:
- `docker-compose.yml`
- `Dockerfile`
- `frontend/Dockerfile`

إذا رغبت في تشغيل كل شيء عبر Docker، فالنهج المعتاد هو:

1. إبقاء MySQL كخدمة منفصلة داخل الحاوية.
2. تشغيل خدمة Java داخل حاوية منفصلة كخدمة مستقلة.
3. تشغيل Laravel داخل حاوية منفصلة.
4. ربط المنافذ كما يلي:
   - تطبيق Laravel: `8000`
   - خدمة gRPC في Java: `9090`
   - MySQL: `3306`

ملاحظات مهمة حول Docker:
- يجب أن تشير `GRPC_INVENTORY_HOST` إلى اسم الحاوية أو اسم الخدمة وليس إلى `localhost` عندما يعمل Laravel داخل حاوية مختلفة.
- مثال:
  ```env
  GRPC_INVENTORY_HOST=inventory-service:9090
  ```
- عندما يكون Laravel و Java في حاويات منفصلة، فإن `localhost` يعني الحاوية نفسها وليس الحاوية التي فيها Java.

---

## المشكلة الشائعة التي يجب تذكرها

المشكلة الأكثر شيوعًا هي:
- يحاول Laravel الاتصال إلى `127.0.0.1:9090`
- لكن خادم gRPC الخاص بـ Java غير مشغّل
- أو المنفذ محجوب
- أو الخدمة تستمع على عنوان/منفذ مختلف

في هذه الحالة تظهر أخطاء مثل:
```text
Connection refused
UNAVAILABLE: ipv4:127.0.0.1:9090
```

هذا يعني أنه يجب تشغيل خدمة Java قبل أن تستدعي Laravel gRPC.

---

## ملاحظة أخيرة

هيكل المشروع الآن كالتالي:
- وحدة الطلبات تتحكم في التجريد والاختبارات
- خدمة Java تتحكم في منطق المخزون و RPC الخاصة بـ gRPC
- Laravel يتولى تنسيق المعاملات والتراجع

هذا يتوافق مع متطلبات فصل إدارة المخزون عن وحدة الطلبات مع الحفاظ على سلامة المخزون وتناسق البيانات.
