---
paths:
  - 'resources/icons/**'
---

# Icons

## Icons are generated from one source SVG and committed
`resources/icons/logo.svg` is the only source for every favicon and PWA icon.
Change the mark there, then run `npm run icons` to rewrite the outputs in
`public/` and commit them. Do not hand-edit anything in `public/` that the
generator writes.

The outputs are committed so the Docker asset stage only needs `npm run build`
— sharp is a devDependency for this script alone and the image build never
invokes it.

`apple-touch-icon.png` and `icon-maskable-512.png` are full-bleed: iOS and
Android apply their own mask, so a second set of rounded corners baked into
the image shows up as a visible inset square. The maskable one keeps the mark
inside an 80% safe zone.

`tests/Feature/PwaTest.php` asserts every icon the manifest names is really on
disk — that failing means you changed the manifest without running the script.
