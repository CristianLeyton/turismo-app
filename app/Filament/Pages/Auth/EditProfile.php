<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Resources\Users\Pages\Auth\EditProfile as BaseEditProfile;

/**
 * Proxy class so Filament can resolve the default profile page path.
 * The real implementation lives in App\Filament\Resources\Users\Pages\Auth\EditProfile.
 */
class EditProfile extends BaseEditProfile
{
    //
}
