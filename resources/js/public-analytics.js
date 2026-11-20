// First-party display confirmation only. Identity stays in an HttpOnly cookie;
// the server validates and aggregates visits. No third-party or device fingerprint.
let previousMarker;
function confirmVisiblePage() {
    if (document.visibilityState !== 'visible') return;
    const marker = document.querySelector('[data-visit-receipt]');
    if (!marker || marker === previousMarker) return;
    previousMarker = marker;
    fetch(marker.dataset.visitUrl, {
        method: 'POST', credentials: 'same-origin', keepalive: true,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': marker.dataset.visitCsrf, 'Accept': 'application/json' },
        body: JSON.stringify({ receipt: marker.dataset.visitReceipt, id: crypto.randomUUID() }),
    }).then(response => { if (!response.ok) console.warn('Konfirmasi kunjungan belum diterima:', response.status); })
        .catch(() => { console.warn('Konfirmasi kunjungan belum terkirim.'); });
}
document.addEventListener('DOMContentLoaded', confirmVisiblePage);
document.addEventListener('livewire:navigated', confirmVisiblePage);
document.addEventListener('visibilitychange', confirmVisiblePage);
window.addEventListener('pageshow', event => { if (event.persisted) previousMarker = null; confirmVisiblePage(); });
