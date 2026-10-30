from __future__ import annotations

import re

FIAT: dict[str, tuple[str, str, str | None, str, tuple[str, ...]]] = {
    "USD": ("دلار آمریکا", "US Dollar", "us", "$", ("دلار", "دلار امریکا", "دلار آمریکایی", "dollar", "dollars", "usd", "$")),
    "EUR": ("یورو", "Euro", "eu", "€", ("یورو", "اورو", "euro", "euros", "€")),
    "GBP": ("پوند انگلیس", "British Pound", "gb", "£", ("پوند", "پوند انگلیس", "پوند انگلستان", "استرلینگ", "pound", "pounds", "£")),
    "AED": ("درهم امارات", "UAE Dirham", "ae", "د.إ", ("درهم", "درهم امارات", "درهم اماراتی", "دیرهم", "dirham", "dirhams")),
    "TRY": ("لیر ترکیه", "Turkish Lira", "tr", "₺", ("لیر", "لیره", "لیر ترکیه", "لیره ترکیه", "lira", "₺")),
    "RUB": ("روبل روسیه", "Russian Ruble", "ru", "₽", ("روبل", "روبل روسیه", "ruble", "rouble", "₽")),
    "CNY": ("یوان چین", "Chinese Yuan", "cn", "¥", ("یوان", "یوان چین", "رنمینبی", "yuan", "rmb")),
    "JPY": ("ین ژاپن", "Japanese Yen", "jp", "¥", ("ین", "ین ژاپن", "yen", "¥")),
    "SAR": ("ریال عربستان", "Saudi Riyal", "sa", "﷼", ("ریال عربستان", "ریال سعودی", "ریال عربستان سعودی", "riyal", "saudi riyal")),
    "IQD": ("دینار عراق", "Iraqi Dinar", "iq", "ع.د", ("دینار", "دینار عراق", "دینار عراقی", "dinar")),
    "AFN": ("افغانی", "Afghan Afghani", "af", "؋", ("افغانی", "افغانی افغانستان", "afghani")),
    "KWD": ("دینار کویت", "Kuwaiti Dinar", "kw", "د.ك", ("دینار کویت", "دینار کویتی")),
    "QAR": ("ریال قطر", "Qatari Riyal", "qa", "ر.ق", ("ریال قطر", "ریال قطری")),
    "OMR": ("ریال عمان", "Omani Rial", "om", "ر.ع", ("ریال عمان", "ریال عمانی")),
    "BHD": ("دینار بحرین", "Bahraini Dinar", "bh", ".د.ب", ("دینار بحرین", "دینار بحرینی")),
    "JOD": ("دینار اردن", "Jordanian Dinar", "jo", "د.ا", ("دینار اردن", "دینار اردنی")),
    "CAD": ("دلار کانادا", "Canadian Dollar", "ca", "C$", ("دلار کانادا", "دلار کانادایی")),
    "AUD": ("دلار استرالیا", "Australian Dollar", "au", "A$", ("دلار استرالیا", "دلار استرالیایی")),
    "NZD": ("دلار نیوزیلند", "New Zealand Dollar", "nz", "NZ$", ("دلار نیوزیلند",)),
    "CHF": ("فرانک سوئیس", "Swiss Franc", "ch", "CHF", ("فرانک", "فرانک سوئیس", "franc")),
    "SEK": ("کرون سوئد", "Swedish Krona", "se", "kr", ("کرون", "کرون سوئد", "krona")),
    "NOK": ("کرون نروژ", "Norwegian Krone", "no", "kr", ("کرون نروژ", "krone")),
    "DKK": ("کرون دانمارک", "Danish Krone", "dk", "kr", ("کرون دانمارک",)),
    "ISK": ("کرون ایسلند", "Icelandic Krona", "is", "kr", ("کرون ایسلند",)),
    "CZK": ("کرون چک", "Czech Koruna", "cz", "Kč", ("کرون چک",)),
    "PLN": ("زلوتی لهستان", "Polish Zloty", "pl", "zł", ("زلوتی", "زلوتی لهستان", "zloty")),
    "HUF": ("فورینت مجارستان", "Hungarian Forint", "hu", "Ft", ("فورینت", "forint")),
    "RON": ("لئو رومانی", "Romanian Leu", "ro", "lei", ("لئو رومانی",)),
    "BGN": ("لو بلغارستان", "Bulgarian Lev", "bg", "лв", ("لو بلغارستان",)),
    "RSD": ("دینار صربستان", "Serbian Dinar", "rs", "дин", ("دینار صربستان",)),
    "UAH": ("گریونا اوکراین", "Ukrainian Hryvnia", "ua", "₴", ("گریونا", "hryvnia")),
    "BYN": ("روبل بلاروس", "Belarusian Ruble", "by", "Br", ("روبل بلاروس",)),
    "MDL": ("لئو مولداوی", "Moldovan Leu", "md", "L", ("لئو مولداوی",)),
    "ALL": ("لک آلبانی", "Albanian Lek", "al", "L", ("لک آلبانی",)),
    "MKD": ("دینار مقدونیه", "Macedonian Denar", "mk", "ден", ("دینار مقدونیه",)),
    "BAM": ("مارک بوسنی", "Bosnia Mark", "ba", "KM", ("مارک بوسنی",)),
    "GEL": ("لاری گرجستان", "Georgian Lari", "ge", "₾", ("لاری", "لاری گرجستان", "lari")),
    "AMD": ("درام ارمنستان", "Armenian Dram", "am", "֏", ("درام", "درام ارمنستان", "dram")),
    "AZN": ("منات آذربایجان", "Azerbaijani Manat", "az", "₼", ("منات", "منات آذربایجان", "manat")),
    "TMT": ("منات ترکمنستان", "Turkmen Manat", "tm", "m", ("منات ترکمنستان",)),
    "KZT": ("تنگه قزاقستان", "Kazakh Tenge", "kz", "₸", ("تنگه", "تنگه قزاقستان", "tenge")),
    "UZS": ("سوم ازبکستان", "Uzbek Som", "uz", "soʻm", ("سوم ازبکستان", "سوم")),
    "KGS": ("سوم قرقیزستان", "Kyrgyz Som", "kg", "с", ("سوم قرقیزستان",)),
    "TJS": ("سامانی تاجیکستان", "Tajik Somoni", "tj", "SM", ("سامانی", "سامانی تاجیکستان", "somoni")),
    "PKR": ("روپیه پاکستان", "Pakistani Rupee", "pk", "₨", ("روپیه پاکستان", "روپیه پاکستانی")),
    "INR": ("روپیه هند", "Indian Rupee", "in", "₹", ("روپیه", "روپیه هند", "روپیه هندوستان", "rupee", "₹")),
    "LKR": ("روپیه سریلانکا", "Sri Lankan Rupee", "lk", "Rs", ("روپیه سریلانکا",)),
    "NPR": ("روپیه نپال", "Nepalese Rupee", "np", "Rs", ("روپیه نپال",)),
    "BDT": ("تاکای بنگلادش", "Bangladeshi Taka", "bd", "৳", ("تاکا", "taka")),
    "MVR": ("روفیا مالدیو", "Maldivian Rufiyaa", "mv", "Rf", ("روفیا",)),
    "BTN": ("انگولتروم بوتان", "Bhutanese Ngultrum", "bt", "Nu", ("انگولتروم",)),
    "IDR": ("روپیه اندونزی", "Indonesian Rupiah", "id", "Rp", ("روپیه اندونزی", "rupiah")),
    "MYR": ("رینگیت مالزی", "Malaysian Ringgit", "my", "RM", ("رینگیت", "رینگیت مالزی", "ringgit")),
    "SGD": ("دلار سنگاپور", "Singapore Dollar", "sg", "S$", ("دلار سنگاپور",)),
    "THB": ("بات تایلند", "Thai Baht", "th", "฿", ("بات", "بات تایلند", "baht")),
    "VND": ("دونگ ویتنام", "Vietnamese Dong", "vn", "₫", ("دونگ", "dong")),
    "PHP": ("پزو فیلیپین", "Philippine Peso", "ph", "₱", ("پزو فیلیپین",)),
    "KHR": ("ریل کامبوج", "Cambodian Riel", "kh", "៛", ("ریل کامبوج",)),
    "LAK": ("کیپ لائوس", "Lao Kip", "la", "₭", ("کیپ لائوس",)),
    "MMK": ("کیات میانمار", "Myanmar Kyat", "mm", "K", ("کیات",)),
    "BND": ("دلار برونئی", "Brunei Dollar", "bn", "B$", ("دلار برونئی",)),
    "KRW": ("وون کره جنوبی", "South Korean Won", "kr", "₩", ("وون", "وون کره", "won", "₩")),
    "KPW": ("وون کره شمالی", "North Korean Won", "kp", "₩", ("وون کره شمالی",)),
    "HKD": ("دلار هنگ کنگ", "Hong Kong Dollar", "hk", "HK$", ("دلار هنگ کنگ",)),
    "TWD": ("دلار تایوان", "Taiwan Dollar", "tw", "NT$", ("دلار تایوان",)),
    "MOP": ("پاتاکا ماکائو", "Macanese Pataca", "mo", "MOP$", ("پاتاکا",)),
    "MNT": ("توگریک مغولستان", "Mongolian Tugrik", "mn", "₮", ("توگریک",)),
    "ILS": ("شکل", "Israeli Shekel", "il", "₪", ("شکل", "shekel")),
    "LBP": ("لیره لبنان", "Lebanese Pound", "lb", "ل.ل", ("لیره لبنان", "لیر لبنان")),
    "SYP": ("لیره سوریه", "Syrian Pound", "sy", "ل.س", ("لیره سوریه", "لیر سوریه")),
    "YER": ("ریال یمن", "Yemeni Rial", "ye", "﷼", ("ریال یمن",)),
    "EGP": ("پوند مصر", "Egyptian Pound", "eg", "E£", ("پوند مصر", "جنیه مصر", "جنیه")),
    "SDG": ("پوند سودان", "Sudanese Pound", "sd", "ج.س", ("پوند سودان",)),
    "SSP": ("پوند سودان جنوبی", "South Sudanese Pound", "ss", "£", ("پوند سودان جنوبی",)),
    "LYD": ("دینار لیبی", "Libyan Dinar", "ly", "ل.د", ("دینار لیبی",)),
    "TND": ("دینار تونس", "Tunisian Dinar", "tn", "د.ت", ("دینار تونس",)),
    "DZD": ("دینار الجزایر", "Algerian Dinar", "dz", "د.ج", ("دینار الجزایر",)),
    "MAD": ("درهم مراکش", "Moroccan Dirham", "ma", "د.م", ("درهم مراکش",)),
    "MRU": ("اوگوئیا موریتانی", "Mauritanian Ouguiya", "mr", "UM", ("اوگوئیا",)),
    "ZAR": ("رند آفریقای جنوبی", "South African Rand", "za", "R", ("رند", "rand")),
    "NGN": ("نایرا نیجریه", "Nigerian Naira", "ng", "₦", ("نایرا", "naira")),
    "GHS": ("سدی غنا", "Ghanaian Cedi", "gh", "₵", ("سدی",)),
    "KES": ("شیلینگ کنیا", "Kenyan Shilling", "ke", "KSh", ("شیلینگ کنیا", "شیلینگ")),
    "TZS": ("شیلینگ تانزانیا", "Tanzanian Shilling", "tz", "TSh", ("شیلینگ تانزانیا",)),
    "UGX": ("شیلینگ اوگاندا", "Ugandan Shilling", "ug", "USh", ("شیلینگ اوگاندا",)),
    "SOS": ("شیلینگ سومالی", "Somali Shilling", "so", "Sh", ("شیلینگ سومالی",)),
    "ETB": ("بیر اتیوپی", "Ethiopian Birr", "et", "Br", ("بیر اتیوپی",)),
    "ERN": ("ناکفا اریتره", "Eritrean Nakfa", "er", "Nfk", ("ناکفا",)),
    "DJF": ("فرانک جیبوتی", "Djiboutian Franc", "dj", "Fdj", ("فرانک جیبوتی",)),
    "RWF": ("فرانک رواندا", "Rwandan Franc", "rw", "FRw", ("فرانک رواندا",)),
    "BIF": ("فرانک بوروندی", "Burundian Franc", "bi", "FBu", ("فرانک بوروندی",)),
    "CDF": ("فرانک کنگو", "Congolese Franc", "cd", "FC", ("فرانک کنگو",)),
    "GNF": ("فرانک گینه", "Guinean Franc", "gn", "FG", ("فرانک گینه",)),
    "KMF": ("فرانک کومور", "Comorian Franc", "km", "CF", ("فرانک کومور",)),
    "XAF": ("فرانک آفریقای مرکزی", "Central African CFA", "cm", "FCFA", ("فرانک آفریقای مرکزی",)),
    "XOF": ("فرانک آفریقای غربی", "West African CFA", "sn", "CFA", ("فرانک آفریقای غربی",)),
    "XPF": ("فرانک اقیانوسیه", "CFP Franc", "pf", "₣", ("فرانک اقیانوسیه",)),
    "AOA": ("کوانزا آنگولا", "Angolan Kwanza", "ao", "Kz", ("کوانزا",)),
    "MZN": ("متیکال موزامبیک", "Mozambican Metical", "mz", "MT", ("متیکال",)),
    "ZMW": ("کواچا زامبیا", "Zambian Kwacha", "zm", "ZK", ("کواچا زامبیا",)),
    "MWK": ("کواچا مالاوی", "Malawian Kwacha", "mw", "MK", ("کواچا مالاوی",)),
    "BWP": ("پولا بوتسوانا", "Botswana Pula", "bw", "P", ("پولا",)),
    "NAD": ("دلار نامیبیا", "Namibian Dollar", "na", "N$", ("دلار نامیبیا",)),
    "LSL": ("لوتی لسوتو", "Lesotho Loti", "ls", "L", ("لوتی",)),
    "SZL": ("لیلانگنی اسواتینی", "Swazi Lilangeni", "sz", "E", ("لیلانگنی",)),
    "MGA": ("آریاری ماداگاسکار", "Malagasy Ariary", "mg", "Ar", ("آریاری",)),
    "MUR": ("روپیه موریس", "Mauritian Rupee", "mu", "₨", ("روپیه موریس",)),
    "SCR": ("روپیه سیشل", "Seychellois Rupee", "sc", "₨", ("روپیه سیشل",)),
    "CVE": ("اسکودو کیپ ورد", "Cape Verdean Escudo", "cv", "Esc", ("اسکودو",)),
    "GMD": ("دالاسی گامبیا", "Gambian Dalasi", "gm", "D", ("دالاسی",)),
    "LRD": ("دلار لیبریا", "Liberian Dollar", "lr", "L$", ("دلار لیبریا",)),
    "SLE": ("لئون سیرالئون", "Sierra Leonean Leone", "sl", "Le", ("لئون",)),
    "STN": ("دوبرا سائوتومه", "São Tomé Dobra", "st", "Db", ("دوبرا",)),
    "MXN": ("پزو مکزیک", "Mexican Peso", "mx", "MX$", ("پزو", "پزو مکزیک", "peso")),
    "BRL": ("رئال برزیل", "Brazilian Real", "br", "R$", ("رئال", "رئال برزیل", "real")),
    "ARS": ("پزو آرژانتین", "Argentine Peso", "ar", "AR$", ("پزو آرژانتین",)),
    "CLP": ("پزو شیلی", "Chilean Peso", "cl", "CL$", ("پزو شیلی",)),
    "COP": ("پزو کلمبیا", "Colombian Peso", "co", "CO$", ("پزو کلمبیا",)),
    "PEN": ("سول پرو", "Peruvian Sol", "pe", "S/", ("سول پرو",)),
    "UYU": ("پزو اروگوئه", "Uruguayan Peso", "uy", "$U", ("پزو اروگوئه",)),
    "PYG": ("گوارانی پاراگوئه", "Paraguayan Guarani", "py", "₲", ("گوارانی",)),
    "BOB": ("بولیویانو", "Bolivian Boliviano", "bo", "Bs", ("بولیویانو",)),
    "VES": ("بولیوار ونزوئلا", "Venezuelan Bolívar", "ve", "Bs.S", ("بولیوار",)),
    "CRC": ("کولون کاستاریکا", "Costa Rican Colón", "cr", "₡", ("کولون",)),
    "GTQ": ("کتزال گواتمالا", "Guatemalan Quetzal", "gt", "Q", ("کتزال",)),
    "HNL": ("لمپیرا هندوراس", "Honduran Lempira", "hn", "L", ("لمپیرا",)),
    "NIO": ("کوردوبا نیکاراگوئه", "Nicaraguan Córdoba", "ni", "C$", ("کوردوبا",)),
    "PAB": ("بالبوا پاناما", "Panamanian Balboa", "pa", "B/.", ("بالبوا",)),
    "DOP": ("پزو دومینیکن", "Dominican Peso", "do", "RD$", ("پزو دومینیکن",)),
    "CUP": ("پزو کوبا", "Cuban Peso", "cu", "₱", ("پزو کوبا",)),
    "HTG": ("گورد هائیتی", "Haitian Gourde", "ht", "G", ("گورد",)),
    "JMD": ("دلار جامائیکا", "Jamaican Dollar", "jm", "J$", ("دلار جامائیکا",)),
    "TTD": ("دلار ترینیداد", "Trinidad Dollar", "tt", "TT$", ("دلار ترینیداد",)),
    "BBD": ("دلار باربادوس", "Barbadian Dollar", "bb", "Bds$", ("دلار باربادوس",)),
    "BSD": ("دلار باهاما", "Bahamian Dollar", "bs", "B$", ("دلار باهاما",)),
    "BZD": ("دلار بلیز", "Belize Dollar", "bz", "BZ$", ("دلار بلیز",)),
    "BMD": ("دلار برمودا", "Bermudian Dollar", "bm", "BD$", ("دلار برمودا",)),
    "KYD": ("دلار جزایر کیمن", "Cayman Dollar", "ky", "CI$", ("دلار کیمن",)),
    "XCD": ("دلار کارائیب شرقی", "East Caribbean Dollar", "ag", "EC$", ("دلار کارائیب",)),
    "AWG": ("فلورین آروبا", "Aruban Florin", "aw", "ƒ", ("فلورین آروبا",)),
    "ANG": ("گیلدر آنتیل", "Antillean Guilder", "cw", "ƒ", ("گیلدر",)),
    "SRD": ("دلار سورینام", "Surinamese Dollar", "sr", "Sr$", ("دلار سورینام",)),
    "GYD": ("دلار گویان", "Guyanese Dollar", "gy", "G$", ("دلار گویان",)),
    "FJD": ("دلار فیجی", "Fijian Dollar", "fj", "FJ$", ("دلار فیجی",)),
    "PGK": ("کینا پاپوآ", "Papua New Guinean Kina", "pg", "K", ("کینا",)),
    "SBD": ("دلار جزایر سلیمان", "Solomon Islands Dollar", "sb", "SI$", ("دلار سلیمان",)),
    "VUV": ("واتو وانواتو", "Vanuatu Vatu", "vu", "VT", ("واتو",)),
    "WST": ("تالا ساموآ", "Samoan Tala", "ws", "WS$", ("تالا",)),
    "TOP": ("پاآنگا تونگا", "Tongan Paʻanga", "to", "T$", ("پاآنگا",)),
    "XAU": ("انس طلا", "Gold Ounce", None, "XAU", ("انس طلا", "اونس طلا", "طلا", "gold")),
    "XAG": ("انس نقره", "Silver Ounce", None, "XAG", ("انس نقره", "اونس نقره", "نقره", "silver")),
}

CRYPTO: dict[str, tuple[str, tuple[str, ...]]] = {
    "USDT": ("تتر", ("تتر", "تدر", "tether", "usdt", "دلار تتر")),
    "TON": ("تون کوین", ("تون", "تن", "تون کوین", "تونکوین", "toncoin", "ton", "گرام")),
    "TRX": ("ترون", ("ترون", "ترانس", "tron", "trx")),
    "BTC": ("بیت کوین", ("بیت کوین", "بیتکوین", "بیت‌کوین", "bitcoin", "btc", "₿")),
    "ETH": ("اتریوم", ("اتریوم", "اتر", "ethereum", "eth", "ether")),
    "BNB": ("بی ان بی", ("بی ان بی", "بی‌ان‌بی", "بایننس کوین", "bnb")),
    "SOL": ("سولانا", ("سولانا", "solana", "sol")),
    "XRP": ("ریپل", ("ریپل", "ripple", "xrp")),
    "DOGE": ("دوج کوین", ("دوج", "دوج کوین", "دوجکوین", "دوگ", "dogecoin", "doge")),
    "SHIB": ("شیبا اینو", ("شیبا", "شیبا اینو", "شیبااینو", "shiba", "shib")),
    "ADA": ("کاردانو", ("کاردانو", "cardano", "ada")),
    "LTC": ("لایت کوین", ("لایت کوین", "لایتکوین", "litecoin", "ltc")),
    "DOT": ("پولکادات", ("پولکادات", "polkadot", "dot")),
    "AVAX": ("آوالانچ", ("آوالانچ", "اوالانچ", "avalanche", "avax")),
    "LINK": ("چین لینک", ("چین لینک", "چینلینک", "chainlink", "link")),
    "POL": ("پالیگان", ("پالیگان", "پلیگان", "ماتیک", "polygon", "matic", "pol")),
    "UNI": ("یونی سواپ", ("یونی سواپ", "یونی‌سواپ", "uniswap", "uni")),
    "XLM": ("استلار", ("استلار", "stellar", "xlm")),
    "XMR": ("مونرو", ("مونرو", "monero", "xmr")),
    "ETC": ("اتریوم کلاسیک", ("اتریوم کلاسیک", "etc")),
    "BCH": ("بیت کوین کش", ("بیت کوین کش", "بیتکوین کش", "bch")),
    "FIL": ("فایل کوین", ("فایل کوین", "فایلکوین", "filecoin", "fil")),
    "APT": ("اپتوس", ("اپتوس", "aptos", "apt")),
    "SUI": ("سویی", ("سویی", "سوئی", "sui")),
    "NOT": ("نات کوین", ("نات کوین", "نات", "notcoin", "not")),
    "HMSTR": ("همستر کامبت", ("همستر", "همستر کامبت", "hamster", "hmstr")),
    "DOGS": ("داگز", ("داگز", "dogs")),
    "CATI": ("کتیزن", ("کتیزن", "کاتیزن", "catizen", "cati")),
    "MAJOR": ("میجر", ("میجر", "major")),
    "PEPE": ("پپه", ("پپه", "پپ", "pepe")),
    "TRUMP": ("ترامپ", ("ترامپ", "trump")),
    "USDC": ("یو اس دی کوین", ("یو اس دی سی", "usdc", "usd coin")),
    "DAI": ("دای", ("دای", "dai")),
    "ARB": ("آربیتروم", ("آربیتروم", "اربیتروم", "arbitrum", "arb")),
    "OP": ("آپتیمیزم", ("آپتیمیزم", "اپتیمیزم", "optimism")),
    "NEAR": ("نیر", ("نیر", "near")),
    "ATOM": ("کازماس", ("کازماس", "کاسموس", "cosmos", "atom")),
    "XAUT": ("تتر گلد", ("تتر گلد", "xaut")),
    "PAXG": ("پکسوس گلد", ("پکسوس گلد", "paxg")),
    "FLOKI": ("فلوکی", ("فلوکی", "floki")),
    "BONK": ("بونک", ("بونک", "bonk")),
    "WLD": ("ورلد کوین", ("ورلد کوین", "ورلدکوین", "worldcoin", "wld")),
    "HYPE": ("هایپرلیکویید", ("هایپرلیکویید", "hype")),
    "ENA": ("اتنا", ("اتنا", "ena")),
    "SEI": ("سی", ("sei",)),
    "INJ": ("اینجکتیو", ("اینجکتیو", "injective", "inj")),
    "RENDER": ("رندر", ("رندر", "render")),
    "AXS": ("اکسی اینفینیتی", ("اکسی", "axs")),
    "SAND": ("سندباکس", ("سندباکس", "sandbox", "sand")),
    "MANA": ("دیسنترالند", ("دیسنترالند", "mana")),
    "GALA": ("گالا", ("گالا", "gala")),
    "APE": ("ایپ کوین", ("ایپ کوین", "apecoin", "ape")),
    "CRO": ("کرونوس", ("کرونوس", "cro")),
    "XTZ": ("تزوس", ("تزوس", "tezos", "xtz")),
    "ALGO": ("الگوراند", ("الگوراند", "algorand", "algo")),
    "VET": ("وی چین", ("وی چین", "vechain", "vet")),
    "HBAR": ("هدرا", ("هدرا", "hedera", "hbar")),
    "IOTA": ("آیوتا", ("آیوتا", "iota")),
    "ZEC": ("زی کش", ("زی کش", "زیکش", "zcash", "zec")),
    "DASH": ("دش", ("دش", "dash")),
    "NEO": ("نئو", ("نئو", "neo")),
    "EOS": ("ایاس", ("ایاس", "eos")),
    "ICP": ("اینترنت کامپیوتر", ("اینترنت کامپیوتر", "icp")),
    "AAVE": ("آوه", ("آوه", "aave")),
    "MKR": ("میکر", ("میکر", "maker", "mkr")),
    "CRV": ("کرو", ("curve", "crv")),
    "COMP": ("کامپاند", ("کامپاند", "compound", "comp")),
    "TWT": ("تراست ولت", ("تراست ولت", "twt")),
    "LUNC": ("لونا کلاسیک", ("لونا کلاسیک", "lunc")),
    "LUNA": ("لونا", ("لونا", "luna")),
    "JST": ("جاست", ("جاست", "jst")),
    "BTT": ("بیت تورنت", ("بیت تورنت", "btt")),
    "WIF": ("داگ ویف هت", ("ویف", "wif")),
    "JUP": ("ژوپیتر", ("ژوپیتر", "jupiter", "jup")),
    "TIA": ("سلستیا", ("سلستیا", "celestia", "tia")),
    "STX": ("استکس", ("استکس", "stacks", "stx")),
    "ONDO": ("اوندو", ("اوندو", "ondo")),
    "KAS": ("کاسپا", ("کاسپا", "kaspa", "kas")),
    "FET": ("فچ", ("فچ", "fetch", "fet")),
    "GRT": ("گراف", ("گراف", "the graph", "grt")),
    "1INCH": ("وان اینچ", ("وان اینچ", "1inch")),
    "CAKE": ("پنکیک سواپ", ("پنکیک", "pancakeswap", "cake")),
    "BGB": ("بیت گت توکن", ("bgb",)),
    "OKB": ("اوکی بی", ("okb",)),
    "LEO": ("لئو", ("leo",)),
    "FDUSD": ("اف دی یو اس دی", ("fdusd",)),
    "USDE": ("یو اس دی ای", ("usde",)),
    "PYUSD": ("پی یو اس دی", ("pyusd",)),
}

TOMAN_ALIASES = {"تومان", "تومن", "تمن", "ت", "toman", "tmn", "irt"}
RIAL_ALIASES = {"ریال", "ریال ایران", "rial", "irr", "ر"}
STARS_ALIASES = {"استارز", "استار", "استارس", "ستاره", "stars", "star", "⭐", "⭐️", "تلگرام استارز"}

_SPACE = re.compile(r"\s+")


def normalize(text: str) -> str:
    text = (text or "").strip().lower()
    text = text.replace("ي", "ی").replace("ك", "ک").replace("ى", "ی").replace("ۀ", "ه").replace("ة", "ه")
    text = text.replace("‌", " ").replace("‏", "").replace("‎", "").replace("ـ", "")
    text = re.sub(r"[ً-ٰٟ]", "", text)
    return _SPACE.sub(" ", text).strip()


_FIAT_INDEX: dict[str, str] = {}
_CRYPTO_INDEX: dict[str, str] = {}


def _build() -> None:
    for code, (fa, en, _cc, sym, aliases) in FIAT.items():
        for a in (code, fa, en, *aliases):
            _FIAT_INDEX.setdefault(normalize(a), code)
    for code, (fa, *_rest) in FIAT.items():
        _FIAT_INDEX.setdefault(normalize(fa.replace(" ", "")), code)
    for sym, (fa, aliases) in CRYPTO.items():
        for a in (sym, fa, *aliases):
            _CRYPTO_INDEX.setdefault(normalize(a), sym)


_build()

CRYPTO_PRIORITY = {"TON", "BTC", "ETH", "SOL", "DOT", "OP"}


def resolve_unit(unit: str) -> tuple[str, str] | None:
    u = normalize(unit)
    if not u:
        return None
    if u in TOMAN_ALIASES:
        return "toman", "IRT"
    if u in RIAL_ALIASES:
        return "rial", "IRR"
    if u in STARS_ALIASES:
        return "stars", "STARS"
    if u.upper() in CRYPTO_PRIORITY:
        return "crypto", u.upper()
    if u in _FIAT_INDEX:
        if u in _CRYPTO_INDEX and not re.fullmatch(r"[a-z]{3}", u):
            return "crypto", _CRYPTO_INDEX[u]
        return "fiat", _FIAT_INDEX[u]
    if u in _CRYPTO_INDEX:
        return "crypto", _CRYPTO_INDEX[u]
    if re.fullmatch(r"[a-z0-9]{2,12}", u) and not u.isdigit():
        return "crypto_guess", u.upper()
    return None


def crypto_name(symbol: str) -> str | None:
    entry = CRYPTO.get(symbol.upper())
    return entry[0] if entry else None


def is_known_crypto_word(text: str) -> str | None:
    return _CRYPTO_INDEX.get(normalize(text))


def flag_emoji(cc: str | None) -> str:
    if not cc or len(cc) != 2:
        return "🏳"
    if cc == "eu":
        return "🇪🇺"
    return "".join(chr(0x1F1E6 + ord(c) - ord("a")) for c in cc.lower())
