import './app.js';

function initBrandingPreviews() {
    document.querySelectorAll('[data-branding-upload]').forEach(input => {
        if (input.dataset.previewBound) return;
        input.dataset.previewBound = '1';
        let objectUrl;
        input.addEventListener('change', () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            const image = document.querySelector(`[data-branding-preview="${input.dataset.brandingUpload}"]`);
            if (!image) return;
            const file = input.files?.[0];
            image.src = file ? (objectUrl = URL.createObjectURL(file)) : image.dataset.currentSrc;
            const largeImage = document.querySelector(`[data-branding-preview-large="${input.dataset.brandingUpload}"]`);
            if (largeImage) largeImage.src = image.src;
            document.querySelector(`[data-branding-selected="${input.dataset.brandingUpload}"]`)?.replaceChildren(document.createTextNode(file?.name || 'Belum ada file baru'));
        });
    });
}

document.addEventListener('DOMContentLoaded', initBrandingPreviews);
document.addEventListener('livewire:navigated', initBrandingPreviews);
