<?php

declare(strict_types=1);

namespace Zen\Http;

use Throwable;
use Zen\Application;
use Zen\Events\EventDispatcher;
use Zen\Http\Events\ExceptionEvent;
use Zen\Http\Events\RequestEvent;
use Zen\Http\Events\ResponseEvent;
use Zen\Http\Events\TerminateEvent;
use Zen\Middleware\Stack;
use Zen\Routing\MethodNotAllowedException;

/**
 * Handles an incoming HTTP request by routing it through middleware and
 * route handlers, emitting lifecycle events, and converting exceptions to
 * responses.
 */
class Kernel
{
    /**
     * Creates the kernel with the application instance.
     *
     * @param  Application $app
     *
     * @return void
     */
    public function __construct(private readonly Application $app)
    {
    }

    /**
     * Dispatches the request and returns a response, catching any unhandled
     * throwable and delegating to the exception handler.
     *
     * @param  Request $request
     *
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $dispatcher = $this->app['events'];

        try {
            return $this->dispatch($request, $dispatcher);
        } catch (Throwable $e) {
            return $this->handleException($e, $request, $dispatcher);
        }
    }

    /**
     * Fires the terminate event after the response has been sent.
     *
     * @param  Request  $request
     * @param  Response $response
     *
     * @return void
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->app['events']->dispatch(new TerminateEvent($request, $response));
    }

    /**
     * Resolves the route, runs the middleware stack, and fires request /
     * response events.
     *
     * @param  Request         $request
     * @param  EventDispatcher $dispatcher
     *
     * @return Response
     */
    private function dispatch(Request $request, EventDispatcher $dispatcher): Response
    {
        $requestEvent = new RequestEvent($request);
        $dispatcher->dispatch($requestEvent);

        if ($requestEvent->hasResponse()) {
            return $this->filterResponse($requestEvent->getResponse(), $request, $dispatcher);
        }

        [$route, $params] = $this->app['router']->match($request->getMethod(), $request->getPath());

        $request->setRouteParams($params);

        $core = static function (Request $request, Response $response) use ($route): Response {
            $callable = $route->getCallable();
            $returned = $callable($request, $response);

            return $returned instanceof Response ? $returned : $response;
        };

        $stack = new Stack();
        $stack->add(...$this->app->resolveMiddlewareList([
            ...$this->app->getMiddleware(),
            ...$route->getMiddleware(),
        ]));

        return $this->filterResponse(
            $stack->run($request, new Response(), $core),
            $request,
            $dispatcher,
        );
    }

    /**
     * Fires the response event and returns the (possibly replaced) response.
     *
     * @param  Response        $response
     * @param  Request         $request
     * @param  EventDispatcher $dispatcher
     *
     * @return Response
     */
    private function filterResponse(Response $response, Request $request, EventDispatcher $dispatcher): Response
    {
        $event = new ResponseEvent($request, $response);
        $dispatcher->dispatch($event);

        return $event->getResponse();
    }

    /**
     * Converts a throwable to a response using registered error handlers or
     * rethrows if no handler is found.
     *
     * @param  Throwable       $error
     * @param  Request         $request
     * @param  EventDispatcher $dispatcher
     *
     * @throws Throwable If no matching error handler is registered.
     *
     * @return Response
     */
    private function handleException(Throwable $error, Request $request, EventDispatcher $dispatcher): Response
    {
        $event = new ExceptionEvent($request, $error);
        $dispatcher->dispatch($event);

        if ($event->hasResponse()) {
            return $this->filterResponse($event->getResponse(), $request, $dispatcher);
        }

        if ($error instanceof MethodNotAllowedException) {
            $status  = 405;
            $handler = $this->app->getErrorHandler($status);

            return $this->filterResponse(
                $handler !== null
                    ? $handler($request, new Response(), $error)
                    : (new Response())->status($status)->header('Allow', implode(', ', $error->getAllowed())),
                $request,
                $dispatcher,
            );
        }

        if ($error instanceof HttpException) {
            $status  = $error->getStatusCode();
            $handler = $this->app->getErrorHandler($status);

            return $this->filterResponse(
                $handler !== null
                    ? $handler($request, new Response(), $error)
                    : (new Response())->status($status),
                $request,
                $dispatcher,
            );
        }

        $handler = $this->app->getErrorHandler(500);

        if ($handler !== null) {
            return $this->filterResponse(
                $handler($request, new Response(), $error),
                $request,
                $dispatcher,
            );
        }

        throw $error;
    }
}
