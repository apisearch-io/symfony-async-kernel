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

/**
 * Class AsyncKernelEvents.
 */
class AsyncKernelEvents
{
    /**
     * The ASYNC_REQUEST event occurs at the very beginning of request
     * dispatching.
     *
     * This event allows you to create a promise for a request before any
     * other code in the framework is executed. Resolving this Promise, you will
     * get a Response
     *
     * @Event("Symfony\Component\HttpKernel\Event\GetResponsePromiseEvent")
     */
    const ASYNC_REQUEST = 'kernel.async_request';

    /**
     * The ASYNC_EXCEPTION event occurs when an uncaught exception appears.
     *
     * This event allows you to create a response for a thrown exception or
     * to modify the thrown exception.
     *
     * @Event("Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent")
     */
    const ASYNC_EXCEPTION = 'kernel.async_exception';

    /**
     * The RESPONSE event occurs once a response was created for
     * replying to a request.
     *
     * This event allows you to modify or replace the response that will be
     * replied.
     *
     * @Event("Symfony\Component\HttpKernel\Event\FilterResponseEvent")
     */
    const ASYNC_RESPONSE = 'kernel.async_response';
}
