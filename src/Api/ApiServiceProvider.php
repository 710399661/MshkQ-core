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

namespace MshkQ\Api;

use MshkQ\Api\Controller\AbstractSerializeController;
use MshkQ\Api\Events\ApiExceptionRegisterHandler;
use MshkQ\Api\Events\ConfigMiddleware;
use MshkQ\Api\ExceptionHandler\FallbackExceptionHandler;
use MshkQ\Api\ExceptionHandler\LoginFailedExceptionHandler;
use MshkQ\Api\ExceptionHandler\LoginFailuresTimesToplimitExceptionHandler;
use MshkQ\Api\ExceptionHandler\NotAuthenticatedExceptionHandler;
use MshkQ\Api\ExceptionHandler\PermissionDeniedExceptionHandler;
use MshkQ\Api\ExceptionHandler\RouteNotFoundExceptionHandler;
use MshkQ\Api\ExceptionHandler\ServiceResponseExceptionHandler;
use MshkQ\Api\ExceptionHandler\TencentCloudSDKExceptionHandler;
use MshkQ\Api\ExceptionHandler\ValidationExceptionHandler;
use MshkQ\Api\Listeners\AutoResisterApiExceptionRegisterHandler;
use MshkQ\Api\Middleware\HandlerErrors;
use MshkQ\Api\Middleware\InstallMiddleware;
use MshkQ\Foundation\Application;
use MshkQ\Http\Middleware\AuthenticateWithHeader;
use MshkQ\Http\Middleware\CheckoutSite;
use MshkQ\Http\Middleware\CheckUserStatus;
use MshkQ\Http\Middleware\DispatchRoute;
use MshkQ\Http\Middleware\ParseJsonBody;
use MshkQ\Http\Middleware\OptionsRequest;
use MshkQ\Http\RouteCollection;
use Illuminate\Support\ServiceProvider;
use MshkQ\JsonApi\ErrorHandler;
use Laminas\Stratigility\MiddlewarePipe;

class ApiServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('MshkQ.api.middleware', function (Application $app) {
            $pipe = new MiddlewarePipe();

            if (!$this->app->isInstall()) {
                $pipe->pipe($app->make(InstallMiddleware::class));
                return $pipe;
            }

            $pipe->pipe($app->make(HandlerErrors::class));
            $pipe->pipe($app->make(OptionsRequest::class));
            $pipe->pipe($app->make(ParseJsonBody::class));
            $pipe->pipe($app->make(AuthenticateWithHeader::class));
            $pipe->pipe($app->make(CheckoutSite::class));
            $pipe->pipe($app->make(CheckUserStatus::class));

            $app->make('events')->dispatch(new ConfigMiddleware($pipe));

            return $pipe;
        });

        $this->app->singleton(ErrorHandler::class, function (Application $app) {
            $errorHandler = new ErrorHandler;
            $errorHandler->registerHandler(new RouteNotFoundExceptionHandler());
            $errorHandler->registerHandler(new ValidationExceptionHandler());
            $errorHandler->registerHandler(new NotAuthenticatedExceptionHandler());
            $errorHandler->registerHandler(new PermissionDeniedExceptionHandler());
            $errorHandler->registerHandler(new TencentCloudSDKExceptionHandler());
            $errorHandler->registerHandler(new ServiceResponseExceptionHandler());
            $errorHandler->registerHandler(new LoginFailuresTimesToplimitExceptionHandler());
            $errorHandler->registerHandler(new LoginFailedExceptionHandler());

            $app->make('events')->dispatch(new ApiExceptionRegisterHandler($errorHandler));

            $errorHandler->registerHandler(new FallbackExceptionHandler($app->config('debug')));
            return $errorHandler;
        });

        // 保证路由中间件最后执行
        $this->app->afterResolving('MshkQ.api.middleware', function (MiddlewarePipe $pipe) {
            $pipe->pipe($this->app->make(DispatchRoute::class));
        });
    }

    public function boot()
    {
        $this->populateRoutes($this->app->make(RouteCollection::class));

        $this->app->make('events')->listen(ApiExceptionRegisterHandler::class, AutoResisterApiExceptionRegisterHandler::class);

        AbstractSerializeController::setContainer($this->app);
    }

    protected function populateRoutes(RouteCollection $route)
    {
        // 始终加载所有 API 路由，不再按请求 URI 动态选择
        $route->group('/api/backAdmin/', function (RouteCollection $route) {
            require $this->app->basePath('routes/apiadmin.php');
        });
        $route->group('/apiv3/', function (RouteCollection $route) {
            require $this->app->basePath('routes/apiv3.php');
        });
        $route->group('/api/v3/', function (RouteCollection $route) {
            require $this->app->basePath('routes/apiv3.php');
        });
        $route->group('/api/', function (RouteCollection $route) {
            require $this->app->basePath('routes/api.php');
        });
    }

    private function startWith($uri, $prefix)
    {
        $p = '/' . $prefix;//兼容前端错误的url拼接
        return str_starts_with($uri, $prefix) || str_starts_with($uri, $p);
    }
}
