# ترويسات الأمان في المشروع

يوثّق هذا الملف ترويسات HTTP الأمنية الموجودة في المشروع، مكان إعدادها، معنى كل ترويسة، طريقة اختبارها، والحدود التي يجب الانتباه لها عند التشغيل والنشر.

## الخلاصة

ترويسات الأمان الأساسية مضافة في Middleware عام في Laravel:

- التنفيذ: `app/Http/Middleware/SecurityHeaders.php`
- تسجيله على استجابات التطبيق: `bootstrap/app.php`
- الاختبارات: `tests/Feature/SecurityHeadersTest.php`

استجابة Laravel عبر HTTP المحلي تتضمن الترويسات التالية:

```text
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; media-src 'self'; worker-src 'self'
```

وتضاف `Strict-Transport-Security` فقط عندما يتعرف Laravel على الطلب على أنه HTTPS.

## أين يضاف Middleware؟

في `bootstrap/app.php` يسجل التطبيق Middleware على مستوى التطبيق:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    // ...
})
```

إضافته باستخدام `append` تعني أنه يعمل على نطاق التطبيق، وليس على مسار API واحد فقط. لذلك يمكن أن تظهر الترويسات على استجابات صفحات الويب وAPI، بما فيها استجابات الأخطاء التي تمر عبر Middleware.

## شرح الترويسات الموجودة

### `X-Frame-Options: DENY`

تمنع المتصفح من عرض الصفحة داخل `iframe` أو frame، حتى لو كان الإطار على نفس الموقع. تقلل ذلك من خطر **Clickjacking**، حيث يُخفي المهاجم واجهة أصلية داخل صفحة أخرى ليخدع المستخدم كي ينقر على عنصر لا يقصده.

سياسة CSP الحالية تتضمن أيضاً `frame-ancestors 'none'`، وهي الطريقة الأحدث للتحكم بمن يستطيع تضمين الصفحة. وجود الطريقتين يعطي دعماً للمتصفحات الحديثة والقديمة.

### `X-Content-Type-Options: nosniff`

تطلب من المتصفح عدم تخمين نوع الملف اعتماداً على محتواه. يجب أن يحدد الخادم نوع المحتوى الصحيح عبر `Content-Type`، وألا يحاول المتصفح تفسير ملف بنوع غير متوقع كأنه JavaScript أو HTML.

### `Referrer-Policy: strict-origin-when-cross-origin`

تتحكم بالمعلومات التي يرسلها المتصفح في ترويسة `Referer` عند الانتقال أو تحميل مورد:

- في الطلبات إلى نفس الأصل، يمكن إرسال عنوان الصفحة كاملاً.
- عند الانتقال بين أصلين مختلفين، يرسل المتصفح الأصل فقط عادةً، مثل `https://example.com`، ولا يرسل المسار وquery string.
- عند الانتقال من HTTPS إلى HTTP، لا يرسل المتصفح معلومات المرجع.

هذا يقلل تسريب معلومات حساسة قد تكون موجودة في مسار أو query string.

### `Permissions-Policy: camera=(), microphone=(), geolocation=()`

تمنع المستند من استخدام واجهات المتصفح التالية:

- الكاميرا.
- الميكروفون.
- الموقع الجغرافي.

هذه إعدادات مناسبة ما دامت وظائف الموقع لا تحتاج تلك القدرات. إذا أضيفت ميزة تعتمد عليها لاحقاً، يجب تعديل السياسة بشكل مقصود ومحدود.

### `Content-Security-Policy` (CSP)

تحدد CSP المصادر التي يسمح للمتصفح بتحميل الموارد منها. وهي من أهم طبقات تقليل أثر **Cross-Site Scripting (XSS)**، لكنها لا تغني عن التحقق من المدخلات وترميز المخرجات ومعالجة البيانات بأمان في التطبيق.

السياسة الفعلية في Middleware:

```php
private const CONTENT_SECURITY_POLICY = "default-src 'self'; "
    . "base-uri 'self'; "
    . "object-src 'none'; "
    . "frame-ancestors 'none'; "
    . "form-action 'self'; "
    . "script-src 'self'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; "
    . "font-src 'self' data:; "
    . "connect-src 'self'; "
    . "media-src 'self'; "
    . "worker-src 'self'";
```

شرح التوجيهات:

| التوجيه | معناه في الإعداد الحالي |
|---|---|
| `default-src 'self'` | المصدر الافتراضي لمعظم أنواع الموارد هو نفس الأصل فقط. |
| `base-uri 'self'` | يسمح بوسم `<base>` من نفس الأصل فقط، ويمنع تغيير أساس الروابط من مصدر خارجي. |
| `object-src 'none'` | يمنع موارد `object` و`embed` التي لم تعد مطلوبة في أغلب التطبيقات. |
| `frame-ancestors 'none'` | يمنع أي موقع من تضمين الصفحة داخل frame. |
| `form-action 'self'` | يسمح بإرسال النماذج إلى نفس الأصل فقط. |
| `script-src 'self'` | يسمح بتحميل JavaScript من نفس الأصل؛ لا يسمح افتراضياً بسكربتات inline أو `eval`. |
| `style-src 'self' 'unsafe-inline'` | يسمح بملفات CSS من نفس الأصل وبأنماط inline. هذا استثناء أوسع من منع inline styles، وقد يلزم إبقاؤه إذا كانت الصفحات تعتمد عليها. |
| `img-src 'self' data:` | يسمح بالصور من نفس الأصل وصور `data:` المضمّنة. |
| `font-src 'self' data:` | يسمح بالخطوط من نفس الأصل أو من مصادر `data:`. |
| `connect-src 'self'` | يقيّد اتصالات `fetch` وXHR وWebSocket إلى نفس الأصل. |
| `media-src 'self'` | يقيّد مصادر الصوت والفيديو إلى نفس الأصل. |
| `worker-src 'self'` | يقيّد تحميل Web Workers إلى نفس الأصل. |

#### ملاحظة حول الخطوط والواجهة

صفحة Laravel الترحيبية الحالية تستورد خطوطاً من `fonts.bunny.net`، بينما CSP أعلاه لا تسمح بهذا النطاق. قد يمنع المتصفح الخط الخارجي ويستخدم خطاً احتياطياً. إذا كان تحميل الخط ضرورياً، يجب إضافة النطاق المطلوب إلى `style-src` و/أو `font-src` بحسب طريقة تحميله، بعد مراجعة الحاجة.

الواجهة الأمامية React تعمل من أصل منفصل أثناء التطوير، عادةً `http://localhost:5173`، والـAPI من `http://localhost:8080`. لذلك:

- CSP التي يرسلها Laravel تحكم المستندات التي يقدمها Laravel؛ لا تنتقل لتصبح سياسة صفحة React التي يقدمها Vite.
- ترويسة CSP على استجابة API بصيغة JSON لا تضبط سياسة HTML الخاص بالواجهة.
- إذا أريدت CSP للواجهة، يجب ضبطها في خادم الواجهة/الاستضافة التي تقدم `index.html`، والسماح صراحةً بعنوان API ضمن `connect-src`.

### `Strict-Transport-Security` (HSTS)

التطبيق يضيفها عند كون الطلب آمناً:

```php
if ($request->isSecure()) {
    $response->headers->set(
        'Strict-Transport-Security',
        'max-age=31536000; includeSubDomains; preload'
    );
}
```

ومعنى القيمة:

- `max-age=31536000`: اطلب من المتصفح تذكر سياسة HTTPS لمدة 31,536,000 ثانية، أي سنة.
- `includeSubDomains`: تشمل السياسة النطاقات الفرعية.
- `preload`: تشير إلى أن إعداد النطاق قد يطابق متطلبات قوائم HSTS preload؛ وجود هذه الكلمة وحده لا يضيف الموقع إلى تلك القوائم.

**لا ينبغي إرسال HSTS على اتصال HTTP عادي.** في Docker المحلي، الموقع يستخدم HTTP على `localhost:8080`، لذلك غياب HSTS متوقع. في الإنتاج، إذا كان TLS ينتهي عند reverse proxy أو load balancer، يجب ضبط proxy موثوق بحيث يتعرف Laravel بدقة على أن الطلب الأصلي كان HTTPS؛ وإلا قد لا يرى Laravel الطلب على أنه آمن. لا تثق عشوائياً بترويسات `X-Forwarded-Proto` من مصادر غير موثوقة.

لا تفعّل `includeSubDomains` أو preload على نطاق إنتاج قبل التأكد من أن كل النطاقات الفرعية تعمل عبر HTTPS؛ فالسياسة قد تمنع المتصفحات من فتحها عبر HTTP.

## ما الذي ظهر في الفحص الفعلي؟

عند طلب `http://localhost:8080/` في بيئة Docker المحلية، أعاد Laravel صفحة HTML مع:

- `X-Frame-Options`
- `X-Content-Type-Options`
- `Referrer-Policy`
- `Permissions-Policy`
- `Content-Security-Policy`
- ترويسات CORS التي يضيفها إعداد Nginx

ولم تظهر `Strict-Transport-Security`، وهذا متوقع لأن الاتصال كان HTTP وليس HTTPS. ظهور `X-Powered-By: PHP/8.2.35` في الاستجابة يكشف إصدار PHP؛ ليست ترويسة حماية، ويمكن تعطيلها لتقليل كشف معلومات البيئة. يجب التحقق من ذلك في الاستجابة بعد أي تغيير في إعداد PHP/Nginx.

## ترويسات CORS ليست بديلاً عن ترويسات الأمان

إعداد CORS في `config/cors.php` وNginx يتعامل مع السماح للمتصفح بطلب الموارد من origins مختلفة. هذا يختلف عن CSP:

- **CORS**: هل يسمح المتصفح لكود صفحة من origin معين بقراءة استجابة API؟
- **CSP**: من أين يُسمح للصفحة بتحميل السكربتات والأنماط والصور والاتصالات؟

إعداد CORS لا يحل محل `X-Frame-Options` أو CSP أو بقية الترويسات. كذلك، وجود ترويسة CORS لا يعني أن API أصبحت عامة أو أن المصادقة غير لازمة.

## شرح اختبار الترويسات

الاختبارات في `tests/Feature/SecurityHeadersTest.php`. هي اختبارات Laravel Feature: ترسل طلباً داخلياً إلى التطبيق وتفحص استجابة HTTP. لا تفتح متصفحاً حقيقياً ولا تختبر إعداد TLS في Docker.

### الاختبار الأول: ترويسات الاستجابة العادية

```php
public function test_it_adds_security_headers_to_responses(): void
{
    $response = $this->get('/up');

    $response
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeader('Content-Security-Policy', '...')
        ->assertHeaderMissing('Strict-Transport-Security');
}
```

- `$this->get('/up')` يحاكي طلباً إلى مسار فحص صحة Laravel.
- `assertOk()` يتأكد من status `200`.
- `assertHeader(...)` يفحص وجود كل ترويسة والقيمة المتوقعة لها.
- فحص CSP يقارن السياسة كاملة، بما فيها ترتيب التوجيهات ونصها.
- `assertHeaderMissing('Strict-Transport-Security')` يتحقق أن HSTS غير مضافة لطلب HTTP غير الآمن.

### الاختبار الثاني: HSTS للطلب الآمن

```php
public function test_it_adds_hsts_only_for_secure_requests(): void
{
    $response = $this->get('https://localhost/up');

    $response
        ->assertOk()
        ->assertHeader(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload'
        );
}
```

هذا يحاكي طلب HTTPS داخل Laravel، ويتحقق من أن HSTS تظهر بالقيمة المحددة. **لا يثبت أن Nginx أو Docker يقدم HTTPS فعلياً**؛ إنه يختبر شرط Middleware بالنسبة إلى طلب يراه Laravel آمناً.

لتشغيل الاختبار:

```powershell
docker compose exec backend php artisan test tests/Feature/SecurityHeadersTest.php
```

نتيجة التحقق في بيئة المشروع: **اختباران ناجحان و15 assertion**.

## ما الذي يغطيه الإعداد، وما الذي لا يغطيه؟

### يغطي

- منع التضمين داخل الإطارات.
- منع MIME sniffing.
- تقييد معلومات Referer.
- تعطيل قدرات متصفح غير مطلوبة حالياً.
- تقييد مصادر المحتوى عبر CSP.
- إضافة HSTS فقط عندما يتعرف Laravel على الاتصال على أنه HTTPS.
- اختبار وجود القيم المتوقعة في استجابات Laravel.

### لا يضمن وحده

- HTTPS/TLS فعلياً؛ يجب إعداده في Nginx أو proxy أو load balancer.
- CSP الخاصة بصفحة React التي يقدمها Vite أو خادم الواجهة.
- الحماية الكاملة من XSS؛ ما زال الترميز والتحقق الآمن من المدخلات مطلوباً.
- إخفاء جميع معلومات الخادم؛ ظهر `X-Powered-By` في الاستجابة المحلية.
- أن سياسة CSP مناسبة لكل الواجهات والموارد الخارجية دون اختبارها في المتصفح.

## توصيات قبل النشر

1. فعّل HTTPS على نقطة الدخول العامة، وتأكد أن Laravel يتعرف بشكل صحيح على HTTPS خلف proxy موثوق.
2. أرسل HSTS في الإنتاج عبر HTTPS فقط، وراجع بعناية `includeSubDomains` و`preload`.
3. أضف CSP مناسبة لخادم React نفسه؛ اسمح لـ`connect-src` بعنوان API المقصود فقط.
4. اختبر CSP في المتصفح على الصفحات الفعلية، خصوصاً الخطوط والصور وVite أو أي موارد خارجية. يمكن البدء بسياسة تقريرية `Content-Security-Policy-Report-Only` عند إدخال تغييرات كبيرة لتجنب حجب وظائف الموقع مباشرة.
5. راجع سبب الحاجة إلى `'unsafe-inline'` في `style-src`، وأزلها إن أمكن باستخدام أنماط ملفات أو nonce/hash مناسب.
6. عطّل `X-Powered-By` من إعداد PHP إذا لم تكن هناك حاجة لإظهار إصدار PHP.
7. أعد فحص الترويسات على الاستجابات الناجحة والأخطاء، ومن خلال العنوان العام بعد الـproxy، وليس فقط من اختبار Laravel الداخلي.

