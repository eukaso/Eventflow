<?php

namespace EventFlow\Presentation\Api;

final class GuestSessionCookie
{
    /**
     * Version the browser cookie so installations upgrading from the original
     * REST-path-scoped cookie cannot submit two cookies with the same name.
     */
    public const NAME = 'eventflow_guest_session_v2';

    /**
     * WordPress may expose REST routes through /wp-json/ or index.php?rest_route.
     * A root path keeps the HttpOnly session available through either routing
     * mode and through reverse-proxy rewrites.
     */
    public const PATH = '/';

    private function __construct()
    {
    }
}
