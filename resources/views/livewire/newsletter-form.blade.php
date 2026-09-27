<div>
    <form wire:submit="subscribe" class="newsletter-form" novalidate>
        <label class="sr-only" for="newsletter-email">Alamat email</label>
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
        <input id="newsletter-email" wire:model="email" type="email" autocomplete="email" inputmode="email" placeholder="Masukkan alamat email Anda" required>
        <button type="submit" wire:loading.attr="disabled" wire:target="subscribe" aria-label="Berlangganan newsletter">
            <span wire:loading.remove wire:target="subscribe" aria-hidden="true">→</span>
            <span wire:loading wire:target="subscribe" class="newsletter-spinner" aria-hidden="true"></span>
        </button>
    </form>
    @error('email')<p class="newsletter-message newsletter-error" role="alert">{{ $message }}</p>@enderror
    @if($notice)<p class="newsletter-message" role="status">{{ $notice }}</p>@endif
    <p class="newsletter-privacy">Email Anda hanya digunakan untuk pembaruan Besofton. <a wire:navigate href="{{ route('newsletter.unsubscribe.form') }}">Berhenti berlangganan</a> kapan saja.</p>
</div>