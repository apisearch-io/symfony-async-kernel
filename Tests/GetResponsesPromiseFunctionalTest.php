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

use Clue\React\Block;
use React\EventLoop\StreamSelectLoop;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class GetResponsesPromiseFunctionalTest.
 */
class GetResponsesPromiseFunctionalTest extends AsyncKernelFunctionalTest
{
    /**
     * Decorate configuration.
     *
     * @param array $configuration
     *
     * @return array
     */
    protected static function decorateConfiguration(array $configuration): array
    {
        $configuration = parent::decorateConfiguration($configuration);
        $configuration['services']['listener'] = [
            'class' => Listener::class,
            'tags' => [
                [
                    'name' => 'kernel.event_listener',
                    'event' => 'kernel.async_request',
                    'method' => 'handleGetResponsePromiseB',
                ],
                [
                    'name' => 'kernel.event_listener',
                    'event' => 'kernel.async_request',
                    'method' => 'handleGetResponsePromiseA',
                ],
            ],
        ];

        return $configuration;
    }

    /**
     * Everything should work as before in the world of sync requests.
     */
    public function testSyncKernel()
    {
        $loop = new StreamSelectLoop();
        $request = new Request([], [], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/promise',
        ]);

        $promise = self::$kernel
            ->handleAsync($request)
            ->then(function (Response $response) {
                $this->assertEquals(
                    'B',
                    $response->getContent()
                );
            });

        $loop->run();
        Block\await($promise, $loop);
    }
}
