<?php

namespace Kiri\Server\Handler;

use Exception;
use Kiri;
use Kiri\Di\Context;
use Kiri\Router\Constrict\ConstrictRequest;
use Kiri\Router\Constrict\ConstrictResponse;
use Kiri\Router\Constrict\Uri;
use Kiri\Router\Interface\OnRequestInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Kiri\Di\Inject\Service;
use Kiri\Router\ServerRequest as KrServer;
use Kiri\Router\Response as Psr7Response;
use Kiri\Di\Inject\Container;

class OnRequest implements OnRequestInterface
{

	/**
	 * @var KrServer
	 */
	#[Container(KrServer::class)]
	public KrServer $onRequest;


	/**
	 * @var Psr7Response
	 */
	#[Service('response')]
	public Psr7Response $response;


	/**
	 * @param Request $request
	 * @param Response $response
	 * @throws Exception
	 */
	public function onRequest(Request $request, Response $response): void
	{
		/** @var ConstrictRequest $PsrRequest */
		$PsrRequest = $this->initPsr7RequestAndPsr7Response($request);

		$this->onRequest->onServerRequest($PsrRequest, $response);
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
			->withUri(static::parse($request))
			->withProtocolVersion($request->server['server_protocol'])
			->withCookieParams($request->cookie ?? [])
			->withQueryParams($request->get ?? [])
			->withUploadedFiles($request->files ?? [])
			->withMethod($request->getMethod())
			->withParsedBody($request->post ?? []);

		/** @var ConstrictRequest $PsrRequest */
		return Context::set(RequestInterface::class, $serverRequest);
	}


	/**
	 * @param Request $request
	 * @return UriInterface
	 */
	public static function parse(Request $request): UriInterface
	{
		$uri = new Uri();
		$uri->withQuery($request->server['query_string'] ?? '')
			->withPath($request->server['path_info'])
			->withPort($request->server['server_port']);
		$host = $request->header['host'] ?? '127.0.0.1';
		if (str_contains($host, ':')) {
			[$host, $port] = explode(':', $host);
			$uri->withHost($host)->withPort((int)$port);
		} else {
			$uri->withHost($host);
		}
		if (isset($request->server['https']) && $request->server['https'] !== 'off') {
			$uri->withScheme('https');
		} else {
			$uri->withScheme('http');
		}
		return $uri;
	}


}
