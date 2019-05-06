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

namespace Symfony\Component\HttpKernel\BaseBundle;

use Mmoreram\SymfonyBundleDependencies\BundleDependenciesResolver;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\AsyncKernel;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Symfony\Component\Yaml\Yaml;

/**
 * Class BaseAsyncKernel.
 */
class BaseAsyncKernel extends AsyncKernel
{
    use MicroKernelTrait;
    use BundleDependenciesResolver;

    /**
     * @var string[]
     *
     * Bundle array
     */
    private $bundlesToLoad;

    /**
     * @var array[]
     *
     * Configuration
     */
    private $configuration;

    /**
     * @var array[]
     *
     * Routes
     */
    private $routes;

    /**
     * @var string
     *
     * Root dir prefix
     */
    private $rootDirPrefix;

    /**
     * BaseKernel constructor.
     *
     * @param string[] $bundlesToLoad
     * @param array[]  $configuration
     * @param array[]  $routes
     * @param string   $environment
     * @param bool     $debug
     * @param string   $rootDirPrefix
     */
    public function __construct(
        array $bundlesToLoad,
        array $configuration = [],
        array $routes = [],
        string $environment = 'test',
        bool $debug = false,
        string $rootDirPrefix = null
    ) {
        $this->rootDirPrefix = $rootDirPrefix;
        $this->bundlesToLoad = $bundlesToLoad;
        $this->routes = $routes;
        $this->configuration = array_merge(
            [
                'parameters' => [
                    'kernel.secret' => '1234',
                ],
            ],
            $configuration
        );

        parent::__construct($environment, $debug);
    }

    /**
     * Returns an array of bundles to register.
     *
     * @return BundleInterface[] An array of bundle instances
     */
    public function registerBundles()
    {
        return $this->getBundleInstances(
            $this,
            $this->bundlesToLoad
        );
    }

    /**
     * Configures the container.
     *
     * You can register extensions:
     *
     * $c->loadFromExtension('framework', array(
     *     'secret' => '%secret%'
     * ));
     *
     * Or services:
     *
     * $c->register('halloween', 'FooBundle\HalloweenProvider');
     *
     * Or parameters:
     *
     * $c->setParameter('halloween', 'lot of fun');
     *
     * @param ContainerBuilder $c
     * @param LoaderInterface  $loader
     */
    protected function configureContainer(
        ContainerBuilder $c,
        LoaderInterface $loader
    ) {
        $yamlContent = Yaml::dump($this->configuration);
        $filePath = sys_get_temp_dir().'/base-test-'.rand(1, 9999999).'.yml';
        file_put_contents($filePath, $yamlContent);
        $loader->load($filePath);
        unlink($filePath);
    }

    /**
     * Add or import routes into your application.
     *
     *     $routes->import('config/routing.yml');
     *     $routes->add('/admin', 'AppBundle:Admin:dashboard', 'admin_dashboard');
     *
     * @param RouteCollectionBuilder $routes
     */
    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        foreach ($this->routes as $route) {
            is_array($route)
                ? $routes->add(
                    $route[0],
                    $route[1],
                    $route[2]
                )
                : $routes->import($route);
        }
    }

    /**
     * Gets the application root dir.
     *
     * @return string The application root dir
     */
    public function getRootDir()
    {
        if (!is_null($this->rootDirPrefix)) {
            return $this->rootDirPrefix;
        }

        $bundles = array_map(function ($bundle) {
            return is_object($bundle)
                ? get_class($bundle)
                : $bundle;
        }, $this->bundlesToLoad);
        $config = $this->configuration;
        $routes = $this->routes;
        sort($bundles);
        sort($routes);
        $this->sortArray($config);

        return sys_get_temp_dir().'/base-kernel/'.'kernel-'.(
            hash(
                'md5',
                json_encode([
                    $bundles,
                    $config,
                    $routes,
                ])
            )
        );
    }

    /**
     * Sort array's first level, taking in account if associative array or
     * sequential array.
     *
     * @param mixed $element
     */
    private function sortArray(&$element)
    {
        if (is_array($element)) {
            array_walk($element, [$this, 'sortArray']);
            array_key_exists(0, $element)
                ? sort($element)
                : ksort($element);
        }
    }

    /**
     * Gets the application root dir (path of the project's composer file).
     *
     * @return string The project root dir
     */
    public function getProjectDir()
    {
        if (file_exists(__DIR__.'/../../../../composer.json')) {
            return realpath(__DIR__.'/../../../..');
        }

        return parent::getProjectDir();
    }
}
