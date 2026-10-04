# Security & regression tests

Runs the bot as a library with a fake Telegram API and a temporary data folder
(nothing real is touched, nothing is sent).

```
php tests/harness.php tests/t_attacks.php   # abuse attempts — every line must be ✅
php tests/harness.php tests/t_regress.php   # normal flows still work
php tests/harness.php tests/t_mysql.php     # SQLite→MySQL translator (row locks, idem, upsert, migration, fail-closed)
php tests/harness.php tests/t_adpanel.php   # airdrop crystal-price panel in /panel
php tests/harness.php tests/t_coupon.php    # admin coupon maker in /panel (make, cap, per-user, custom, delete)
php tests/harness.php tests/t_race.php      # coupon caps hold under a REAL race (parallel processes, no double-use)
php tests/harness.php tests/t_crawl.php     # presses every admin/user button, fails on any PHP error
php tests/harness.php tests/t_speed.php     # card image render time
```

Run them all at once:

```
php tests/harness.php tests/t_attacks.php tests/t_regress.php tests/t_mysql.php \
  tests/t_adpanel.php tests/t_coupon.php tests/t_race.php tests/t_crawl.php tests/t_speed.php
```

`_race_worker.php` is a helper spawned by `t_race.php` (one redemption attempt per
process) — it is not a standalone test, don't pass it to the harness.
