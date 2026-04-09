<?php

namespace App\Enums;

enum VersionType: int
{
    case RELEASE = 0;
    case BETA = 1;
    case ALPHA = 2;
    case SNAPSHOT = 3;
    case RELEASE_HIGHLIGHTED = 4;

    /**
     * Returns basic version types: release, beta and alpha.
     *
     * @return VersionType[]
     */
    public static function getBasic(): array
    {
        return [self::RELEASE, self::BETA, self::ALPHA];
    }

    public function isRelease(): bool
    {
        return $this === self::RELEASE || $this === self::RELEASE_HIGHLIGHTED;
    }

    public function name(): string
    {
        return match ($this) {
            self::RELEASE, self::RELEASE_HIGHLIGHTED => 'Release',
            self::BETA => 'Beta',
            self::ALPHA => 'Alpha',
            self::SNAPSHOT => 'Snapshot',
        };
    }

    public function nameShort(): string
    {
        return match ($this) {
            self::RELEASE, self::RELEASE_HIGHLIGHTED => 'R',
            self::BETA => 'B',
            self::ALPHA => 'A',
            self::SNAPSHOT => 'S',
        };
    }

    public static function fromName(string $name): VersionType
    {
        return match (strtolower($name)) {
            'release' => self::RELEASE,
            'beta' => self::BETA,
            'alpha' => self::ALPHA,
            'snapshot' => self::SNAPSHOT,
            default => throw new \RuntimeException('Unknown version type: '.$name)
        };
    }

    public static function fromMojang(string $type): VersionType
    {
        return match ($type) {
            'release' => self::RELEASE,
            'old_beta' => self::BETA,
            'old_alpha' => self::ALPHA,
            'snapshot' => self::SNAPSHOT,
            default => throw new \RuntimeException('Unknown version type: '.$type)
        };
    }
}
