<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Shared\Domain\Identity\PublicId;
use App\Shared\Domain\Identity\PublicIdGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\ClockInterface;
use Tests\TestCase;

final class PublicIdTest extends TestCase
{
    public function test_it_accepts_and_canonicalizes_uuid_v7_values(): void
    {
        $id = PublicId::fromString('018F47A2-4F4A-7B0F-8B15-9F82558B5924');

        self::assertSame('018f47a2-4f4a-7b0f-8b15-9f82558b5924', $id->toString());
        self::assertSame($id->toString(), (string) $id);
        self::assertTrue($id->equals(PublicId::fromString($id->toString())));
    }

    #[DataProvider('invalidIdentifiers')]
    public function test_it_rejects_non_v7_or_malformed_identifiers(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        PublicId::fromString($value);
    }

    /**
     * Provide malformed and non-version-seven identifiers.
     *
     * @return iterable<string, array{string}>
     */
    public static function invalidIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'uuid v4' => ['550e8400-e29b-41d4-a716-446655440000'];
        yield 'invalid variant' => ['018f47a2-4f4a-7b0f-7b15-9f82558b5924'];
        yield 'extra data' => ['018f47a2-4f4a-7b0f-8b15-9f82558b5924-suffix'];
    }

    public function test_the_bound_generator_emits_unique_uuid_v7_values_using_the_utc_clock(): void
    {
        $generator = $this->app->make(PublicIdGenerator::class);
        $clock = $this->app->make(ClockInterface::class);

        $first = $generator->generate();
        $second = $generator->generate();

        self::assertNotSame($first->toString(), $second->toString());
        self::assertSame('+00:00', $clock->now()->format('P'));
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $first->toString(),
        );
    }
}
