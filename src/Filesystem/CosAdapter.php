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

namespace Discuz\Filesystem;

use Exception;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Arr;
use League\Flysystem\FileAttributes;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Config;
use Qcloud\Cos\Client;
use Throwable;

class CosAdapter implements FilesystemAdapter
{
    protected $client;

    protected $httpClient;

    protected $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getBucket()
    {
        return $this->config['bucket'];
    }

    public function getAppId()
    {
        return $this->config['credentials']['appId'] ?? null;
    }

    public function getRegion()
    {
        return $this->config['region'] ?? '';
    }

    public function getSourcePath($path)
    {
        $schema = $this->config['schema'] ?  $this->config['schema'] . '://' : 'https://';
        return  sprintf(
            $schema . '%s.cos.%s.myqcloud.com/%s',
            $this->getBucket(),
            $this->getRegion(),
            $path
        );
    }

    public function getUrl($path)
    {
        if (!empty($this->config['cdn'])) {
            return rtrim($this->config['cdn'], '/') . '/' . ltrim($path, '/');
        }

        $options = [
            'Schema' => $this->config['schema'] ?? 'https',
        ];

        return $this->getClient()->getObjectUrl(
            $this->getBucket(),
            $path,
            null,
            $options
        );
    }

    public function getTemporaryUrl($path, $expiration, array $options = [])
    {
        $options = array_merge($options, ['Schema' => $this->config['schema'] ?? 'https']);

        $expiration = date('c', !\is_numeric($expiration) ? \strtotime($expiration) : \intval($expiration));

        $objectUrl = $this->getClient()->getObjectUrl(
            $this->getBucket(),
            $path,
            $expiration,
            $options
        );

        $url = parse_url($objectUrl);

        if (!empty($this->config['cdn'])) {
            return \sprintf(
                '%s/%s?%s',
                \rtrim($this->config['cdn'], '/'),
                \ltrim(urldecode($url['path']), '/'),
                $url['query']
            );
        }

        return $objectUrl;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $options = $this->getUploadOptions($config);

        $this->getClient()->upload($this->getBucket(), $path, $contents, $options);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $options = $this->getUploadOptions($config);

        $this->getClient()->upload(
            $this->getBucket(),
            $path,
            stream_get_contents($contents, -1, 0),
            $options
        );
    }

    public function read(string $path): string
    {
        try {
            if (Arr::get($this->config, 'read_from_cdn')) {
                $response = $this->getHttpClient()
                    ->get($this->getTemporaryUrl($path, date('+5 min')))
                    ->getBody()
                    ->getContents();
            } else {
                $response = $this->getClient()->getObject([
                    'Bucket' => $this->getBucket(),
                    'Key' => $path,
                ])['Body'];
            }

            return (string) $response;
        } catch (Throwable $e) {
            throw new \League\Flysystem\UnableToReadFile($e->getMessage(), $path, $e);
        } catch (Exception $e) {
            throw new \League\Flysystem\UnableToReadFile($e->getMessage(), $path, $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            $temporaryUrl = $this->getTemporaryUrl($path, \strtotime('+5 min'));

            $stream = $this->getHttpClient()
                ->get($temporaryUrl, ['stream' => true])
                ->getBody()
                ->detach();

            return $stream;
        } catch (\Throwable $e) {
            throw new \League\Flysystem\UnableToReadFile($e->getMessage(), $path, $e);
        } catch (Exception $e) {
            throw new \League\Flysystem\UnableToReadFile($e->getMessage(), $path, $e);
        }
    }

    public function delete(string $path): void
    {
        $this->getClient()->deleteObject([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
        ]);
    }

    public function deleteDirectory(string $path): void
    {
        $response = $this->listObjects($path);

        if (empty($response['Contents'])) {
            return;
        }

        $keys = array_map(function ($item) {
            return ['Key' => $item['Key']];
        }, (array) $response['Contents']);

        $this->getClient()->deleteObjects([
            'Bucket' => $this->getBucket(),
            'Objects' => $keys,
        ]);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->getClient()->putObject([
            'Bucket' => $this->getBucket(),
            'Key' => $path.'/',
            'Body' => '',
        ]);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->getClient()->PutObjectAcl([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
            'ACL' => $this->normalizeVisibility($visibility),
        ]);
    }

    public function visibility(string $path): FileAttributes
    {
        $meta = $this->getClient()->getObjectAcl([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
        ]);

        $visibility = \League\Flysystem\Visibility::PRIVATE;

        foreach ($meta['Grants'] as $grant) {
            if ('READ' === $grant['Grant']['Permission'] && false !== strpos($grant['Grantee']['URI'] ?? '', 'global/AllUsers')) {
                $visibility = \League\Flysystem\Visibility::PUBLIC;
                break;
            }
        }

        return new FileAttributes($path, null, $visibility);
    }

    public function fileExists(string $path): bool
    {
        try {
            return (bool) $this->getMetadata($path);
        } catch (\Throwable $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        return $this->fileExists(rtrim($path, '/') . '/');
    }

    public function mimeType(string $path): FileAttributes
    {
        $meta = $this->getMetadata($path);

        $mimeType = isset($meta['ContentType']) ? $meta['ContentType'] : null;

        return new FileAttributes($path, null, null, null, $mimeType);
    }

    public function lastModified(string $path): FileAttributes
    {
        $meta = $this->getMetadata($path);

        $lastModified = isset($meta['LastModified']) ? strtotime($meta['LastModified']) : null;

        return new FileAttributes($path, null, null, $lastModified);
    }

    public function fileSize(string $path): FileAttributes
    {
        $meta = $this->getMetadata($path);

        $size = isset($meta['ContentLength']) ? intval($meta['ContentLength']) : null;

        return new FileAttributes($path, $size);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $list = [];

        $response = $this->listObjects($path, $deep);

        foreach ((array) $response['Contents'] as $content) {
            $list[] = $this->normalizeFileInfo($content);
        }

        return $list;
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);

        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $cosSource = [
            'Region' => $this->getRegion(),
            'Bucket' => $this->getBucket(),
            'Key' => $source,
        ];

        $this->getClient()->copy($this->getBucket(), $destination, $cosSource);
    }

    public function getClient()
    {
        return $this->client ?: $this->client = new Client($this->config);
    }

    public function setClient(Client $client)
    {
        $this->client = $client;

        return $this;
    }

    public function getHttpClient()
    {
        return $this->httpClient ?: $this->httpClient = new HttpClient();
    }

    public function setHttpClient(HttpClient $client)
    {
        $this->httpClient = $client;

        return $this;
    }

    protected function getMetadata($path)
    {
        return $this->getClient()->headObject([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
        ]);
    }

    protected function normalizeFileInfo(array $content)
    {
        $path = $content['Key'];
        $isDir = substr($path, -1) === '/';

        if ($isDir) {
            return new DirectoryAttributes(rtrim($path, '/'));
        }

        return new FileAttributes(
            $path,
            intval($content['Size']),
            null,
            strtotime($content['LastModified'])
        );
    }

    protected function listObjects($directory = '', $recursive = false)
    {
        return $this->getClient()->listObjects([
            'Bucket' => $this->getBucket(),
            'Prefix' => ('' === (string) $directory) ? '' : ($directory.'/'),
            'Delimiter' => $recursive ? '' : '/',
        ]);
    }

    protected function getUploadOptions(Config $config)
    {
        $options = [];

        if ($config->has('header')) {
            $options += $config->get('header');
        }
        if ($config->has('params')) {
            $options['params'] = $config->get('params');
        }
        if ($config->has('visibility')) {
            $options['params']['ACL'] = $this->normalizeVisibility($config->get('visibility'));
        }

        return $options;
    }

    protected function normalizeVisibility($visibility)
    {
        switch ($visibility) {
            case \League\Flysystem\Visibility::PUBLIC:
                $visibility = 'public-read';
                break;
        }

        return $visibility;
    }
}
