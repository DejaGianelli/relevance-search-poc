<?php

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\GuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Maps a native UUID column to a Symfony\Component\Uid\Uuid object.
 */
class UuidType extends GuidType
{
    public const NAME = 'uuid';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Uuid
    {
        if ($value === null || $value instanceof Uuid) {
            return $value;
        }

        try {
            return Uuid::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailed($value, self::NAME, $e);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Uuid) {
            return $value->toRfc4122();
        }

        if (is_string($value) && Uuid::isValid($value)) {
            return $value;
        }

        throw ConversionException::conversionFailed((string) $value, self::NAME);
    }
}
