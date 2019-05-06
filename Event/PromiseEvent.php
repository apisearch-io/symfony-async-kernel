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

/**
 * Class PromiseEvent.
 */
class PromiseEvent extends KernelEvent
{
    /**
     * @var PromiseInterface
     *
     * Promise
     */
    protected $promise;

    /**
     * Returns the response object.
     *
     * @return PromiseInterface|null
     */
    public function getPromise(): ? PromiseInterface
    {
        return $this->promise;
    }

    /**
     * Sets a promise and stops event propagation.
     *
     * @param PromiseInterface $promise
     */
    public function setPromise(PromiseInterface $promise)
    {
        $this->promise = $promise;

        $this->stopPropagation();
    }

    /**
     * Returns whether a promise was set.
     *
     * @return bool
     */
    public function hasPromise(): bool
    {
        return null !== $this->promise;
    }
}
