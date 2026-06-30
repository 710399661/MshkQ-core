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

use DateTimeImmutable;
use GuzzleHttp\Client as HttpClient;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\PathAttributes;
use Illuminate\Support\Arr;
use League\Flysystem\StorageAttributes;
use Qcloud\Cos\Client;

/**
 * Class CosAdapter.
 * Tencent COS adapter for Flysystem 3.x
 */
class CosAdapter implements FilesystemAdapter
{
    protected ?Client $client = null;

    protected ?HttpClient $httpClient = null;

    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getBucket(): string
    {
        return $this->config['bucket'];
    }

    public function getAppId(): ?string
    {
        return $this->config['credentials']['appId'] ?? null;
    }

    public function getRegion(): string
    {
        return $this->config['region'] ?? '';
    }

    public function getSourcePath(string $path): string
    {
        $schema = ($this->config['schema'] ?? 'https') . '://';
        return sprintf(
            '%s%s.cos.%s.myqcloud.com/%s',
            $schema,
            $this->getBucket(),
            $this->getRegion(),
            $path
        );
    }

    public function getUrl(string $path): string
    {
        if (!empty($this->config['cdn'])) {
            return rtrim($this->config['cdn'], '/') . '/' . ltrim($path, '/');
        }

        return $this->getClient()->getObjectUrl(
            $this->getBucket(),
            $path,
            null,
            ['Schema' => $this->config['schema'] ?? 'https']
        );
    }

    public function getTemporaryUrl(string $path, DateTimeInterface $expiresAt, array $config = []): string
    {
        $options = ['Schema' => $this->config['schema'] ?? 'https'];
        $expiration = date('c', $expiresAt->getTimestamp());

        $objectUrl = $this->getClient()->getObjectUrl(
            $this->getBucket(),
            $path,
            $expiration,
            $options
        );

        $url = parse_url($objectUrl);

        if (!empty($this->config['cdn'])) {
            return sprintf(
                '%s/%s?%s',
                rtrim($this->config['cdn'], '/'),
                ltrim(urldecode($url['path']), '/'),
                $url['query']
            );
        }

        return $objectUrl;
    }

    public function write(string $path, string $contents, array $config = []): FileAttributes
    {
        $options = $this->getUploadOptions($config);
        $this->getClient()->upload($this->getBucket(), $path, $contents, $options);

        return new FileAttributes($path, strlen($contents));
    }

    public function writeStream(string $path, $contents, array $config = []): FileAttributes
    {
        $options = $this->getUploadOptions($config);
        $content = stream_get_contents($contents, -1, 0);
        $this->getClient()->upload($this->getBucket(), $path, $content, $options);

        return new FileAttributes($path, strlen($content));
    }

    public function read(string $path): string
    {
        if (Arr::get($this->config, 'read_from_cdn')) {
            return $this->getHttpClient()
                ->get($this->getTemporaryUrl($path, new DateTimeImmutable('+5 minutes')))
                ->getBody()
                ->getContents();
        }

        return (string) $this->getClient()->getObject([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
        ])['Body'];
    }

    public function readStream(string $path)
    {
        $temporaryUrl = $this->getTemporaryUrl($path, new DateTimeImmutable('+5 minutes'));

        return $this->getHttpClient()
            ->get($temporaryUrl, ['stream' => true])
            ->getBody()
            ->detach();
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

    public function createDirectory(string $path, array $config = []): void
    {
        $this->getClient()->putObject([
            'Bucket' => $this->getBucket(),
            'Key' => $path . '/',
            'Body' => '',
        ]);
    }

    public function setVisibility(string $path, string $visibility): FileAttributes
    {
        $acl = $visibility === 'public' ? 'public-read' : $visibility;

        $this->getClient()->putObjectAcl([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
            'ACL' => $acl,
        ]);

        return new FileAttributes($path, null, $visibility);
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->getClient()->headObject([
                'Bucket' => $this->getBucket(),
                'Key' => $path,
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            $response = $this->listObjects($path);
            return !empty($response['Contents']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function mimeType(string $path): FileAttributes
    {
        $meta = $this->getClient()->headObject([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
        ]);

        return new FileAttributes(
            $path,
            null,
            null,
            null,
            $meta['ContentType'] ?? null
        );
    }

    public function lastModified(string $path): FileAttributes
    {
        $meta = $this->getClient()->headObject([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
        ]);

        return new FileAttributes(
            $path,
            null,
            null,
            isset($meta['LastModified']) ? strtotime($meta['LastModified']) : null
        );
    }

    public function fileSize(string $path): FileAttributes
    {
        $meta = $this->getClient()->headObject([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
        ]);

        return new FileAttributes(
            $path,
            $meta['ContentLength'] ?? null
        );
    }

    public function visibility(string $path): FileAttributes
    {
        $meta = $this->getClient()->getObjectAcl([
            'Bucket' => $this->getBucket(),
            'Key' => $path,
        ]);

        $visibility = 'private';
        foreach ($meta['Grants'] as $grant) {
            if ($grant['Grant']['Permission'] === 'READ'
                && strpos($grant['Grantee']['URI'] ?? '', 'global/AllUsers') !== false
            ) {
                $visibility = 'public';
                break;
            }
        }

        return new FileAttributes($path, null, $visibility);
    }

    public function listContents(string $path = '', bool $deep = false): iterable
    {
        $response = $this->listObjects($path, $deep);

        foreach ((array) ($response['Contents'] ?? []) as $content) {
            $isDir = substr($content['Key'], -1) === '/';
            yield from [$this->normalizeFileInfo($content, $isDir)];
        }
    }

    public function move(string $source, string $destination, array $config = []): void
    {
        $this->copy($source, $destination);
        $this->delete($source);
    }

    public function copy(string $source, string $destination): void
    {
        $this->getClient()->copy(
            $this->getBucket(),
            $destination,
            ['Bucket' => $this->getBucket(), 'Key' => $source]
        );
    }

    public function getClient(): Client
    {
        return $this->client ??= new Client($this->config);
    }

    public function setClient(Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getHttpClient(): HttpClient
    {
        return $this->httpClient ??= new HttpClient();
    }

    public function setHttpClient(HttpClient $client): static
    {
        $this->httpClient = $client;
        return $this;
    }

    protected function normalizeFileInfo(array $content, bool $isDir): StorageAttributes
    {
        $path = $content['Key'];
        $size = (int) ($content['Size'] ?? 0);
        $timestamp = strtotime($content['LastModified'] ?? 'now');

        if ($isDir) {
            return new PathAttributes($path, $timestamp);
        }

        return new FileAttributes($path, $size, null, $timestamp);
    }

    protected function listObjects(string $directory = '', bool $recursive = false): array
    {
        return $this->getClient()->listObjects([
            'Bucket' => $this->getBucket(),
            'Prefix' => $directory === '' ? '' : ($directory . '/'),
            'Delimiter' => $recursive ? '' : '/',
        ]);
    }

    protected function getUploadOptions(array $config): array
    {
        $options = [];
        if (isset($config['header'])) {
            $options += $config['header'];
        }
        if (isset($config['params'])) {
            $options['params'] = $config['params'];
        }
        if (isset($config['visibility'])) {
            $acl = $config['visibility'] === 'public' ? 'public-read' : $config['visibility'];
            $options['params']['ACL'] = $acl;
        }
        return $options;
    }
}
