<?php

namespace App\API\DTO\Library;

use Illuminate\Support\Str;

class BasicComponent
{
    public readonly ?string $host;

    public function __construct(
        public readonly ?string $path,
        ?string $host,
        public readonly ?string $url,
        public readonly ?string $sha1,
        public readonly ?int $size,
    )
    {
        // Workaround for hardcoded plain http host urls in old Fabric manifests
        // https://support.sonatype.com/hc/en-us/articles/360041287334-Central-501-HTTPS-Required
        if (str_starts_with($host, 'http://repo.maven.apache.org')) {
            $this->host = str_replace('http://', 'https://', $host);
        } else {
            $this->host = $host;
        }
    }

    public function hasHash(): bool
    {
        return !!$this->sha1;
    }

    public function getHashAssoc(): array
    {
        return ['sha1' => $this->sha1];
    }

    public function getUrl(?string $host = null): ?string
    {
        if ($this->url && $host) {
            $domain = parse_url($this->url, PHP_URL_HOST);
            if (! $domain) return $this->url;
            $domainPos = strpos($this->url, $domain);
            if ($domainPos === false) return $this->url;
            $everythingButDomain = substr($this->url, $domainPos + strlen($domain));
            // Remove double slash after domain, if exists
            if (str_ends_with($host, '/') && str_starts_with($everythingButDomain, '/')) {
                return $host.substr($everythingButDomain, 1);
            }
            return $host.$everythingButDomain;
        }

        return $this->url;
    }

    public function getFileName(): ?string
    {
        if ($this->url) return Str::afterLast($this->url, '/');
        return null;
    }

    public function getPathWithoutFile(): ?string
    {
        if ($this->path) {
            return Str::beforeLast($this->path, '/');
        }

        return null;
    }
}
