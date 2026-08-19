<?php

namespace EventFlow\Presentation\Api;

final readonly class GuestSessionAccessRequestMapper
{
    public function requireEmptyBody(RestRequest $request): void
    {
        if ($request->json() !== []) {
            throw new RequestInputException('validation_failed');
        }
    }
}
