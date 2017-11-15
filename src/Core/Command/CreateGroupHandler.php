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

use Flarum\Core\Access\AssertPermissionTrait;
use Flarum\Core\Exception\PermissionDeniedException;
use Flarum\Core\Group;
use Flarum\Core\Repository\UserRepository;
use Flarum\Core\Support\DispatchEventsTrait;
use Flarum\Core\Validator\GroupValidator;
use Flarum\Event\GroupWillBeSaved;
use Illuminate\Contracts\Events\Dispatcher;

class CreateGroupHandler
{
    use DispatchEventsTrait;
    use AssertPermissionTrait;

    /**
     * @var GroupValidator
     */
    protected $validator;

    /**
     * @param Dispatcher $events
     * @param GroupValidator $validator
     */
    public function __construct(Dispatcher $events, GroupValidator $validator, UserRepository $users)
    {
        $this->events = $events;
        $this->validator = $validator;
        $this->users = $users;
    }

    /**
     * @param CreateGroup $command
     * @return Group
     * @throws PermissionDeniedException
     */
    public function handle(CreateGroup $command)
    {
        $actor = $command->actor;
        $data = $command->data;

        $this->assertCan($actor, 'createGroup');

        // DFSKLARD:  normalize given user ID
        $leader = array_get($data, 'attributes.leader_user_id');
        // ^^^ This ended up working!  Made into mysql row whether or
        // not the JSON representation was string or int.

        $leader = $this->users->findOrFail(
           array_get($data, 'attributes.leader_user_id'), $actor);

        $group = Group::build(
            array_get($data, 'attributes.nameSingular'),
            array_get($data, 'attributes.namePlural'),
            array_get($data, 'attributes.color'),
            array_get($data, 'attributes.icon'),
            $leader->id
        );

        $this->events->fire(
            new GroupWillBeSaved($group, $actor, $data)
        );

        $this->validator->assertValid($group->getAttributes());

        $group->save();

        $this->dispatchEventsFor($group, $actor);

        return $group;
    }
}
