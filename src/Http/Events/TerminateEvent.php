<?php

declare(strict_types=1);

namespace Zen\Http\Events;

use Zen\Events\Event;
use Zen\Http\Request;
use Zen\Http\Response;

/**
 * Fired after the response has been sent to the client, providing a hook for
 * post-send cleanup tasks.
 */
class TerminateEvent extends Event
{
    /**
     * Creates the event with the finalised request and response.
     *
     * @param  Request  $request
     * @param  Response $response
     *
     * @return void
     */
    public function __construct(
        private readonly Request $request,
        private readonly Response $response,
    )
    {
    }

    /**
     * Returns the request that was handled.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Returns the response that was sent to the client.
     *
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }
}
