<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Resources\ResourceForm;
use Xelqun\Panels\Resources\ResourceTable;
use Xelqun\Panels\Tests\Fixtures\Post;
use Xelqun\Panels\Tests\Fixtures\Resources\PostPolicy;
use Xelqun\Panels\Tests\Fixtures\Resources\PostResource;
use Xelqun\Panels\Tests\Fixtures\User;

beforeEach(function () {
    Gate::policy(Post::class, PostPolicy::class);
    PostPolicy::$denied = [];
    PostResource::$calls = [];
    Storage::fake('local');
    Storage::fake('public');

    $this->signIn();
    $this->post = Post::create(['title' => 'Mine']);
    $this->otherTeamsPost = Post::create(['title' => 'Theirs', 'team' => 'blue']);
});

function pageProps(TestResponse $response): array
{
    return $response->viewData('page')['props']['page'];
}

function saveAsBrowser(string $method, string $url, array $data): TestResponse
{
    return test()->from($url)->{strtolower($method)}($url, ['data' => $data]);
}

describe('the list page', function () {
    it('shows the records the resource reaches, and no others', function () {
        $this->get('/admin/posts')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('xelqun/Panel')
            ->where('page.title', 'Posts')
            ->where('page.content.0.type', 'table')
            ->has('page.content.0.schema.rows', 1)
            ->where('page.content.0.schema.rows.0.cells.title.state', 'Mine'));
    });

    it('links each row to its edit page, or view page, or nothing', function (array $denied, ?string $path) {
        PostPolicy::$denied = $denied;

        $row = pageProps($this->get('/admin/posts'))['content'][0]['schema']['rows'][0];
        $expected = $path === null ? null : url(str_replace('{id}', (string) $this->post->id, $path));

        expect($row['url'] ?? null)->toBe($expected);
    })->with([
        'can edit' => [[], '/admin/posts/{id}/edit'],
        'can only view' => [['update'], '/admin/posts/{id}'],
        'can do neither' => [['update', 'view'], null],
    ]);

    it('applies the resource\'s table settings, with an id of its own', function () {
        $table = pageProps($this->get('/admin/posts'))['content'][0]['schema'];

        expect($table['id'])->toBe('posts-all-table')
            ->and($table['sort'])->toBe('title')
            ->and($table['emptyState']['heading'])->toBe('No posts yet');
    });

    it('applies a filter, from the page and through the table\'s endpoint', function () {
        Post::create(['title' => 'Published', 'status' => 'live']);
        $table = ResourceTable::for(PostResource::class);

        expect(pageProps($this->get('/admin/posts'))['content'][0]['schema']['filters'])->not->toBeNull();

        $this->queryTable($table, ['filters' => ['status' => 'live']])
            ->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.cells.title.state', 'Published');
    });

    it('searches through the resource, not around it', function () {
        $this->queryTable(ResourceTable::for(PostResource::class), ['search' => 'Theirs'])
            ->assertOk()
            ->assertJsonCount(0, 'rows');
    });

    it('offers New only to someone who can create', function () {
        expect(pageProps($this->get('/admin/posts'))['headerActions']['components'][1]['hidden'])->toBeFalse();

        PostPolicy::$denied = ['create'];

        expect(pageProps($this->get('/admin/posts'))['headerActions']['components'][1]['hidden'])->toBeTrue();
    });

    it('appears in the sidebar for someone who can list', function () {
        $this->get('/admin/posts')->assertInertia(fn (Assert $page) => $page
            ->where('xelqunPanel.navigation.1.label', 'Content')
            ->where('xelqunPanel.navigation.1.items.0.label', 'Posts')
            ->where('xelqunPanel.navigation.1.items.0.active', true));

        PostPolicy::$denied = ['viewAny'];

        expect(collect(app(Panels::class)->get('admin')->getNavigation())->pluck('label'))->not->toContain('Content');
    });
});

describe('creating', function () {
    it('shows an empty form', function () {
        $this->get('/admin/posts/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('page.title', 'Create post')
            ->where('page.content.0.type', 'form')
            ->where('page.content.0.action', route('xelqun-panels.admin.resources.posts.store'))
            ->where('page.breadcrumbs.0.url', route('xelqun-panels.admin.resources.posts.index')));
    });

    it('saves, tells the user, and goes to the new record', function () {
        $response = saveAsBrowser('POST', '/admin/posts', ['title' => '  Fresh  ', 'tags' => []]);
        $post = Post::query()->firstWhere('title', 'Fresh');

        $response->assertRedirect("/admin/posts/{$post->id}/edit")
            ->assertInertiaFlash('xelqunNotifications.0.title', 'Post created.');

        expect(PostResource::$calls)->toBe(['beforeCreate', 'afterCreate']);
    });

    it('goes back to the list when the user can\'t reach the new record', function () {
        PostPolicy::$denied = ['update', 'view'];

        saveAsBrowser('POST', '/admin/posts', ['title' => 'Fresh', 'tags' => []])->assertRedirect('/admin/posts');
    });

    it('reports a validation error on the form, and saves nothing', function () {
        saveAsBrowser('POST', '/admin/posts', ['title' => '', 'tags' => []])->assertSessionHasErrors('data.title');

        expect(Post::query()->count())->toBe(2);
    });
});

describe('editing', function () {
    it('fills the form from the record', function () {
        $this->get("/admin/posts/{$this->post->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('page.title', 'Edit Mine')
            ->where('page.content.0.schema.state.data.title', 'Mine'));
    });

    it('saves, and stays on the page', function () {
        saveAsBrowser('PUT', "/admin/posts/{$this->post->id}", ['title' => 'Changed', 'tags' => []])
            ->assertRedirect("/admin/posts/{$this->post->id}/edit")
            ->assertInertiaFlash('xelqunNotifications.0.title', 'Post saved.');

        expect($this->post->fresh()->title)->toBe('Changed')
            ->and(PostResource::$calls)->toBe(['beforeSave', 'afterSave']);
    });

    it('keeps the same form revision after a failed save, so typed rows survive', function () {
        $url = "/admin/posts/{$this->post->id}/edit";
        $before = pageProps($this->get($url))['content'][0]['revision'];

        saveAsBrowser('PUT', "/admin/posts/{$this->post->id}", ['title' => '', 'tags' => [['name' => 'typed']]])
            ->assertSessionHasErrors('data.title');

        expect(pageProps($this->get($url))['content'][0]['revision'])->toBe($before);
    });

    it('gives the form a new revision after the record changes', function () {
        $url = "/admin/posts/{$this->post->id}/edit";
        $before = pageProps($this->get($url))['content'][0]['revision'];

        $this->post->update(['title' => 'Changed elsewhere']);

        expect(pageProps($this->get($url))['content'][0]['revision'])->not->toBe($before);
    });

    it('deletes from the header, back to the list', function () {
        $actions = pageProps($this->get("/admin/posts/{$this->post->id}/edit"))['headerActions'];

        $this->postJson(route('xelqun.forms.action'), ['token' => $actions['token'], 'action' => 'data#delete', 'phase' => 'run', 'state' => ['data' => []], 'deps' => (object) []])
            ->assertOk()
            ->assertJsonPath('redirect', url('/admin/posts'));

        expect(Post::query()->find($this->post->id))->toBeNull();
    });
});

describe('viewing', function () {
    it('shows a locked form with no way to save it', function () {
        $this->get("/admin/posts/{$this->post->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('page.title', 'View Mine')
            ->where('page.content.0.schema.disabled', true));
    });
});

describe('the table\'s actions', function () {
    it('deletes a row', function () {
        $this->runTableAction(ResourceTable::for(PostResource::class), 'delete', $this->post->id)->assertOk();

        expect(Post::query()->find($this->post->id))->toBeNull();
    });

    it('refuses a row outside the resource', function () {
        $this->runTableAction(ResourceTable::for(PostResource::class), 'delete', $this->otherTeamsPost->id)->assertNotFound();

        expect(Post::query()->find($this->otherTeamsPost->id))->not->toBeNull();
    });

    it('asks the policy, and shows no button when it refuses', function () {
        PostPolicy::$denied = ['delete'];
        $table = ResourceTable::for(PostResource::class);

        $this->runTableAction($table, 'delete', $this->post->id)->assertForbidden();

        expect(array_column(pageProps($this->get('/admin/posts'))['content'][0]['schema']['rows'][0]['actions'], 'name'))->toBe(['edit']);
    });

    it('bulk deletes only what it can reach', function () {
        $this->runBulkTableAction(ResourceTable::for(PostResource::class), 'delete', [$this->post->id, $this->otherTeamsPost->id])->assertOk();

        expect(Post::query()->pluck('title')->all())->toBe(['Theirs']);
    });
});

describe('a record outside the resource', function () {
    it('is a 404 on every page', function (string $method, string $path) {
        $this->call($method, str_replace('{id}', (string) $this->otherTeamsPost->id, $path), ['data' => ['title' => 'Stolen']])->assertNotFound();

        expect($this->otherTeamsPost->fresh()->title)->toBe('Theirs');
    })->with([
        'view' => ['GET', '/admin/posts/{id}'],
        'edit' => ['GET', '/admin/posts/{id}/edit'],
        'update' => ['PUT', '/admin/posts/{id}'],
    ]);

    it('is a 404 through a form token too', function () {
        $form = ResourceForm::for(PostResource::class, ResourceForm::EDIT, $this->otherTeamsPost);

        $this->updateForm($form, [])->assertNotFound();
    });

    it('is a 404 before the policy can say it exists', function () {
        PostPolicy::$denied = ['update', 'view'];

        $this->get("/admin/posts/{$this->otherTeamsPost->id}/edit")->assertNotFound();
        $this->get("/admin/posts/{$this->post->id}/edit")->assertForbidden();
    });
});

describe('the policy', function () {
    it('guards each page', function (string $ability, string $method, string $path) {
        PostPolicy::$denied = [$ability];

        $this->call($method, str_replace('{id}', (string) $this->post->id, $path), ['data' => ['title' => 'Blocked', 'tags' => []]])->assertForbidden();

        expect(Post::query()->where('title', 'Blocked')->exists())->toBeFalse();
    })->with([
        'list' => ['viewAny', 'GET', '/admin/posts'],
        'create page' => ['create', 'GET', '/admin/posts/create'],
        'store' => ['create', 'POST', '/admin/posts'],
        'view' => ['view', 'GET', '/admin/posts/{id}'],
        'edit page' => ['update', 'GET', '/admin/posts/{id}/edit'],
        'update' => ['update', 'PUT', '/admin/posts/{id}'],
    ]);

    it('guards a form\'s live requests too', function () {
        $form = ResourceForm::for(PostResource::class, ResourceForm::EDIT, $this->post);
        $token = $form->makeToken();
        PostPolicy::$denied = ['update'];

        $this->postJson(route('xelqun.forms.update'), ['token' => $token, 'seq' => 1, 'state' => ['data' => []], 'changed' => [], 'deps' => (object) []])
            ->assertForbidden();
    });
});

describe('a resource with no policy', function () {
    it('is refused on every page, and missing from the sidebar', function () {
        $this->get('/admin/unguardeds')->assertForbidden();
        $this->get('/admin/unguardeds/create')->assertForbidden();
        $this->post('/admin/unguardeds', ['data' => ['title' => 'x']])->assertForbidden();
        $this->get("/admin/unguardeds/{$this->post->id}/edit")->assertForbidden();

        expect(collect(app(Panels::class)->get('admin')->getNavigation())->pluck('items.*.label')->flatten())->not->toContain('Unguardeds');
    });

    it('says what to do, only in debug', function (bool $debug, string $message) {
        config(['app.debug' => $debug]);
        $this->withoutExceptionHandling();

        expect(fn () => $this->get('/admin/unguardeds'))->toThrow(function (HttpException $exception) use ($message) {
            expect($exception->getMessage())->toContain($message);
        });
    })->with([
        'debug' => [true, 'needs a policy'],
        'production' => [false, ''],
    ]);
});

describe('uploads', function () {
    /** Uploads belong to a session, and a test client gets a new one for each request. */
    function uploadFor(string $resource): string
    {
        test()->withCredentials()->withCookie(config('session.cookie'), str_repeat('a', 40));

        return test()->uploadToForm(ResourceForm::for($resource, ResourceForm::CREATE), 'data.cover', UploadedFile::fake()->image('a.png'))
            ->assertOk()
            ->json('token');
    }

    it('save on their own resource', function () {
        saveAsBrowser('POST', '/admin/posts', ['title' => 'Covered', 'tags' => [], 'cover' => uploadFor(PostResource::class)])
            ->assertSessionHasNoErrors();

        expect(Post::query()->firstWhere('title', 'Covered')->cover)->not->toBeNull();
    });

    it('can\'t cross to another resource with the same field', function () {
        saveAsBrowser('POST', '/admin/other-posts', ['title' => 'Crossed', 'tags' => [], 'cover' => uploadFor(PostResource::class)])
            ->assertSessionHasErrors('data.cover');

        expect(Post::query()->where('title', 'Crossed')->exists())->toBeFalse();
    });
});

it('can\'t be pointed at a class that isn\'t a resource', function () {
    expect(fn () => ResourceForm::make(null, ['resource' => stdClass::class]))->toThrow(LogicException::class);
});

describe('links from outside a panel', function () {
    it('finds the only panel that registers the resource, as in a job or a mail', function () {
        expect(app(Panels::class)->current())->toBeNull()
            ->and(PostResource::getUrl())->toBe(url('/admin/posts'));
    });

    it('is inside the panel once a test signs in to it', function () {
        $this->actingInPanel(User::query()->firstOrFail(), 'admin');

        expect(app(Panels::class)->current()->getId())->toBe('admin');
    });
});
