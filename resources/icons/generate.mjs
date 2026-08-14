/**
 * Regenerates every favicon and PWA icon in `public/` from `logo.svg`.
 *
 * Run `npm run icons` after changing the mark. The outputs are committed, so
 * the Docker asset stage never runs this — it only needs `npm run build`.
 */
import { readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const iconsDir = dirname(fileURLToPath(import.meta.url));
const publicDir = resolve(iconsDir, '../../public');

/** The rounded square's fill — `primary` from the dark palette in app.css. */
const brandColor = '#6e56f8';

/** Rasterise the source well above the target size, then downsample. */
const sourceDensity = 512;

const source = await readFile(resolve(iconsDir, 'logo.svg'));

/**
 * Rasterise the mark at `size`, keeping its transparent rounded corners.
 *
 * @param {number} size
 * @returns {Promise<Buffer>}
 */
function render(size) {
    return sharp(source, { density: sourceDensity })
        .resize(size, size)
        .png()
        .toBuffer();
}

/**
 * Rasterise the mark full-bleed on brand colour. The source's rounded corners
 * disappear against a background of the same colour, which is what both iOS
 * and Android maskable icons want — each applies its own mask, and a second
 * set of corners baked into the image shows up as a visible inset square.
 *
 * @param {number} size
 * @param {number} contentRatio share of the canvas the mark may occupy
 * @returns {Promise<Buffer>}
 */
async function renderFullBleed(size, contentRatio) {
    const mark = await render(Math.round(size * contentRatio));

    return sharp({
        create: {
            width: size,
            height: size,
            channels: 4,
            background: brandColor,
        },
    })
        .composite([{ input: mark, gravity: 'centre' }])
        .png()
        .toBuffer();
}

/**
 * Wrap PNGs in an ICO container: a 6-byte header, one 16-byte directory entry
 * per image, then the payloads. PNG payloads are legal inside ICO, so there is
 * nothing to re-encode and no second dependency to install.
 *
 * @param {Array<{ size: number, png: Buffer }>} images
 * @returns {Buffer}
 */
function buildIco(images) {
    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(images.length, 4);

    let offset = header.length + images.length * 16;

    const entries = images.map(({ size, png }) => {
        const entry = Buffer.alloc(16);
        entry.writeUInt8(size >= 256 ? 0 : size, 0);
        entry.writeUInt8(size >= 256 ? 0 : size, 1);
        entry.writeUInt8(0, 2);
        entry.writeUInt8(0, 3);
        entry.writeUInt16LE(1, 4);
        entry.writeUInt16LE(32, 6);
        entry.writeUInt32LE(png.length, 8);
        entry.writeUInt32LE(offset, 12);
        offset += png.length;

        return entry;
    });

    return Buffer.concat([header, ...entries, ...images.map(({ png }) => png)]);
}

/**
 * `apple-touch-icon` and the maskable icon are full-bleed because their
 * platforms mask them; the rest keep the mark's own rounded corners.
 */
const outputs = {
    'favicon.svg': async () => source,
    'favicon.ico': async () =>
        buildIco(
            await Promise.all(
                [32, 48].map(async (size) => ({
                    size,
                    png: await render(size),
                })),
            ),
        ),
    'apple-touch-icon.png': () => renderFullBleed(180, 1),
    'icon-192.png': () => render(192),
    'icon-512.png': () => render(512),
    'icon-maskable-512.png': () => renderFullBleed(512, 0.8),
};

for (const [name, build] of Object.entries(outputs)) {
    await writeFile(resolve(publicDir, name), await build());

    console.log(`wrote public/${name}`);
}
