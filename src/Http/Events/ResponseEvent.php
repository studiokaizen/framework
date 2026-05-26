<?php

declare(strict_types=1);

namespace Zen\Http\Events;

use Zen\Events\Event;
use Zen\Http\Request;
use Zen\Http\Response;

/**
 * Fired after a response has been produced, allowing listeners to inspect or
 * replace it before it is sent to the client.
 */
class ResponseEvent extends Event
{
    /**
     * Creates the event with the originating request and current response.
     *
     * @param  Request  $request
     * @param  Response $response
     *
     * @return void
     */
    public function __construct(
        private readonly Request $request,
        private Response $response,
    )
    {
    }

    /**
     * Returns the request that produced this response.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Returns the current response associated with this event.
     *
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * Replaces the response that will be returned to the caller.
     *
     * @param  Response $response
     *
     * @return void
     */
    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }
}
