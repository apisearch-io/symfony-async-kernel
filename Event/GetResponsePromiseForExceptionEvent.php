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

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Class GetResponsePromiseForExceptionEvent.
 */
class GetResponsePromiseForExceptionEvent extends GetResponsePromiseEvent
{
    /**
     * The exception object.
     *
     * @var \Exception
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
     * @param Exception           $exception
     */
    public function __construct(
        HttpKernelInterface $kernel,
        Request $request,
        int $requestType,
        Exception $exception
    ) {
        parent::__construct($kernel, $request, $requestType);

        $this->setException($exception);
    }

    /**
     * Returns the thrown exception.
     *
     * @return \Exception The thrown exception
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * Replaces the thrown exception.
     *
     * This exception will be thrown if no response is set in the event.
     *
     * @param \Exception $exception The thrown exception
     */
    public function setException(\Exception $exception)
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
