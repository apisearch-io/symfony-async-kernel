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

use Exception;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;
use React\Promise\RejectedPromise;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Controller.
 */
class Controller
{
    /**
     * Return value.
     *
     * @return Response
     */
    public function getValue(): Response
    {
        return new Response('X');
    }

    /**
     * Return fulfilled promise.
     *
     * @return PromiseInterface
     */
    public function getPromise(): PromiseInterface
    {
        return new FulfilledPromise(new Response('Y'));
    }

    /**
     * Throw exception.
     *
     * @throws Exception
     */
    public function throwException()
    {
        throw new Exception('E1');
    }

    /**
     * Return rejected promise.
     *
     * @return PromiseInterface
     */
    public function getPromiseException(): PromiseInterface
    {
        return new RejectedPromise(new Exception('E2'));
    }

    /**
     * Return array.
     *
     * @return array
     */
    public function getSimpleResult(): array
    {
        return ['a', 'b'];
    }
}
