<?php

/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Core\Repository;

use Flarum\Core\User;
use Illuminate\Database\Eloquent\Builder;

class UserRepository
{
    /**
     * Get a new query builder for the users table.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return User::query();
    }

    /**
     * Find a user by ID, optionally making sure it is visible to a certain
     * user, or throw an exception.
     * DFSKLARD ADDED support for lookup by UID as a backup.
     *
     * @param int $id
     * @param User $actor
     * @return User
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail($id, User $actor = null)
    {
        $query = User::where('id', $id);
        if (! $query->first()) {
            /*
             * OK so this next stuff is weird.
             * I'm taking the full 32-character UID and truncating it to 28-char.
             * WHY?
             * Because at one point in the schema's history, UIDs in the mysql table
             * were being truncated.  This allows those legacy UIDs to be compatible
             * with attempts to login using the full 32-char UID.
             * At some point, we should eliminate or repair all rows that are living with
             * the damaged UIDs and turn off this use of "like" that is obviously 
             * dangerous if two UIDs happen to be virtually identical.
             */
            $query = User::where('uid', 'like', substr($id, 0, 28)."%");
        }
        return $this->scopeVisibleTo($query, $actor)->firstOrFail();
    }


    /**
     * Find a user by an identification (username or email).
     *
     * @param string $identification
     * @return User|null
     */
    public function findByIdentification($identification)
    {
        $field = filter_var($identification, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return User::where($field, $identification)->first();
    }

    /**
     * Find a user by email.
     *
     * @param string $email
     * @return User|null
     */
    public function findByEmail($email)
    {
        return User::where('email', $email)->first();
    }

    /**
     * Get the ID of a user with the given username.
     *
     * @param string $username
     * @param User|null $actor
     * @return int|null
     */
    public function getIdForUsername($username, User $actor = null)
    {
        $query = User::where('username', 'like', $username);

        return $this->scopeVisibleTo($query, $actor)->value('id');
    }

    /**
     * Get the ID of a user with the given UUID ("uid").
     *
     * @param string $uid
     * @param User|null $actor
     * @return int|null
     */
    public function getIdForUID($uid, User $actor = null)
    {
        $query = User::where('uid', '=', $uid);

        return $this->scopeVisibleTo($query, $actor)->value('id');
    }

    /**
     * Find users by matching a string of words against their username,
     * optionally making sure they are visible to a certain user.
     *
     * @param string $string
     * @param User|null $actor
     * @return array
     */
    public function getIdsForUsername($string, User $actor = null)
    {
        $query = User::where('username', 'like', '%'.$string.'%')
            ->orderByRaw('username = ? desc', [$string])
            ->orderByRaw('username like ? desc', [$string.'%']);

        return $this->scopeVisibleTo($query, $actor)->lists('id');
    }

    /**
     * Scope a query to only include records that are visible to a user.
     *
     * @param Builder $query
     * @param User $actor
     * @return Builder
     */
    protected function scopeVisibleTo(Builder $query, User $actor = null)
    {
        if ($actor !== null) {
            $query->whereVisibleTo($actor);
        }

        return $query;
    }
}
