<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        // TODO: send an email, save to the database, or whatever you need.
        // Example (uncomment once you have a Mailable set up):
        // Mail::to('mohammedamina8678@gmail.com')->send(new ContactFormMail($validated));

        return back()->with('status', 'Thanks! Your message has been sent.');
    }
}