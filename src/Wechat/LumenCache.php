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

namespace Discuz\Wechat;
use App\Common\CacheKey;
use Psr\SimpleCache\CacheInterface;

class LumenCache implements CacheInterface
{
    public function get(string $key, ?mixed $default = null): mixed
    {
        return app('cache')->get($key);
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        return app('cache')->put($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return app('cache')->forget($key);
    }

    public function clear(): bool
    {
        return false;
    }

    public function getMultiple(iterable $keys, ?mixed $default = null): iterable
    {
        return [];
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        return false;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return false;
    }

    public function has(string $key): bool
    {
        return app('cache')->has($key);
    }
}
