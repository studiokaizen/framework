<?php

declare(strict_types=1);

namespace Zen\Http\Events;

use Zen\Events\Event;
use Zen\Http\Request;
use Zen\Http\Response;

/**
 * Fired before the router is consulted, allowing listeners to short-circuit
 * routing by attaching a response directly.
 */
class RequestEvent extends Event
{
    /**
     * A response set by a listener to short-circuit normal routing.
     *
     * @var Response|null
     */
    private ?Response $response = null;

    /**
     * Creates the event with the incoming request.
     *
     * @param  Request $request
     *
     * @return void
     */
    public function __construct(private readonly Request $request)
    {
    }

    /**
     * Returns the incoming request.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Attaches a response and stops propagation so no further listeners run.
     *
     * @param  Response $response
     *
     * @return void
     */
    public function setResponse(Response $response): void
    {
        $this->response = $response;
        $this->stopPropagation();
    }

    /**
     * Returns the response set by a listener, or null if none was set.
     *
     * @return Response|null
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }

    /**
     * Returns true when a listener has attached a response to this event.
     *
     * @return bool
     */
    public function hasResponse(): bool
    {
        return $this->response !== null;
    }
}
