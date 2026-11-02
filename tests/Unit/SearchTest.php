<?php

namespace Tests\Unit;

use App\Support\Search;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchTest extends TestCase
{
    public function test_it_escapes_like_wildcards(): void
    {
        $this->assertSame('100\\% sure\\_thing\\\\done', Search::escapeLike('100% sure_thing\\done'));
        $this->assertSame('%100\\% sure\\_thing\\\\done%', Search::containsPattern('100% sure_thing\\done'));
    }

    public function test_it_returns_appropriate_like_operator_for_driver(): void
    {
        $this->assertSame(DB::getDriverName() === 'pgsql' ? 'ilike' : 'like', Search::likeOperator());
    }
}
