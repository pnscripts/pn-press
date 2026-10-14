<?php

namespace Tests\Unit;

use App\Press\Hooks;
use PHPUnit\Framework\TestCase;

class HooksTest extends TestCase
{
    public function test_filters_run_in_priority_order_and_keep_insertion_order_for_ties(): void
    {
        $hooks = new Hooks;
        $hooks->addFilter('title', fn ($v) => $v.'b');
        $hooks->addFilter('title', fn ($v) => $v.'a', 5);
        $hooks->addFilter('title', fn ($v) => $v.'c');
        $hooks->addFilter('title', fn ($v) => $v.'z', 99);

        $this->assertSame('-abcz', $hooks->applyFilters('title', '-'));
    }

    public function test_filters_receive_extra_arguments(): void
    {
        $hooks = new Hooks;
        $hooks->addFilter('price', fn ($value, $rate) => $value * $rate);

        $this->assertSame(20, $hooks->applyFilters('price', 10, 2));
        $this->assertSame('untouched', $hooks->applyFilters('missing', 'untouched'));
    }

    public function test_actions_run_and_are_counted(): void
    {
        $hooks = new Hooks;
        $seen = [];
        $hooks->addAction('saved', function ($id) use (&$seen) {
            $seen[] = $id;
        });

        $hooks->doAction('saved', 7);
        $hooks->doAction('saved', 8);

        $this->assertSame([7, 8], $seen);
        $this->assertSame(2, $hooks->didAction('saved'));
        $this->assertTrue($hooks->hasAction('saved'));
    }

    public function test_render_joins_string_output_only(): void
    {
        $hooks = new Hooks;
        $hooks->addAction('head', fn () => '<meta name="a">');
        $hooks->addAction('head', fn () => null);
        $hooks->addAction('head', fn () => '<meta name="b">', 1);

        $this->assertSame("<meta name=\"b\">\n<meta name=\"a\">", (string) $hooks->render('head'));
    }

    public function test_callbacks_can_be_removed(): void
    {
        $hooks = new Hooks;
        $callback = fn ($v) => $v.'!';
        $hooks->addFilter('x', $callback);
        $hooks->addFilter('x', fn ($v) => $v.'?');
        $hooks->removeFilter('x', $callback);

        $this->assertSame('a?', $hooks->applyFilters('x', 'a'));

        $hooks->removeFilter('x');
        $this->assertFalse($hooks->hasFilter('x'));
    }
}
