from __future__ import annotations

SEP = "━━━━━━━━━━━━━━━━━"

TEXTS: dict[str, tuple[str, str]] = {
    "start_private": (
        "👋 سلام <b>{name}</b> خوش اومدی!\n"
        f"{SEP}\n"
        "🤖 من یک ربات همه‌کاره کریپتو و مدیریت گروه هستم:\n\n"
        "<blockquote>"
        "💱 قیمت و تبدیل هزاران ارز دیجیتال (مثلا <code>10 تتر</code>)\n"
        "🇮🇷 تبدیل تومان (مثلا <code>500000 تومان</code>)\n"
        "🌍 تبدیل ارز همه کشورها (مثلا <code>500 درهم</code>)\n"
        "👛 چکر ولت روی همه شبکه‌ها (آدرس رو بفرست)\n"
        "🔗 چکر هش تراکنش روی همه شبکه‌ها\n"
        "🎁 قیمت گیفت‌های تلگرام (لینک گیفت یا <code>/gift @username</code>)\n"
        "🌐 ترجمه (روی پیام ریپلای کن و بنویس <code>ترجمه</code>)\n"
        "🧮 ماشین حساب (مثلا <code>200+79</code>)\n"
        "🆔 آیدی عددی (ریپلای + <code>UID</code>)\n"
        "😎 کد ایموجی پریمیوم (<code>/emoji</code>)\n"
        "👤 اطلاعات اکانت (<code>/account</code>)\n"
        "🛡 ضد سلف، ضد تبچی، ضد کلاهبرداری، عضویت اجباری\n"
        "🐋 هشدار جابجایی‌های سنگین روی همه شبکه‌ها"
        "</blockquote>\n"
        f"{SEP}\n"
        "➕ منو به گروهت اضافه کن و ادمینم کن 👇",
        "پیام استارت در پیوی",
    ),
    "help": (
        "📚 <b>راهنمای ربات</b>\n"
        f"{SEP}\n"
        "<blockquote expandable>"
        "💱 <b>قیمت ارز:</b> <code>btc</code> ، <code>قیمت تون</code> ، <code>/p eth</code>\n"
        "💵 <b>تبدیل:</b> <code>10 تتر</code> ، <code>500 تون</code> ، <code>2.5 btc</code> ، <code>1000 استارز</code>\n"
        "🇮🇷 <b>تومان:</b> <code>500000 تومان</code> ، <code>2 میلیون تومن</code> ، <code>10m ریال</code>\n"
        "🌍 <b>ارز کشورها:</b> <code>500 درهم</code> ، <code>100 یورو</code> ، <code>50 usd</code> ، <code>1000 لیر</code>\n"
        "👛 <b>چکر ولت:</b> آدرس کیف پول را بفرستید (BTC, ETH, BSC, TRON, TON, SOL, ...)\n"
        "🔗 <b>چکر هش:</b> هش تراکنش را بفرستید\n"
        "🎁 <b>گیفت:</b> لینک <code>t.me/nft/...</code> یا <code>/gift @username</code>\n"
        "🌐 <b>ترجمه:</b> روی پیام ریپلای و <code>ترجمه</code>\n"
        "🧮 <b>حساب:</b> <code>698*789</code> ، <code>797÷788</code>\n"
        "🆔 <b>آیدی عددی:</b> ریپلای و <code>UID</code>\n"
        "😎 <b>ایموجی:</b> <code>/emoji</code> + ایموجی پریمیوم یا ریپلای\n"
        "🚨 <b>گزارش کلاهبردار:</b> <code>/scam @username</code>\n"
        "🔍 <b>استعلام کلاهبردار:</b> <code>/check @username</code>\n"
        "👤 <b>اکانت:</b> <code>/account</code>\n"
        "⚙️ <b>تنظیمات گروه:</b> <code>/settings</code> (ادمین گروه)"
        "</blockquote>",
        "راهنما",
    ),
    "bot_added": (
        "🎉 <b>ربات با موفقیت به گروه اضافه شد!</b>\n"
        f"{SEP}\n"
        "⚠️ برای فعال شدن همه قابلیت‌ها (ضد سلف، ضد تبچی، عضویت اجباری و ...) "
        "من رو <b>ادمین کامل</b> کنید.\n"
        "⚙️ تنظیمات: <code>/settings</code>",
        "پیام اضافه شدن ربات به گروه",
    ),
    "error": ("⚠️ خطایی رخ داد، لطفا دوباره تلاش کنید.", "خطای عمومی"),
    "loading": ("⏳ لطفا صبر کنید...", "در حال پردازش"),
    "force_join_private": (
        "🔒 <b>{name}</b> عزیز\n"
        "برای استفاده از ربات ابتدا باید در کانال‌های زیر عضو شوید 👇\n"
        "تا عضو نشوید نمی‌توانید از ربات استفاده کنید.\n"
        "✅ بعد از عضویت روی «عضو شدم» بزنید.",
        "عضویت اجباری در پیوی",
    ),
    "force_join_group": (
        "🔒 {mention} عزیز\n"
        "برای ارسال پیام در این گروه باید در کانال‌های زیر عضو شوید 👇\n"
        "⛔️ تا عضو نشوید نمی‌توانید چت کنید.",
        "عضویت اجباری در گروه ({mention})",
    ),
    "force_join_ok": ("✅ عضویت شما تایید شد، خوش آمدید!", "تایید عضویت (پاپ‌آپ)"),
    "force_join_not_yet": ("❌ هنوز در همه کانال‌ها عضو نشده‌اید!", "عدم عضویت (پاپ‌آپ)"),
    "conv_crypto": (
        "💎 <b>{amount} {symbol}</b> <i>{name}</i>\n"
        f"{SEP}\n"
        "<blockquote>"
        "🇮🇷 تومان: <code>{toman}</code>\n"
        "💵 دلار: <code>{usd}</code> $\n"
        "🟢 تتر: <code>{usdt}</code> USDT\n"
        "💎 تون: <code>{ton}</code> TON\n"
        "🔺 ترون: <code>{trx}</code> TRX\n"
        "⭐️ استارز: <code>{stars}</code> Stars"
        "</blockquote>\n"
        "📊 قیمت واحد: <code>{price_usd}</code> $ | <code>{price_toman}</code> ت\n"
        "📈 تغییر ۲۴ ساعته: {change}\n"
        f"{SEP}\n"
        "🕰 {time}",
        "تبدیل ارز دیجیتال ({amount} {symbol} {name} {toman} {usd} {usdt} {ton} {trx} {stars} {price_usd} {price_toman} {change} {time})",
    ),
    "conv_toman": (
        "🇮🇷 <b>{amount} تومان</b>\n"
        f"{SEP}\n"
        "<blockquote>"
        "🟢 تتر: <code>{usdt}</code> USDT\n"
        "💎 تون: <code>{ton}</code> TON\n"
        "🔺 ترون: <code>{trx}</code> TRX\n"
        "💵 دلار: <code>{usd}</code> $\n"
        "⭐️ استارز: <code>{stars}</code> Stars\n"
        "🔁 ریال: <code>{rial}</code>"
        "</blockquote>\n"
        "💹 نرخ تتر: <code>{usdt_rate}</code> تومان\n"
        f"{SEP}\n"
        "🕰 {time}",
        "تبدیل تومان ({amount} {usdt} {ton} {trx} {usd} {stars} {rial} {usdt_rate} {time})",
    ),
    "conv_stars": (
        "⭐️ <b>{amount} استارز تلگرام</b>\n"
        f"{SEP}\n"
        "<blockquote>"
        "🇮🇷 تومان: <code>{toman}</code>\n"
        "💵 دلار: <code>{usd}</code> $\n"
        "💎 تون: <code>{ton}</code> TON\n"
        "🔺 ترون: <code>{trx}</code> TRX"
        "</blockquote>\n"
        "ℹ️ قیمت هر استار: <code>{star_usd}</code> $\n"
        f"{SEP}\n"
        "🕰 {time}",
        "تبدیل استارز ({amount} {toman} {usd} {ton} {trx} {star_usd} {time})",
    ),
    "conv_fiat": (
        "{flag} <b>{amount} {name}</b> (<code>{code}</code>)\n"
        f"{SEP}\n"
        "<blockquote>"
        "🇮🇷 تومان: <code>{toman}</code>\n"
        "💵 دلار: <code>{usd}</code> $\n"
        "💶 یورو: <code>{eur}</code> €\n"
        "🟢 تتر: <code>{usdt}</code> USDT\n"
        "💎 تون: <code>{ton}</code> TON\n"
        "🔺 ترون: <code>{trx}</code> TRX"
        "</blockquote>\n"
        "📌 هر ۱ {name}: <code>{unit_toman}</code> تومان\n"
        f"{SEP}\n"
        "🕰 {time}",
        "کپشن کارت ارز کشورها ({flag} {amount} {name} {code} {toman} {usd} {eur} {usdt} {ton} {trx} {unit_toman} {time})",
    ),
    "market": (
        "📊 <b>بازار لحظه‌ای</b>\n"
        f"{SEP}\n"
        "<blockquote>{lines}</blockquote>\n"
        "💹 تتر: <code>{usdt_toman}</code> تومان\n"
        "🕰 {time}",
        "تابلوی قیمت /p ({lines} {usdt_toman} {time})",
    ),
    "market_line": ("{icon} <b>{symbol}</b>: <code>{usd}</code> $ | <code>{toman}</code> ت {change}", "هر خط تابلوی قیمت ({icon} {symbol} {usd} {toman} {change})"),
    "currency_not_found": ("❌ ارز «{query}» پیدا نشد.", "ارز پیدا نشد ({query})"),
    "price_unavailable": ("⚠️ دریافت قیمت در حال حاضر ممکن نیست، کمی بعد تلاش کنید.", "قیمت در دسترس نیست"),
    "wallet_checking": ("🔎 در حال بررسی کیف پول روی همه شبکه‌ها...", "در حال چک ولت"),
    "wallet_header": (
        "👛 <b>نتیجه چکر کیف پول</b>\n"
        "📍 <code>{address}</code>\n"
        "🌐 شبکه‌های یافت شده: <b>{count}</b>\n"
        f"{SEP}",
        "سربرگ چکر ولت ({address} {count})",
    ),
    "wallet_chain": (
        "<b>{chain}</b>\n"
        "<blockquote>"
        "💰 موجودی: <code>{balance}</code> {symbol}\n"
        "💵 ارزش: <code>{usd}</code> $ | <code>{toman}</code> ت\n"
        "🪙 توکن‌ها:\n{tokens}\n"
        "🔁 تعداد تراکنش: <code>{txs}</code>\n"
        "🖼 NFT: <code>{nfts}</code>\n"
        "📅 تاریخ ساخت ولت: {created}\n"
        "📌 وضعیت: {status}\n"
        "🔗 <a href=\"{explorer}\">مشاهده در اکسپلورر</a>"
        "</blockquote>",
        "بلوک هر شبکه در چکر ولت ({chain} {balance} {symbol} {usd} {toman} {tokens} {txs} {nfts} {status} {explorer})",
    ),
    "wallet_footer": (
        f"{SEP}\n"
        "💼 <b>مجموع دارایی:</b> <code>{total_usd}</code> $ | <code>{total_toman}</code> تومان\n"
        "🕰 {time}",
        "پاورقی چکر ولت ({total_usd} {total_toman} {time})",
    ),
    "wallet_not_found": (
        "❌ هیچ دارایی یا فعالیتی برای این آدرس روی شبکه‌های پشتیبانی شده پیدا نشد.\n📍 <code>{address}</code>",
        "ولت پیدا نشد ({address})",
    ),
    "tx_checking": ("🔎 در حال جستجوی تراکنش روی همه شبکه‌ها...", "در حال چک هش"),
    "tx_result": (
        "🔗 <b>نتیجه چکر هش تراکنش</b>\n"
        f"{SEP}\n"
        "<blockquote>"
        "🌐 شبکه: <b>{network}</b>\n"
        "📌 وضعیت: {status}\n"
        "💸 مقدار: <code>{amount}</code>\n"
        "💵 ارزش: <code>{usd}</code> $ | <code>{toman}</code> ت\n"
        "🪙 توکن: {token}\n"
        "📜 کانترکت / آیدی توکن: <code>{contract}</code>\n"
        "📤 از: <code>{sender}</code>\n"
        "📥 به: <code>{receiver}</code>\n"
        "⛽️ کارمزد: <code>{fee}</code>\n"
        "🧱 بلاک: <code>{block}</code>\n"
        "✅ تاییدها: <code>{confirmations}</code>\n"
        "🕰 زمان: {time}"
        "</blockquote>\n"
        "🔑 <code>{hash}</code>\n"
        "🔍 <a href=\"{explorer}\">مشاهده در اکسپلورر</a>",
        "نتیجه چک هش ({network} {status} {amount} {usd} {toman} {sender} {receiver} {fee} {block} {confirmations} {time} {hash} {explorer})",
    ),
    "tx_not_found": (
        "❌ این هش روی هیچ‌کدام از شبکه‌های پشتیبانی شده پیدا نشد.\n🔑 <code>{hash}</code>",
        "هش پیدا نشد ({hash})",
    ),
    "tron_transfers": (
        "📜 <b>تراکنش‌های TRC20</b>\n"
        "📍 <code>{address}</code>\n"
        "📄 صفحه {page} از {pages} | کل: {total}\n"
        f"{SEP}\n"
        "{items}",
        "لیست تراکنش‌های TRC20 ({address} {page} {pages} {total} {items})",
    ),
    "tron_transfer_item": (
        "{direction} <b>{amount} {symbol}</b> • {time}\n"
        "   👤 <code>{party}</code>\n"
        "   🔗 <a href=\"https://tronscan.org/#/transaction/{hash}\">مشاهده تراکنش</a>",
        "هر تراکنش در لیست TRC20 ({direction} {amount} {symbol} {time} {party} {hash})",
    ),
    "tron_transfers_empty": ("📭 تراکنش TRC20 برای این ولت ثبت نشده است.", "ولت بدون تراکنش TRC20"),
    "tron_tokens_file": ("📄 لیست کامل {total} توکن ولت\n<code>{address}</code>", "کپشن فایل توکن‌ها ({total} {address})"),
    "not_your_button": ("❌ شما دسترسی ندارید", "دکمه مال کاربر دیگر (پاپ‌آپ)"),
    "expired": ("⌛️ این دکمه منقضی شده است، دوباره درخواست دهید.", "دکمه منقضی (پاپ‌آپ)"),
    "card_result": (
        "💳 <b>استعلام کارت بانکی</b>\n"
        f"{SEP}\n"
        "<blockquote>"
        "🔢 شماره کارت: <code>{card}</code>\n"
        "🏦 بانک: <b>{bank}</b>\n"
        "👤 صاحب کارت: <b>{owner}</b>\n"
        "🧾 شبا: <code>{iban}</code>\n"
        "🔐 اعتبارسنجی: {valid}\n"
        "{extra}"
        "</blockquote>",
        "نتیجه استعلام کارت ({card} {bank} {owner} {iban} {valid} {extra})",
    ),
    "iban_result": (
        "🧾 <b>استعلام شماره شبا</b>\n"
        f"{SEP}\n"
        "<blockquote>"
        "🔢 شبا: <code>{iban}</code>\n"
        "🏦 بانک: <b>{bank}</b>\n"
        "🔐 اعتبارسنجی: {valid}"
        "</blockquote>",
        "نتیجه استعلام شبا ({iban} {bank} {valid})",
    ),
    "phone_result": (
        "📱 <b>چکر شماره تلگرام</b>\n"
        f"{SEP}\n"
        "<blockquote>"
        "☎️ شماره: <code>{phone}</code>\n"
        "{status} {description}"
        "</blockquote>",
        "نتیجه چکر شماره ({phone} {status} {description})",
    ),
    "phone_invalid": ("⚠️ شماره را به شکل بین‌المللی بفرستید، مثلا: <code>/phone +15005550006</code>", "راهنمای چکر شماره"),
    "phone_failed": ("🔵 مشکل از سمت سرور است، کمی بعد دوباره تلاش کنید.", "خطای چکر شماره"),
    "arz_board": (
        "🏦 <b>نرخ ارز بازار آزاد</b>\n"
        f"{SEP}\n"
        "<blockquote>{lines}</blockquote>\n"
        "📡 منبع: {source}\n"
        "🕰 {time}",
        "تابلوی نرخ ارز /arz ({lines} {source} {time})",
    ),
    "arz_line": ("{flag} {name}: <code>{toman}</code> ت {change}", "هر خط تابلوی ارز ({flag} {name} {toman} {change})"),
    "insta_loading": ("🔍 در حال دریافت اطلاعات از اینستاگرام...", "در حال دریافت اینستاگرام"),
    "insta_count": ("🔱 لطفا صبر کنید\n🗂 تعداد عکس و فیلم‌ها: {count}", "تعداد مدیا ({count})"),
    "insta_media_caption": ("({index}/{count})\n🤖 ارسال شده توسط: @{bot}", "کپشن هر مدیا ({index} {count} {bot})"),
    "insta_not_found": ("♻️ اطلاعاتی پیدا نشد.", "اینستاگرام پیدا نشد"),
    "insta_private": ("🔒 پیج مورد نظر شخصی است.", "پیج خصوصی"),
    "insta_no_story": ("♻️ پیج مورد نظر استوری ندارد.", "بدون استوری"),
    "insta_no_highlight": ("♻️ پیج مورد نظر هایلایت ندارد.", "بدون هایلایت"),
    "insta_no_key": ("⚠️ سرویس اینستاگرام هنوز توسط مدیر فعال نشده است.", "کلید اینستاگرام تنظیم نشده"),
    "insta_highlights": ("♻️ هایلایت‌های <b>{username}</b>", "لیست هایلایت‌ها ({username})"),
    "insta_info": (
        "📸 <b>اطلاعات پیج اینستاگرام</b>\n"
        f"{SEP}\n"
        "<blockquote>"
        "🆔 آیدی: <code>{id}</code>\n"
        "🖊 نام: <b>{name}</b>\n"
        "✉️ یوزرنیم: <code>{username}</code>\n"
        "👥 فالوور: <code>{followers}</code>\n"
        "👣 فالووینگ: <code>{following}</code>\n"
        "📝 پست: <code>{posts}</code>\n"
        "🔵 تیک آبی: {verified}\n"
        "🔒 خصوصی: {private}"
        "</blockquote>\n"
        "🔖 بیو:\n<blockquote expandable>{bio}</blockquote>",
        "اطلاعات پیج اینستاگرام ({id} {name} {username} {followers} {following} {posts} {verified} {private} {bio})",
    ),
    "bot_off": ("💤 ربات در حال حاضر خاموش است.", "ربات خاموش"),
    "banned": ("⛔️ شما از ربات مسدود شده‌اید.", "کاربر مسدود"),
    "bot_promoted": (
        "💎 <b>ربات در این گروه ادمین شد!</b>\n"
        f"{SEP}\n"
        "🔗 برای استعلام هش کافیه هش یا لینک تراکنش رو بفرستی\n"
        "👛 برای استعلام ولت: <code>wallet آدرس</code>\n"
        "💱 برای قیمت: <code>10 تتر</code>\n"
        "⚙️ تنظیمات گروه: /settings",
        "پیام ادمین شدن ربات در گروه",
    ),
    "admin_message": ("👨‍💻 <b>پیام از طرف مدیریت</b>\n\n{text}", "پیام مدیر به کاربر ({text})"),
    "gift_link": (
        "🎁 <b>{name} #{number}</b>\n"
        f"{SEP}\n"
        "<blockquote>"
        "🧸 مدل: <b>{model}</b> {model_rarity}\n"
        "🎨 بک‌دراپ: <b>{backdrop}</b> {backdrop_rarity}\n"
        "🔣 سیمبل: <b>{symbol}</b> {symbol_rarity}\n"
        "👤 مالک: {owner}\n"
        "📦 تعداد: {quantity}"
        "</blockquote>\n"
        "<blockquote>"
        "📉 کف کالکشن: <code>{floor_ton}</code> TON\n"
        "🧸 کف مدل: <code>{model_floor}</code> TON\n"
        "🎨 کف بک‌دراپ: <code>{backdrop_floor}</code> TON\n"
        "🔣 کف سیمبل: <code>{symbol_floor}</code> TON"
        "</blockquote>\n"
        "💰 <b>ارزش تخمینی:</b>\n"
        "💎 <code>{est_ton}</code> TON\n"
        "💵 <code>{est_usd}</code> $\n"
        "🇮🇷 <code>{est_toman}</code> تومان\n"
        "⭐️ <code>{est_stars}</code> Stars\n"
        f"{SEP}\n"
        "🔗 {link}",
        "قیمت لینک گیفت ({name} {number} {model} {model_rarity} {backdrop} {backdrop_rarity} {symbol} {symbol_rarity} {owner} {quantity} {floor_ton} {model_floor} {backdrop_floor} {symbol_floor} {est_ton} {est_usd} {est_toman} {est_stars} {link})",
    ),
    "gift_user_caption": (
        "🎁 <b>کلکسیون گیفت {name}</b>\n"
        "📦 تعداد: <b>{count}</b> (یونیک: {unique_count})\n"
        "💎 ارزش کل: <code>{total_ton}</code> TON\n"
        "💵 <code>{total_usd}</code> $ | 🇮🇷 <code>{total_toman}</code> ت\n"
        "{pl_line}\n"
        "{change_line}\n"
        f"{SEP}\n"
        "{links}",
        "کپشن کارت گیفت‌های کاربر ({name} {count} {unique_count} {total_ton} {total_usd} {total_toman} {pl_line} {change_line} {links})",
    ),
    "gift_profit": ("📈 سود ۷ روز اخیر بازار: <b>+{ton} TON</b> (+{percent}%) ≈ {toman} ت", "خط سود ({ton} {percent} {toman})"),
    "gift_loss": ("📉 ضرر ۷ روز اخیر بازار: <b>-{ton} TON</b> (-{percent}%) ≈ {toman} ت", "خط ضرر ({ton} {percent} {toman})"),
    "gift_since_last": ("🔁 نسبت به چک قبلی: <b>{sign}{ton} TON</b> ({sign}{percent}%)", "تغییر نسبت به چک قبلی"),
    "gift_user_none": ("📭 هیچ گیفت عمومی برای {name} پیدا نشد.", "کاربر گیفت ندارد ({name})"),
    "gift_user_unknown": (
        "❌ نتوانستم این کاربر را پیدا کنم.\n"
        "ℹ️ کاربر باید حداقل یک بار در گروهی که ربات هست پیام داده باشد یا ربات را استارت کرده باشد.\n"
        "💡 می‌توانید روی پیامش ریپلای کنید و <code>/gift</code> بفرستید.",
        "کاربر پیدا نشد",
    ),
    "gift_not_found": ("❌ اطلاعات این گیفت پیدا نشد.", "گیفت پیدا نشد"),
    "self_warn": (
        "⚠️ {mention}\n"
        "🤖 <b>سلف (کنترل خودکار) روی اکانت شما شناسایی شد!</b>\n"
        "لطفا سلف خود را <b>خاموش</b> کنید، در غیر این صورت <b>سکوت</b> می‌شوید. ⛔️",
        "اخطار سلف ({mention})",
    ),
    "self_muted": (
        "🔇 {mention} به دلیل روشن بودن <b>سلف</b> سکوت شد.\n⏳ مدت: {duration}",
        "سکوت سلف ({mention} {duration})",
    ),
    "tabchi_muted": (
        "🚫 {mention} به عنوان <b>تبچی / ربات تبلیغاتی</b> شناسایی و سکوت شد.\n⏳ مدت: {duration}",
        "سکوت تبچی ({mention} {duration})",
    ),
    "unmuted": ("🔊 {mention} از سکوت خارج شد.", "رفع سکوت ({mention})"),
    "scam_keyword_warn": (
        "🚨 {mention}\n"
        "⚠️ <b>هشدار امنیتی:</b> معامله در پیوی و بدون واسطه معتبر، ریسک کلاهبرداری بالایی دارد!\n"
        "🛡 هرگز کد، رمز، عبارت بازیابی (Seed) یا کلید خصوصی خود را برای کسی نفرستید.",
        "هشدار رفتار مشکوک ({mention})",
    ),
    "scam_known": (
        "⛔️ <b>هشدار!</b> {mention} در <b>لیست کلاهبرداران</b> ثبت شده است.\n"
        "📝 علت: {reason}\n"
        "⚠️ از معامله با این شخص خودداری کنید.",
        "هشدار حضور کلاهبردار ({mention} {reason})",
    ),
    "scam_usage": (
        "ℹ️ نحوه گزارش: <code>/scam @username</code>\nیا روی پیام کلاهبردار ریپلای کنید و <code>/scam</code> بفرستید.",
        "راهنمای /scam",
    ),
    "scam_go_private": (
        "🚨 گزارش کلاهبرداری علیه {target} ثبت اولیه شد.\n"
        "📩 لطفا برای ارسال مدارک روی دکمه زیر بزنید و در پیوی ربات مدارک را بفرستید.",
        "انتقال گزارش به پیوی ({target})",
    ),
    "scam_ask_evidence": (
        "🚨 <b>گزارش کلاهبرداری علیه {target}</b>\n"
        f"{SEP}\n"
        "📎 لطفا <b>مدارک</b> خود را ارسال کنید (اسکرین‌شات، فیلم، فایل، متن توضیحات، هش تراکنش و ...).\n"
        "✅ پس از ارسال همه مدارک روی «ثبت و ارسال» بزنید.",
        "درخواست مدرک ({target})",
    ),
    "scam_evidence_saved": ("✅ مدرک #{count} دریافت شد. مدرک بعدی را بفرستید یا «ثبت و ارسال» را بزنید.", "دریافت مدرک ({count})"),
    "scam_need_evidence": ("⚠️ حداقل یک مدرک ارسال کنید.", "عدم ارسال مدرک (پاپ‌آپ)"),
    "scam_submitted": (
        "📨 گزارش شما با شماره <b>#{id}</b> برای مدیریت ارسال شد.\nپس از بررسی نتیجه به شما اطلاع داده می‌شود.",
        "گزارش ارسال شد ({id})",
    ),
    "scam_cancelled": ("❌ گزارش لغو شد.", "لغو گزارش"),
    "scam_admin": (
        "🚨 <b>گزارش کلاهبرداری جدید #{id}</b>\n"
        f"{SEP}\n"
        "🎯 متهم: {target}\n"
        "🆔 آیدی عددی: <code>{target_id}</code>\n"
        "👤 گزارش‌دهنده: {reporter}\n"
        "📎 تعداد مدارک: {count}\n"
        "📝 توضیحات: {reason}",
        "پیام گزارش برای مدیر ({id} {target} {target_id} {reporter} {count} {reason})",
    ),
    "scam_approved_user": ("✅ گزارش #{id} شما تایید شد و در گروه‌ها اعلام شد. ممنون از همکاری شما 🙏", "تایید گزارش (به گزارش‌دهنده)"),
    "scam_rejected_user": ("❌ گزارش #{id} شما توسط مدیریت رد شد.", "رد گزارش (به گزارش‌دهنده)"),
    "scam_broadcast": (
        "🚨🚨 <b>اعلام کلاهبردار</b> 🚨🚨\n"
        f"{SEP}\n"
        "<blockquote>"
        "🎯 کاربر: {target}\n"
        "🆔 آیدی عددی: <code>{target_id}</code>\n"
        "📝 علت: {reason}\n"
        "🔖 شماره پرونده: #{id}"
        "</blockquote>\n"
        "⛔️ لطفا با این شخص معامله نکنید تا به دام نیفتید!",
        "اعلام عمومی کلاهبردار ({target} {target_id} {reason} {id})",
    ),
    "scam_check_clean": ("✅ {target} در لیست کلاهبرداران نیست.\n⚠️ با این حال همیشه با واسطه معتبر معامله کنید.", "استعلام سالم ({target})"),
    "scam_check_found": (
        "⛔️ {target} در <b>لیست کلاهبرداران</b> است!\n📝 علت: {reason}\n🔖 پرونده: #{id}",
        "استعلام کلاهبردار ({target} {reason} {id})",
    ),
    "uid": (
        "🆔 <b>اطلاعات کاربر</b>\n"
        "<blockquote>"
        "👤 نام: {name}\n"
        "🔢 آیدی عددی: <code>{id}</code>\n"
        "🔗 یوزرنیم: {username}"
        "</blockquote>",
        "نمایش آیدی عددی ({name} {id} {username})",
    ),
    "emoji_header": ("😎 <b>کد ایموجی‌های پریمیوم</b> ({count} عدد)\n" f"{SEP}", "سربرگ /emoji ({count})"),
    "emoji_item": (
        "<blockquote>"
        "{emoji} ایموجی: {emoji}\n"
        "🆔 کد: <code>{id}</code>\n"
        "📦 پک: {pack}\n"
        "🧩 HTML: <code>{html}</code>"
        "</blockquote>",
        "هر ایموجی در /emoji ({emoji} {id} {pack} {html})",
    ),
    "emoji_none": (
        "❌ هیچ ایموجی پریمیومی پیدا نشد.\nℹ️ <code>/emoji</code> را همراه ایموجی پریمیوم بفرستید یا روی پیام دارای ایموجی پریمیوم ریپلای کنید.",
        "ایموجی پیدا نشد",
    ),
    "translate_choose": ("🌐 لطفاً نوع زبان خود را انتخاب کنید", "انتخاب زبان ترجمه"),
    "translate_result": (
        "🌐 <b>ترجمه به {lang}</b>\n"
        f"{SEP}\n"
        "<blockquote expandable>{text}</blockquote>",
        "نتیجه ترجمه ({lang} {text})",
    ),
    "translate_not_yours": ("⛔️ این درخواست ترجمه مربوط به شما نیست.", "ترجمه مال کاربر دیگر (پاپ‌آپ)"),
    "translate_used": ("✅ این پیام قبلا ترجمه شده است.", "ترجمه تکراری (پاپ‌آپ)"),
    "translate_need_reply": ("ℹ️ برای ترجمه روی یک پیام متنی ریپلای کنید و بنویسید <code>ترجمه</code>.", "راهنمای ترجمه"),
    "translate_failed": ("⚠️ ترجمه ناموفق بود، دوباره تلاش کنید.", "خطای ترجمه"),
    "calc_result": ("🧮 <code>{expr}</code>\n🟰 <b><code>{result}</code></b>", "نتیجه ماشین حساب ({expr} {result})"),
    "account": (
        "👤 <b>اطلاعات حساب کاربری</b>\n"
        f"{SEP}\n"
        "<blockquote>"
        "📛 نام: {name}\n"
        "🔢 آیدی عددی: <code>{id}</code>\n"
        "🔗 یوزرنیم: {username}\n"
        "⭐️ پریمیوم: {premium}\n"
        "🏅 لول اکانت تلگرام: {level}\n"
        "📅 تاریخ ساخت (تخمینی): {created}\n"
        "🌐 دیتاسنتر: {dc}\n"
        "🗣 زبان: {lang}\n"
        "🎂 تولد: {birthdate}\n"
        "📝 بیو: {bio}\n"
        "🎁 تعداد گیفت: {gifts}"
        "</blockquote>\n"
        "<blockquote>"
        "📊 لول در ربات: <b>{bot_level}</b> ({xp} XP)\n"
        "💬 پیام‌ها: <code>{msgs}</code>\n"
        "💰 موجودی: <code>{balance}</code>\n"
        "📆 عضویت در ربات: {joined}"
        "</blockquote>",
        "اطلاعات اکانت ({name} {id} {username} {premium} {level} {created} {dc} {lang} {birthdate} {bio} {gifts} {bot_level} {xp} {msgs} {balance} {joined})",
    ),
    "whale_alert": (
        "🐋 <b>هشدار جابجایی سنگین!</b> {bar}\n"
        f"{SEP}\n"
        "<blockquote>"
        "💸 مقدار: <b>{amount} {symbol}</b>\n"
        "💵 ارزش: <code>{usd}</code> $\n"
        "🇮🇷 معادل: <code>{toman}</code> تومان\n"
        "🌐 شبکه: <b>{network}</b>\n"
        "📤 از: {sender}\n"
        "📥 به: {receiver}"
        "</blockquote>\n"
        "🔍 <a href=\"{link}\">مشاهده تراکنش</a> | 🕰 {time}",
        "هشدار جابجایی ({bar} {amount} {symbol} {usd} {toman} {network} {sender} {receiver} {link} {time})",
    ),
    "group_settings": (
        "⚙️ <b>تنظیمات گروه</b> {title}\n"
        "روی هر گزینه بزنید تا روشن/خاموش شود:",
        "تنظیمات گروه ({title})",
    ),
    "only_group_admins": ("⛔️ فقط ادمین‌های گروه می‌توانند این کار را انجام دهند.", "فقط ادمین (پاپ‌آپ)"),
}

BUTTONS: dict[str, tuple[str, str | None, str]] = {
    "btn_add_group": ("➕ افزودن به گروه", "primary", "دکمه افزودن به گروه"),
    "btn_channel": ("📢 کانال ربات", "success", "دکمه کانال ربات"),
    "btn_join": ("📢 عضویت در {title}", "primary", "دکمه هر کانال عضویت اجباری ({title})"),
    "btn_check_join": ("✅ عضو شدم", "success", "دکمه بررسی عضویت"),
    "btn_help": ("📚 راهنما", None, "دکمه راهنما"),
    "lang_fa": ("🇮🇷 فارسی", None, "زبان فارسی"),
    "lang_en": ("🇬🇧 انگلیسی", None, "زبان انگلیسی"),
    "lang_ru": ("🇷🇺 روسی", None, "زبان روسی"),
    "lang_zh": ("🇨🇳 چینی", None, "زبان چینی"),
    "lang_ar": ("🇸🇦 عربی", None, "زبان عربی"),
    "btn_scam_private": ("📩 ارسال مدارک", "danger", "دکمه انتقال به پیوی برای گزارش"),
    "btn_scam_submit": ("✅ ثبت و ارسال", "success", "دکمه ثبت گزارش"),
    "btn_scam_cancel": ("❌ لغو", "danger", "دکمه لغو گزارش"),
    "btn_approve": ("✅ تایید و اعلام", "success", "دکمه تایید مدیر"),
    "btn_reject": ("❌ رد گزارش", "danger", "دکمه رد مدیر"),
    "btn_copy_id": ("📋 کپی آیدی", "primary", "دکمه کپی آیدی"),
    "btn_unmute": ("🔊 رفع سکوت", "success", "دکمه رفع سکوت (ادمین)"),
    "btn_explorer": ("🔍 اکسپلورر", None, "دکمه اکسپلورر"),
    "btn_sender_wallet": ("📤 اطلاعات ولت مبدا", "primary", "دکمه ولت مبدا زیر هش"),
    "btn_receiver_wallet": ("📥 اطلاعات ولت مقصد", "primary", "دکمه ولت مقصد زیر هش"),
    "btn_trc20_history": ("📜 تراکنش‌های TRC20", None, "دکمه تاریخچه TRC20"),
    "btn_tokens_file": ("📄 دریافت کل موجودی‌های ولت", None, "دکمه فایل توکن‌ها"),
    "btn_prev": ("◀️ قبلی", None, "دکمه صفحه قبل"),
    "btn_next": ("بعدی ▶️", None, "دکمه صفحه بعد"),
    "btn_insta_story": ("🎥 دانلود استوری 📸", "primary", "دکمه استوری اینستاگرام"),
    "btn_insta_highlight": ("💫 دانلود هایلایت", "primary", "دکمه هایلایت اینستاگرام"),
    "btn_gift_open": ("🎁 مشاهده گیفت", "primary", "دکمه مشاهده گیفت"),
}
