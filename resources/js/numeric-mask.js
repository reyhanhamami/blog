const DECIMAL_MASKS = new Set(['decimal', 'money', 'price', 'currency', 'percentage', 'duration']);
const CURRENCY_MASKS = new Set(['money', 'price', 'currency']);

function rawValue(value, decimal) {
    let cleaned = String(value).replace(/[^0-9,.-]/g, '');
    if (cleaned === '' || cleaned === '-') return '';
    if (cleaned.includes(',')) cleaned = cleaned.replace(/\./g, '').replace(',', '.');
    else if (/^-?\d{1,3}(\.\d{3})+$/.test(cleaned)) cleaned = cleaned.replace(/\./g, '');
    const number = Number(cleaned);
    if (!Number.isFinite(number)) return '';
    return decimal ? String(number) : String(Math.trunc(number));
}

function displayValue(value, mask) {
    const decimal = DECIMAL_MASKS.has(mask);
    const raw = rawValue(value, decimal);
    if (raw === '') return '';
    const [whole, fraction] = raw.split('.');
    const grouped = Number(whole).toLocaleString('id-ID');
    const formatted = fraction ? `${grouped},${fraction}` : grouped;
    if (CURRENCY_MASKS.has(mask)) return `Rp ${formatted}`;
    if (mask === 'percentage') return `${formatted}%`;
    return formatted;
}

export function initNumericMasks() {
    document.querySelectorAll('input[type="number"], input[data-numeric-mask]').forEach(input => {
        if (input.dataset.numericBound) return;
        input.dataset.numericBound = '1';
        const mask = input.dataset.numericMask || 'integer';
        const decimal = DECIMAL_MASKS.has(mask);
        input.type = 'text';
        input.inputMode = decimal ? 'decimal' : 'numeric';
        input.value = displayValue(input.value, mask);
        input.addEventListener('focus', () => { input.value = rawValue(input.value, decimal); });
        input.addEventListener('blur', () => { input.value = displayValue(input.value, mask); });
        input.form?.addEventListener('submit', event => {
            if (!event.defaultPrevented) input.value = rawValue(input.value, decimal);
        });
    });
}