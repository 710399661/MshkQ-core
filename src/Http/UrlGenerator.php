<?php

/**
 * Copyright (C) 2020 Tencent Cloud.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Discuz\Http;

use DateTimeInterface;
use Discuz\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator as UrlGeneratorContracts;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;

class UrlGenerator implements UrlGeneratorContracts
{
    protected $app;

    protected $routes;

    protected $cachedScheme;

    /**
     * @var ServerRequestInterface
     */
    protected static $request;

    public function __construct(Application $app, RouteCollection $routes)
    {
        $this->app = $app;
        $this->routes = $routes;
    }

    /**
     * Get the current URL for the request.
     *
     * @return string
     */
    public function current()
    {
        return  collect([$this->formatHost().$this->formatPath(), $this->formatQuery()])->filter()->join('?');
    }

    /**
     * Get the URL for the previous request.
     *
     * @param mixed $fallback
     * @return string
     */
    public function previous($fallback = false)
    {
        // TODO: Implement previous() method.
    }

    /**
     * Generate an absolute URL to the given path.
     *
     * @param string $path
     * @param mixed $extra
     * @param bool|null $secure
     * @return string
     */
    public function to($path, $extra = [], $secure = null)
    {
        return $this->formatHost().$path;
    }

    /**
     * Generate a secure, absolute URL to the given path.
     *
     * @param string $path
     * @param array $parameters
     * @return string
     */
    public function secure($path, $parameters = [])
    {
        // TODO: Implement secure() method.
    }

    /**
     * Generate the URL to an application asset.
     *
     * @param string $path
     * @param bool|null $secure
     * @return string
     */
    public function asset($path, $secure = null)
    {
        // TODO: Implement asset() method.
    }

    /**
     * Get the URL to a named route.
     *
     * @param string $name
     * @param mixed $parameters
     * @param bool $absolute
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function route($name, $parameters = [], $absolute = true)
    {
        return $this->formatHost().$this->routes->getPath($name, $parameters);
    }

    /**
     * Get the URL to a controller action.
     *
     * @param string|array $action
     * @param mixed $parameters
     * @param bool $absolute
     * @return string
     */
    public function action($action, $parameters = [], $absolute = true)
    {
        return rtrim($this->to($action), '/') . '?' . Arr::query($parameters);
    }

    /**
     * Create a signed route URL for a named route.
     *
     * @param  string  $name
     * @param  mixed  $parameters
     * @param  \DateTimeInterface|\DateInterval|int|null  $expiration
     * @param  bool  $absolute
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function signedRoute($name, $parameters = [], $expiration = null, $absolute = true)
    {
        $this->ensureSignedRouteParametersAreNotReserved(
            $parameters = Arr::wrap($parameters)
        );

        if ($expiration) {
            $parameters['expires'] = $this->availableAt($expiration);
        }

        ksort($parameters);

        $url = $this->route($name, $parameters, $absolute);

        $url = $this->addQueryString($url, $parameters);

        $signature = hash_hmac(
            'sha256',
            $url,
            $this->getKey()
        );

        return $this->addQueryString($url, ['signature' => $signature]);
    }

    /**
     * Add a query string to the given URL.
     *
     * @param  string  $url
     * @param  array  $query
     * @return string
     */
    protected function addQueryString($url, array $query)
    {
        if (empty($query)) {
            return $url;
        }

        $question = str_contains($url, '?') ? '&' : '?';

        return $url . $question . Arr::query($query);
    }

    /**
     * Create a temporary signed route URL for a named route.
     *
     * @param  string  $name
     * @param  \DateTimeInterface|\DateInterval|int  $expiration
     * @param  array  $parameters
     * @param  bool  $absolute
     * @return string
     */
    public function temporarySignedRoute($name, $expiration, $parameters = [], $absolute = true)
    {
        return $this->signedRoute($name, $parameters, $expiration, $absolute);
    }

    /**
     * Generate an absolute URL with the given query parameters.
     *
     * @param  string  $path
     * @param  array  $query
     * @param  mixed  $extra
     * @param  bool|null  $secure
     * @return string
     */
    public function query($path, $query = [], $extra = [], $secure = null)
    {
        $url = $this->to($path, $extra, $secure);

        $query = array_merge(
            $this->extractQueryString($url),
            $query
        );

        $uri = trim($this->stripQueryString($url), '/');

        if (! empty($query)) {
            $uri .= '?' . Arr::query($query);
        }

        return $uri;
    }

    /**
     * Ensure the given signed route parameters are not reserved.
     *
     * @param  mixed  $parameters
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function ensureSignedRouteParametersAreNotReserved($parameters)
    {
        if (array_key_exists('signature', $parameters)) {
            throw new \InvalidArgumentException('"Signature" is a reserved parameter when generating signed routes.');
        }

        if (array_key_exists('expires', $parameters)) {
            throw new \InvalidArgumentException('"Expires" is a reserved parameter when generating signed routes.');
        }
    }

    /**
     * Get the "available at" UNIX timestamp.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return int
     */
    protected function availableAt($delay = 0)
    {
        $delay = $this->parseDateInterval($delay);

        return $delay instanceof DateTimeInterface
            ? $delay->getTimestamp()
            : time() + $delay;
    }

    /**
     * If the given value is an interval, convert it to a DateTime instance.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return \DateTimeInterface|int
     */
    protected function parseDateInterval($delay)
    {
        if ($delay instanceof \DateInterval) {
            $delay = \DateTime::createFromFormat('U', time())->add($delay);
        }

        return $delay;
    }

    /**
     * Get the encryption key from the application config.
     *
     * @return string
     */
    protected function getKey()
    {
        $key = $this->app->make('config')->get('app.key');

        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(Str::after($key, 'base64:'));
        }

        return $key;
    }

    /**
     * Extract the query string from the given URL.
     *
     * @param  string  $url
     * @return array
     */
    protected function extractQueryString($url)
    {
        $query = [];

        if (str_contains($url, '?')) {
            [$path, $queryString] = explode('?', $url, 2);
            parse_str($queryString, $query);
        }

        return $query;
    }

    /**
     * Strip the query string from the given URL.
     *
     * @param  string  $url
     * @return string
     */
    protected function stripQueryString($url)
    {
        if (str_contains($url, '?')) {
            return explode('?', $url, 2)[0];
        }

        return $url;
    }

    /**
     * Set the root controller namespace.
     *
     * @param string $rootNamespace
     * @return \Illuminate\Contracts\Routing\UrlGenerator
     */
    public function setRootControllerNamespace($rootNamespace)
    {
        // TODO: Implement setRootControllerNamespace() method.
    }

    /**
     * Get the root controller namespace.
     *
     * @return string
     */
    public function getRootControllerNamespace()
    {
        return '';
    }

    protected function formatHost()
    {
        if (is_null(self::$request)) {
            return '';
        }

        $port = self::$request->getUri()->getPort();
        return self::$request->getUri()->getScheme() . '://' . self::$request->getUri()->getHost().(in_array($port, [80, 443, null]) ? '' : ':'.$port);
    }

    protected function formatScheme()
    {
        if (is_null($this->cachedScheme)) {
            $this->cachedScheme = self::$request->getUri()->getScheme().'://';
        }

        return $this->cachedScheme;
    }

    public function formatPath()
    {
        return self::$request->getUri()->getPath();
    }

    protected function formatQuery()
    {
        return self::$request->getUri()->getQuery();
    }

    public static function setRequest($request)
    {
        self::$request = $request;
    }
}
