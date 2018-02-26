<?php

/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Api\Controller;

use Flarum\Core\Repository\UserRepository;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ShowUserController extends AbstractResourceController
{
    /**
     * {@inheritdoc}
     */
    public $serializer = 'Flarum\Api\Serializer\UserSerializer';

    /**
     * {@inheritdoc}
     */
    public $include = ['groups', 'grouprequests'];

    /**
     * @var UserRepository
     */
    protected $users;

    /**
     * @param UserRepository $users
     */
    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    /**
     * {@inheritdoc}
     */
    protected function data(ServerRequestInterface $request, Document $document)
    {
        $id = array_get($request->getQueryParams(), 'id');

        if (! is_numeric($id)) {
            $testid = $this->users->getIdForUsername($id);
            if (! $testid) {
                // Last resort: it might be a UUID.
                // DFSKLARD: long-range todo: we should use a U- protocol just in case
                // we run into a UUID that has no hex A-F chars and thus looks numeric!
                $testid = $this->users->getIdForUID($id);
            }
            $id = $testid;
        }

        $actor = $request->getAttribute('actor');

        if ($actor->id == $id) {
            $this->serializer = 'Flarum\Api\Serializer\CurrentUserSerializer';
        }

        return $this->users->findOrFail($id, $actor);
    }
}
