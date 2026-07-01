<?php

namespace App\Policies;

use App\Models\Send;
use App\Models\User;
use App\Policies\Traits\HandlesPolicyResponses;
use App\Services\Interfaces\SendReadServiceInterface;
use Illuminate\Auth\Access\Response;

class SendPolicy
{
    use HandlesPolicyResponses;

    public function __construct(private SendReadServiceInterface $sendReadService) {}

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response
    {
        return $this->sendResponse(false);
    }

    /**
     * Determine whether the user allowed creating a new model.
     */
    public function create(User $user): Response
    {
        $activeSendCount = $this->sendReadService->countActiveForUser($user->id);

        $maxSends = config('send.max_per_user');

        return $activeSendCount < $maxSends
            ? Response::allow()
            : Response::deny("You have exceeded the maximum number of sends ({$maxSends}).");
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Send $send): Response
    {
        if ($user->relationLoaded('authorizedSends')) {
            return $this->sendResponse($user->authorizedSends->contains('id', $send->id));
        }

        return $this->sendResponse(
            $this->isOwner($user, $send) ||
            $this->sendReadService->userHasActiveAuthorizedAccess($user->id, $send->id)
        );
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Send $send): Response
    {
        return $this->sendResponse($this->isOwner($user, $send));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Send $send): Response
    {
        return $this->sendResponse($this->isOwner($user, $send));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Send $send): Response
    {
        return $this->sendResponse($this->isOwner($user, $send));
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Send $send): Response
    {
        return $this->sendResponse($this->isOwner($user, $send));
    }

    /**
     * Helper to verify if the user owns the model.
     */
    private function isOwner(User $user, Send $send): bool
    {
        return $send->user_id === $user->id;
    }
}
