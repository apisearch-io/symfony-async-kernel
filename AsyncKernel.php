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

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AsyncHttpKernelNeededException;

/**
 * Class AsyncKernel.
 */
abstract class AsyncKernel extends Kernel implements CompilerPassInterface
{
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
        $this->boot();

        $httpKernel = $this->getHttpKernel();
        if (!$httpKernel instanceof AsyncHttpKernel) {
            throw new AsyncHttpKernelNeededException('In order to use this AsyncKernel, you need to have the HttpAsyncKernel installed');
        }

        return $httpKernel->handleAsync(
            $request,
            $type,
            $catch
        );
    }

    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->has('event_dispatcher')) {
            $container
                ->getDefinition('event_dispatcher')
                ->setClass(AsyncEventDispatcher::class);
        }

        if ($container->has('http_kernel')) {
            $container
                ->getDefinition('http_kernel')
                ->setClass(AsyncHttpKernel::class);
        }

        if (!$container->has('reactphp.event_loop')) {
            $loop = new Definition(LoopInterface::class);
            $loop->setSynthetic(true);
            $loop->setPublic(true);
            $container->setDefinition('reactphp.event_loop', $loop);
        }

        if ($container->has('reactphp.event_loop')) {
            $container->setAlias(LoopInterface::class, 'reactphp.event_loop');
        }
    }
}
