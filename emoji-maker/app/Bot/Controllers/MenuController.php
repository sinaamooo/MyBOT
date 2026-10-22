<?php

declare(strict_types=1);

namespace EmojiMaker\Bot\Controllers;

use EmojiMaker\Bot\Context;
use EmojiMaker\Bot\Texts;

/** /start, main menu buttons, help, cancel. */
final class MenuController extends Controller
{
    public function menuKeyboard(): array
    {
        return $this->menu();
    }

    public function isMenuButton(string $text): bool
    {
        foreach (Texts::MENU as $row) {
            if (in_array($text, $row, true)) {
                return true;
            }
        }
        return false;
    }

    public function start(Context $ctx, string $arg): void
    {
        $ctx->clearState();
        if (preg_match('/^t_([a-z0-9-]{1,64})$/', $arg, $m)) {
            (new TemplateController($this->app))->openTemplate($ctx, $m[1]);
            return;
        }
        $name = Texts::e(Texts::cut((string) ($ctx->user['first_name'] ?? ''), 32));
        $welcome = trim($this->app->settings()->string('welcome_text', ''));
        $ctx->reply($welcome !== '' ? Texts::e($welcome) : sprintf(Texts::WELCOME, $name), $this->menu());
    }

    public function showMenu(Context $ctx): void
    {
        $ctx->clearState();
        $ctx->reply('منوی اصلی 👇', $this->menu());
    }

    public function help(Context $ctx): void
    {
        $text = Texts::HELP;
        $support = trim($this->app->settings()->string('support_contact', ''));
        if ($support !== '') {
            $text .= "\n\nپشتیبانی: " . Texts::e($support);
        }
        $ctx->reply($text, $this->menu());
    }

    public function cancel(Context $ctx): void
    {
        $ctx->clearState();
        $ctx->reply(Texts::CANCELLED, $this->menu());
    }

    public function menuButton(Context $ctx, string $text): void
    {
        $design = new DesignController($this->app);
        match ($text) {
            Texts::MENU_NEW => $design->startNew($ctx),
            Texts::MENU_TEMPLATES => (new TemplateController($this->app))->packs($ctx),
            Texts::MENU_IMAGE => $design->startWith($ctx, 'image'),
            Texts::MENU_TEXT => $design->startWith($ctx, 'text'),
            Texts::MENU_ANIM => $design->animationEntry($ctx),
            Texts::MENU_BATCH => $design->batchEntry($ctx),
            Texts::MENU_PROJECTS => (new ProjectController($this->app))->list($ctx, 1),
            default => $this->help($ctx),
        };
    }

    /** @param list<string> $p */
    public function callback(Context $ctx, array $p): void
    {
        match ($p[1] ?? '') {
            'menu' => $this->showMenu($ctx),
            'help' => $this->help($ctx),
            default => $ctx->toast(Texts::EXPIRED),
        };
    }
}
