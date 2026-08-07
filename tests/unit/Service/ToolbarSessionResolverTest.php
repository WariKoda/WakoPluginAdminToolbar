<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Tests\Unit\Service;

use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use WakoPluginAdminToolbar\Service\Toolbar\ToolbarPermissionService;
use WakoPluginAdminToolbar\Service\Toolbar\ToolbarSessionResolver;

final class ToolbarSessionResolverTest extends TestCase
{
    public function testInvalidBearerTokenIsRejectedByShopwareValidator(): void
    {
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects(static::never())->method('search');

        $bearerTokenValidator = $this->createMock(SymfonyBearerTokenValidator::class);
        $bearerTokenValidator
            ->expects(static::once())
            ->method('validateAuthorization')
            ->willThrowException(OAuthServerException::accessDenied('Invalid access token.'));

        $resolver = new ToolbarSessionResolver(
            $userRepository,
            $bearerTokenValidator,
            new RateLimiterFactory(
                ['id' => 'toolbar-test', 'policy' => 'no_limit'],
                new InMemoryStorage(),
            ),
            new ToolbarPermissionService(),
        );

        $request = Request::create('/admin/toolbar-auth');
        $request->cookies->set('bearerAuth', json_encode([
            'access' => 'invalid-token',
            'expiry' => PHP_INT_MAX,
        ], \JSON_THROW_ON_ERROR));

        static::assertNull($resolver->resolve($request));
    }

    public function testRequestWithoutBearerCookieDoesNotInvokeValidator(): void
    {
        $bearerTokenValidator = $this->createMock(SymfonyBearerTokenValidator::class);
        $bearerTokenValidator->expects(static::never())->method('validateAuthorization');

        $resolver = new ToolbarSessionResolver(
            $this->createMock(EntityRepository::class),
            $bearerTokenValidator,
            new RateLimiterFactory(
                ['id' => 'toolbar-test', 'policy' => 'no_limit'],
                new InMemoryStorage(),
            ),
            new ToolbarPermissionService(),
        );

        static::assertNull($resolver->resolve(Request::create('/admin/toolbar-auth')));
    }
}
