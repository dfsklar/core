<?php

/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Api;

use Flarum\Api\Controller\AbstractSerializeController;
use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\NotificationSerializer;
use Flarum\Event\ConfigureApiRoutes;
use Flarum\Event\ConfigureNotificationTypes;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Http\Handler\RouteHandlerFactory;
use Flarum\Http\RouteCollection;
use Tobscure\JsonApi\ErrorHandler;
use Tobscure\JsonApi\Exception\Handler\FallbackExceptionHandler;
use Tobscure\JsonApi\Exception\Handler\InvalidParameterExceptionHandler;

class ApiServiceProvider extends AbstractServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register()
    {
        $this->app->singleton(UrlGenerator::class, function () {
            return new UrlGenerator($this->app, $this->app->make('flarum.api.routes'));
        });

        $this->app->singleton('flarum.api.routes', function () {
            return new RouteCollection;
        });

        $this->app->singleton(ErrorHandler::class, function () {
            $handler = new ErrorHandler;

            $handler->registerHandler(new Handler\FloodingExceptionHandler);
            $handler->registerHandler(new Handler\IlluminateValidationExceptionHandler);
            $handler->registerHandler(new Handler\InvalidAccessTokenExceptionHandler);
            $handler->registerHandler(new Handler\InvalidConfirmationTokenExceptionHandler);
            $handler->registerHandler(new Handler\MethodNotAllowedExceptionHandler);
            $handler->registerHandler(new Handler\ModelNotFoundExceptionHandler);
            $handler->registerHandler(new Handler\PermissionDeniedExceptionHandler);
            $handler->registerHandler(new Handler\RouteNotFoundExceptionHandler);
            $handler->registerHandler(new Handler\TokenMismatchExceptionHandler);
            $handler->registerHandler(new Handler\ValidationExceptionHandler);
            $handler->registerHandler(new InvalidParameterExceptionHandler);
            $handler->registerHandler(new FallbackExceptionHandler($this->app->inDebugMode()));

            return $handler;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        $this->populateRoutes($this->app->make('flarum.api.routes'));

        $this->registerNotificationSerializers();

        AbstractSerializeController::setContainer($this->app);
        AbstractSerializeController::setEventDispatcher($events = $this->app->make('events'));

        AbstractSerializer::setContainer($this->app);
        AbstractSerializer::setEventDispatcher($events);
    }

    /**
     * Register notification serializers.
     */
    protected function registerNotificationSerializers()
    {
        $blueprints = [];
        $serializers = [
            'discussionRenamed' => 'Flarum\Api\Serializer\DiscussionBasicSerializer'
        ];

        $this->app->make('events')->fire(
            new ConfigureNotificationTypes($blueprints, $serializers)
        );

        foreach ($serializers as $type => $serializer) {
            NotificationSerializer::setSubjectSerializer($type, $serializer);
        }
    }

    /**
     * Populate the API routes.
     * 
     * DFSKLARD: The ROUTES for the API on the PHP side of the world.
     *
     * @param RouteCollection $routes
     */
    protected function populateRoutes(RouteCollection $routes)
    {
        $route = $this->app->make(RouteHandlerFactory::class);

        // Get forum information
        $routes->get(
            '/forum',
            'forum.show',
            $route->toController(Controller\ShowForumController::class)
        );

        // Retrieve authentication token
        $routes->post(
            '/token',
            'token',
            $route->toController(Controller\TokenController::class)
        );

        // Send forgot password email
        $routes->post(
            '/forgot',
            'forgot',
            $route->toController(Controller\ForgotPasswordController::class)
        );

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */

        // List users
        $routes->get(
            '/users',
            'users.index',
            $route->toController(Controller\ListUsersController::class)
        );


        // Register a user
        // POST: /api/users
        // Body: { data: { attributes: {
        //          uid:
        //          username: 
        //          email:
        //          password:
        //       }}
        // Error response:  code 422 if missing required fields
        $routes->post(
            '/users',
            'users.create',
            $route->toController(Controller\CreateUserController::class)
        );

        
        // Get all info about a single user
        /*
        Actual sample result:
        {
    "data": {
        "type": "users",
        "id": "2",
        "attributes": {
            "username": "sklard",
            "displayName": "sklard",
            "avatarUrl": null,
            "joinTime": "2017-11-12T18:38:45+00:00",
            "discussionsCount": 0,
            "commentsCount": 2,
            "canEdit": true,
            "canDelete": true,
            "lastSeenTime": "2017-11-14T02:41:36+00:00",
            "isActivated": true,
            "email": "sklard@tumblr.com",
            "readTime": null,
            "unreadNotificationsCount": 7,
            "newNotificationsCount": 2,
            "preferences": {
                "notify_discussionRenamed_alert": true,
                "notify_postLiked_alert": true,
                "notify_discussionLocked_alert": true,
                "notify_postMentioned_alert": true,
                "notify_postMentioned_email": false,
                "notify_userMentioned_alert": true,
                "notify_userMentioned_email": false,
                "notify_newPost_alert": true,
                "notify_newPost_email": true,
                "followAfterReply": false,
                "discloseOnline": true,
                "indexProfile": true,
                "locale": null
            },
            "newFlagsCount": 0,
            "suspendUntil": null,
            "canSuspend": true
        },
        "relationships": {
            "groups": {
                "data": [
                    {
                        "type": "groups",
                        "id": "7"
                    }
                ]
            }
        }
    },
    "included": [
        {
            "type": "groups",
            "id": "7",
            "attributes": {
                "nameSingular": "Layperson",
                "namePlural": "Laypeople",
                "color": null,
                "icon": null
            }
        }
    ]
}*/
        $routes->get(
            '/users/{id}',
            'users.show',
            $route->toController(Controller\ShowUserController::class)
        );

        // Edit a user
        /*
        It is possible to edit a user's GROUP MEMBERSHIP via this call.
        Use this body:
        {"data":{
	        "relationships": {
              "groups": {
                "data": [
                    {
                        "type": "groups",
                        "id": "7"
                    }
                ]
              }
        }   }
        Keep in mind: you will be completely revising the user's group membership!
        So you must REPEAT existing group memberships if your intent is to add!
        */
        $routes->patch(
            '/users/{id}',
            'users.update',
            $route->toController(Controller\UpdateUserController::class)
        );

        // Delete a user
        $routes->delete(
            '/users/{id}',
            'users.delete',
            $route->toController(Controller\DeleteUserController::class)
        );

        // Upload avatar
        $routes->post(
            '/users/{id}/avatar',
            'users.avatar.upload',
            $route->toController(Controller\UploadAvatarController::class)
        );

        // Remove avatar
        $routes->delete(
            '/users/{id}/avatar',
            'users.avatar.delete',
            $route->toController(Controller\DeleteAvatarController::class)
        );

        // send confirmation email
        $routes->post(
            '/users/{id}/send-confirmation',
            'users.confirmation.send',
            $route->toController(Controller\SendConfirmationEmailController::class)
        );

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        // List notifications for the current user
        $routes->get(
            '/notifications',
            'notifications.index',
            $route->toController(Controller\ListNotificationsController::class)
        );

        // Mark all notifications as read
        $routes->post(
            '/notifications/read',
            'notifications.readAll',
            $route->toController(Controller\ReadAllNotificationsController::class)
        );

        // Mark a single notification as read
        $routes->patch(
            '/notifications/{id}',
            'notifications.update',
            $route->toController(Controller\UpdateNotificationController::class)
        );

        /*
        |--------------------------------------------------------------------------
        | Discussions
        |--------------------------------------------------------------------------
        */

        // List discussions
        $routes->get(
            '/discussions',
            'discussions.index',
            $route->toController(Controller\ListDiscussionsController::class)
        );

        // Create a discussion
        $routes->post(
            '/discussions',
            'discussions.create',
            $route->toController(Controller\CreateDiscussionController::class)
        );

        // Show a single discussion
        $routes->get(
            '/discussions/{id}',
            'discussions.show',
            $route->toController(Controller\ShowDiscussionController::class)
        );

        // Edit a discussion
        $routes->patch(
            '/discussions/{id}',
            'discussions.update',
            $route->toController(Controller\UpdateDiscussionController::class)
        );

        // Delete a discussion
        $routes->delete(
            '/discussions/{id}',
            'discussions.delete',
            $route->toController(Controller\DeleteDiscussionController::class)
        );

        /*
        |--------------------------------------------------------------------------
        | Posts
        |--------------------------------------------------------------------------
        */

        // List posts, usually for a discussion
        $routes->get(
            '/posts',
            'posts.index',
            $route->toController(Controller\ListPostsController::class)
        );

        // Create a post
        $routes->post(
            '/posts',
            'posts.create',
            $route->toController(Controller\CreatePostController::class)
        );

        // Show a single or multiple posts by ID
        $routes->get(
            '/posts/{id}',
            'posts.show',
            $route->toController(Controller\ShowPostController::class)
        );

        // Edit a post
        $routes->patch(
            '/posts/{id}',
            'posts.update',
            $route->toController(Controller\UpdatePostController::class)
        );

        // Delete a post
        $routes->delete(
            '/posts/{id}',
            'posts.delete',
            $route->toController(Controller\DeletePostController::class)
        );






        /*
        |--------------------------------------------------------------------------
        | Groups
        |--------------------------------------------------------------------------
        */

        // List groups
        // Example response:
        /*
        {"data":
            [{"type":"groups","id":"1","attributes":{"nameSingular":"Admin","namePlural":"Admins","color":"#B72A2A","icon":"wrench"}},
             {"type":"groups","id":"2","attributes":{"nameSingular":"Guest","namePlural":"Guests","color":null,"icon":null}},
             {"type":"groups","id":"3","attributes":{"nameSingular":"Member","namePlural":"Members","color":null,"icon":null}},
             {"type":"groups","id":"4","attributes":{"nameSingular":"Mod","namePlural":"Mods","color":"#80349E","icon":"bolt"}},
             {"type":"groups","id":"5","attributes":{"nameSingular":"YoungMarrieds","namePlural":"YoungMarrieds","color":"","icon":""}},
             {"type":"groups","id":"6","attributes":{"nameSingular":"Youth Group","namePlural":"Youth Group","color":"#458B00","icon":""}}]}
        */
        $routes->get(
            '/groups',
            'groups.index',
            $route->toController(Controller\ListGroupsController::class)
        );


        // Create a group
        // Required attributes:  nameSingular and namePlural
        // Sample body: {"data":{"attributes":{"nameSingular":"Layperson", "namePlural":"Laypeople"}}}
        $routes->post(
            '/groups',
            'groups.create',
            $route->toController(Controller\CreateGroupController::class)
        );



        // Edit a group's characteristics 
        // ***> BUT NOT ITS MEMBERSHIP LIST
        $routes->patch(
            '/groups/{id}',
            'groups.update',
            $route->toController(Controller\UpdateGroupController::class)
        );

        // Delete a group
        $routes->delete(
            '/groups/{id}',
            'groups.delete',
            $route->toController(Controller\DeleteGroupController::class)
        );



        /*
        |--------------------------------------------------------------------------
        | Administration
        |--------------------------------------------------------------------------
        */

        // Toggle an extension
        $routes->patch(
            '/extensions/{name}',
            'extensions.update',
            $route->toController(Controller\UpdateExtensionController::class)
        );

        // Uninstall an extension
        $routes->delete(
            '/extensions/{name}',
            'extensions.delete',
            $route->toController(Controller\UninstallExtensionController::class)
        );

        // Update settings
        $routes->post(
            '/settings',
            'settings',
            $route->toController(Controller\SetSettingsController::class)
        );

        // Update a permission
        $routes->post(
            '/permission',
            'permission',
            $route->toController(Controller\SetPermissionController::class)
        );

        // Upload a logo
        $routes->post(
            '/logo',
            'logo',
            $route->toController(Controller\UploadLogoController::class)
        );

        // Remove the logo
        $routes->delete(
            '/logo',
            'logo.delete',
            $route->toController(Controller\DeleteLogoController::class)
        );

        // Upload a favicon
        $routes->post(
            '/favicon',
            'favicon',
            $route->toController(Controller\UploadFaviconController::class)
        );

        // Remove the favicon
        $routes->delete(
            '/favicon',
            'favicon.delete',
            $route->toController(Controller\DeleteFaviconController::class)
        );

        $this->app->make('events')->fire(
            new ConfigureApiRoutes($routes, $route)
        );
    }
}
