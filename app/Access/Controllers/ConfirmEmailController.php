<?php

namespace BookStack\Access\Controllers;

use BookStack\Access\EmailConfirmationService;
use BookStack\Access\LoginService;
use BookStack\Exceptions\ConfirmationEmailException;
use BookStack\Exceptions\UserTokenExpiredException;
use BookStack\Exceptions\UserTokenNotFoundException;
use BookStack\Http\Controller;
use BookStack\Users\UserRepo;
use Exception;
use Illuminate\Http\Request;

class ConfirmEmailController extends Controller
{
    public function __construct(
        protected EmailConfirmationService $emailConfirmationService,
        protected LoginService $loginService,
        protected UserRepo $userRepo
    ) {
    }

    /**
     * Show the page to tell the user to check their email
     * and confirm their address.
     */
    public function show()
    {
        return view('auth.register-confirm');
    }

    /**
     * Shows a notice that a user's email address has not been confirmed,
     * along with the option to re-send the confirmation email.
     */
    public function showAwaiting()
    {
        $user = $this->loginService->getLastLoginAttemptUser();
        if ($user === null) {
            $this->showErrorNotification(trans('errors.login_user_not_found'));
            return redirect('/login');
        }

        return view('auth.register-confirm-awaiting');
    }

    /**
     * Show the form for a user to provide their positive confirmation of their email.
     */
    public function showAcceptForm(string $token)
    {
        return view('auth.register-confirm-accept', ['token' => $token]);
    }

    /**
     * Confirms an email via a token and logs the user into the system.
     *
     * @throws ConfirmationEmailException
     * @throws Exception
     */
    public function confirm(Request $request)
    {
        $validated = $this->validate($request, [
            'token' => ['required', 'string']
        ]);

        $token = $validated['token'];

        try {
            $userId = $this->emailConfirmationService->checkTokenAndGetUserId($token);
        } catch (UserTokenNotFoundException $exception) {
            $this->showErrorNotification(trans('errors.email_confirmation_invalid'));

            return redirect('/register');
        } catch (UserTokenExpiredException $exception) {
            $user = $this->userRepo->getById($exception->userId);
            $this->emailConfirmationService->sendConfirmation($user);
            $this->showErrorNotification(trans('errors.email_confirmation_expired'));

            return redirect('/register/confirm');
        }

        $user = $this->userRepo->getById($userId);
        $user->email_confirmed = true;
        $user->save();

        $this->emailConfirmationService->deleteByUser($user);
        $this->showSuccessNotification(trans('auth.email_confirm_success'));

        return redirect('/login');
    }

    /**
     * Resend the confirmation email.
     */
    public function resend()
    {
        $user = $this->loginService->getLastLoginAttemptUser();
        if ($user === null) {
            $this->showErrorNotification(trans('errors.login_user_not_found'));
            return redirect('/login');
        }

        try {
            $this->emailConfirmationService->sendConfirmation($user);
        } catch (ConfirmationEmailException $e) {
            $this->showErrorNotification($e->getMessage());

            return redirect('/login');
        } catch (Exception $e) {
            $this->showErrorNotification(trans('auth.email_confirm_send_error'));

            return redirect('/register/awaiting');
        }

        $this->showSuccessNotification(trans('auth.email_confirm_resent'));

        return redirect('/register/confirm');
    }
}
