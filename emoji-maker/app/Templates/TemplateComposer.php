<?php

declare(strict_types=1);

namespace EmojiMaker\Templates;

/**
 * Applies a template spec to its original animation: removes the original
 * logo / text and inserts the user's content groups into the slot hosts.
 *
 * Everything else of the animation is kept byte for byte (keyframes, easing,
 * parenting, mattes, masks, timing): removal deletes items from their lists,
 * insertion adds new groups; nothing is re-timed or re-encoded.
 *
 * Removal rules
 *  - a shape item / group path: the item is deleted
 *  - a group that is also a slot host: its content is cleared, its "tr" kept
 *  - a layer path: the layer's artwork is cleared; shape layers stay as
 *    (possibly host) layers, other layer types become null layers so parent
 *    links keep working. Cleared layers that nobody needs (no parent link,
 *    no matte role, no slot) are dropped afterwards, and assets nobody uses
 *    any more (e.g. an embedded logo image) are deleted.
 */
final class TemplateComposer
{
    private const RM = '__emx_rm';
    private const INS = '__emx_ins';

    /**
     * @param list<string> $remove
     * @param list<array{host:string,index:int,item:array<string,mixed>}> $insertions
     */
    public function apply(array $doc, array $remove, array $insertions): array
    {
        foreach ($remove as $p) {
            if (!LottieDoc::has($doc, $p)) {
                throw new TemplateException('عنصر حذف‌شدنی قالب پیدا نشد: ' . $p);
            }
            LottieDoc::set($doc, $p . '/' . self::RM, 1);
        }
        foreach ($insertions as $k => $ins) {
            if (!LottieDoc::has($doc, $ins['host'])) {
                throw new TemplateException('محل قرار گرفتن محتوا در قالب پیدا نشد.');
            }
            $key = $ins['host'] . '/' . self::INS;
            $list = LottieDoc::has($doc, $key) ? (array) LottieDoc::get($doc, $key) : [];
            $list[] = $k;
            LottieDoc::set($doc, $key, $list);
        }
        $doc['layers'] = $this->rebuildLayers((array) $doc['layers'], $insertions);
        foreach ((array) ($doc['assets'] ?? []) as $ai => $asset) {
            if (is_array($asset) && isset($asset['layers']) && is_array($asset['layers'])) {
                $doc['assets'][$ai]['layers'] = $this->rebuildLayers($asset['layers'], $insertions);
            }
        }
        return $this->dropUnusedAssets($doc);
    }

    /** @param list<array<string,mixed>> $layers */
    private function rebuildLayers(array $layers, array $insertions): array
    {
        $cleared = [];
        foreach ($layers as $i => $l) {
            if (!is_array($l)) {
                continue;
            }
            $hostOf = (array) ($l[self::INS] ?? []);
            if (!empty($l[self::RM])) {
                $l = $this->clearLayer($l);
                if (!$hostOf) {
                    $cleared[$i] = true;
                }
            }
            if ((int) ($l['ty'] ?? -1) === 4) {
                $l['shapes'] = $this->rebuildItems((array) ($l['shapes'] ?? []), $hostOf, $insertions, false);
            }
            unset($l[self::RM], $l[self::INS]);
            $layers[$i] = $l;
        }
        if (!$cleared) {
            return array_values($layers);
        }
        // drop cleared layers that play no structural role any more
        $out = [];
        foreach ($layers as $i => $l) {
            if (isset($cleared[$i]) && !LottieDoc::isParent($layers, $i) && empty($l['td']) && empty($l['tt'])) {
                continue;
            }
            $out[] = $l;
        }
        return $out;
    }

    private function clearLayer(array $l): array
    {
        $ty = (int) ($l['ty'] ?? -1);
        if ($ty === 4) {
            $l['shapes'] = [];
            return $l;
        }
        if ($ty === 3) {
            return $l;
        }
        // precomp / image / solid / text -> null layer (keeps transform for children)
        foreach (['refId', 'w', 'h', 'tm', 'sc', 'sw', 'sh', 't', 'shapes', 'masksProperties', 'hasMask', 'ef'] as $k) {
            unset($l[$k]);
        }
        $l['ty'] = 3;
        return $l;
    }

    /**
     * @param list<int> $hostOf insertion indices targeting this list
     * @param list<array{host:string,index:int,item:array<string,mixed>}> $insertions
     */
    private function rebuildItems(array $items, array $hostOf, array $insertions, bool $isGroup): array
    {
        $pending = [];
        foreach ($hostOf as $k) {
            $pending[] = $insertions[$k];
        }
        usort($pending, static fn ($a, $b) => $a['index'] <=> $b['index']);
        $out = [];
        $pos = 0;
        $flush = function (int $upto) use (&$pending, &$out): void {
            while ($pending && $pending[0]['index'] <= $upto) {
                $out[] = array_shift($pending)['item'];
            }
        };
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $ty = (string) ($it['ty'] ?? '');
            if ($isGroup && $ty === 'tr') {
                $flush(PHP_INT_MAX); // content always goes before the group transform
                unset($it[self::RM], $it[self::INS]);
                $out[] = $it;
                continue;
            }
            $hostHere = (array) ($it[self::INS] ?? []);
            if (!empty($it[self::RM])) {
                if ($ty === 'gr' && $hostHere) {
                    // cleared host group: keep only its transform, insert content
                    $keep = array_values(array_filter((array) ($it['it'] ?? []), static fn ($x) => is_array($x) && ($x['ty'] ?? '') === 'tr'));
                    if (!$keep) {
                        $keep[] = TemplateContent::identityTransform();
                    }
                    $it['it'] = $this->rebuildItems($keep, $hostHere, $insertions, true);
                    unset($it[self::RM], $it[self::INS]);
                    $flush($pos);
                    $out[] = $it;
                    $pos++;
                }
                continue;
            }
            $flush($pos);
            if ($ty === 'gr') {
                $it['it'] = $this->rebuildItems((array) ($it['it'] ?? []), $hostHere, $insertions, true);
            }
            unset($it[self::RM], $it[self::INS]);
            $out[] = $it;
            $pos++;
        }
        $flush(PHP_INT_MAX);
        return $out;
    }

    /** Deletes assets that no layer references any more. */
    private function dropUnusedAssets(array $doc): array
    {
        if (empty($doc['assets'])) {
            return $doc;
        }
        $byId = [];
        foreach ($doc['assets'] as $i => $a) {
            if (is_array($a) && isset($a['id'])) {
                $byId[(string) $a['id']] = $i;
            }
        }
        $used = [];
        $visit = function (array $layers) use (&$visit, &$used, $doc, $byId): void {
            foreach ($layers as $l) {
                if (!is_array($l) || !isset($l['refId'])) {
                    continue;
                }
                $id = (string) $l['refId'];
                if (isset($used[$id])) {
                    continue;
                }
                $used[$id] = true;
                $asset = isset($byId[$id]) ? $doc['assets'][$byId[$id]] : null;
                if (is_array($asset) && isset($asset['layers'])) {
                    $visit((array) $asset['layers']);
                }
            }
        };
        $visit((array) $doc['layers']);
        $doc['assets'] = array_values(array_filter($doc['assets'], static fn ($a) => !is_array($a) || !isset($a['id']) || isset($used[(string) $a['id']])));
        return $doc;
    }
}
