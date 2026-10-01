<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;

final class ContactController extends Controller
{
    public function store(ContactRequest $request): RedirectResponse
    {
        // Honeypot: bots fill every field. Pretend success and store nothing.
        if ($request->filled('website')) {
            return redirect('/contact-us')->with('contact_sent', true);
        }

        ContactMessage::query()->create([
            'name' => $request->string('name')->trim()->toString(),
            'email' => $request->string('email')->trim()->lower()->toString(),
            'phone' => $request->filled('phone') ? $request->string('phone')->trim()->toString() : null,
            'message' => $request->string('message')->trim()->toString(),
            'ip_address' => $request->ip(),
        ]);

        return redirect('/contact-us')->with('contact_sent', true);
    }
}
