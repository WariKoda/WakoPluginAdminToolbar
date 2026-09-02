<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Service\Toolbar;

use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

final class ToolbarRenderContextFactory
{
    private const PAGE_TYPES = [
        'generic',
        'product',
        'navigation',
        'landingPage',
        'cmsPage',
    ];

    private const LOCALES = [
        'de-DE',
        'en-GB',
    ];

    /**
     * @return array{
     *     pageType: string,
     *     entityId: string|null,
     *     parentId: string|null,
     *     cmsPageId: string|null,
     *     routeName: string|null,
     *     locale: string
     * }
     */
    public function create(Request $request): array
    {
        $query = $request->query->all();

        $pageType = $this->stringValue($query, 'pageType');
        if ($pageType === null || !\in_array($pageType, self::PAGE_TYPES, true)) {
            $pageType = 'generic';
        }

        return [
            'pageType' => $pageType,
            'entityId' => $this->uuidValue($query, 'entityId'),
            'parentId' => $this->uuidValue($query, 'parentId'),
            'cmsPageId' => $this->uuidValue($query, 'cmsPageId'),
            'routeName' => $this->routeNameValue($query),
            'locale' => $this->localeValue($query),
        ];
    }

    /**
     * @param array<string, mixed> $query
     */
    private function uuidValue(array $query, string $key): ?string
    {
        $value = $this->stringValue($query, $key);

        return $value !== null && Uuid::isValid($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function routeNameValue(array $query): ?string
    {
        $routeName = $this->stringValue($query, 'routeName');
        if ($routeName === null || \strlen($routeName) > 190) {
            return null;
        }

        return \preg_match('/^[A-Za-z0-9_.:-]+$/', $routeName) === 1 ? $routeName : null;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function localeValue(array $query): string
    {
        $locale = $this->stringValue($query, 'locale');
        $locale = $locale === null ? null : str_replace('_', '-', $locale);

        return $locale !== null && \in_array($locale, self::LOCALES, true) ? $locale : 'en-GB';
    }

    /**
     * @param array<string, mixed> $query
     */
    private function stringValue(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
