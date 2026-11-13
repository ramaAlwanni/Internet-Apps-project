# شرح إعدادات قاعدة البيانات و`sticky` واختبارات القراءة/الكتابة

> الملفات المرجعية: `config/database.php`، `tests/k6/read-write-verification.js`، `phpunit.xml`، `tests/bootstrap.php`، `tests/Feature/TestingDatabaseIsolationTest.php`، `docker-compose.yml`، `README.md`.

---

## 1) الملف بيعمل إيه؟

`config/database.php` هو ملف إعداد الاتصالات. أهم جزء فيه هو اتصال `mysql`:

```php
'read'  => ['host' => [env('DB_READ_HOST',  env('DB_HOST', '127.0.0.1'))]],
'write' => ['host' => [env('DB_WRITE_HOST', env('DB_HOST', '127.0.0.1'))]],
'sticky' => true,
```

- **`write`** بيتستخدم في `INSERT / UPDATE / DELETE / DDL` وكل حاجة بتغيّر البيانات.
- **`read`** بيتستخدم في `SELECT`.
- كل واحد فيهم `host` **مصفوفة**، مش قيمة واحدة: Laravel بيعمل `Arr::shuffle` على المصفوفة ويختار host عشوائي (توزيع أحمال)، ولو فشل الاتصال بيجرّب اللي بعده (failover). ده في `vendor/laravel/framework/src/Illuminate/Database/Connectors/ConnectionFactory.php` سطر 179–196.
- في `.env.example` عندك:
  - `DB_WRITE_HOST=mysql-primary`
  - `DB_READ_HOST=mysql-replica`
  - الاتنين بيرجعوا لـ `DB_HOST` لو مش متعرّفين.
- `options` بتاعة SSL بتضيف `MYSQL_ATTR_SSL_CA` لو الـ extension متاح (مع فرق بسيط في PHP 8.5 بسبب `Pdo\Mysql`).
- `mariadb` هنا **مش** فيه read/write split؛ لو هتستخدم MariaDB مع replica لازم تكرّر نفس بنية `read` / `write` / `sticky` جواها.

---

## 2) فائدة `sticky` بالتفصيل

### المشكلة اللي بيحلها

تخيّل الريكوست ده:

1. المستخدم يعمل `POST /orders` → البيانات بتتكتب على **primary**.
2. نفس الريكوست (أو نفس دورة الحياة) يعمل `SELECT` → Laravel بيروح للـ **replica**.
3. الـ replica لسه ما استلمش التغيير (replication lag) → الصفحة بتقول "الأوردر مش موجود" رغم إنه اتكتب.

دي مشكلة **read-your-writes**.

### إزاي `sticky` بيحلها

Laravel بيتابع flag اسمه `recordsModified` على اتصال الـ Database. أي write (`statement` / `affectingStatement` / `unprepared`) بيخلي الـ flag = `true`. وبعدها في `getReadPdo()`:

```php
if ($this->readOnWriteConnection ||
    ($this->recordsModified && $this->getConfig('sticky'))) {
    return $this->getPdo(); // اقرأ من اتصال الكتابة (primary)
}
```

يعني: **بعد أول عملية كتابة في نفس الاتصال، كل القراءات اللي بعدها بتروح للـ primary**، فبتشوف بياناتك فورًا. (`Connection.php` سطر 1278–1296).

كمان مهم تعرف:

- **الـ Transactions بتقرأ من الـ primary دايمًا** حتى لو `sticky = false`، لأن `transactions > 0` بيرجّع `getPdo()`.
- الـ flag بيفضل `true` لحد ما الاتصال يتصفّر أو تعمل `forgetRecordModificationState()`.
- في Laravel كمان فيه `useWriteConnectionWhenReading()` على الاتصال، و`->useWritePdo()` على الـ Query Builder، و`Model::onWriteConnection()` لو عايز تجبر قراءة معينة على الـ primary.

### حدود `sticky` (مهمة جدًا)

`sticky` شغّال **في نطاق الـ Connection object / دورة حياة الريكوست الواحد**. مع PHP-FPM:

- `POST /orders` ريكوست مستقل → اتصال جديد → `recordsModified = false`.
- `GET /orders` اللي بعده ريكوست تاني → اتصال جديد → الـ flag مش موجود → القراءة بتروح للـ replica تاني.

يعني **`sticky` لوحده مش ضمان لـ read-your-writes بين ريكوستين HTTP منفصلين**. ده مكتوب صراحة في `README.md` سطر 276. الحلول لو محتاج ضمان أعلى:

- توجيه القراءة الحرجة دي للـ primary بـ `useWritePdo()`.
- أو "session stickiness" لمدة صغيرة (cache flag لكل مستخدم بعد الكتابة).
- أو قراءة من الـ primary لأي endpoint حساس للاتساق.

### التكلفة

`sticky = true` معناها إن أي ريكوست فيه write، كل قراءاته الباقية هتضرب على الـ primary → ضغط أعلى على الـ primary وتوزيع أقل على الـ replica. عادةً دي مقايضة مقبولة مقابل صحة البيانات، لكنها سبب إن `sticky` مش بيتفعّل في كل الـ connections.

> ملاحظة على `docker-compose.yml`: خدمة `migrate` عاملة `DB_READ_HOST: mysql-primary` (سطر 113–114) عن قصد، لأن الـ migrations والـ DDL المفروض تتنفّذ على الـ primary مش على الـ replica.

---

## 3) ملفات الاختبار المتعلقة بالقراءة/الكتابة

### أ) `tests/k6/read-write-verification.js` — الاختبار الفعلي للـ routing

ده الملف الأساسي في المشروع اللي بيختبر سلوك read/write. بيعمل:

1. **`setup()`**: يعمل login على `/api/v1/users/sign-in` ويتأكد إن الرد `200` وفيه `token`، ولو `TEST_EMAIL` / `TEST_PASSWORD` / `PRODUCT_ID` ناقصين بيفشل فورًا.
2. **`default function`**:
   - `POST /api/v1/orders` بمنتج واحد → المفروض `201` والرد فيه `data.id`.
   - `GET /api/v1/orders/admin` فورًا بعدها → بيدوّر على الأوردر الجديد جوه `body.data.data`.
3. **القياسات**: `order_write_duration` و`order_immediate_read_duration` (من نوع `Trend`).
4. **الـ threshold**: `checks: ['rate==1']` — أي check يفشل، الاختبار كله يفشل.
5. لو الأوردر مش ظاهر فورًا، بيطبع رسالة بتقول إن السبب ممكن **replica lag** أو إن **sticky مش شغّال بين ريكوستين منفصلين** (سطر 124–129).

نقطة مهمة جدًا في فهم الاختبار ده:

- هو بيختبر **هل البيانات ظهرت فورًا من الـ read endpoint؟** (أي بيقيس أثر الـ replication lag).
- هو **مش** بيثبت إن الـ GET راح على الـ replica ولا الـ primary. نجاحه ممكن يكون بسبب إن الـ replica لحقت، أو إن القراءة راحت للـ primary. عشان تتحقق من الـ routing نفسه، لازم تراقب من ناحية الداتابيز (general log / `performance_schema` / connections على كل host).
- السبب إنه k6 وليس PHPUnit: لأن PHPUnit بيشتغل في نفس العملية والـ `sticky` behavior مختلف؛ الاختبار هنا محتاج ريكوستين HTTP حقيقيين مستقلين.
- المتغيرات: `API_BASE_URL`, `TEST_EMAIL`, `TEST_PASSWORD`, `PRODUCT_ID`, `TEST_ITERATIONS` (افتراضي 1), `TEST_VUS` (افتراضي 1). كل iteration ناجحة بتنشئ أوردر وتستهلك وحدة مخزون، عشان كده الافتراضي 1.

### ب) `tests/Feature/TestingDatabaseIsolationTest.php` + `phpunit.xml` + `tests/bootstrap.php`

دول مش اختبارات read/write، لكنهم **بيضمنوا إن اختبارات PHPUnit متلمسش MySQL خالص**:

- `phpunit.xml` بيفرض:

  ```xml
  <env name="DB_CONNECTION" value="sqlite" force="true"/>
  <env name="DB_DATABASE" value=":memory:" force="true"/>
  <env name="DB_URL" value="" force="true"/>
  ```

  عشان أي `DB_*` موروثة من Docker/MySQL ما تعملش migrations مدمّرة على الداتابيز الحقيقية.

- `tests/bootstrap.php` بيتأكد من نفس القيم، ويتأكد إن `pdo_sqlite` موجود، ويمنع تشغيل الاختبارات لو الإعدادات مش آمنة.
- `TestingDatabaseIsolationTest` بيؤكد على `app.env = testing`، `database.default = sqlite`، `database.connections.sqlite.database = :memory:`، و`order.stock_manager = local`.

يعني: **مفيش في المشروع اختبار PHPUnit بيختبر read/write split فعليًا**. الاختبار الوحيد لسلوك القراءة/الكتابة هو `tests/k6/read-write-verification.js`، ومعاه الاختبارات اللي بتضمن إن باقي الـ PHPUnit tests معزولة على SQLite. (كمان اختبارات `vendor/laravel/framework/tests` الخاصة بـ `sticky` مش موجودة في `vendor` عندك أصلًا، لأن الحزمة بتتوزّع من غير `tests`.)

### ج) البنية اللي الاختبار بيشتغل عليها

في `docker-compose.yml`:

- `mysql-primary` → الكتابة.
- `mysql-replica` → القراءة.
- `backend` بياخد `DB_WRITE_HOST=mysql-primary` و`DB_READ_HOST=mysql-replica` (سطر 169–170).

فلو عايز نتائج ذات معنى من اختبار k6، لازم الـ replica تكون شغالة ولسه بتتزامن، لأن لو `DB_READ_HOST = mysql-primary` الاختبار هينجح لكنه مش هيثبت أي حاجة عن الـ routing.

---

## خلاصة في سطرين

`sticky` = "اقرأ من الـ primary بعد ما تكتب، لكن في نفس الريكوست/الاتصال فقط" — مفيد جدًا لمنع read-your-writes داخل الريكوست، وغير كافٍ بين ريكوستين HTTP منفصلين. وملف `tests/k6/read-write-verification.js` هو اللي بيقيس أثر ده عمليًا، بينما `phpunit.xml` + `bootstrap.php` + `TestingDatabaseIsolationTest` وظيفتهم حماية اختبارات PHPUnit من لمس MySQL أصلًا.
