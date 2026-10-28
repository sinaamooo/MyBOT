# ICT درس دوم — نقدینگی (Liquidity)

جزوه PDF آموزشی با نمودارهای کندلی: `ICT-Lesson-02-Liquidity.pdf`

ساخت دوباره (نیاز به Node.js و Playwright):

```bash
node build.js            # خروجی: ICT-Lesson-02-Liquidity.pdf
node build.js out.pdf --png previews/   # همراه با پیش‌نمایش PNG هر صفحه
```

- `charts.js` — موتور رسم نمودار کندلی (SVG)
- `figures.js` — داده و برچسب‌های هر نمودار
- `build.js` — متن، صفحه‌آرایی و خروجی PDF
- `fonts/` — فونت Vazirmatn (مجوز OFL)

نمودارها نمایشی‌اند و از داده واقعی بازار ساخته نشده‌اند.
