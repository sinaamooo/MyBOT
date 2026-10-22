<?php

declare(strict_types=1);

/*
 * Telegram webhook endpoint (registered with setWebhook + secret token by the
 * installer, bin/set-webhook.php or the admin panel). Every check - method,
 * content type, size, secret token, optional source IP, duplicate updates -
 * and the early "200 ok" answer live in EmojiMaker\Bot\Webhook.
 */

use EmojiMaker\App;
use EmojiMaker\Bot\Webhook;

require dirname(__DIR__) . '/app/autoload.php';

$app = App::boot();
$app->registerErrorHandlers(false);
(new Webhook($app))->handle();
