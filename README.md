# Symfony Async Kernel Adapter

This package provides async features to the Symfony Kernel. This implementation
uses [ReactPHP Promise](https://github.com/reactphp/promise) implementation for
this purposes.

> This package is still on progress. Subscribe to the repository to be aware of
> updates and follow future stable versions

## POC

This package is part of a Proof of Concept with the main goal of working with
Promises in Symfony. Before that, let's check where are we. Let's start from the
beginning and talk about what Symfony is made for, the regular usage is usually
used for and how we can really improve **SO MUCH** the performance of our
applications.

> As a disclaimer, this POC is focused on APIs, so we are not adding here any
> Twig support. Once the package becomes tested and a little bit stable, then we
> will add this feature.

## Symfony on top of Apache / Nginx

When we talk about Symfony, we usually talk about a small stack of technologies
that, and not only with Symfony but with almost every single PHP 7 framework and
project.

We can think about Apache or Nginx as a server, Doctrine as a Database layer and
maybe Redis as cache or key-value persistent storage. That's great. If we could
ask every single Symfony project in this world, we would find mainly this
configuration.

Let's check this configuration in a performance point of view. And when we mean
performance, we mean about CPU consumption, Memory usage and Response times.
Everything matters here, right? At least, everything should matter in terms of
software economy.

- **Step 1** - Apache server receives a new request and sends it directly to the
app.php file.
- **Step 2** - Symfony kernel boots. That means that, in the better of the cases, the
container is cached properly. The Kernel is created *(once again)*, the 
container configuration is loaded from this cache, and a new Request object is
created with the data received from Apache.
- **Step 3** - The Kernel handles this Request, waiting for a Response.
- **Step 4** - Some framework magic (resolve controller, resolve dispatch some
events...)
- **Step 5** - We call the controller entry point. Remember that we MUST return a
Response instance (remember that we don't use Views here, so we discard
returning an array here. Anyway, would be the same).
- **Step 6** - We do our logic. For example, we call a repository to get an array of
values from Redis.
- **Step 7** - Redis returns an array of values, where the controller return a new
Response with these values, where the Kernel, after some extra event dispatches,
return this Response to Apache, which return the response to the final client.

This is one natural Request / Response workflow in one of our applications.
Fast, isn't it? Let's check in terms of performance.

- **Step 1** - We must have Apache server installed. By adding Apache as a man in
the middle, we spend some time. Even if it's **1ms**, we will see later that
each single **1ms** can be so much important here.
- **Step 2** - Symfony kernel is booted every time. Once and again. Every single
request. Let's say... **15ms**? **20ms?** Something like that. Let's say
**15ms** being SO optimists.
- **Step 7** - Imagine a Redis call as a representation of any external call. This
could be a redis one (fast one), or an HTTP one, slow one. This action will
last the time this operation lasts. Let's say **50ms**.

If we consider that the PHP application can be around **3ms**, let's calculate
the total time of our requests from the final user point of view. This time is
**70ms** per thread. Because both Apache or Nginx can manage several threads at
the same time, we can say that the final response will be around 70ms per
request. I would say not bad, but for people that respect performance, 70ms are
very bad numbers.

## Symfony on top of ReactPHP

The main goal of this layer is to delete Apache. Why? Well, if you review the
numbers below, you will find that booting the kernel each time is so expensive.
We said around **15ms** as a symbolic number, but these numbers can easily
increase so much depending on the server.

The main goal is to be sure that we keep the kernel built and running forever
and ever, listening new Requests, and returning new Responses.

For this, we must know a project called [ReactPHP](https://github.com/reactphp).
It is important to know that project because they have a nice HTTP Server to
handle requests and return responses. Is based on Promises, but, as always, you
don't have to work with Promises to work with Promises.

By using this server, we would remove the **Step 1** and the **Step 2**. The
kernel is booted only once (can really be booted before the first request), and
after that, each requests would start at **Step 3 **.

Time elapsed? Well, **54ms** per each server. In that case, the server would be
the same PHP. The problem? Well, we only have one single thread here, so this
server would be completely blocking here, allowing only to return around 20
requests per second (while the HTTP blocking call is not resolved, the thread is
waiting for it).

We can easily solve this problem by emulating what Apache does internally,
having multiple threads or workers, and balancing between them as long as they
are not available.

You can check a project called [PHP-PM](https://github.com/php-pm/php-pm). This
server creates as many ReactPHP servers you need and use them all in a smart
way.

Performance review. Well. If you have so many requests per second, and you have
very hard I/O operations, you might want to add many workers there. Otherwise,
you will experiment timeouts. Remember that you will still having several
threads with blocking calls. A good approach, but not as good as a person that
cares about performance while doing PHP wants to see.

## Symfony & Promises

So what about Promises? Can I work with Symfony and Promises at the same time?
Yes you can. And is very easy. There is only one condition here. You can async
everything you want, even I/O operations by using some Client like
[BuzzClient](https://github.com/clue/reactphp-buzz), but as long as you get
returned to the controller, you will have to turn this promises asynchronous and
and get returned their value. You can do that by using
[ReactPHP Block](https://github.com/clue/reactphp-block). Remember that the
Symfony Kernel **MUST** return a Response object, and the same for the
Controller.

So will it be really asynchronous if the event loop is only shared by one single
thread? At all.

## Symfony Async Kernel

So on one side we have a Server called ReactPHP Http Server that work with a
running loop, and on the other side, we have a domain built on top of Promises,
with some non-blocking clients like the HTTP one or a Redis one.

This is the workflow.

- **Step 1** - ReactPHP receives it's own Request, and creates a Symfony request.
- **Step 2** - The Kernel handles this Request, waiting for a Response.
- **Step 3** - Some framework magic (resolve controller, resolve dispatch some
events...)
- **Step 4** - We call the controller entry point. Remember that we MUST return a
Response instance.
- **Step 5** - We do our logic. For example, we call a repository to get an array
of values from Redis.
- **Step 6** - Redis returns a **Promise** of values. This promise is returned to
the controller, and has to be resolved. Once is resolved, returns a Response to
the Kernel.
- **Step 7** - The Kernel returns the Response to the ReactPHP server, which
creates a new promise with that Response.

When checking performance, we see that the I/O, even if it's asynchronous, is
blocked in the controller by the application, so lasts the same 50ms than
before. Our server is still blocking.

So, can we all see that this Symfony Kernel is the blocking part of the whole
application?

What if would have a way of, instead of returning a Response, our Kernel could
be able to handle Promises? In that case, we should'nt have to wait for any
Promise response, passing the promise created by the I/O async client directly
to the ReactPHP server.

Let's check the workflow.

- **Step 1** - ReactPHP receives it's own Request, and creates a Symfony request.
- **Step 2** - The Kernel handles **asynchronously** this Request, waiting for a 
Promise containing a Response.
- **Step 3** - Some framework magic (resolve controller, resolve dispatch some
events...)
- **Step 4** - We call the controller entry point. Now we can return a Promise
instead of a Response instance.
- **Step 5** - We do our logic. For example, we call a repository to get an array
of values from Redis.
- **Step 6** - Redis returns a Promise of values. This promise is returned to
the controller. No need to resolve anything. Returning the Promise to the
Kernel.
- **Step 7** - The Kernel returns the Promise to the ReactPHP server. Directly.

And checking the performance? Easy. The response will still return in 54ms. We
can improve this time by improving your networking interface or by adding some
cache. By the time spent in the server for that request?

**4ms**.
Only **4ms**.

And the most important part.

With the old implementation:
- (time 0) Request 1 *(slow)*
- (time 1) Request 2 *(ultrafast)*
- (time 2) Request 3 *(fast)*
- (time 80) Response 1
- (time 81) Response 2
- (time 91) Response 3

With the new implementation
- (time 0) Request 1 *(slow)*
- (time 1) Request 2 *(ultrafast)*
- (time 1) Response 2
- (time 2) Request 3 *(fast)*
- (time 12) Response 3
- (time 80) Response 1
