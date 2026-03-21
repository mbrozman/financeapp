<?php

namespace App\Observers;

use App\Models\User;
use App\Services\UserInitializationService;

class UserObserver
{
    public function __construct(
        protected UserInitializationService $initializationService
    ) {}

    /**
     * Spracuje udalosť vytvorenia užívateľa.
     */
    public function created(User $user): void
    {
        $this->initializationService->initialize($user);
    }
}
