<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\FrontendContactMessage;
use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

class FrontendContactController extends Controller
{
    /**
     * Display the public Contact Us page with campus details and inquiry form.
     */
    public function index(): Response
    {
        $settings = SchoolSetting::current();

        return Inertia::render('frontend/Contact', [
            'contactInfo' => [
                'school_name' => $settings->school_name,
                'address' => $settings->address,
                'phone' => $settings->phone,
                'email' => $settings->email,
                'office_hours' => $settings->office_hours,
                'map_embed_url' => $settings->map_embed_url,
                'social_links' => $settings->social_links ?? [],
            ],
        ]);
    }

    /**
     * Store an incoming public contact message inquiry.
     */
    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $throttleKey = 'contact-submission:'.($request->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->with('error', "Too many inquiry attempts. Please wait {$seconds} seconds before sending another message.");
        }

        RateLimiter::hit($throttleKey, 60);

        FrontendContactMessage::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'subject' => $request->validated('subject'),
            'message' => $request->validated('message'),
            'status' => 'unread',
        ]);

        return back()->with('success', 'Thank you! Your message has been received and routed to the school administration.');
    }
}
