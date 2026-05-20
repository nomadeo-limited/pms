<?php

namespace App\Policies;

use App\Models\Organizer;
use App\Models\User;

class OrganizerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, Organizer $organizer): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->organizers()->where('organizers.id', $organizer->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, Organizer $organizer): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasRole('organizer_admin')
            && $user->organizers()->where('organizers.id', $organizer->id)->exists();
    }

    public function delete(User $user, Organizer $organizer): bool
    {
        return $user->hasRole('super_admin');
    }
}
