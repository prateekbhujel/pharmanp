const CODE128_PATTERNS = [
    '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312',
    '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222',
    '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131',
    '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321',
    '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
    '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121',
    '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321',
    '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224',
    '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114',
    '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
    '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112',
    '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113',
    '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412',
    '211214', '211232', '2331112',
];

const START_CODE_B = 104;
const STOP_CODE = 106;

export function normalizeCode128Value(value) {
    return String(value ?? '')
        .trim()
        .replace(/[^\x20-\x7E]/g, '');
}

export function makeBarcodeCandidate(seed = 'PNP') {
    const safeSeed = normalizeCode128Value(seed)
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .slice(0, 8) || 'PNP';
    const timePart = Date.now().toString(36).toUpperCase();
    const randomPart = Math.random().toString(36).slice(2, 6).toUpperCase();

    return `${safeSeed}-${timePart}-${randomPart}`;
}

export function code128Bars(value) {
    const normalized = normalizeCode128Value(value);

    if (!normalized) {
        return null;
    }

    const dataCodes = [...normalized].map((character) => {
        const code = character.charCodeAt(0) - 32;

        if (code < 0 || code > 94) {
            throw new Error(`Unsupported Code 128 character: ${character}`);
        }

        return code;
    });

    const checksum = dataCodes.reduce(
        (total, code, index) => total + code * (index + 1),
        START_CODE_B,
    ) % 103;

    const symbols = [START_CODE_B, ...dataCodes, checksum, STOP_CODE];
    const bars = [];
    let cursor = 0;

    symbols.forEach((symbol) => {
        const pattern = CODE128_PATTERNS[symbol];

        [...pattern].forEach((moduleWidth, index) => {
            const width = Number(moduleWidth);

            if (index % 2 === 0) {
                bars.push({ x: cursor, width });
            }

            cursor += width;
        });
    });

    return { value: normalized, bars, width: cursor };
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

export function renderCode128Svg(value, options = {}) {
    const barcode = code128Bars(value);

    if (!barcode) {
        return '';
    }

    const quiet = Number(options.quiet ?? 10);
    const height = Number(options.height ?? 58);
    const barHeight = Number(options.barHeight ?? 42);
    const moduleWidth = Number(options.moduleWidth ?? 1.5);
    const textY = barHeight + 12;
    const width = (barcode.width + quiet * 2) * moduleWidth;
    const label = escapeHtml(options.label ?? barcode.value);
    const bars = barcode.bars
        .map((bar) => `<rect x="${(bar.x + quiet) * moduleWidth}" y="0" width="${bar.width * moduleWidth}" height="${barHeight}" />`)
        .join('');

    return [
        `<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="${label}" viewBox="0 0 ${width} ${height}" width="${width}" height="${height}">`,
        `<rect width="100%" height="100%" fill="#fff" />`,
        `<g fill="#0f172a">${bars}</g>`,
        options.showText === false ? '' : `<text x="${width / 2}" y="${textY}" text-anchor="middle" font-family="Inter, Arial, sans-serif" font-size="10" font-weight="700" fill="#0f172a">${escapeHtml(barcode.value)}</text>`,
        '</svg>',
    ].join('');
}
