<?php

namespace App\Http\Controllers;

use App\Mail\ConfirmNewsletterUnsubscribe;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class NewsletterController extends Controller
{
    public function unsubscribeForm()
    {
        return view('public.newsletter-unsubscribe');
    }

    public function unsubscribe(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190']]);
        $subscriber = NewsletterSubscriber::where('email', mb_strtolower(trim($data['email'])))
            ->where('status', NewsletterSubscriber::SUBSCRIBED)->first();

        if ($subscriber) {
            $url = URL::temporarySignedRoute('newsletter.unsubscribe.confirm', now()->addDay(), ['subscriber' => $subscriber->id]);
            Mail::to($subscriber->email)->send(new ConfirmNewsletterUnsubscribe($url));
        }

        return back()->with('success', 'Jika email terdaftar, tautan konfirmasi telah dikirim.');
    }

    public function confirmUnsubscribe(NewsletterSubscriber $subscriber)
    {
        if ($subscriber->status === NewsletterSubscriber::SUBSCRIBED) {
            $subscriber->update(['status' => NewsletterSubscriber::UNSUBSCRIBED, 'unsubscribed_at' => now()]);
        }

        return redirect()->route('home')->with('success', 'Langganan newsletter telah dihentikan.');
    }
}
