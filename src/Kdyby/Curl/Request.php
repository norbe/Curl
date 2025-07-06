<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Curl;

use Kdyby;
use Nette;
use Nette\Http\IRequest;
use Nette\Http\UrlScript as Url;
use Nette\Utils\ObjectMixin;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Request extends RequestOptions
{
	/**#@+ HTTP Request method */
	const GET = IRequest::GET;
	const POST = IRequest::POST;
	const PUT = IRequest::PUT;
	const HEAD = IRequest::HEAD;
	const DELETE = IRequest::DELETE;
	const PATCH = 'PATCH';
	const DOWNLOAD = 'DOWNLOAD';
	/**#@- */

	/** @var \Nette\Http\UrlScript */
	public $url;

	/** @var string */
	public $method = self::GET;

	/** @var array */
	public $headers = array();

	/** @var array|string */
	public $post = array();

	/** @var array */
	public $files = array();

	/** @var CurlSender */
	private $sender;
	/** @var string */
	private $cookieFile;
	/** @var bool */
	public $cookieUnlink = TRUE;

	/**
	 * @param string $url
	 * @param array|string $post
	 */
	public function __construct($url, $post = array())
	{
		$this->setUrl($url);
		$this->post = $post;
		$this->updateCookieFile();
	}

	public function __destruct() {
		if(isset($this->cookieFile) && $this->cookieUnlink) {
			@unlink($this->cookieFile);
		}
	}

	public function updateCookieFile() {
		
		$this->setCookieFile(tempnam(sys_get_temp_dir(), 'cookie'));
	}

	public function setCookieFile($cookieFile) {
		if(isset($this->cookieFile) && $this->cookieUnlink) {
			@unlink($this->cookieFile);
		}
		$this->cookieFile = $cookieFile;
		$this->options['cookiejar'] = $this->cookieFile;
		$this->options['cookiefile'] = $this->cookieFile;
	}

	public function getCookieFile() {
		return $this->cookieFile;
	}

	/**
	 * @return \Nette\Http\UrlScript
	 */
	public function getUrl()
	{
		if (!$this->url instanceof Url) {
			$this->url = new Url($this->url);
		}
		return $this->url;
	}
	
	public function setUrl($url) {
		$this->url = $url;
		return $this;
	}



	/**
	 * @param string $method
	 * @return boolean
	 */
	public function isMethod($method)
	{
		return $this->method === $method;
	}
	
	public function setMethod($method) {
		$this->method = $method;
	}



	/**
	 * @param CurlSender $sender
	 *
	 * @return Request
	 */
	public function setSender(CurlSender $sender)
	{
		$this->sender = $sender;
		return $this;
	}



	/**
	 * @param array|string $post
	 * @param array $files
	 * @return Request
	 */
	public function setPost($post, $files = array())
	{
		$this->post = $post;
		$this->files = $files;
		$this->method = self::POST;

		return $this;
	}



	/**
	 * @throws CurlException
	 * @return Response
	 */
	public function send()
	{
		if ($this->sender === NULL) {
			$this->sender = new CurlSender();
		}

		return $this->sender->send($this);
	}



	/**
	 * @param array|string $query
	 *
	 * @throws CurlException
	 * @return Response
	 */
	public function get($query = NULL)
	{
		$this->method = static::GET;
		$this->post = $this->files = array();
		$this->url = $this->getUrl()->withQuery($query);
		return $this->send();
	}



	/**
	 * @param array|string $post
	 * @param array $files
	 *
	 * @throws CurlException
	 * @return Response
	 */
	public function post($post = array(), ?array $files = NULL)
	{
		$this->method = static::POST;
		$this->post = $post;
		$this->files = (array)$files;
		return $this->send();
	}



	/**
	 * @param array|string $post
	 *
	 * @throws CurlException
	 * @return Response
	 */
	public function put($post = array())
	{
		$this->method = static::PUT;
		$this->post = $post;
		$this->files = array();
		return $this->send();
	}



	/**
	 * @throws CurlException
	 * @return Response
	 */
	public function delete()
	{
		$this->method = static::DELETE;
		$this->post = $this->files = array();
		return $this->send();
	}



	/**
	 * @param array $post
	 * @return Response
	 */
	public function patch($post = array())
	{
		$this->method = static::PATCH;
		$this->post = $post;
		return $this->send();
	}



	/**
	 * @param array|string $post
	 *
	 * @throws CurlException
	 * @return FileResponse
	 */
	public function download($post = array())
	{
		$this->method = static::DOWNLOAD;
		$this->post = $post;
		return $this->send();
	}



	/**
	 * Creates new request that can follow requested location
	 * @param Response $response
	 *
	 * @return Request
	 */
	final public function followRedirect(Response $response)
	{
		$request = clone $this;
		if (!$request->isMethod(Request::DOWNLOAD)) {
			$request->setMethod(Request::GET);
		}
		$request->cookieUnlink = FALSE;
		$request->post = $request->files = array();
		$request->setUrl(static::fixUrl($request->getUrl(), $response->headers['Location']));
		return $request;
	}



	/**
	 * Clones the url
	 */
	public function __clone()
	{
		if ($this->url instanceof Url) {
			$this->url = clone $this->url;
		}
	}



	/**
	 * @param string|Url $from
	 * @param string|Url $to
	 *
	 * @throws InvalidUrlException
	 * @return Url
	 */
	public static function fixUrl($from, $to)
	{
		$lastUrl = new Url($from);
		$url = new Url($to);



		if (!$to instanceof Url && $url->path[0] !== '/') { // relative
			$url = $url->withPath(substr($lastUrl->path, 0, strrpos($lastUrl->path, '/') + 1) . $url->path);
		}

		foreach (array('scheme', 'host', 'port') as $copy) {
			if (empty($url->{$copy})) {
				if (empty($lastUrl->{$copy})) {
					throw new InvalidUrlException("Missing URL $copy!");
				}
				switch($copy) {
					case 'scheme':
						$url = $url->withScheme($lastUrl->scheme);
						break;
					case 'host':
						$url = $url->withHost($lastUrl->host);
						break;
					case 'port':
						$url = $url->withPort($lastUrl->port);
						break;
				}
			}
		}
		if (!$url->path || $url->path[0] !== '/') {
			$url= $url->withPath('/' . $url->path);
		}

		return $url;
	}



	/**
	 * @return array
	 */
	public function __sleep()
	{
		return array('url', 'method', 'headers', 'options', 'post', 'files');
	}

}
