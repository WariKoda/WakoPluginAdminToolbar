<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Service\Toolbar;

use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Symfony\Component\HttpFoundation\Request;
use WakoPluginAdminToolbar\Struct\ToolbarSession;

final class ToolbarCustomerContextProvider
{
    public function __construct(
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
        private readonly ActiveRuleProvider $activeRuleProvider,
    ) {}

    /**
     * @return array{
     *     customer: array{
     *         id: string,
     *         displayName: string,
     *         firstName: string,
     *         lastName: string,
     *         customerNumber: string,
     *         email: string
     *     },
     *     activeRules: array<int, array{id: string, name: string, priority: int}>
     * }|null
     */
    public function load(Request $request, ToolbarSession $session, bool $includeRules = true): ?array
    {
        if (!$session->isEnabled() || !$request->hasSession()) {
            return null;
        }

        $requestSession = $request->getSession();

        $salesChannelId = (string) $requestSession->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, '');
        if ($salesChannelId === '') {
            return null;
        }

        $contextToken = (string) $requestSession->get(PlatformRequest::HEADER_CONTEXT_TOKEN, '');
        if ($contextToken === '') {
            return null;
        }

        try {
            $salesChannelContext = $this->salesChannelContextService->get(
                new SalesChannelContextServiceParameters($salesChannelId, $contextToken)
            );
        } catch (\Throwable) {
            return null;
        }

        $customer = $salesChannelContext->getCustomer();
        if ($customer === null || $customer->getGuest()) {
            return null;
        }

        $displayName = trim($customer->getFirstName() . ' ' . $customer->getLastName());

        return [
            'customer' => [
                'id' => (string) $customer->getId(),
                'displayName' => $displayName,
                'firstName' => (string) $customer->getFirstName(),
                'lastName' => (string) $customer->getLastName(),
                'customerNumber' => (string) $customer->getCustomerNumber(),
                'email' => (string) $customer->getEmail(),
            ],
            'activeRules' => $includeRules
                ? $this->activeRuleProvider->loadAssignedRules(
                    $salesChannelContext->getRuleIds(),
                    $salesChannelContext->getContext(),
                )
                : [],
        ];
    }
}
