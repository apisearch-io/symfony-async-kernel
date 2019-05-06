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

namespace Symfony\Component\HttpKernel\Event;

use React\Promise\PromiseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Class FilterResponsePromiseEvent.
 */
class FilterResponsePromiseEvent extends PromiseEvent
{
    /**
     * FilterResponsePromiseEvent constructor.
     *
     * @param HttpKernelInterface $kernel
     * @param Request             $request
     * @param int                 $requestType
     * @param PromiseInterface    $promise
     */
    public function __construct(
        HttpKernelInterface $kernel,
        Request $request,
        int $requestType,
        PromiseInterface $promise
    ) {
        parent::__construct($kernel, $request, $requestType);

        $this->promise = $promise;
    }
}
