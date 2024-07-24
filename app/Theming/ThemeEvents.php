<?php

namespace BookStack\Theming;

/**
 * The ThemeEvents used within BookStack.
 *
 * This file details the events that BookStack may fire via the custom
 * theme system, including event names, parameters and expected return types.
 *
 * This system is regarded as semi-stable.
 * We'll look to fix issues with it or migrate old event types but
 * events and their signatures may change in new versions of BookStack.
 * We'd advise testing any usage of these events upon upgrade.
 */
class ThemeEvents
{
    /**
     * Activity logged event.
     * Runs right after an activity is logged by bookstack.
     * These are the activities that can be seen in the audit log area of BookStack.
     * Activity types can be seen listed in the \BookStack\Actions\ActivityType class.
     * The provided $detail can be a string or a loggable type of model. You should check
     * the type before making use of this parameter.
     *
     * @param string $type
     * @param string|\BookStack\Activity\Models\Loggable $detail
     */
    const ACTIVITY_LOGGED = 'activity_logged';

    /**
     * Application boot-up.
     * After main services are registered.
     *
     * @param \BookStack\App\Application $app
     */
    const APP_BOOT = 'app_boot';

    /**
     * Auth login event.
     * Runs right after a user is logged-in to the application by any authentication
     * system as a standard app user. This includes a user becoming logged in
     * after registration. This is not emitted upon API usage.
     *
     * @param string $authSystem
     * @param \BookStack\Users\Models\User $user
     */
    const AUTH_LOGIN = 'auth_login';

    /**
     * Auth pre-register event.
     * Runs right before a new user account is registered in the system by any authentication
     * system as a standard app user including auto-registration systems used by LDAP,
     * SAML, OIDC and social systems. It only includes self-registrations,
     * not accounts created by others in the UI or via the REST API.
     * It runs after any other normal validation steps.
     * Any account/email confirmation occurs post-registration.
     * The provided $userData contains the main details that would be used to create
     * the account, and may depend on authentication method.
     * If false is returned from the event, registration will be prevented and the user
     * will be returned to the login page.
     *
     * @param string $authSystem
     * @param array $userData
     * @returns bool|null
     */
    const AUTH_PRE_REGISTER = 'auth_pre_register';

    /**
     * Auth register event.
     * Runs right after a user is newly registered to the application by any authentication
     * system as a standard app user. This includes auto-registration systems used
     * by LDAP, SAML, OIDC and social systems. It only includes self-registrations.
     *
     * @param string $authSystem
     * @param \BookStack\Users\Models\User $user
     */
    const AUTH_REGISTER = 'auth_register';

    /**
     * Commonmark environment configure.
     * Provides the commonmark library environment for customization before it's used to render markdown content.
     * If the listener returns a non-null value, that will be used as an environment instead.
     *
     * @param \League\CommonMark\Environment\Environment $environment
     * @returns \League\CommonMark\Environment\Environment|null
     */
    const COMMONMARK_ENVIRONMENT_CONFIGURE = 'commonmark_environment_configure';

    /**
     * OIDC ID token pre-validate event.
     * Runs just before BookStack validates the user ID token data upon login.
     * Provides the existing found set of claims for the user as a key-value array,
     * along with an array of the proceeding access token data provided by the identity platform.
     * If the listener returns a non-null value, that will replace the existing ID token claim data.
     *
     * @param array $idTokenData
     * @param array $accessTokenData
     * @returns array|null
     */
    const OIDC_ID_TOKEN_PRE_VALIDATE = 'oidc_id_token_pre_validate';

    /**
     * Page include parse event.
     * Runs when a page include tag is being parsed, typically when page content is being processed for viewing.
     * Provides the "include tag" reference string, the default BookStack replacement content for the tag,
     * the current page being processed, and the page that's being referenced by the include tag.
     * The referenced page may be null where the page does not exist or where permissions prevent visibility.
     * If the listener returns a non-null value, that will be used as the replacement HTML content instead.
     *
     * @param string $tagReference
     * @param string $replacementHTML
     * @param \BookStack\Entities\Models\Page $currentPage
     * @param ?\BookStack\Entities\Models\Page $referencedPage
     */
    const PAGE_INCLUDE_PARSE = 'page_include_parse';

    /**
     * Routes register web event.
     * Called when standard web (browser/non-api) app routes are registered.
     * Provides an app router, so you can register your own web routes.
     *
     * @param \Illuminate\Routing\Router $router
     */
    const ROUTES_REGISTER_WEB = 'routes_register_web';

    /**
     * Routes register web auth event.
     * Called when auth-required web (browser/non-api) app routes can be registered.
     * These are routes that typically require login to access (unless the instance is made public).
     * Provides an app router, so you can register your own web routes.
     *
     * @param \Illuminate\Routing\Router $router
     */
    const ROUTES_REGISTER_WEB_AUTH = 'routes_register_web_auth';

    /**
     * Web before middleware action.
     * Runs before the request is handled but after all other middleware apart from those
     * that depend on the current session user (Localization for example).
     * Provides the original request to use.
     * Return values, if provided, will be used as a new response to use.
     *
     * @param \Illuminate\Http\Request $request
     * @returns \Illuminate\Http\Response|null
     */
    const WEB_MIDDLEWARE_BEFORE = 'web_middleware_before';

    /**
     * Web after middleware action.
     * Runs after the request is handled but before the response is sent.
     * Provides both the original request and the currently resolved response.
     * Return values, if provided, will be used as a new response to use.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse $response
     * @returns \Illuminate\Http\Response|null
     */
    const WEB_MIDDLEWARE_AFTER = 'web_middleware_after';

    /**
     * Webhook call before event.
     * Runs before a webhook endpoint is called. Allows for customization
     * of the data format & content within the webhook POST request.
     * Provides the original event name as a string (see \BookStack\Actions\ActivityType)
     * along with the webhook instance along with the event detail which may be a
     * "Loggable" model type or a string.
     * If the listener returns a non-null value, that will be used as the POST data instead
     * of the system default.
     *
     * @param string $event
     * @param \BookStack\Activity\Models\Webhook $webhook
     * @param string|\BookStack\Activity\Models\Loggable $detail
     * @param \BookStack\Users\Models\User $initiator
     * @param int $initiatedTime
     * @returns array|null
     */
    const WEBHOOK_CALL_BEFORE = 'webhook_call_before';
}
