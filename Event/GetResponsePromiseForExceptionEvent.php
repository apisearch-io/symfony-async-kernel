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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

/**
 * Class GetResponsePromiseForExceptionEvent.
 */
class GetResponsePromiseForExceptionEvent extends GetResponsePromiseEvent
{
    /**
     * The exception object.
     *
     * @var Throwable
     */
    private $exception;

    /**
     * @var bool
     */
    private $allowCustomResponseCode = false;

    /**
     * GetResponsePromiseForExceptionEvent constructor.
     *
     * @param HttpKernelInterface $kernel
     * @param Request             $request
     * @param int                 $requestType
     * @param Throwable           $exception
     */
    public function __construct(
        HttpKernelInterface $kernel,
        Request $request,
        int $requestType,
        Throwable $exception
    ) {
        parent::__construct($kernel, $request, $requestType);

        $this->setException($exception);
    }

    /**
     * Returns the thrown exception.
     *
     * @return Throwable
     */
    public function getException(): Throwable
    {
        return $this->exception;
    }

    /**
     * Replaces the thrown exception.
     *
     * This exception will be thrown if no response is set in the event.
     *
     * @param Throwable $exception
     */
    public function setException(Throwable $exception)
    {
        $this->exception = $exception;
    }

    /**
     * Mark the event as allowing a custom response code.
     */
    public function allowCustomResponseCode()
    {
        $this->allowCustomResponseCode = true;
    }

    /**
     * Returns true if the event allows a custom response code.
     *
     * @return bool
     */
    public function isAllowingCustomResponseCode()
    {
        return $this->allowCustomResponseCode;
    }
}
