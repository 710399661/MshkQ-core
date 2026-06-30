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

namespace MshkQ\Filesystem;

use MshkQ\Http\UrlGenerator;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Class LocalAdapter
 * @package MshkQ\Filesystem
 */
class LocalAdapter extends LocalFilesystemAdapter
{
    protected array $config;

    protected UrlGenerator $url;

    public function __construct(array $config = [])
    {
        $this->config = $config;

        parent::__construct($this->config['root']);

        $this->url = app(UrlGenerator::class);
    }

    /**
     * 获取本地图片/附件 URL 地址
     */
    public function getUrl(string $path): string
    {
        return $this->url->to(str_replace('public', '/storage', $path));
    }
}
