<?php

namespace App\Policies;

use App\Models\Send;
use App\Models\User;
use App\Policies\Traits\HandlesPolicyResponses;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\BinaryCodec;

class SendPolicy
{
    use HandlesPolicyResponses;

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
        $activeSendCount = Send::query()
            ->where('user_id', $user->id)
            ->where('valid_to', '>=', now())
            ->count();

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
            $send->user_id === $user->id ||
            $user->authorizedSends()
                ->where('sends.id', BinaryCodec::encode($send->id, 'ulid'))
                ->where('valid_to', '>=', now())
                ->exists()
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
