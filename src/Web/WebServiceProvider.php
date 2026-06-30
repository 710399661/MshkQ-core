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

namespace MshkQ\Web;

use MshkQ\Http\Middleware\DispatchRoute;
use MshkQ\Http\Middleware\HandleErrorsWithView;
use MshkQ\Http\Middleware\HandleErrorsWithWhoops;
use MshkQ\Http\RouteCollection;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Laminas\Stratigility\MiddlewarePipe;

class WebServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('MshkQ.web.middleware', function ($app) {
            $app->register(ViewServiceProvider::class);
            $pipe = new MiddlewarePipe();
            if ($app->config('debug')) {
                $pipe->pipe($app->make(HandleErrorsWithWhoops::class));
            } else {
                $pipe->pipe($app->make(HandleErrorsWithView::class));
            }
            return $pipe;
        });

        //保证路由中间件最后执行
        $this->app->afterResolving('MshkQ.web.middleware', function (MiddlewarePipe $pipe) {
            $pipe->pipe($this->app->make(DispatchRoute::class));
        });
    }

    public function boot()
    {
        $this->populateRoutes($this->app->make(RouteCollection::class));
    }

    protected function populateRoutes(RouteCollection $route)
    {
        $route->group('', function (RouteCollection $route) {
            require $this->app->basePath('routes/web.php');
        });
    }
}
