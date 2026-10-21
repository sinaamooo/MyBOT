# Reviewing animated emoji templates

The default templates come from three packs of the owner's own Telegram
channels (`resources/templates/tofan`, `royall`, `qiushop`). They are sold to
other customers, so every template must become **brand neutral**: the buyer
puts their own logo / text and colour in it, and nothing may remind of the
original channel. The original animation (every movement, timing, easing,
shading) must stay exactly as it is — we only remove the brand artwork and
insert the buyer's content where it was.

Each template is `resources/templates/<pack>/<slug>.json` (the spec) next to
`<slug>.tgs` (the original animation, never edited). The specs were created by
the automatic detector (`status: "auto"`); reviewing means checking and fixing
the spec until it is perfect, then setting `"status": "reviewed"`.

## Tools (run from the project root `emoji-maker/`)

```
php bin/templates.php inspect <slug> [--deep]     # layer / group tree with JSON paths, colours, canvas boxes,
                                                  # df = in how many animations of the pack this path occurs,
                                                  # flags REMOVE / HOST / PROTECT from the current spec
php bin/templates.php strip <slug> --out=/abs/file.png [--frames=8] [--size=180]
        [--text=LOGO] [--caption=BRAND] [--recolor=#E53935] [--recolor-mode=main|all|gray]
                                                  # PNG grid: rows = original / removed / sample text / sample logo,
                                                  # columns = frames over the whole animation. LOOK AT IT (Read tool).
php bin/templates.php box <slug> --host=<path> --paths=<p1,p2>   # slot box of elements in host coordinates
php bin/templates.php palette <slug>              # detected main colour, recolour settings
php bin/templates.php compose <slug> --format=tgs --out=/abs/dir --logo=sample --text=BRAND --no-preview
                                                  # real TGS: size (<= 64 KB) and validation
php bin/templates.php check --pack=<pack>         # structural checks of all specs of a pack
```

Write images to the scratchpad directory you were given, never into the repository.

## Spec fields

```json
{
  "slug": "tofan-05", "pack": "tofan", "name": "...", "source": "tofan-05.tgs",
  "status": "auto | reviewed | disabled",
  "remove": ["layers/0", "layers/3/shapes/2", "assets/1/layers/0/shapes/0/it/4"],
  "slots": [
    {"role": "main", "host": "layers/0", "index": 0, "box": [x0, y0, x1, y1],
     "accepts": ["logo", "text"], "color": "#0255FE", "pad": 0.92,
     "also": [{"host": "layers/2", "index": 0, "box": [...]}]},
    {"role": "caption", "host": "layers/0", "index": 0, "box": [...], "accepts": ["text"], "color": "#141414", "pad": 0.95, "also": []}
  ],
  "recolor": {"enabled": true, "primary": null, "tolerance": 35, "protect": ["layers/7"]},
  "meta": {"still": 90, "review": "short note of what you checked / changed", ...}
}
```

* `remove`: JSON paths of the brand artwork (logo, brand name, slogan, brand
  mascot, website/handle text). A layer path clears that layer's artwork but
  keeps the layer (and its transform / parenting / mattes). A group path
  deletes the group. A single `sh` path deletes only that path.
* `slots[].host`: the container (shape layer or group) that receives the
  buyer's content. The content is inserted at `index` in its item list
  (0 = drawn on top) and inherits every transform / parent / matte of the host,
  so it moves exactly like the removed logo. When the host is itself in
  `remove`, it is cleared and keeps only its transform (`tr`).
* `box`: the area in the **host's own coordinates** (before the host transform)
  where the content is fitted ("contain", centred, `pad` = margin factor).
  Use `bin/templates.php box` to measure removed elements; you may enlarge /
  shrink / move it so the content looks balanced on the badge.
* `role`: `main` (logo or main text) and optionally `caption` (a short brand
  name line under the logo; text only). Only make a caption slot where the
  original really had a separate text line.
* `also`: copies of the same content in other hosts — only when the original
  logo had a real duplicate (e.g. a glow / shadow copy that must follow the
  content, or a matte source that shapes a highlight sweep over the logo).
  A copy draws the content again, so it must be pixel aligned with the main
  slot. If a copy looks messy (double / offset text, dark sweep over the
  buyer's logo), remove the copy and also put the effect layer that used it
  into `remove` (e.g. the matte target layers that only colour the logo).
* `color`: default colour of text in that slot (taken from the original
  artwork); the buyer can change it.
* `recolor`: buyers recolour the template to their brand colour.
  `primary` = the template's brand colour (`#RRGGBB`, null = auto detected,
  check with `palette` and with `strip --recolor=#E53935` and another colour
  like `#22C55E`); `tolerance` = hue range in degrees around it that moves.
  `protect` = paths of colour sensitive artwork that must keep its default
  colours (faces, skin, eyes, characters such as frogs / ducks / bears,
  gold, fire, food, flags, third party brand logos such as Instagram or
  Tether). Neutrals and skin tones are protected automatically.
  `enabled: false` only when recolouring cannot look right at all.

## What "perfect" means — check every point on the strip images

1. **Nothing of the brand is left** in any frame: no logo fragment, no letters
   of the brand name, no glow / shadow / outline / reflection copy of the logo,
   no handle or slogan. Look at the "removed" row in every column. Also look
   for brand artwork that appears only in some frames (intros / outros) —
   use `--frames=12` if the animation has phases.
2. **Nothing else was removed**: the badge, frame, characters, hands, effects,
   sparkles and background are intact in every frame (compare original vs
   removed rows column by column).
3. **The content sits where the logo was** and follows its movement (scale,
   rotation, bounce, flip) in every frame, centred and nicely sized on the
   badge — not clipped, not covering important artwork, not floating outside.
   Content that is hidden behind a hand or object is fine when the original
   logo was hidden there too.
4. **Caption** (if any) is a clean single line where the brand name was.
5. **Recolouring**: with `--recolor=#E53935` the brand colour of the template
   (e.g. the blue of the tofan pack) becomes red everywhere it should, while
   protected / sensitive parts keep their colours. Try a second colour too.
6. **Brand identity check**: if the template itself *is* the brand (e.g. the
   whole animation is the channel's logo or mascot, like a yellow logo badge
   or the tornado that spells the brand name) and it cannot be made neutral by
   removing parts, set `"status": "disabled"` and explain in `meta.review`.
   Templates of other brands (Instagram icon, Tether coin...) stay usable
   only if they are generic icons — otherwise disable.
7. **TGS size**: `compose ... --format=tgs --logo=sample --text=BRAND` must
   succeed (<= 64 KB, status valid or warning).

When everything passes set `"status": "reviewed"` and write a one or two line
`meta.review` note. Keep the JSON valid (2-space or 4-space indentation is
fine, no comments). Only edit the spec files of the templates you were given.
