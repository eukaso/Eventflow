<?php

namespace EventFlow\Presentation\WordPress;

use Closure;
use EventFlow\Application\Authorization\PrincipalContext;
use EventFlow\Presentation\Api\{AuthenticatedPrincipalResolver, RequestInputException, RestRequest};

final readonly class WordPressAuthenticatedPrincipalResolver implements AuthenticatedPrincipalResolver
{
    private Closure $currentUserId;

    public function __construct(?Closure $currentUserId = null)
    {
        $this->currentUserId = $currentUserId ?? static fn (): int => function_exists('get_current_user_id')
            ? (int) get_current_user_id()
            : 0;
    }

    public function resolve(RestRequest $request): PrincipalContext
    {
        $userId = ($this->currentUserId)();
        if (!is_int($userId) || $userId < 1) {
            throw new RequestInputException('authentication_required');
        }
        return PrincipalContext::wordpressUser($userId);
    }
}
