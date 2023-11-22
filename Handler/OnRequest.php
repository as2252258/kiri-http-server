<?php

declare(strict_types=1);

namespace Kiri\Server\Handler;

use Exception;
use Kiri;
use Kiri\Router\Constrict\Stream;
use Kiri\Core\Xml;
use Kiri\Di\Inject\Container;
use Kiri\Di\Context;
use Kiri\Di\Interface\ResponseEmitterInterface;
use Kiri\Router\Base\ExceptionHandlerDispatcher;
use Kiri\Router\Constrict\ConstrictRequest;
use Kiri\Router\Constrict\ConstrictResponse;
use Kiri\Router\Constrict\Uri;
use Kiri\Router\DataGrip;
use Kiri\Router\Interface\ExceptionHandlerInterface;
use Kiri\Router\Interface\OnRequestInterface;
use Kiri\Router\RouterCollector;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionException;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Kiri\Router\Base\Middleware as MiddlewareManager;
use Throwable;

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
    public ResponseEmitterInterface $responseEmitter;


    /**
     * @var ConstrictResponse
     */
    #[Container(ConstrictResponse::class)]
    public ConstrictResponse $constrictResponse;


    /**
     * @param ResponseInterface $response
     * @param RequestInterface $request
     * @param ContainerInterface $container
     * @param DataGrip $dataGrip
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function __construct(public ResponseInterface $response, public RequestInterface $request, public ContainerInterface $container,
                                public DataGrip          $dataGrip)
    {
        $this->responseEmitter = $this->response->emmit;
        $exception             = $this->request->exception;
        if (!in_array(ExceptionHandlerInterface::class, class_implements($exception))) {
            $exception = ExceptionHandlerDispatcher::class;
        }
        $this->exception = $this->container->get($exception);
        $this->router    = $this->dataGrip->get(ROUTER_TYPE_HTTP);
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
            $PsrRequest = Context::set(RequestInterface::class, $this->createConstrictRequest($request));

            /** @var ConstrictResponse $PsrResponse */
            Context::set(ResponseInterface::class, new ConstrictResponse($this->response->contentType));

            /** @var $PsrResponse */
            $PsrResponse = $this->router->query($request->server['path_info'], $request->getMethod())->run($PsrRequest);
        } catch (Throwable $throwable) {
            $PsrResponse = $this->exception->emit($throwable, $this->constrictResponse);
        } finally {
            $this->responseEmitter->response($PsrResponse, $response, $PsrRequest);
        }
    }


    /**
     * @param Request $request
     * @return ConstrictRequest
     */
    protected function createConstrictRequest(Request $request): ConstrictRequest
    {
        return (new ConstrictRequest())->withHeaders($request->header ?? [])
                                       ->withUri(new Uri($request))
                                       ->withProtocolVersion($request->server['server_protocol'])
                                       ->withCookieParams($request->cookie ?? [])
                                       ->withServerParams($request->server)
                                       ->withQueryParams($request->get ?? [])
                                       ->withBody(new Stream($request->getContent()))
                                       ->withParsedBody($request)
                                       ->withUploadedFiles($request->files ?? [])
                                       ->withMethod($request->getMethod());
    }


}
