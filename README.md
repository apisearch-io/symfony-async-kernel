# Symfony Async Kernel Adapter

This package provides async features to the Symfony Kernel. This implementation
uses [ReactPHP Promise](https://github.com/reactphp/promise) implementation for
this purposes.

> This package is still on progress. Subscribe to the repository to be aware of
> updates and follow future stable versions

> IMPORTANT !!!  
> **You can take a look at the first Symfony example on top of this package at
> [Symfony and ReactPHP Demo](https://github.com/apisearch-io/symfony-react-demo)**

> At this moment, and before this Kernel is tested properly, Twig is not
> supported yet. Use this kernel to serve APIs

## Installation

You can install the package with composer. This is a PHP Library, so installing
this repository will not change your original project behavior.

```yml
{
  "require": {
    "apisearch-io/symfony-async-http-kernel": "dev-master"
  }
}
```

Once you have the package under your vendor folder, now it's time to turn you
application asynchronous-friendly, bu changing your kernel implementation, from
the Symfony regular HTTP Kernel class, to the new Async one.

```php
use Symfony\Component\HttpKernel\AsyncKernel as BaseKernel;
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
```

## Controllers

Your controller will be able to return a Promise now. It is mandatory to do
that? Non-blocking operations are always optional, so if you build your domain
blocking, this is going to work as well.

```php
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
}
```

Both controller actions are correct.

## Event Dispatcher

Going asynchronous has some intrinsic effects, and one of these effects is that
event dispatcher has to work a little bit different. If you base all your domain
on top of Promises, your event listeners can't be different. The events
dispatched are the same, but the listeners attached to them must change a little
bit the implementation

An event listener can return a Promise. Everything inside this promise will be
executed once the Promise is executed, and everything outside the promise will
be executed at the beginning of all listeners, just before the first one is
fulfilled.

```php
/**
 * Handle get Response.
 *
 * @param GetResponseEvent $event
 *
 * @return PromiseInterface
 */
public function handleGetResponsePromiseA(GetResponseEvent $event)
{
    $promise = (new FulfilledPromise())
        ->then(function () use ($event) {
        
            // This line is executed after the last event listener promise is
            // fulfilled
        
            $event->setResponse(new Response('A'));
        });
        
    // This line is executed before the first event listener promise is
    // fulfilled
        
    return $promise;
}
```

## ReactPHP Server

In order to use ReactPHP in the whole application you must use a ReactPHP based
HTTP Server that uses this Async Kernel. You can take a look at the Symfony
ReactPHP Server and start a new server

- [Symfony ReactPHP Server](https://github.com/apisearch-io/symfony-react-server)

You can take a look at the 
[Symfony + ReactPHP Series](https://medium.com/@apisearch/symfony-and-reactphp-series-82082167f6fb)
in order to understand a little bit better the rationale behind using this
server and Promises in your domain.

```bash
php vendor/bin/server 0.0.0.0:8100 --non-blocking
```
