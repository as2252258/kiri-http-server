<?php

declare(strict_types=1);

namespace Kiri\Server\Handler;

use Exception;
use Kiri\Di\Inject\Container;
use Kiri\Di\Context;
use Kiri\Di\Interface\ResponseEmitterInterface;
use Kiri\Router\Base\ExceptionHandlerDispatcher;
use Kiri\Router\Constrict\ConstrictRequest as CQ;
use Kiri\Router\Constrict\ConstrictResponse;
use Kiri\Router\DataGrip;
use Kiri\Router\Interface\ExceptionHandlerInterface;
use Kiri\Router\Interface\OnRequestInterface;
use Kiri\Router\RouterCollector;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;
use Swoole\Http\Request;
use Swoole\Http\Response;
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
     * @param ContainerInterface $container
     * @param DataGrip $dataGrip
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    public function __construct(public ResponseInterface $response, public ContainerInterface $container,
                                public DataGrip          $dataGrip)
    {
        $this->responseEmitter = $this->response->emmit;
        $exception             = \config('request.exception');
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
        /** @var CQ $PsrRequest */
        try {
            $PsrRequest = $this->initRequestAndResponse($request);

            $PsrResponse = $this->router->query($request->server['path_info'], $request->getMethod())
                                        ->run($PsrRequest);
        } catch (Throwable $throwable) {
            $PsrResponse = $this->exception->emit($throwable, $this->constrictResponse);
        } finally {
            $this->responseEmitter->response($PsrResponse, $response, $PsrRequest);
        }
    }


    /**
     * @param Request $request
     * @return ServerRequestInterface
     */
    public function initRequestAndResponse(Request $request): ServerRequestInterface
    {
        $response = new ConstrictResponse($this->response->contentType);

        Context::set(ResponseInterface::class, $response);

        return Context::set(RequestInterface::class, CQ::builder($request));
    }

}
