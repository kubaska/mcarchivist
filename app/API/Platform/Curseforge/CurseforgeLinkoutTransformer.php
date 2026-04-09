<?php

namespace App\API\Platform\Curseforge;

use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

class CurseforgeLinkoutTransformer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return ['a'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['href'];
    }

    /**
     * @param string $element
     * @param string $attribute
     * @param string $value e.g. /linkout?remoteUrl=https%253a%252f%252fludocrypt.bandcamp.com%252f
     *        full element: <a href="/linkout?remoteUrl=https%253a%252f%252fludocrypt.bandcamp.com%252f">
     * @param HtmlSanitizerConfig $config
     * @return string|null
     */
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        if (! str_starts_with($value, '/linkout')) {
            return $value;
        }

        return urldecode(urldecode(Str::after($value, '?remoteUrl=')));
    }
}
