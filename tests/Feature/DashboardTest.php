<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Tests\Fixtures\Post;
use Xelqun\Panels\Tests\Fixtures\Resources\PostPolicy;
use Xelqun\Panels\Tests\Fixtures\Resources\PostResource;
use Xelqun\Panels\Tests\Fixtures\Widgets\BrokenWidget;
use Xelqun\Panels\Tests\Fixtures\Widgets\PendingPosts;
use Xelqun\Panels\Tests\Fixtures\Widgets\RecordStats;
use Xelqun\Panels\Tests\Fixtures\Widgets\SalesChart;

class InvisibleList extends PendingPosts
{
    public static function canView(): bool
    {
        return false;
    }
}

beforeEach(function () {
    PendingPosts::$canApprove = true;
    $this->user = $this->signIn();
    $this->draft = Post::create(['title' => 'Draft one']);
});

/** The deferred request the browser makes once the page has painted. */
function loadWidgets(): TestResponse
{
    $version = test()->get('/admin/dashboard')->viewData('page')['version'];

    return test()->get('/admin/dashboard', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Component' => 'xelqun/Panel',
        'X-Inertia-Partial-Data' => 'widgets',
    ]);
}

function widget(string $id): array
{
    return collect(loadWidgets()->json('props.widgets'))->firstWhere('id', $id);
}

it('paints first, and sends the widgets when asked', function () {
    $this->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('page.hasWidgets', true)
        ->missing('widgets'));

    expect(loadWidgets()->json('props.widgets'))->toHaveCount(3);
});

it('tells other pages not to wait for widgets', function () {
    $this->get('/admin/reports')->assertInertia(fn (Assert $page) => $page->where('page.hasWidgets', false));
});

it('puts widgets in order, by their sort', function () {
    expect(array_column(loadWidgets()->json('props.widgets'), 'id'))->toBe(['post-stats', 'pending-posts', 'post-tiles']);
});

it('never works out a widget the user can\'t see', function () {
    expect(array_column(loadWidgets()->json('props.widgets'), 'id'))->not->toContain('hidden-widget');
});

it('leaves out a widget that fails, and shows the rest', function () {
    BrokenWidget::$breaks = true;
    $this->withoutExceptionHandling();

    expect(array_column(loadWidgets()->assertOk()->json('props.widgets'), 'id'))->toBe(['post-stats', 'pending-posts', 'post-tiles']);

    BrokenWidget::$breaks = false;
});

it('leaves out a list its own authorize() refuses, rather than failing the page', function () {
    expect(array_column(loadWidgets()->assertOk()->json('props.widgets'), 'id'))->not->toContain('denied-list');
});

it('sends how wide each one is', function () {
    expect(widget('pending-posts')['columnSpan'])->toBe(8)
        ->and(widget('post-stats')['columnSpan'])->toBe(12);
});

it('sends where See all goes', function () {
    expect(widget('pending-posts')['data']['seeAllUrl'])->toBe('/posts')
        ->and(widget('post-stats')['data'])->not->toHaveKey('seeAllUrl');
});

it('sends a stat\'s parts', function () {
    expect(widget('post-stats')['data']['stats'][0])->toBe([
        'label' => 'Posts',
        'value' => '1',
        'description' => 'All of them',
        'icon' => 'file-text',
        'chart' => [1, 3, 2],
    ]);
});

it('sends tiles, and drops an unsafe link', function () {
    expect(widget('post-tiles')['data']['tiles'])
        ->sequence(
            fn ($tile) => $tile->url->toBe('/teams/red')->status->toBe('Working')->color->toBe('primary'),
            fn ($tile) => $tile->url->toBeNull(),
        );
});

describe('a list widget', function () {
    it('sends each row\'s parts as cells', function () {
        $cells = widget('pending-posts')['data']['table']['rows'][0]['cells'];

        expect($cells['title']['state'])->toBe('Draft one')
            ->and($cells['meta']['state'])->toBe('Team red')
            ->and($cells['icon']['state'])->toBe('file-text')
            ->and($cells['iconColor']['state'])->toBe('primary')
            ->and($cells['progress']['state'])->toBe(0.5)
            ->and($cells['progressNote']['state'])->toBe('Halfway');
    });

    it('stops at its limit, but counts them all', function () {
        Post::create(['title' => 'Draft two']);
        Post::create(['title' => 'Draft three']);

        $table = widget('pending-posts')['data']['table'];

        expect($table['rows'])->toHaveCount(2)
            ->and($table['pagination']['total'])->toBe(3);
    });

    it('runs a row action, and the row leaves the list', function () {
        $table = PendingPosts::make();

        $this->runTableAction($table, 'approve', $this->draft->id)->assertOk();

        expect($this->draft->fresh()->status)->toBe('live');
        $this->queryTable($table)->assertJsonCount(0, 'rows');
    });

    it('refuses its rows and actions to someone who can\'t see it', function () {
        $table = InvisibleList::make();

        $this->queryTable($table)->assertForbidden();
        $this->runTableAction($table, 'approve', $this->draft->id)->assertForbidden();

        expect($this->draft->fresh()->status)->toBe('draft');
    });

    it('refuses a record its query leaves out', function () {
        $other = Post::create(['title' => 'Other team', 'team' => 'blue']);

        $this->runTableAction(PendingPosts::make(), 'approve', $other->id)->assertNotFound();

        expect($other->fresh()->status)->toBe('draft');
    });

    it('hides and refuses an action the user can\'t take', function () {
        PendingPosts::$canApprove = false;

        expect(widget('pending-posts')['data']['table']['rows'][0]['actions'])->toBe([]);

        $this->runTableAction(PendingPosts::make(), 'approve', $this->draft->id)->assertForbidden();

        expect($this->draft->fresh()->status)->toBe('draft');
    });
});

it('needs the panel, like any other page', function () {
    auth('admin')->logout();

    $this->get('/admin/dashboard')->assertRedirect('/admin/login');
});

describe('charts', function () {
    afterEach(function () {
        SalesChart::$type = 'line';
    });

    it('sends the data the browser draws from', function () {
        expect(SalesChart::make()->toWidget())->toMatchArray(['type' => 'chart'])
            ->and(SalesChart::make()->toWidget()['data']['chart'])->toBe([
                'type' => 'line',
                'labels' => ['Jan', 'Feb'],
                'datasets' => [['label' => 'Orders', 'data' => [1.0, 2.0]]],
            ]);
    });

    it('refuses a type it can\'t draw', function () {
        SalesChart::$type = 'radar';

        SalesChart::make()->toWidget();
    })->throws(PanelDefinitionException::class);

    it('folds extra doughnut slices into one, rather than repeating a color', function () {
        SalesChart::$type = 'doughnut';
        $before = SalesChart::$data;
        SalesChart::$data = ['labels' => range(1, 10), 'datasets' => [['data' => range(1, 10)]]];

        $chart = SalesChart::make()->toWidget()['data']['chart'];
        SalesChart::$data = $before;

        expect($chart['labels'])->toHaveCount(8)
            ->and($chart['labels'][7])->toBe('Other')
            ->and($chart['datasets'][0]['data'][7])->toBe(27.0)
            ->and(array_sum($chart['datasets'][0]['data']))->toBe(55.0);
    });

    it('refuses more series than it has colors', function () {
        $before = SalesChart::$data;
        SalesChart::$data = ['labels' => ['a'], 'datasets' => array_fill(0, 9, ['data' => [1]])];

        try {
            SalesChart::make()->toWidget();
            $this->fail('It drew nine series.');
        } catch (PanelDefinitionException) {
            expect(true)->toBeTrue();
        } finally {
            SalesChart::$data = $before;
        }
    });
});

it('shows a resource\'s widgets above its list', function () {
    Gate::policy(Post::class, PostPolicy::class);
    PostResource::$headerWidgets = [SalesChart::class];

    $this->get('/admin/posts')->assertInertia(fn (Assert $page) => $page->where('page.hasWidgets', true));

    PostResource::$headerWidgets = [];

    $this->get('/admin/posts')->assertInertia(fn (Assert $page) => $page->where('page.hasWidgets', false));
});

it('gives a widget on a record\'s page that record', function () {
    Gate::policy(Post::class, PostPolicy::class);
    PostPolicy::$denied = [];
    PostResource::$headerWidgets = [RecordStats::class];
    $post = Post::create(['title' => 'Mine']);

    $this->get("/admin/posts/{$post->id}", ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'xelqun/Panel', 'X-Inertia-Partial-Data' => 'widgets', 'X-Inertia-Version' => ''])
        ->assertJsonPath('props.widgets.0.data.stats.0.value', 'Mine');

    PostResource::$headerWidgets = [];
});

describe('filters, footer widgets and polling', function () {
    function loadFooter(string $query = ''): TestResponse
    {
        $version = test()->get('/admin/dashboard')->viewData('page')['version'];

        return test()->get('/admin/dashboard'.$query, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'xelqun/Panel',
            'X-Inertia-Partial-Data' => 'footerWidgets',
        ]);
    }

    it('sends the filters form with the values in the URL', function () {
        $this->get('/admin/dashboard?filters[team]=blue')->assertInertia(fn (Assert $page) => $page
            ->where('page.hasFooterWidgets', true)
            ->where('page.filters.state.data.team', 'blue')
            ->missing('footerWidgets'));
    });

    it('gives widgets the filter values, and the default for one that fails its rules', function () {
        expect(loadFooter('?filters[team]=blue')->json('props.footerWidgets.0'))->toMatchArray(['id' => 'filter-echo', 'pollSeconds' => 30])
            ->and(loadFooter('?filters[team]=blue')->json('props.footerWidgets.0.data.filters'))->toBe(['team' => 'blue'])
            ->and(loadFooter('?filters[team]=green')->json('props.footerWidgets.0.data.filters'))->toBe(['team' => 'red'])
            ->and(loadFooter()->json('props.footerWidgets.0.data.filters'))->toBe(['team' => 'red']);
    });
});
