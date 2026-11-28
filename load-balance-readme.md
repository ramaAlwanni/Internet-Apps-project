# تشغيل واختبار التوسع وتوزيع الأحمال

هذا الدليل يشرح تشغيل متجر Laravel/React باستخدام Docker Compose، مع ثلاث نسخ Backend خلف Nginx، وثلاثة RabbitMQ workers، واختبارات توزيع الطلبات والاستمرارية. الأوامر مكتوبة لـ **PowerShell على Windows**.

> نفّذي الأوامر من مجلد المشروع الذي يحتوي `docker-compose.yml`. لا تضعي كلمات المرور أو مفاتيح التطبيق في هذا الملف أو في Git.

## 1. المتطلبات

- Docker Desktop يعمل وحالته Running.
- PowerShell مفتوح في مجلد المشروع.
- ملفات إعداد Docker وNginx موجودة، ومنها `docker-compose.yml` و`docker/loadbalancer.conf`.
- ملف اختبار K6 المصادق عليه موجود في `tests/k6/authenticated-products.js`.
- للاختبار المصادق عليه، استخدمي حساباً تجريبياً صالحاً في المتجر.

تحققي من مجلد العمل:

```powershell
Get-Location
Test-Path .\docker-compose.yml
```

يفترض أن يعرض الأمر الثاني `True`.

## 2. تشغيل النظام بثلاث نسخ وثلاثة عمّال

شغّلي خدمات Compose المعتادة، مع تحديد ثلاث نسخ من خدمة `backend` وثلاثة عمال:

```powershell
docker compose up -d --build --scale backend=3 --scale rabbitmq-worker=3
```

قد يستغرق أول تشغيل وقتاً أطول بسبب بناء صور Docker. في التشغيلات اللاحقة، إذا لم يتغير الكود أو Dockerfile، يمكن استخدام:

```powershell
docker compose up -d --no-build --scale backend=3 --scale rabbitmq-worker=3
```

تحققي من الخدمات والنسخ:

```powershell
docker compose ps
```

وتحققي تحديداً من نسخ الـBackend وموازن الحمل والعمال:

```powershell
docker compose ps backend loadbalancer rabbitmq-worker
```

ينبغي أن تري `backend-1` و`backend-2` و`backend-3`، وحاوية `loadbalancer`، وثلاثة عمال بحالة تشغيل. قد تختلف بادئة أسماء الحاويات بحسب اسم مجلد المشروع.

تحققي من إعداد Nginx:

```powershell
docker compose exec loadbalancer nginx -t
docker compose exec loadbalancer nginx -T 2>&1 | Select-String -Context 2,2 'upstream store_backends|least_conn|server backend'
```

منفذ الوصول على جهاز Windows يحدده ربط المنافذ في Compose؛ في الإعداد المستخدم هنا العنوان هو `http://localhost:8080`.

## 3. استراتيجية توزيع الحمل

يستخدم إعداد Nginx في `docker/loadbalancer.conf` مجموعة `store_backends`، وبداخلها:

```nginx
least_conn;
server backend:80 resolve;
```

تعني `least_conn` أن Nginx يختار عادةً النسخة ذات الاتصالات النشطة الأقل وقت الاختيار. هي استراتيجية مناسبة عندما تختلف مدة معالجة الطلبات. لا تضمن تساوي العدد النهائي للطلبات، ولا تقيس CPU أو الذاكرة مباشرةً؛ لذلك قد تظهر أعداد متقاربة أو مختلفة في سجل الاختبار.

## 4. فحص العمال والطابور

اعرضي حاويات العمال:

```powershell
docker compose ps rabbitmq-worker
```

ثم افحصي مستهلكي RabbitMQ:

```powershell
docker compose exec rabbitmq rabbitmqctl list_queues name consumers messages_ready messages_unacknowledged
```

في الطابور `order_emails_queue`، المتوقع أن يكون عدد `consumers` مساوياً 3 عند عمل العمال الثلاثة. افحصي السجلات بحثاً عن مهام معالجة مكتملة أو أخطاء:

```powershell
docker compose logs --since 30m rabbitmq-worker
```

وجود ثلاثة مستهلكين يثبت اتصال العمال بالطابور نفسه، لكنه لا يعني بالضرورة أن كل عامل عالج العدد نفسه من الرسائل.

## 5. اختبار K6: 500 طلب قراءة مصادق عليه

الاختبار في `tests/k6/authenticated-products.js` يسجل الدخول مرة في `setup()`، ثم يرسل 500 طلب قراءة إلى:

```text
GET /api/v1/products?page=1&per_page=4
```

ويستخدم JWT. لا ينشئ طلبات شراء ولا يغيّر المخزون. قبل كل جولة، تحققي أن النسخ الثلاث وموازن الحمل تعمل:

```powershell
docker compose ps backend loadbalancer
```

### إدخال بيانات الحساب بأمان

في نافذة PowerShell واحدة، امسحي متغيرات الجولة السابقة:

```powershell
Remove-Item Env:K6_EMAIL, Env:K6_PASSWORD, Env:K6_USER_AGENT -ErrorAction SilentlyContinue
```

أدخلي بيانات حساب تجريبي. كلمة المرور لا تظهر أثناء الكتابة:

```powershell
$env:K6_EMAIL = Read-Host "Email for test account"
$secure = Read-Host "Password for test account" -AsSecureString
$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try {
    $env:K6_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
}
```

أنشئي علامة فريدة لكل جولة، واحتفظي بقيمتها في `$marker` حتى تنتهي عملية عدّ السجلات:

```powershell
$env:K6_USER_AGENT = "k6-auth-products-$(Get-Date -Format yyyyMMdd-HHmmss)"
$marker = $env:K6_USER_AGENT
"Test marker: $marker"
```

شغّلي الاختبار مع `--no-deps` حتى لا يعيد Compose تشغيل التبعيات أو يزيل النسخ أثناء الاختبار:

```powershell
docker compose --profile load-test run --rm --no-deps -e K6_EMAIL -e K6_PASSWORD -e K6_USER_AGENT k6 run /tests/authenticated-products.js
```

تحققي من ملخص K6. المتوقع 500 iteration و500 check ناجح، مع استجابات HTTP 200. قد يعرض `http_reqs=501` لأن ذلك يشمل طلب تسجيل الدخول الواحد إضافة إلى 500 طلب منتجات. لذلك عند عدّ التوزيع، احسبي طلبات `GET /api/v1/products` فقط.

### عدّ طلبات المنتجات بحسب نسخة Backend

بعد انتهاء K6، وفي **نافذة PowerShell نفسها** التي تحتوي `$marker`:

```powershell
$logs = docker compose logs --since 10m --timestamps backend 2>&1
$marked = $logs | Select-String -SimpleMatch $marker
$productLogs = $marked | Where-Object { $_.Line -match '"GET /api/v1/products\?' }

$counts = $productLogs |
    ForEach-Object {
        if ($_.Line -match 'backend-\d+') { $Matches[0] }
    } |
    Group-Object |
    Sort-Object Name |
    Select-Object Name, Count

$counts
"Marked product GET total: $($productLogs.Count)"
```

ينبغي أن يكون مجموع طلبات المنتجات المعلّمة 500، وأن تظهر النسخ التي استقبلت الطلبات. استخدمي الأعداد الفعلية في التوثيق؛ لا تفترضي أن التوزيع سيكون متساوياً تماماً.

## 6. اختبار الاستمرارية أثناء 500 طلب

هذا اختبار منفصل عن اختبار التوزيع العادي. يشغّل 500 طلب قراءة، ثم يوقف نسخة Backend واحدة أثناء تنفيذ K6. لا توقفي قاعدة البيانات أو Redis أو RabbitMQ أو Nginx.

1. تأكدي أن النسخ الثلاث تعمل:

   ```powershell
   docker compose ps backend loadbalancer
   ```

2. في نافذة PowerShell الأولى، أنشئي علامة جديدة وأدخلي بيانات الاختبار كما في القسم السابق، ثم شغّلي أمر K6 نفسه. أبقي النافذة مفتوحة وراقبي بدء الاختبار.

3. أثناء تنفيذ الاختبار، افتحي نافذة PowerShell ثانية في مجلد المشروع وأوقفي نسخة واحدة فقط:

   ```powershell
   docker stop internet-apps-project-backend-2
   ```

   إذا اختلف اسم الحاوية عندك، خذيه من `docker compose ps backend`. أوقفيها بعد بدء K6 وليس قبله.

4. اتركي النسخة متوقفة حتى ينتهي اختبار K6. سجّلي ملخص K6 والنسخة التي أوقفتِها. النجاح يعني اكتمال 500 طلب المنتجات دون فشل HTTP بحسب ملخص الاختبار؛ وثّقي النتيجة الفعلية، ولا تفترضيها قبل رؤية المخرجات.

5. احسبي السجلات المعلّمة باستخدام أمر العدّ أعلاه. توقعي أن الطلبات أثناء التوقف ستذهب إلى النسخ الباقية؛ لا تحسبي طلب تسجيل الدخول ضمن طلبات المنتجات.

6. أعيدي تشغيل النسخة فور انتهاء الاختبار:

   ```powershell
   docker start internet-apps-project-backend-2
   docker compose ps backend
   ```

   تأكدي من عودة الحاوية إلى حالة `Up`.

**مهم:** لا تنفذي `docker compose up` أثناء اختبار الفشل؛ قد يعيد Compose إنشاء الخدمات أو يغير حالة النسخ ويُفسد الاختبار. إذا فشل K6 أو ظهرت أخطاء، احتفظي بالمخرجات كما هي ثم أعيدي النسخة المتوقفة.

## 7. فحوص الحالة والملفات المحلية

### إعداد الجلسة والكاش والقرص الافتراضي

```powershell
docker compose exec --index 1 backend php artisan tinker --execute="dump(config('session.driver'));"
docker compose exec --index 1 backend php artisan tinker --execute="dump(config('cache.default'));"
docker compose exec --index 1 backend php artisan tinker --execute="dump(config('filesystems.default'));"
```

القيم التي شوهدت في الفحص السابق كانت `database` للجلسات، و`redis` للكاش، و`local` للقرص الافتراضي. `local` هو الافتراضي فقط؛ لا يحدد وحده القرص المستخدم لصور المنتجات إذا كانت الشيفرة تختار قرصاً آخر صراحةً.

### اختبار عملي لمشاركة الكاش

اكتبي قيمة مؤقتة من النسخة الأولى ثم اقرئيها من النسختين الأخريين:

```powershell
$testKey = "scalability-cache-check-$(Get-Date -Format yyyyMMddHHmmss)"
docker compose exec --index 1 backend php artisan tinker --execute="Illuminate\Support\Facades\Cache::put('$testKey', 'shared-ok', 120);"
docker compose exec --index 2 backend php artisan tinker --execute="dump(Illuminate\Support\Facades\Cache::get('$testKey'));"
docker compose exec --index 3 backend php artisan tinker --execute="dump(Illuminate\Support\Facades\Cache::get('$testKey'));"
```

المتوقع أن يعرض آخر أمرين `shared-ok`. القيمة مؤقتة وتنتهي بعد 120 ثانية. هذا يثبت مشاركة قيمة Laravel Cache في الاختبار، ولا يثبت تلقائياً صحة كل ميزة تستخدم الكاش.

### فحص volume صور المنتجات

```powershell
Select-String -Path docker-compose.yml -Pattern 'product_images|storage/app/public/products' -Context 2,2

1..3 | ForEach-Object {
    "`n--- backend-$_ ---"
    docker compose exec --index $_ backend sh -lc "find storage/app/public/products -maxdepth 1 -type f | sort | head -5"
}
```

إذا ظهر ربط `product_images` إلى مسار الصور وظهرت الملفات في النسخ الثلاث، فهذا دليل على أن مسار صور المنتجات الذي فُحص مشترك. لا تعممي النتيجة على كل الملفات أو كل مسارات `storage` من دون فحصها.

### فحص إعداد الجلسات وقاعدة البيانات

إعداد `session.driver=database` يعني أن Laravel مهيأ لتخزين الجلسات في قاعدة البيانات، لكنه وحده لا يثبت أن مسار JWT في التطبيق يستخدم جلسات Laravel. لا تصفي اختبار JWT على المنتجات بأنه اختبار جلسة Laravel ما لم يكن ذلك صحيحاً في تنفيذ التطبيق.

## 8. إيقاف الخدمات

لإيقاف الخدمات من دون حذف بيانات volumes:

```powershell
docker compose stop
```

لإيقاف الخدمات وحذف الحاويات والشبكات التي أنشأها Compose، مع إبقاء volumes عادةً:

```powershell
docker compose down
```

لا تستخدمي `docker compose down -v` إلا إذا كنتِ تقصدين حذف volumes والبيانات المخزنة فيها؛ هذا الأمر قد يحذف بيانات قواعد البيانات وصور المنتجات والطوابير.

## 9. ما الذي يوثّق للمناقشة؟

احتفظي بلقطات أو مخرجات لهذه الأدلة:

1. `docker compose ps` ويظهر ثلاث نسخ Backend وثلاثة عمال وموازن الحمل.
2. إعداد Nginx الفعلي الذي يظهر `least_conn` و`server backend:80 resolve`.
3. ملخص K6 لطلبات المنتجات: iterations وchecks والفشل وp95.
4. توزيع سجلات طلبات المنتجات حسب `backend-1/2/3` مع مجموع 500.
5. اختبار الاستمرارية الذي أُوقف فيه Backend أثناء تشغيل 500 طلب، مع النتيجة الفعلية قبل وبعد إعادة تشغيله.
6. اختبار الكاش العملي ونتيجة `shared-ok` من النسختين الثانية والثالثة.
7. إعداد volume وصور المنتجات الظاهرة في النسخ الثلاث.
8. إعدادات الجلسات والكاش، وأدلة العمال والطابور.
9. أي مشكلة واجهت فعلاً: وثّقي رسالة الخطأ قبل الإصلاح، التغيير الذي أصلحها، ودليل النجاح بعد الإصلاح. لا تنسبي مشكلة إعداد عامة إلى التوسع الأفقي ما لم يكن لديك دليل أنها ظهرت بسبب تشغيل نسخ متعددة.

## 10. حماية الأسرار والتنظيف

لا ترفعي ملف `.env` أو كلمات المرور أو JWT أو أسرار SMTP إلى Git أو ترفقيها في صور التوثيق. بعد انتهاء K6، امسحي متغيرات الحساب من نافذة PowerShell:

```powershell
Remove-Item Env:K6_EMAIL, Env:K6_PASSWORD, Env:K6_USER_AGENT -ErrorAction SilentlyContinue
if ($secure) { $secure.Dispose() }
$bstr = $null
```

هذا يمسح متغيرات البيئة من جلسة PowerShell الحالية؛ أغلقي النافذة بعد الاختبار أيضاً.
