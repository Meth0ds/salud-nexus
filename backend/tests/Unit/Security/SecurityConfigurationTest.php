<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use LogicException;
use Tests\TestCase;

final class SecurityConfigurationTest extends TestCase
{
    public function test_session_and_cors_defaults_are_fail_safe(): void
    {
        self::assertTrue(config()->boolean('session.encrypt'));
        self::assertTrue(config()->boolean('session.http_only'));
        self::assertSame('lax', config('session.same_site'));
        self::assertSame('json', config('session.serialization'));

        self::assertTrue(config()->boolean('cors.supports_credentials'));
        self::assertNotContains('*', config('cors.allowed_origins'));
        self::assertNotContains('*', config('cors.allowed_headers'));
        self::assertNotContains('*', config('cors.allowed_methods'));

        self::assertSame(['^localhost$', '^127\\.0\\.0\\.1$'], config('api.trusted_hosts'));
        self::assertSame([], config('api.trusted_proxies'));
    }

    public function test_document_storage_and_download_defaults_fail_closed(): void
    {
        self::assertSame('documents', config('documents.disk'));
        self::assertSame(90, config('documents.download_grant_ttl_seconds'));
        self::assertSame(10_485_760, config('documents.maximum_download_bytes'));
        self::assertSame('local', config('filesystems.disks.documents.driver'));
        self::assertFalse(config()->boolean('filesystems.disks.documents.serve'));

        $root = config('filesystems.disks.documents.root');
        self::assertIsString($root);
        $normalizedRoot = str_replace('\\', '/', $root);
        $normalizedPrivateStorage = str_replace('\\', '/', storage_path('app/private'));
        $normalizedPublicPath = str_replace('\\', '/', public_path());
        if ($normalizedPrivateStorage === '' || $normalizedPublicPath === '') {
            throw new LogicException('Resolved application paths must not be empty.');
        }
        self::assertStringStartsWith($normalizedPrivateStorage, $normalizedRoot);
        self::assertStringStartsNotWith($normalizedPublicPath, $normalizedRoot);
    }
}
