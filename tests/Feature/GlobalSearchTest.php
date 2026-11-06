<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Tests\Fixtures\Discovered\Nested\NoteResource;
use Xelqun\Panels\Tests\Fixtures\Discovered\PageResource;
use Xelqun\Panels\Tests\Fixtures\Post;
use Xelqun\Panels\Tests\Fixtures\Resources\OtherPostResource;
use Xelqun\Panels\Tests\Fixtures\Resources\PostPolicy;
use Xelqun\Panels\Tests\Fixtures\Resources\PostResource;
use Xelqun\Panels\Tests\Fixtures\User;

function searchPosts(string $search): array
{
    return test()->postJson('/admin/_search', ['q' => $search])->assertOk()->json('groups');
}

/**
 * @return array<int, string>
 */
function foundTitles(string $search): array
{
    return collect(searchPosts($search)[0]['results'] ?? [])->pluck('title')->all();
}

beforeEach(function () {
    Gate::policy(Post::class, PostPolicy::class);
    PostPolicy::$denied = [];
    PostPolicy::$hiddenTitles = [];
});

describe('search', function () {
    beforeEach(function () {
        $this->user = $this->signIn();
    });

    it('finds records per resource, and links to each one', function () {
        $post = Post::create(['title' => 'Launch plan', 'status' => 'live']);

        expect(searchPosts('launch'))->toBe([
            [
                'label' => 'Posts',
                'icon' => 'file-text',
                'results' => [[
                    'title' => 'Launch plan',
                    'url' => PostResource::getUrl('view', ['record' => $post], app(Panels::class)->get('admin')),
                    'details' => 'Status: live',
                ]],
            ],
            [
                'label' => 'Posts',
                'icon' => 'file-text',
                'results' => [[
                    'title' => 'Launch plan',
                    'url' => OtherPostResource::getUrl('view', ['record' => $post], app(Panels::class)->get('admin')),
                    'details' => 'Status: live',
                ]],
            ],
        ]);
    });

    it('looks in every searchable attribute, through relations too', function () {
        Post::create(['title' => 'By status', 'status' => 'archived']);
        Post::create(['title' => 'By author', 'user_id' => $this->user->id]);

        expect(foundTitles('archiv'))->toBe(['By status'])
            ->and(foundTitles('Ada'))->toBe(['By author']);
    });

    it('never reaches past the resource query, whichever attribute matched', function () {
        Post::create(['title' => 'Blue post', 'team' => 'blue', 'status' => 'draft']);
        Post::create(['title' => 'Red post', 'team' => 'red', 'status' => 'draft']);

        expect(foundTitles('draft'))->toBe(['Red post'])
            ->and(foundTitles('Blue'))->toBe([]);
    });

    it('runs no query for a resource the user may not list or that has no policy', function () {
        Post::create(['title' => 'Launch plan']);
        PostPolicy::$denied = ['viewAny'];
        DB::enableQueryLog();

        expect(searchPosts('launch'))->toBe([])
            ->and(collect(DB::getQueryLog())->pluck('query')->implode(' '))->not->toContain('"posts"');
    });

    it('leaves out records the policy refuses, and still fills the list', function () {
        foreach (range(1, 7) as $number) {
            Post::create(['title' => "Post {$number}"]);
        }

        PostPolicy::$hiddenTitles = ['Post 1', 'Post 2'];

        expect(foundTitles('Post'))->toBe(['Post 3', 'Post 4', 'Post 5', 'Post 6', 'Post 7']);
    });

    it('refuses a query that is not text, or is too long', function (mixed $search) {
        $this->postJson('/admin/_search', ['q' => $search])->assertUnprocessable();
    })->with([
        'a list' => [['launch']],
        'too long' => [str_repeat('a', 101)],
    ]);

    it('runs out for someone who keeps searching', function () {
        foreach (range(1, 60) as $attempt) {
            $this->postJson('/admin/_search', ['q' => 'launch'])->assertOk();
        }

        $this->postJson('/admin/_search', ['q' => 'launch'])->assertTooManyRequests();
    });
});

describe('access', function () {
    it('answers a signed out browser plainly, and remembers no page to return to', function () {
        Post::create(['title' => 'Launch plan']);

        $this->postJson('/admin/_search', ['q' => 'launch'])->assertUnauthorized();

        expect(session()->has('url.intended'))->toBeFalse();
    });

    it('still sends a guest visiting a page to sign in', function () {
        $this->get('/admin/posts')->assertRedirect('/admin/login');
    });

    it('stops at once for someone whose access was taken away', function () {
        Post::create(['title' => 'Launch plan']);
        $this->signIn();
        User::$allowed = false;

        $this->postJson('/admin/_search', ['q' => 'launch'])->assertForbidden();
    });
});

describe('the new menu', function () {
    it('offers what this user may create', function () {
        $this->signIn();
        $this->get('/admin/posts')->assertInertia(fn ($page) => $page
            ->where('xelqunPanel.quickCreate', [
                ['label' => 'Post', 'icon' => 'file-text', 'url' => url('/admin/posts/create')],
                ['label' => 'Post', 'icon' => 'file-text', 'url' => url('/admin/other-posts/create')],
            ])
            ->where('xelqunPanel.searchUrl', url('/admin/_search')));
    });

    it('is empty when the policy refuses creating', function () {
        $this->signIn();
        PostPolicy::$denied = ['create'];

        $this->get('/admin/posts')->assertInertia(fn ($page) => $page->where('xelqunPanel.quickCreate', []));
    });

    it('keeps search and the menu away from a guest on the login page', function () {
        $this->get('/admin/login')->assertInertia(fn ($page) => $page
            ->where('xelqunPanel.quickCreate', [])
            ->where('xelqunPanel.searchUrl', null));
    });
});

describe('discoverResources', function () {
    function discovered(): Panel
    {
        return Panel::make()->id('found')->path('found')->discoverResources(
            in: dirname(__DIR__).'/Fixtures/Discovered',
            for: 'Xelqun\\Panels\\Tests\\Fixtures\\Discovered',
        );
    }

    it('finds resources in nested folders, and nothing else', function () {
        expect(discovered()->getResources())->toEqualCanonicalizing([PageResource::class, NoteResource::class]);
    });

    it('adds a listed resource only once', function () {
        expect(discovered()->resources([PageResource::class])->getResources())->toHaveCount(2);
    });

    it('adds nothing from a folder that is not there yet', function () {
        expect(Panel::make()->discoverResources(in: '/nowhere', for: 'App')->getResources())->toBe([]);
    });
});
