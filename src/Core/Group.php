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
use Flarum\Core\Support\ScopeVisibilityTrait;
use Flarum\Database\AbstractModel;
use Flarum\Event\GroupWasCreated;
use Flarum\Event\GroupWasDeleted;
use Flarum\Event\GroupWasRenamed;

/**
 * @property int $id
 * @property string $name_singular
 * @property string $name_plural
 * @property string|null $color
 * @property string|null $icon
 * @property int|null $start_user_id
 * @property \Illuminate\Database\Eloquent\Collection $users
 * @property \Illuminate\Database\Eloquent\Collection $wannabeusers
 * @property \Illuminate\Database\Eloquent\Collection $permissions
 */
class Group extends AbstractModel
{
    use EventGeneratorTrait;
    use ScopeVisibilityTrait;

    /**
     * {@inheritdoc}
     */
    protected $table = 'groups';

    /**
     * The ID of the administrator group.
     */
    const ADMINISTRATOR_ID = 1;

    /**
     * The ID of the guest group.
     */
    const GUEST_ID = 2;

    /**
     * The ID of the member group.
     */
    const MEMBER_ID = 3;

    /**
     * The ID of the mod group.
     */
    const MODERATOR_ID = 4;

    /**
     * Boot the model.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        static::deleted(function (Group $group) {
            $group->raise(new GroupWasDeleted($group));

            $group->permissions()->delete();
        });
    }

    /**
     * Create a new group.
     *
     * @param string $nameSingular
     * @param string $namePlural
     * @param string $color
     * @param string $icon
     * @return static
     */
    public static function build($nameSingular, $namePlural, $color, $icon, $leader_user_id, $slug)
    {
        $group = new static;

        $group->name_singular = $nameSingular;
        $group->name_plural = $namePlural;
        $group->color = $color;
        $group->icon = $icon;
        $group->slug = $slug;   // DFSKLARD new field

        // The given USER ID (for leader) is currently ambiguous: either ID or UID.
        // Must normalize to UID.
        $group->leader_user_id = $leader_user_id;

        $group->raise(new GroupWasCreated($group));

        return $group;
    }

    /**
     * Rename the group.
     *
     * @param string $nameSingular
     * @param string $namePlural
     * @return $this
     */
    public function rename($nameSingular, $namePlural)
    {
        $this->name_singular = $nameSingular;
        $this->name_plural = $namePlural;

        $this->raise(new GroupWasRenamed($this));

        return $this;
    }

    /**
     * Define the relationship with the group's leader.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function leader_user()
    {
        return $this->belongsTo('Flarum\Core\User', 'leader_user_id');
    }


    /**
     * Query the discussion's participants (a list of unique users who have
     * posted in the discussion).
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function members()
    {
        return User::join('groups', 'groups.user_id', '=', 'users.id')
            ->where('groups.id', $this->id)
            ->select('users.*')
            ->distinct();
    }

    /**
     * Define the relationship with the group's users.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany('Flarum\Core\User', 'users_groups');
    }

    /**
     * Define the relationship with the group's "wannabe" users.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function wannabeusers()
    {
        return $this->belongsToMany('Flarum\Core\User', 'users_grouprequests');
    }

    /**
     * Define the relationship with the group's permissions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function permissions()
    {
        return $this->hasMany('Flarum\Core\Permission');
    }
}
