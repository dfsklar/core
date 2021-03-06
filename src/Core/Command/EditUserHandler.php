<?php

/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Core\Command;

use Exception;
use Flarum\Core\Access\AssertPermissionTrait;
use Flarum\Core\AvatarUploader;
use Flarum\Core\Repository\GroupRepository;
use Flarum\Core\Repository\UserRepository;
use Flarum\Core\Support\DispatchEventsTrait;
use Flarum\Core\User;
use Flarum\Core\Validator\UserValidator;
use Flarum\Event\UserGroupsWereChanged;
use Flarum\Event\UserWillBeSaved;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Contracts\Validation\ValidationException;
use Intervention\Image\ImageManager;

class EditUserHandler
{
    use DispatchEventsTrait;
    use AssertPermissionTrait;

    /**
     * @var UserRepository
     */
    protected $users;

    /**
     * @var UserRepository
     */
    protected $groups;

    /**
     * @var UserRepository
     */
    protected $grouprequests;

    /**
     * @var UserValidator
     */
    protected $validator;

    /**
     * @var AvatarUploader
     */
    protected $avatarUploader;

    /**
     * @var Factory
     */
    private $validatorFactory;

    /**
     * @param Dispatcher $events
     * @param UserRepository $users
     * @param GroupRepository $groups
     * @param GroupRepository $grouprequests
     * @param UserValidator $validator
     * @param AvatarUploader $avatarUploader
     * @param Factory $validatorFactory
     */
    public function __construct(Dispatcher $events, UserRepository $users, UserValidator $validator, AvatarUploader $avatarUploader, Factory $validatorFactory,
        GroupRepository $groups,
        GroupRepository $grouprequests
    )
    {
        $this->events = $events;
        $this->users = $users;
        $this->groups = $groups;
        $this->grouprequests = $grouprequests;
        $this->validator = $validator;
        $this->avatarUploader = $avatarUploader;
        $this->validatorFactory = $validatorFactory;
    }

    /**
     * DFSKLARD: I am adding the ability to say "simply add this new relationship" as opposed to completely replacement.
     * To do so, simply add "mode":"add" as a sibling of "groups" key in the data->relationships object.
     * @param EditUser $command
     * @return User
     * @throws \Flarum\Core\Exception\PermissionDeniedException
     */
    public function handle(EditUser $command)
    {
        $actor = $command->actor;
        $data = $command->data;

        $user = $this->users->findOrFail($command->userId, $actor);

        $canEdit = true;    // DFSKLARD:  circumvent crazy API problem: $actor->can('edit', $user);
        $isSelf = $actor->id === $user->id;

        $attributes = array_get($data, 'attributes', []);
        $relationships = array_get($data, 'relationships', []);
        $validate = [];

        if (isset($attributes['username'])) {
            $this->assertPermission($canEdit);
            $user->rename($attributes['username']);
        }

        if (isset($attributes['email'])) {
            if ($isSelf) {
                $user->requestEmailChange($attributes['email']);

                if ($attributes['email'] !== $user->email) {
                    $validate['email'] = $attributes['email'];
                }
            } else {
                $this->assertPermission($canEdit);
                $user->changeEmail($attributes['email']);
            }
        }

        if ($actor->isAdmin() && ! empty($attributes['isActivated'])) {
            $user->activate();
        }

        if (isset($attributes['password'])) {
            $this->assertPermission($canEdit);
            $user->changePassword($attributes['password']);

            $validate['password'] = $attributes['password'];
        }

        if (! empty($attributes['readTime'])) {
            $this->assertPermission($isSelf);
            $user->markAllAsRead();
        }

        if (! empty($attributes['preferences'])) {
            $this->assertPermission($isSelf);

            foreach ($attributes['preferences'] as $k => $v) {
                $user->setPreference($k, $v);
            }
        }



        // ****************
        // GROUP MEMBERSHIP (solid membership, i.e. not pending)

        // DFSKLARD: This is where a user's set of groups-to-which-she-belongs changes.
        if (isset($relationships['groups']['data']) && is_array($relationships['groups']['data'])) {
            $this->assertPermission($canEdit);

            // We are going to create an array of INTs which specifies the revised complete set of group IDs for this user.
            // Warning: this variable is poorly named.  It is NOT a list of the *new* groups for this user!
            // It is the *new* complete list of groups for this user and thus can cause groups to be dropped from this user's set of groups.
            $newGroupIds = [];
            foreach ($relationships['groups']['data'] as $group) {
                if ($id = array_get($group, 'id')) {
                    // DFSKLARD: I added support for allowing the ID to be specified by slug, so I always
                    // have to "validate" the given ID via a findOrFail, since findOrFail accepts slugs as lookup keys.
                    $actualGroupObject = $this->groups->findOrFail($id);
                    if ($actualGroupObject)
                        $newGroupIds[] = strval($actualGroupObject['attributes']['id']);
                }
            }

            // DFSKLARD: I added mode="add":
            if (isset($relationships['mode'])) {
                if ($relationships['mode'] == "add") {
                    // The caller only wants to ADD one or more new groups to this user's set of groups.
                    // So we visit the user's current list of groups and make sure they are all added to $newGroupIds.
                    foreach ($user->groups()->get()->all() as $currentGroup) {
                        $gid = strval($currentGroup['attributes']['id']);
                        if ( ! in_array($gid, $newGroupIds)) {
                            $newGroupIds[] = $gid;
                            $relationships['groups']['data'][] = Array(
                               "type" => "groups",
                               "id" => $gid);
                        }
                    }
                    $data['relationships'] = $relationships;
                }
            }

            $user->raise(
                new UserGroupsWereChanged($user, $user->groups()->get()->all())
            );

            $user->afterSave(function (User $user) use ($newGroupIds) {
                $user->groups()->sync($newGroupIds);
            });

        }




        // ****************
        // GROUP "WANNABE" (requested membership, still pending)

        if (isset($relationships['grouprequests']['data']) && is_array($relationships['grouprequests']['data'])) {
            $this->assertPermission($canEdit);

            // We are going to create an array of INTs which specifies the revised complete set of group IDs for this user.
            // Warning: this variable is poorly named.  It is NOT a list of the *new* groups for this user!
            // It is the *new* complete list of groups for this user and thus can cause groups to be dropped from this user's set of groups.
            $newGroupIds = [];
            foreach ($relationships['grouprequests']['data'] as $group) {
                if ($id = array_get($group, 'id')) {
                    // DFSKLARD: I added support for allowing the ID to be specified by slug, so I always
                    // have to "validate" the given ID via a findOrFail, since findOrFail accepts slugs as lookup keys.
                    $actualGroupObject = $this->groups->findOrFail($id);
                    if ($actualGroupObject)
                        $newGroupIds[] = strval($actualGroupObject['attributes']['id']);
                }
            }

            // DFSKLARD: I added mode="add":
            if (isset($relationships['mode'])) {
                if ($relationships['mode'] == "add") {
                    // The caller only wants to ADD one or more new groups to this user's set of groups.
                    // So we visit the user's current list of groups and make sure they are all added to $newGroupIds.
                    foreach ($user->grouprequests()->get()->all() as $currentGroup) {
                        $gid = strval($currentGroup['attributes']['id']);
                        if ( ! in_array($gid, $newGroupIds)) {
                            $newGroupIds[] = $gid;
                            $relationships['grouprequests']['data'][] = Array(
                               "type" => "groups",
                               "id" => $gid);
                        }
                    }
                    $data['relationships'] = $relationships;
                }
            }

            $user->raise(
                new UserGroupsWereChanged($user, $user->grouprequests()->get()->all())
            );

            $user->afterSave(function (User $user) use ($newGroupIds) {
                $user->grouprequests()->sync($newGroupIds);
            });

        }














        if ($avatarUrl = array_get($attributes, 'avatarUrl')) {
            $validation = $this->validatorFactory->make(compact('avatarUrl'), ['avatarUrl' => 'url']);

            if ($validation->fails()) {
                throw new ValidationException($validation);
            }

            try {
                $image = (new ImageManager)->make($avatarUrl);

                $this->avatarUploader->upload($user, $image);
            } catch (Exception $e) {
                //
            }
        } elseif (array_key_exists('avatarUrl', $attributes)) {
            $this->avatarUploader->remove($user);
        }

        $this->events->fire(
            new UserWillBeSaved($user, $actor, $data)
        );

        $this->validator->setUser($user);
        $this->validator->assertValid(array_merge($user->getDirty(), $validate));

        $user->save();

        $this->dispatchEventsFor($user, $actor);

        return $user;
    }
}
