<?php

declare(strict_types=1);

namespace Hyvor\Sdk\Serialization;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Builds the Symfony Serializer used to (de)normalize SDK DTOs to/from the
 * API's JSON shape. DTO properties are named identically to the API's JSON
 * fields (snake_case), so no name converter is needed.
 *
 * A PhpDocExtractor-backed PropertyInfoExtractor is required so that
 * `@var Foo[]` array properties (e.g. `Comment::$history`) are denormalized
 * into arrays of DTOs instead of arrays of raw arrays; plain Reflection
 * cannot see collection item types.
 *
 * @internal
 */
final class SerializerFactory
{
    public static function create(): Serializer
    {
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();

        $propertyInfo = new PropertyInfoExtractor(
            listExtractors: [$reflectionExtractor],
            typeExtractors: [$phpDocExtractor, $reflectionExtractor],
            descriptionExtractors: [$phpDocExtractor],
            accessExtractors: [$reflectionExtractor],
            initializableExtractors: [$reflectionExtractor],
        );

        return new Serializer([
            new BackedEnumNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(propertyTypeExtractor: $propertyInfo),
        ]);
    }
}
