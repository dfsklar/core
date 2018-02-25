<?php

/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Core;

use Flarum\Core\Support\EventGeneratorTrait;
use Flarum\Database\AbstractModel;
use Flarum\Event\DiscussionWasRead;
use Illuminate\Database\Eloquent\Builder;

/**
 * Models a group-to-user state record in the database.
 *
 * Stores information about a user's membership in a given group.
 * Status can be:  "pending", "member", or "rejected".
 * 
 * @property int $user_id
 * @property int $group_id
 * @property Group $group
 * @property \Flarum\Core\User $user
 */
class DiscussionState extends AbstractModel
{
    use EventGeneratorTrait;

    /**
     * {@inheritdoc}
     */
    protected $table = 'users_groups';

    /**
     * Mark the group as having this particular relationship.
     * 
     * @param int $number
     * @return $this
     */
    public function setUserState($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Define the relationship with the group that this state is for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group()
    {
        return $this->belongsTo('Flarum\Core\Group', 'group_id');
    }

    /**
     * Define the relationship with the user that this state is for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('Flarum\Core\User', 'user_id');
    }

    /**
     * Set the keys for a save update query.
     *
     * @param Builder $query
     * @return Builder
     */
    protected function setKeysForSaveQuery(Builder $query)
    {
        $query->where('group_id', $this->group_id)
              ->where('user_id', $this->user_id);

        return $query;
    }
}
