# مشارکت / Contributing

سپاسگزار از علاقه‌مندی شما برای بهبود افزونه‌ی TS BNPL.

## کد‌نویسی / Code Style

### PHP
- تمام کد باید با `php -l` خالی از خطا باشد
- استفاده از spaces بجای tabs (۴ کاراکتر)
- نام‌گذاری کلاس‌ها: `TS_BNPL_Feature`
- نام‌گذاری توابع: `ts_bnpl_function_name`
- نام‌گذاری متغیرها: `$snake_case`
- داکومنت توابع با PHPDoc

```php
/**
 * توضیح تابع.
 *
 * جزئیات بیشتر اگر لازم است.
 *
 * @param string $param توضیح پارامتر.
 * @return bool نتیجه.
 */
function ts_bnpl_example( $param ) {
    // کد
    return true;
}
```

### CSS
- BEM naming: `.ts-bnpl__element--modifier`
- استفاده از CSS variables از قالب
- بدون `!important`
- mobile-first approach

### JavaScript
- استفاده از jQuery برای سازگاری
- jQuery namespace: `.tsBnpl`
- متغیرهای ثابت: `CONSTANT_NAME`

```javascript
$( document ).on( 'click.tsBnpl', '.selector', function () {
    // کد
} );
```

## تست‌کردن / Testing

### Product Page
```bash
npm test -- tests/product-variation.test.mjs
```

### Checkout
```bash
npm test -- tests/checkout-experience.test.mjs
```

### تمام تست‌ها
```bash
npm test
```

## فرآیند / Process

1. **شاخه جدید بسازید:**
   ```bash
   git checkout -b feature/نام-ویژگی
   ```

2. **تغییرات کنید** و با قواعد کد نویسی مطابقت داشته باشد

3. **تست‌کنید:**
   ```bash
   php -l includes/class-ts-bnpl-*.php
   npm test
   ```

4. **commit پیام معنادار:**
   ```bash
   git commit -m "feat(display): add accordion mode for credit purchase"
   ```

   فرمت: `type(scope): description`
   - `feat:` ویژگی جدید
   - `fix:` رفع اشکال
   - `refactor:` بازنویسی بدون تغییر رفتار
   - `test:` تست جدید
   - `docs:` مستندات
   - `style:` فرمت‌گذاری
   - `chore:` وابستگی‌ها

5. **Pull Request بسازید:**
   - توضیح واضح از تغییرات
   - لینک به issue اگر مرتبط است
   - تصویر/فیلم اگر UI تغییر کرده

## معماری / Architecture

```
ts-bnpl/
├── includes/
│   ├── class-ts-bnpl-data.php        # داده‌های متادیتا
│   ├── class-ts-bnpl-admin.php       # فیلد تنظیمات محصول
│   ├── class-ts-bnpl-report.php      # گزارش و تنظیمات جهانی
│   ├── class-ts-bnpl-checkout.php    # هوک‌های درگاه و سفارش
│   ├── class-ts-bnpl-display.php     # رندر فرانت‌اند
│   └── class-ts-bnpl-landing.php     # صفحه‌ی فرود و کاروسل واجد شرایط
├── assets/
│   ├── css/ts-bnpl.css
│   ├── css/ts-bnpl-landing.css
│   ├── js/ts-bnpl.js
│   ├── js/ts-bnpl-landing.js
│   └── images/digipay.svg
├── languages/                         # ترجمه‌ها
├── tests/                             # تست‌های قرارداد
└── ts-bnpl.php                        # بوت‌استرپ
```

## بازنویسی حالت‌های درگاه

نه از MIN/MAX ثابت، بلکه با فیلتر:

```php
add_filter( 'ts_bnpl_cart_eligible', function ( $eligible, $cart_total ) {
    // منطق دلخواه شما
    return $eligible && $cart_total < 10_000_000;
}, 10, 2 );
```

## سؤالات؟

درخواست issue جدید یا پیام‌رسان بزنید.

---

**تشکر از تلاش شما!** 🙏
