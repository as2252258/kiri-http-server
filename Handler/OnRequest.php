<?php

namespace Kiri\Server\Handler;

use Exception;
use Kiri;
use Kiri\Di\Inject\Container;
use Kiri\Di\Context;
use Kiri\Di\Interface\ResponseEmitterInterface;
use Kiri\Router\Base\ExceptionHandlerDispatcher;
use Kiri\Router\Constrict\ConstrictRequest;
use Kiri\Router\Constrict\ConstrictResponse;
use Kiri\Router\Constrict\Uri;
use Kiri\Router\DataGrip;
use Kiri\Router\HttpRequestHandler;
use Kiri\Router\Interface\ExceptionHandlerInterface;
use Kiri\Router\Interface\OnRequestInterface;
use Kiri\Router\RouterCollector;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Kiri\Router\Base\Middleware as MiddlewareManager;

/**
 * OnRequest event
 */
class OnRequest implements OnRequestInterface
{

    /**
     * @var RouterCollector
     */
    public RouterCollector $router;


    /**
     * @var ExceptionHandlerInterface
     */
    public ExceptionHandlerInterface $exception;


    /**
     * @var ResponseEmitterInterface
     */
    public ResponseEmitterInterface $emitter;


    /**
     * @var Kiri\Router\Request
     */
    #[Container(RequestInterface::class)]
    public RequestInterface $request;


    /**
     * @var ResponseInterface
     */
    #[Container(ResponseInterface::class)]
    public ResponseInterface $response;


    /**
     * @var MiddlewareManager
     */
    public MiddlewareManager $middlewareManager;

    /**
     * @throws Exception
     */
    public function init(): void
    {
        $container = Kiri::getDi();
        $exception = $this->request->exception;
        if (!in_array(ExceptionHandlerInterface::class, class_implements($exception))) {
            $exception = ExceptionHandlerDispatcher::class;
        }
        $this->exception = $container->get($exception);
        $this->router = $container->get(DataGrip::class)->get(ROUTER_TYPE_HTTP);
        $this->emitter = $this->response->emmit;

        $this->middlewareManager = \Kiri::getDi()->get(MiddlewareManager::class);
    }


    /**
     * @param Request $request
     * @param Response $response
     * @throws Exception
     */
    public function onRequest(Request $request, Response $response): void
    {
        try {
            /** @var ConstrictRequest $PsrRequest */
            $PsrRequest = $this->initPsr7RequestAndPsr7Response($request);
            $dispatcher = $this->router->query($request->server['path_info'], $request->getMethod());

            $middleware = $this->middlewareManager->get($dispatcher->getClass(), $dispatcher->getMethod());

            $PsrResponse = (new HttpRequestHandler($middleware, $dispatcher))->handle($PsrRequest);
        } catch (\Throwable $throwable) {
            $PsrResponse = $this->exception->emit($throwable, di(ConstrictResponse::class));
        } finally {
            $this->emitter->sender($PsrResponse, $response);
        }
    }


    /**
     * @param Request $request
     * @return RequestInterface
     * @throws Exception
     */
    private function initPsr7RequestAndPsr7Response(Request $request): RequestInterface
    {
        /** @var ConstrictResponse $PsrResponse */
        $PsrResponse = Context::set(ResponseInterface::class, new ConstrictResponse());
        $PsrResponse->withContentType($this->response->contentType);

        $serverRequest = (new ConstrictRequest())->withDataHeaders($request->getData())
            ->withUri(new Uri($request->server))
            ->withProtocolVersion($request->server['server_protocol'])
            ->withCookieParams($request->cookie ?? [])
            ->withServerParams($request->server)
            ->withQueryParams($request->get ?? [])
            ->withUploadedFiles($request->files ?? [])
            ->withMethod($request->getMethod())
            ->withParsedBody($request->post ?? []);

        /** @var ConstrictRequest $PsrRequest */
        return Context::set(RequestInterface::class, $serverRequest);
    }


}
