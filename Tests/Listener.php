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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponsePromiseEvent;
use Symfony\Component\HttpKernel\Event\GetResponsePromiseForExceptionEvent;

/**
 * Class Listener.
 */
class Listener
{
    /**
     * Handle get Response.
     *
     * @param GetResponsePromiseEvent $event
     */
    public function handleGetResponsePromiseA(GetResponsePromiseEvent $event)
    {
        $event->setPromise(
            new FulfilledPromise(
                new Response('A')
            )
        );
    }

    /**
     * Handle get Response.
     *
     * @param GetResponsePromiseEvent $event
     */
    public function handleGetResponsePromiseB(GetResponsePromiseEvent $event)
    {
        $event->setPromise(
            new FulfilledPromise(
                new Response('B')
            )
        );
    }

    /**
     * Handle get Response.
     *
     * @param GetResponsePromiseEvent $event
     */
    public function handleGetResponsePromiseNothing(GetResponsePromiseEvent $event)
    {
    }

    /**
     * Handle get Exception.
     *
     * @param GetResponsePromiseForExceptionEvent $event
     */
    public function handleGetExceptionNothing(GetResponsePromiseForExceptionEvent $event)
    {
    }

    /**
     * Handle get Exception.
     *
     * @param GetResponsePromiseForExceptionEvent $event
     */
    public function handleGetExceptionA(GetResponsePromiseForExceptionEvent $event)
    {
        $event->setPromise(
            new FulfilledPromise(
                new Response('EXC')
            )
        );
    }
}
