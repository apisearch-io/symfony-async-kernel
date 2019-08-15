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

use Exception;
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
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AsyncEventDispatcherNeededException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ControllerDoesNotReturnResponseException;
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

        $this->requestStack->push($request);
        $event = new GetResponseEvent($this, $request, $type);

        return $dispatcher
            ->asyncDispatch(KernelEvents::REQUEST, $event)
            ->then(function (GetResponseEvent $event) use ($request, $type) {
                return $event->hasResponse()
                    ? $this->filterResponsePromise(
                        $event->getResponse(),
                        $request,
                        $type
                    )
                    : $this->callAsyncController($request, $type);
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

        return (new FulfilledPromise())
            ->then(function () use ($controller, $arguments) {
                return $controller(...$arguments);
            })
            ->then(function ($response) use ($request, $type, $controller) {
                if (!$response instanceof Response) {
                    return $this->callAsyncView($request, $response, $controller, $type);
                }

                return $response;
            })
            ->then(function ($response) use ($request, $type) {
                return $this->filterResponsePromise($response, $request, $type);
            });
    }

    /**
     * Call async view.
     *
     * @param Request  $request
     * @param mixed    $response
     * @param callable $controller
     * @param int      $type
     *
     * @return PromiseInterface
     */
    private function callAsyncView(
        Request $request,
        $response,
        callable $controller,
        int $type
    ): PromiseInterface {
        return (new FulfilledPromise())
            ->then(function () use ($request, $response, $controller, $type) {
                $event = new GetResponseForControllerResultEvent($this, $request, $type, $response);
                $this->dispatcher->dispatch(KernelEvents::VIEW, $event);

                if ($event->hasResponse()) {
                    return $event->getResponse();
                } else {
                    $msg = sprintf('The controller must return a "Symfony\Component\HttpFoundation\Response" object but it returned %s.', $this->varToString($response));
                    // the user may have forgotten to return something
                    if (null === $response) {
                        $msg .= ' Did you forget to add a return statement somewhere in your controller?';
                    }

                    throw new ControllerDoesNotReturnResponseException($msg, $controller, __FILE__, __LINE__ - 17);
                }
            });
    }

    /**
     * Filters a response object.
     *
     * @param Response $response
     * @param Request  $request
     * @param int      $type
     *
     * @return PromiseInterface
     *
     * @throws \RuntimeException if the passed object is not a Response instance
     */
    private function filterResponsePromise(Response $response, Request $request, int $type)
    {
        $event = new FilterResponseEvent($this, $request, $type, $response);

        return $this
            ->dispatcher
            ->asyncDispatch(KernelEvents::RESPONSE, $event)
            ->then(function (FilterResponseEvent $event) use ($request, $type) {
                $this->finishRequestPromise($request, $type);

                return $event->getResponse();
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
     * @throws Exception
     */
    private function handleExceptionPromise(
        Throwable $exception,
        Request $request,
        int $type
    ): PromiseInterface {
        if (!$exception instanceof Exception) {
            $exception = new Exception(
                $exception->getMessage(),
                $exception->getCode()
            );
        }

        $event = new GetResponseForExceptionEvent($this, $request, $type, $exception);

        return $this
            ->dispatcher
            ->asyncDispatch(KernelEvents::EXCEPTION, $event)
            ->then(function (GetResponseForExceptionEvent $event) use ($request, $type) {
                $exception = $event->getException();
                if (!$event->hasResponse()) {
                    $this->finishRequestPromise($request, $type);

                    throw $event->getException();
                } else {
                    $response = $event->getResponse();
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
                }
            })
            ->then(function (Response $response) use ($request, $type) {
                return $this->filterResponsePromise($response, $request, $type);
            });
    }

    /**
     * Returns a human-readable string for the specified variable.
     */
    private function varToString($var): string
    {
        if (\is_object($var)) {
            return sprintf('an object of type %s', \get_class($var));
        }
        if (\is_array($var)) {
            $a = [];
            foreach ($var as $k => $v) {
                $a[] = sprintf('%s => ...', $k);
            }

            return sprintf('an array ([%s])', mb_substr(implode(', ', $a), 0, 255));
        }
        if (\is_resource($var)) {
            return sprintf('a resource (%s)', get_resource_type($var));
        }
        if (null === $var) {
            return 'null';
        }
        if (false === $var) {
            return 'a boolean value (false)';
        }
        if (true === $var) {
            return 'a boolean value (true)';
        }
        if (\is_string($var)) {
            return sprintf('a string ("%s%s")', mb_substr($var, 0, 255), mb_strlen($var) > 255 ? '...' : '');
        }
        if (is_numeric($var)) {
            return sprintf('a number (%s)', (string) $var);
        }

        return (string) $var;
    }
}
