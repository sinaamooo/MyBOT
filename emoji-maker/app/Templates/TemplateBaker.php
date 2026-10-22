<?php

declare(strict_types=1);

namespace EmojiMaker\Templates;

/**
 * Makes a template brand neutral for good: the elements listed in
 * spec.remove are deleted from the animation file itself (not only when an
 * emoji is composed), every name / comment / marker / author field is
 * stripped, and the spec is rewritten for the new document (remove = [],
 * slot hosts and indexes, recolour protect paths and the frozen brand colour).
 *
 * Composing the baked template gives exactly the same animation as composing
 * the original with its removal list: the removal is done by the same
 * TemplateComposer, with empty placeholder groups inserted at the slots so
 * that their hosts are kept and their final positions can be read back.
 */
final class TemplateBaker
{
    private const SLOT = '__emx_slot';
    private const REF = '__emx_ref';
    /** Root keys kept in a baked document (everything else is metadata). */
    private const ROOT_KEYS = ['v', 'fr', 'ip', 'op', 'w', 'h', 'ddd', 'assets', 'layers', 'tgs'];

    /**
     * @return array{doc:array<string,mixed>,spec:TemplateSpec,removed:int}
     */
    public function bake(TemplateSpec $spec, array $doc): array
    {
        $spec = TemplateSpec::fromArray($spec->toArray(), $spec->dir);
        $problems = $spec->check($doc);
        if ($problems) {
            throw new TemplateException('Spec does not match the animation: ' . implode('; ', $problems));
        }
        // the automatic brand colour was measured on the original artwork
        // (brand logo included): freeze it so recolouring stays identical
        if ($spec->recolor['primary'] === null && $spec->recolor['enabled']) {
            $p = (new TemplateRecolor())->primary($doc, $spec->recolor['protect']);
            $spec->recolor['primary'] = $p['hex'] ?? null;
        }
        foreach ($spec->recolor['protect'] as $i => $path) {
            if (LottieDoc::has($doc, $path)) {
                $refs = LottieDoc::has($doc, $path . '/' . self::REF) ? (array) LottieDoc::get($doc, $path . '/' . self::REF) : [];
                $refs[] = $i;
                LottieDoc::set($doc, $path . '/' . self::REF, $refs);
            }
        }
        $insertions = [];
        foreach ($spec->slots as $s => $slot) {
            $targets = [['host' => $slot['host'], 'index' => $slot['index']], ...$slot['also']];
            foreach ($targets as $k => $t) {
                $insertions[] = [
                    'host' => (string) $t['host'],
                    'index' => (int) $t['index'],
                    'item' => ['ty' => 'gr', 'it' => [], self::SLOT => $s . ':' . $k],
                ];
            }
        }
        $removed = count($spec->remove);
        $doc = (new TemplateComposer())->apply($doc, $spec->remove, $insertions);

        // read back where everything ended up
        $slots = [];
        $protect = [];
        $this->collect($doc, '', $slots, $protect);
        if (count($slots) !== count($insertions)) {
            throw new TemplateException('Baking lost a slot of ' . $spec->slug);
        }
        $doc = $this->dropPlaceholders($doc, $slots);
        foreach ($spec->slots as $s => &$slot) {
            foreach ([0, ...array_keys($slot['also'])] as $pos => $k) {
                $key = $s . ':' . $pos;
                [$host, $index] = $slots[$key]['final'];
                if ($pos === 0) {
                    $slot['host'] = $host;
                    $slot['index'] = $index;
                } else {
                    $slot['also'][$k]['host'] = $host;
                    $slot['also'][$k]['index'] = $index;
                }
            }
        }
        unset($slot);
        ksort($protect);
        $spec->recolor['protect'] = array_values(array_unique($protect));
        $spec->remove = [];

        $clean = [];
        foreach (self::ROOT_KEYS as $k) {
            if (array_key_exists($k, $doc)) {
                $clean[$k] = $doc[$k];
            }
        }
        $clean = LottieDoc::stripMetadata($clean);
        $clean = $this->dropKey($clean, self::REF);
        $problems = $spec->check($clean);
        if ($problems) {
            throw new TemplateException('Baked spec is inconsistent: ' . implode('; ', $problems));
        }
        return ['doc' => $clean, 'spec' => $spec, 'removed' => $removed];
    }

    /**
     * Finds the placeholders (slot key => path of the list item) and the
     * protect markers (protect index => node path).
     * @param array<string,array{path:string,list:string,index:int,final?:array{0:string,1:int}}> $slots
     * @param array<int,string> $protect
     */
    private function collect(array $node, string $path, array &$slots, array &$protect): void
    {
        foreach ((array) ($node[self::REF] ?? []) as $i) {
            $protect[(int) $i] = $path;
        }
        foreach ($node as $k => $v) {
            if (!is_array($v) || $k === self::REF) {
                continue;
            }
            $child = $path === '' ? (string) $k : $path . '/' . $k;
            if (isset($v[self::SLOT])) {
                $slots[(string) $v[self::SLOT]] = ['path' => $child, 'list' => $path, 'index' => (int) $k];
                continue;
            }
            $this->collect($v, $child, $slots, $protect);
        }
    }

    /**
     * Removes the placeholder groups; each slot gets its host (the layer or
     * group owning the list) and the index the content is inserted at.
     * @param array<string,array{path:string,list:string,index:int}> $slots
     */
    private function dropPlaceholders(array $doc, array &$slots): array
    {
        $byList = [];
        foreach ($slots as $key => $s) {
            $byList[$s['list']][$key] = $s['index'];
        }
        foreach ($byList as $list => $entries) {
            asort($entries);
            $n = 0;
            foreach ($entries as $key => $idx) {
                // earlier placeholders of the same list are gone after removal
                $slots[$key]['final'] = [self::ownerOf($list), $idx - $n];
                $n++;
            }
            $items = (array) LottieDoc::get($doc, $list);
            $items = array_values(array_filter($items, static fn ($it) => !is_array($it) || !isset($it[self::SLOT])));
            LottieDoc::set($doc, $list, $items);
        }
        return $doc;
    }

    /** "layers/3/shapes" → "layers/3", "…/it" → the group path. */
    private static function ownerOf(string $listPath): string
    {
        $parts = explode('/', $listPath);
        array_pop($parts);
        return implode('/', $parts);
    }

    private function dropKey(array $node, string $key): array
    {
        unset($node[$key]);
        foreach ($node as $k => $v) {
            if (is_array($v)) {
                $node[$k] = $this->dropKey($v, $key);
            }
        }
        return $node;
    }
}
