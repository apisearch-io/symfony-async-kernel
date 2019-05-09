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

namespace Symfony\Component\HttpKernel\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\AsyncEventDispatcher;
use Symfony\Component\HttpKernel\AsyncHttpKernel;

/**
 * Class AsyncHttpKernelCompilerPass.
 */
class AsyncHttpKernelCompilerPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container)
    {
        $container
            ->getDefinition('event_dispatcher')
            ->setClass(AsyncEventDispatcher::class);

        $container
            ->getDefinition('http_kernel')
            ->setClass(AsyncHttpKernel::class);
    }
}
