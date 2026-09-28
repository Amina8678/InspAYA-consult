<?php

namespace App\Policies;

use App\Models\SiteSetting;
use App\Models\User;

/**
 * §7 "Manage site settings" (Full / Full / No / No). Setting keys are defined
 * in code (SiteSettingsSeeder), so values are edited but keys are never
 * created or deleted through the CMS.
 */
class SiteSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('settings.manage');
    }

    public function update(User $user, SiteSetting $setting): bool
    {
        return $user->hasPermission('settings.manage');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, SiteSetting $setting): bool
    {
        return false;
    }
}
