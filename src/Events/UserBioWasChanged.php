<?php namespace Flarum\Events;

use Flarum\Core\Users\User;

class UserBioWasChanged
{
    /**
     * The user whose bio was changed.
     *
     * @var User
     */
    public $user;

    /**
     * @param User $user The user whose bio was changed.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }
}