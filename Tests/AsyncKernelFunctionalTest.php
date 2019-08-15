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

use Mmoreram\BaseBundle\Kernel\AsyncBaseKernel;
use Mmoreram\BaseBundle\Tests\BaseFunctionalTest;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\HttpKernel\AsyncEventDispatcher;
use Symfony\Component\HttpKernel\AsyncHttpKernel;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class AsyncKernelFunctionalTest.
 */
abstract class AsyncKernelFunctionalTest extends BaseFunctionalTest
{
    /**
     * Get kernel.
     *
     * @return KernelInterface
     */
    protected static function getKernel(): KernelInterface
    {
        $configuration = [
            'parameters' => [
                'kernel.secret' => 'gdfgfdgd',
            ],
            'framework' => [
                'test' => true,
            ],
            'services' => [
                '_defaults' => [
                    'autowire' => false,
                    'autoconfigure' => false,
                    'public' => true,
                ],
                'event_dispatcher' => [
                    'class' => AsyncEventDispatcher::class,
                    'tags' => [
                        ['name' => 'container.hot_path'],
                    ],
                ],
                'http_kernel' => [
                    'class' => AsyncHttpKernel::class,
                    'arguments' => [
                        '@event_dispatcher',
                        '@controller_resolver',
                        '@request_stack',
                        '@argument_resolver',
                    ],
                    'tags' => [
                        ['name' => 'container.hot_path'],
                    ],
                ],
            ],
        ];

        $routes = [
            [
                '/value',
                Controller::class.':getValue',
                'value',
            ],
            [
                '/promise',
                Controller::class.':getPromise',
                'promise',
            ],
            [
                '/exception',
                Controller::class.':throwException',
                'exception',
            ],
            [
                '/promise-exception',
                Controller::class.':getPromiseException',
                'promise-exception',
            ],
            [
                '/simple-result',
                Controller::class.':getSimpleResult',
                'simple-result',
            ],
        ];

        return new AsyncBaseKernel(
            [
                FrameworkBundle::class,
            ],
            static::decorateConfiguration($configuration),
            static::decorateRoutes($routes),
            'dev', false
        );
    }

    /**
     * Decorate configuration.
     *
     * @param array $configuration
     *
     * @return array
     */
    protected static function decorateConfiguration(array $configuration): array
    {
        return $configuration;
    }

    /**
     * Decorate routes.
     *
     * @param array $routes
     *
     * @return array
     */
    protected static function decorateRoutes(array $routes): array
    {
        return $routes;
    }
}
