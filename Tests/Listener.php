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

namespace Symfony\Component\HttpKernel\Tests;

use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

/**
 * Class Listener.
 */
class Listener
{
    /**
     * Handle get Response.
     *
     * @param GetResponseEvent $event
     *
     * @return PromiseInterface
     */
    public function handleGetResponsePromiseA(GetResponseEvent $event)
    {
        return (new FulfilledPromise())
            ->then(function () use ($event) {
                $event->setResponse(new Response('A'));
            });
    }

    /**
     * Handle get Response.
     *
     * @param GetResponseEvent $event
     *
     * @return PromiseInterface
     */
    public function handleGetResponsePromiseB(GetResponseEvent $event)
    {
        return (new FulfilledPromise())
            ->then(function () use ($event) {
                $event->setResponse(new Response('B'));
            });
    }

    /**
     * Handle get Response.
     *
     * @param GetResponseEvent $event
     */
    public function handleGetResponsePromiseNothing(GetResponseEvent $event)
    {
    }

    /**
     * Handle get Exception.
     *
     * @param GetResponseForExceptionEvent $event
     */
    public function handleGetExceptionNothing(GetResponseForExceptionEvent $event)
    {
    }

    /**
     * Handle get Exception.
     *
     * @param GetResponseForExceptionEvent $event
     *
     * @return PromiseInterface
     */
    public function handleGetExceptionA(GetResponseForExceptionEvent $event)
    {
        return (new FulfilledPromise())
            ->then(function () use ($event) {
                $event->setResponse(new Response('EXC', 404));
            });
    }

    /**
     * Handle get Response 1.
     *
     * @param GetResponseEvent $event
     *
     * @return PromiseInterface
     */
    public function handleGetResponsePromise1(GetResponseEvent $event): PromiseInterface
    {
        return
            (new FulfilledPromise())
                ->then(function () {
                    $_GET['partial'] .= '1';
                });
    }

    /**
     * Handle get Response 1.
     *
     * @param GetResponseEvent $event
     */
    public function handleGetResponsePromise2(GetResponseEvent $event)
    {
        $_GET['partial'] .= '2';
    }

    /**
     * Handle get Response 1.
     *
     * @param GetResponseEvent $event
     */
    public function handleGetResponsePromise3(GetResponseEvent $event)
    {
        $_GET['partial'] .= '3';
    }

    /**
     * Handle view.
     *
     * @param GetResponseForControllerResultEvent $event
     */
    public function handleView(GetResponseForControllerResultEvent $event)
    {
        $event->setResponse(new JsonResponse($event->getControllerResult()));
    }
}
