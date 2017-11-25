<?php

/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Core\Validator;

class DiscussionValidator extends AbstractValidator
{

    // DFSKLARD: we do not want to require any title on a flarum discussion (a.k.a. "thread").
    protected $rules = [
        'title' => [
            'min:0',
            'max:80'
        ]
    ];
}
