<?php
namespace Yeelight\Http\Controllers\Api\Middleware;

use Closure;
use Dingo\Api\Auth\Auth as Authentication;
use Dingo\Api\Routing\Router;
use League\OAuth2\Server\Exception\OAuthServerException;
use Yeelight\Http\Controllers\Api\Auth\Provider\OAuth2;
use Yeelight\Events\User\UserLoggedInEvent;
use Yeelight\Models\Foundation\User;

class ApiAuthMiddleware
{
    /**
     * Router instance.
     *
     * @var \Dingo\Api\Routing\Router
     */
    protected $router;

    /**
     * Authenticator instance.
     *
     * @var \Dingo\Api\Auth\Auth
     */
    protected $auth;

    /**
     * Create a new auth middleware instance.
     *
     * @param \Dingo\Api\Routing\Router $router
     * @param \Dingo\Api\Auth\Auth $auth
     */
    public function __construct(Router $router, Authentication $auth)
    {
        $this->router = $router;
        $this->auth = $auth;
    }

    /**
     * Perform authentication before a request is executed.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param $grant
     *
     * @return mixed
     * @throws OAuthServerException
     */
    public function handle($request, Closure $next, $grant = null)
    {
        $route = $this->router->getCurrentRoute();

        /**
         * FOR (Internal API requests)
         * @note GRANT(user) will always be able to access routes that are protected by: GRANT(client)
         *
         * For OAuth grants from password (i.e. Resource Owner: user)
         * @Auth will only check once, because user exists in auth afterwards
         *
         * For OAuth grants from client_credentials (i.e. Resource Owner: client)
         * @Auth will always check, because user is never exists in auth
         */
        if (!$this->auth->check(false)) {
            $this->auth->authenticate($route->getAuthenticationProviders());

            $provider = $this->auth->getProviderUsed();

            /** @var OAuth2 $provider */
            if ($provider instanceof OAuth2) {
                // check oauth grant type
                if (!is_null($grant) && $provider->getResourceOwnerType() !== $grant) {
                    throw new OAuthServerException();
                }
            }

            // login user through Auth
            $user = $this->auth->getUser();
            if ($user instanceof User) {
                \Auth::login($user);

                event(new UserLoggedInEvent($user));
            }
        }

        return $next($request);
    }
}