# Security & regression tests

Runs the bot as a library with a fake Telegram API and a temporary data folder
(nothing real is touched, nothing is sent).

```
php tests/harness.php tests/t_attacks.php   # abuse attempts — every line must be ✅
php tests/harness.php tests/t_regress.php   # normal flows still work
php tests/harness.php tests/t_crawl.php     # presses every admin/user button, fails on any PHP error
php tests/harness.php tests/t_speed.php     # card image render time
```
