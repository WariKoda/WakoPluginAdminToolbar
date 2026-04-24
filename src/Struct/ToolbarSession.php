<?php declare(strict_types=1);

namespace WakoPluginAdminToolbar\Struct;

use Shopware\Core\System\User\UserEntity;

final class ToolbarSession
{
    /**
     * @param array<string, bool> $privileges
     * @param array<string, bool> $featurePreferences
     */
    public function __construct(
        private readonly string $userId,
        private readonly bool $enabled,
        private readonly bool $isAdmin,
        private readonly array $privileges,
        private readonly UserEntity $user,
        private readonly array $featurePreferences = [],
    ) {}

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    /**
     * @return array<string, bool>
     */
    public function getPrivileges(): array
    {
        return $this->privileges;
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    /**
     * @return array<string, bool>
     */
    public function getFeaturePreferences(): array
    {
        return $this->featurePreferences;
    }

    public function isFeatureEnabled(string $feature): bool
    {
        return $this->featurePreferences[$feature] ?? true;
    }

    public function hasPrivilege(string $privilege): bool
    {
        return $this->isAdmin || isset($this->privileges[$privilege]);
    }

    /**
     * @param array<string> $privileges
     */
    public function hasAllPrivileges(array $privileges): bool
    {
        foreach ($privileges as $privilege) {
            if (!$this->hasPrivilege($privilege)) {
                return false;
            }
        }

        return true;
    }
}
