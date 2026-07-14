<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Modules\Core\Entities\User;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;
use Tests\TestCase;

class ChatControllerAllowedIncludesTest extends TestCase
{
    public function test_allowed_includes_with_relationship_alias_does_not_throw(): void
    {
        $request = Request::create('/', 'GET');

        $query = QueryBuilder::for(User::query(), $request)
            ->allowedIncludes([
                'sendable',
                'attachment',
                AllowedInclude::relationship('attachments', 'attachment'),
            ]);

        $this->assertInstanceOf(QueryBuilder::class, $query);
    }

    public function test_spreading_relationship_all_reproduces_the_original_bug(): void
    {
        $this->expectException(\TypeError::class);

        $request = Request::create('/', 'GET');

        QueryBuilder::for(User::query(), $request)
            ->allowedIncludes([
                'sendable',
                'attachment',
                ...AllowedInclude::relationship('attachments', 'attachment')->all(),
            ]);
    }
}
