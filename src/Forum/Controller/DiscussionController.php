<?php

/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Forum\Controller;

use Flarum\Api\Client;
use Flarum\Core\User;
use Flarum\Forum\UrlGenerator;
use Flarum\Forum\WebApp;
use Flarum\Http\Exception\RouteNotFoundException;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Http\Message\ServerRequestInterface as Request;

class DiscussionController extends WebAppController
{
    /**
     * @var ApiClient
     */
    protected $api;

    /**
     * @var UrlGenerator
     */
    protected $url;

    /**
     * {@inheritdoc}
     */
    public function __construct(WebApp $webApp, Dispatcher $events, Client $api, UrlGenerator $url)
    {
        parent::__construct($webApp, $events);

        $this->api = $api;
        $this->url = $url;
    }

    /**
     * {@inheritdoc}
     */
    protected function getView(Request $request)
    {
        // DFSKLARD: Warning, this is hit only upon webapp initial bootstrap load!
        // DFSKLARD: Did I set the 200 posts-per-page amount, overriding the default 20?
        $view = parent::getView($request);

        $queryParams = $request->getQueryParams();
        $page = max(1, array_get($queryParams, 'page'));

        $params = [
            'id' => (int) array_get($queryParams, 'id'),
            'page' => [
                'near' => array_get($queryParams, 'near'),
                'offset' => ($page - 1) * 200,
                'limit' => 200
            ]
        ];

        $document = $this->getDocument($request->getAttribute('actor'), $params);

        $getResource = function ($link) use ($document) {
            return array_first($document->included, function ($key, $value) use ($link) {
                return $value->type === $link->type && $value->id === $link->id;
            });
        };

        $url = function ($newQueryParams) use ($queryParams, $document) {
            $newQueryParams = array_merge($queryParams, $newQueryParams);
            $queryString = http_build_query($newQueryParams);

            return $this->url->toRoute('discussion', ['id' => $document->data->id]).
            ($queryString ? '?'.$queryString : '');
        };

        // DFSKLARD: I have proven that the generation of a $posts array is actually
        // ignored downstream.  You can set it to null just before end of this routine
        // and nothing changes.

        // DFSKLARD: $posts used to be a regular linear array, but I'm now changing
        // it to a hash with key of the message ID.

        // DFSKLARD: Here is where we may want to reorganize the flat list of posts
        // to create a hierarchy of comments/replies.
        // DFSKLARD: WRONG - This returned result is actually IGNORED!
        $regexpIsReply = '/^@.*?#(\d+) /';
        foreach ($document->included as $resource) {
            if ($resource->type === 'posts' && isset($resource->relationships->discussion) && isset($resource->attributes->contentHtml)) {
                if (preg_match($regexpIsReply, $resource->attributes->content, $matches)) {
                    // WHOA!  This is a reply, not a comment.
                    $origCommentID = intval($matches[1]);
                    $posts[$origCommentID]->replies[] = $resource;
                } else {
                    // THIS IS A COMMENT (which may have its own set of replies).
                    $resource->replies = [];
                    $posts[$resource->id] = $resource;
                }
                // DFSKLARD NOTES: $resource->attributes->contentHtml
                // $resource->id
                // If $resource->attributes->content starts with @____#__
                // then it is a reply.
                // How best to do this regex?
            }
        }

        $view->title = $document->data->attributes->title;
        $view->document = $document;

        // DFSKLARD: This next line aggregates the variables that were
        // initialized above, so $view->content will have a ['posts'] entry, etc.
        $view->content = app('view')->make('flarum.forum::discussion', compact('document', 'page', 'getResource', 'posts', 'url'));

        return $view;
    }

    /**
     * Get the result of an API request to show a discussion.
     *
     * @param User $actor
     * @param array $params
     * @return object
     * @throws RouteNotFoundException
     */
    protected function getDocument(User $actor, array $params)
    {
        $response = $this->api->send('Flarum\Api\Controller\ShowDiscussionController', $actor, $params);
        $statusCode = $response->getStatusCode();

        if ($statusCode === 404) {
            throw new RouteNotFoundException;
        }

        return json_decode($response->getBody());
    }
}
