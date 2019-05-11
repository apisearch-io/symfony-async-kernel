<?php

/*
 * This file is part of the Symfony Async Kernel
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author Marc Morera <yuhu@mmoreram.com>
 */

declare(strict_types=1);

namespace Symfony\Component\HttpKernel;

use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;
use React\Promise\RejectedPromise;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponsePromiseEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponsePromiseEvent;
use Symfony\Component\HttpKernel\Event\GetResponsePromiseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\PromiseEvent;
use Symfony\Component\HttpKernel\Exception\AsyncEventDispatcherNeededException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Class AsyncHttpKernel.
 */
class AsyncHttpKernel extends HttpKernel
{
    /**
     * @var AsyncEventDispatcher
     */
    protected $dispatcher;
    protected $resolver;
    protected $requestStack;
    private $argumentResolver;

    /**
     * AsyncHttpKernel constructor.
     *
     * @param AsyncEventDispatcher           $dispatcher
     * @param ControllerResolverInterface    $resolver
     * @param RequestStack|null              $requestStack
     * @param ArgumentResolverInterface|null $argumentResolver
     */
    public function __construct(
        AsyncEventDispatcher $dispatcher,
        ControllerResolverInterface $resolver,
        RequestStack $requestStack = null,
        ArgumentResolverInterface $argumentResolver = null
    ) {
        $this->dispatcher = $dispatcher;
        $this->resolver = $resolver;
        $this->requestStack = $requestStack ?: new RequestStack();
        $this->argumentResolver = $argumentResolver;

        if (null === $this->argumentResolver) {
            $this->argumentResolver = new ArgumentResolver();
        }

        parent::__construct(
            $dispatcher,
            $resolver,
            $requestStack,
            $argumentResolver
        );
    }

    /**
     * Handles a Request to convert it to a Response.
     *
     * When $catch is true, the implementation must catch all exceptions
     * and do its best to convert them to a Response instance.
     *
     * @param Request $request A Request instance
     * @param int     $type    The type of the request
     *                         (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     * @param bool    $catch   Whether to catch exceptions or not
     *
     * @return PromiseInterface
     *
     * @throws \Exception When an Exception occurs during processing
     */
    public function handleAsync(
        Request $request,
        $type = self::MASTER_REQUEST,
        $catch = true
    ): PromiseInterface {
        if (!$this->dispatcher instanceof AsyncEventDispatcher) {
            return new RejectedPromise(new AsyncEventDispatcherNeededException());
        }

        $request->headers->set('X-Php-Ob-Level', ob_get_level());

        return
            $this->handleAsyncRaw(
                $request,
                $type
            )
            ->then(null,
                function (Throwable $exception) use ($request, $type, $catch) {
                    if ($exception instanceof RequestExceptionInterface) {
                        $exception = new BadRequestHttpException($exception->getMessage(), $exception);
                    }

                    if (false === $catch) {
                        $this->finishRequestPromise($request, $type);

                        throw $exception;
                    }

                    return $this->handleExceptionPromise($exception, $request, $type);
                }
            );
    }

    /**
     * Handles a request to convert it to a response.
     *
     * Exceptions are not caught.
     *
     * @param Request $request A Request instance
     * @param int     $type    The type of the request (one of HttpKernelInterface::MASTER_REQUEST or HttpKernelInterface::SUB_REQUEST)
     *
     * @return PromiseInterface
     *
     * @throws \LogicException                     If one of the listener does not behave as expected
     * @throws NotFoundHttpException               When controller cannot be found
     * @throws AsyncEventDispatcherNeededException
     */
    private function handleAsyncRaw(
        Request $request,
        int $type = self::MASTER_REQUEST
    ): PromiseInterface {
        $dispatcher = $this->dispatcher;

        // request
        $event = new GetResponseEvent($this, $request, $type);
        $this->dispatcher->dispatch(KernelEvents::REQUEST, $event);

        if ($event->hasResponse()) {
            return $this
                ->filterResponsePromise(new FulfilledPromise($event->getResponse()), $request, $type);
        }

        $this->requestStack->push($request);
        $event = new GetResponsePromiseEvent($this, $request, $type);

        return $dispatcher
            ->asyncDispatch(AsyncKernelEvents::ASYNC_REQUEST, $event)
            ->then(function (PromiseEvent $event) use ($request, $type) {
                if ($event->hasPromise()) {
                    return $event
                        ->getPromise()
                        ->then(function ($response) use ($request, $type) {
                            return $response instanceof Response
                                ? $this->filterResponsePromise(
                                    new FulfilledPromise($response),
                                    $request,
                                    $type
                                )
                                : $this->callAsyncController($request, $type);
                        });
                }

                return $this->callAsyncController($request, $type);
            });
    }

    /**
     * Call async controller.
     *
     * @param Request $request
     * @param int     $type
     *
     * @return PromiseInterface
     */
    private function callAsyncController(Request $request, int $type): PromiseInterface
    {
        if (false === $controller = $this->resolver->getController($request)) {
            throw new NotFoundHttpException(
                sprintf('Unable to find the controller for path "%s". The route is wrongly configured.', $request->getPathInfo())
            );
        }

        $event = new FilterControllerEvent($this, $controller, $request, $type);
        $this->dispatcher->dispatch(KernelEvents::CONTROLLER, $event);
        $controller = $event->getController();

        // controller arguments
        $arguments = $this->argumentResolver->getArguments($request, $controller);

        $event = new FilterControllerArgumentsEvent($this, $controller, $arguments, $request, $type);
        $this->dispatcher->dispatch(KernelEvents::CONTROLLER_ARGUMENTS, $event);
        $controller = $event->getController();
        $arguments = $event->getArguments();

        /**
         * Call controller.
         *
         * @var PromiseInterface
         */
        $promise = $controller(...$arguments);

        return $this->filterResponsePromise($promise, $request, $type);
    }

    /**
     * Filters a response object.
     *
     * @param PromiseInterface $promise
     * @param Request          $request
     * @param int              $type
     *
     * @return PromiseInterface
     *
     * @throws \RuntimeException if the passed object is not a Response instance
     */
    private function filterResponsePromise(PromiseInterface $promise, Request $request, int $type)
    {
        $event = new FilterResponsePromiseEvent($this, $request, $type, $promise);

        return $this
            ->dispatcher
            ->asyncDispatch(AsyncKernelEvents::ASYNC_RESPONSE, $event)
            ->then(function (PromiseEvent $event) use ($request, $type, $promise) {
                $this->finishRequestPromise($request, $type);

                return $event->hasPromise()
                    ? $event->getPromise()
                    : $promise;
            });
    }

    /**
     * COPY / PASTE methods.
     */

    /**
     * Publishes the finish request event, then pop the request from the stack.
     *
     * Note that the order of the operations is important here, otherwise
     * operations such as {@link RequestStack::getParentRequest()} can lead to
     * weird results.
     */
    private function finishRequestPromise(Request $request, int $type)
    {
        $this->dispatcher->dispatch(KernelEvents::FINISH_REQUEST, new FinishRequestEvent($this, $request, $type));
        $this->requestStack->pop();
    }

    /**
     * Handles an exception by trying to convert it to a Response.
     *
     * @param Throwable $exception
     * @param Request   $request
     * @param int       $type
     *
     * @return PromiseInterface
     *
     * @throws \Throwable
     */
    private function handleExceptionPromise(
        Throwable $exception,
        Request $request,
        int $type
    ): PromiseInterface {
        $event = new GetResponsePromiseForExceptionEvent($this, $request, $type, $exception);
        $promise = $this
            ->dispatcher
            ->asyncDispatch(AsyncKernelEvents::ASYNC_EXCEPTION, $event)
            ->then(function (GetResponsePromiseForExceptionEvent $event) use ($request, $type) {
                $exception = $event->getException();
                if (!$event->hasPromise()) {
                    $this->finishRequestPromise($request, $type);

                    throw $event->getException();
                } else {
                    return $event
                        ->getPromise()
                        ->then(function (Response $response) use ($request, $type, $event, $exception) {
                            // the developer asked for a specific status code
                            if (!$event->isAllowingCustomResponseCode() && !$response->isClientError() && !$response->isServerError() && !$response->isRedirect()) {
                                // ensure that we actually have an error response
                                if ($exception instanceof HttpExceptionInterface) {
                                    // keep the HTTP status code and headers
                                    $response->setStatusCode($exception->getStatusCode());
                                    $response->headers->add($exception->getHeaders());
                                } else {
                                    $response->setStatusCode(500);
                                }
                            }

                            return $response;
                        });
                }
            });

        return $this->filterResponsePromise($promise, $request, $type);
    }
}
