<?php

namespace BookStack\Access\Controllers;

use BookStack\Activity\ActivityType;
use BookStack\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Sleep;

class ForgotPasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
        $this->middleware('guard:standard');
    }

    /**
     * Display the form to request a password reset link.
     */
    public function showLinkRequestForm()
    {
        return view('auth.passwords.email');
    }

    /**
     * Send a reset link to the given user.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $this->validate($request, [
            'email' => ['required', 'email'],
        ]);

        // Add random pause to the response to help avoid time-base sniffing
        // of valid resets via slower email send handling.
        Sleep::for(random_int(1000, 3000))->milliseconds();

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $response = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if ($response === Password::RESET_LINK_SENT) {
            $this->logActivity(ActivityType::AUTH_PASSWORD_RESET, $request->get('email'));
        }

        if (in_array($response, [Password::RESET_LINK_SENT, Password::INVALID_USER, Password::RESET_THROTTLED])) {
            $message = trans('auth.reset_password_sent', ['email' => $request->get('email')]);
            $this->showSuccessNotification($message);

            return redirect('/password/email')->with('status', trans($response));
        }

        // If an error was returned by the password broker, we will get this message
        // translated so we can notify a user of the problem. We'll redirect back
        // to where the users came from so they can attempt this process again.
        return redirect('/password/email')->withErrors(
            ['email' => trans($response)]
        );
    }
}
