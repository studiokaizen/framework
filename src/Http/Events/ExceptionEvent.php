<?php

declare(strict_types=1);

namespace Zen\Http\Events;

use Throwable;
use Zen\Events\Event;
use Zen\Http\Request;
use Zen\Http\Response;

/**
 * Fired when an unhandled exception is caught during request handling,
 * allowing listeners to convert it into a custom response.
 */
class ExceptionEvent extends Event
{
    /**
     * A response set by a listener to suppress the exception.
     *
     * @var Response|null
     */
    private ?Response $response = null;

    /**
     * Creates the event with the originating request and the throwable.
     *
     * @param  Request   $request
     * @param  Throwable $throwable
     *
     * @return void
     */
    public function __construct(
        private readonly Request $request,
        private Throwable $throwable,
    )
    {
    }

    /**
     * Returns the request during which the exception was thrown.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Returns the throwable that triggered this event.
     *
     * @return Throwable
     */
    public function getThrowable(): Throwable
    {
        return $this->throwable;
    }

    /**
     * Replaces the throwable with a different exception or error.
     *
     * @param  Throwable $throwable
     *
     * @return void
     */
    public function setThrowable(Throwable $throwable): void
    {
        $this->throwable = $throwable;
    }

    /**
     * Attaches a response to suppress the exception and stops propagation.
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
