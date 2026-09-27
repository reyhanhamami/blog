<?php

namespace App\Livewire;

use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class NewsletterForm extends Component
{
    public string $email = '';

    public ?string $notice = null;

    public function subscribe(): void
    {
        $this->validate(['email' => ['required', 'email:rfc', 'max:190']]);
        $key = 'newsletter:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Terlalu banyak percobaan. Coba kembali sebentar lagi.');

            return;
        }
        RateLimiter::hit($key, 60);

        $email = mb_strtolower(trim($this->email));
        if (NewsletterSubscriber::where('email', $email)->where('status', NewsletterSubscriber::SUBSCRIBED)->exists()) {
            $this->notice = 'Email Anda sudah terdaftar untuk Besofton Insights.';
        } else {
            DB::table('newsletter_subscribers')->upsert([[
                'email' => $email,
                'status' => NewsletterSubscriber::SUBSCRIBED,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]], ['email'], ['status', 'subscribed_at', 'unsubscribed_at', 'updated_at']);
            $this->notice = 'Terima kasih! Anda berhasil berlangganan Besofton Insights.';
        }
        $this->email = '';
    }

    public function render()
    {
        return view('livewire.newsletter-form');
    }
}
