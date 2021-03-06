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
use Flarum\Core\AuthToken;
use Flarum\Core\AvatarUploader;
use Flarum\Core\Exception\PermissionDeniedException;
use Flarum\Core\Support\DispatchEventsTrait;
use Flarum\Core\User;
use Flarum\Core\Validator\UserValidator;
use Flarum\Event\UserWillBeSaved;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Contracts\Validation\ValidationException;
use Intervention\Image\ImageManager;

class RegisterUserHandler
{
    use DispatchEventsTrait;
    use AssertPermissionTrait;

    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

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
     * @param SettingsRepositoryInterface $settings
     * @param UserValidator $validator
     * @param AvatarUploader $avatarUploader
     * @param Factory $validatorFactory
     */
    public function __construct(Dispatcher $events, SettingsRepositoryInterface $settings, UserValidator $validator, AvatarUploader $avatarUploader, Factory $validatorFactory)
    {
        $this->events = $events;
        $this->settings = $settings;
        $this->validator = $validator;
        $this->avatarUploader = $avatarUploader;
        $this->validatorFactory = $validatorFactory;
    }

    /**
     * @param RegisterUser $command
     * @throws PermissionDeniedException if signup is closed and the actor is
     *     not an administrator.
     * @throws \Flarum\Core\Exception\InvalidConfirmationTokenException if an
     *     email confirmation token is provided but is invalid.
     * @return User
     */
    public function handle(RegisterUser $command)
    {
        $actor = $command->actor;
        $data = $command->data;

        if (! $this->settings->get('allow_sign_up')) {
            $this->assertAdmin($actor);
        }

        $username = array_get($data, 'attributes.username');
        $email = array_get($data, 'attributes.email');
        $password = array_get($data, 'attributes.password');
        $uid = array_get($data, 'attributes.uid');   // DFSKLARD addition
        
        // If a valid authentication token was provided as an attribute,
        // then we won't require the user to choose a password.
        if (isset($data['attributes']['token'])) {
            $token = AuthToken::validOrFail($data['attributes']['token']);
            $password = $password ?: str_random(20);
        }

        $user = User::register($username, $uid, $email, $password);

        // If a valid authentication token was provided, then we will assign
        // the attributes associated with it to the user's account. If this
        // includes an email address, then we will activate the user's account
        // from the get-go.
        if (isset($token)) {
            foreach ($token->payload as $k => $v) {
                if (in_array($user->$k, ['', null], true)) {
                    $user->$k = $v;
                }
            }

            if (isset($token->payload['email'])) {
                $user->activate();
            }
        }

        if ($actor->isAdmin() && array_get($data, 'attributes.isActivated')) {
            $user->activate();
        }

        // DFSKLARD: the above is not working for me, so must activate automatically.
        $user->activate();
        

        if ($avatarUrl = array_get($data, 'attributes.avatarUrl')) {
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
        }

        $this->events->fire(
            new UserWillBeSaved($user, $actor, $data)
        );

        $this->validator->assertValid(array_merge($user->getAttributes(), compact('password')));

        $user->save();

        if (isset($token)) {
            $token->delete();
        }

        $this->dispatchEventsFor($user, $actor);

        return $user;
    }
}
