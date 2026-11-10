<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Xelqun\Panels\Infolists\CodeEntry;
use Xelqun\Panels\Infolists\KeyValueEntry;
use Xelqun\Panels\Infolists\RepeatableEntry;
use Xelqun\Panels\Infolists\TextEntry;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Resources\RelationTable;
use Xelqun\Panels\Resources\ResourceTable;
use Xelqun\Panels\Tests\Fixtures\Comment;
use Xelqun\Panels\Tests\Fixtures\Label;
use Xelqun\Panels\Tests\Fixtures\Note;
use Xelqun\Panels\Tests\Fixtures\Post;
use Xelqun\Panels\Tests\Fixtures\Resources\CommentsRelationManager;
use Xelqun\Panels\Tests\Fixtures\Resources\EditableCommentsManager;
use Xelqun\Panels\Tests\Fixtures\Resources\LabelsRelationManager;
use Xelqun\Panels\Tests\Fixtures\Resources\NestedCommentsManager;
use Xelqun\Panels\Tests\Fixtures\Resources\NoteResource;
use Xelqun\Panels\Tests\Fixtures\Resources\PostCommentResource;
use Xelqun\Panels\Tests\Fixtures\Resources\PostPolicy;
use Xelqun\Panels\Tests\Fixtures\Resources\PostResource;
use Xelqun\Panels\Tests\Fixtures\Resources\RelatedPolicy;
use Xelqun\Panels\Tests\Fixtures\Resources\ScopedLabelsManager;

beforeEach(function () {
    Gate::policy(Post::class, PostPolicy::class);
    Gate::policy(Comment::class, RelatedPolicy::class);
    Gate::policy(Label::class, RelatedPolicy::class);
    Gate::policy(Note::class, RelatedPolicy::class);
    PostPolicy::$denied = [];
    RelatedPolicy::$denied = [];
    NoteResource::$calls = [];
    PostResource::$usesInfolist = false;

    $this->signIn();
    app(Panels::class)->enter(app(Panels::class)->get('admin'));
    $this->post = Post::create(['title' => 'Mine']);
    $this->comment = $this->post->comments()->create(['body' => 'First']);
});

function commentsTable(Post $post, bool $isReadOnly = false): RelationTable
{
    return RelationTable::for(PostResource::class, CommentsRelationManager::class, $post, $isReadOnly);
}

/**
 * @return array<int, string>
 */
function rowTitles(mixed $response, string $column): array
{
    return collect($response->json('rows'))->pluck("cells.{$column}.state")->all();
}

describe('relation managers', function () {
    it('shows a heading and only this parent\'s rows, on edit and view', function (string $path) {
        Post::create(['title' => 'Other'])->comments()->create(['body' => 'Not mine']);

        $this->get("/admin/posts/{$this->post->id}{$path}")->assertInertia(fn (Assert $page) => $page
            ->where('page.content.1.heading', 'Comments')
            ->has('page.content.1.schema.rows', 1)
            ->where('page.content.1.schema.rows.0.cells.body.state', 'First'));
    })->with(['edit' => '/edit', 'view' => '']);

    it('creates a record on the parent, in a modal', function () {
        $this->runTableAction(commentsTable($this->post), 'create', null, ['body' => 'New'])->assertOk();

        expect($this->post->comments()->pluck('body')->all())->toBe(['First', 'New']);
    });

    it('checks a modal\'s fields', function () {
        $this->runTableAction(commentsTable($this->post), 'create', null, ['body' => str_repeat('a', 21)])->assertUnprocessable();

        expect($this->post->comments()->count())->toBe(1);
    });

    it('attaches a new record to a many-to-many', function () {
        $table = RelationTable::for(PostResource::class, LabelsRelationManager::class, $this->post, false);

        $this->runTableAction($table, 'create', null, ['name' => 'Urgent'])->assertOk();

        expect($this->post->labels()->pluck('name')->all())->toBe(['Urgent']);
        $this->queryTable($table)->assertJsonPath('rows.0.cells.name.state', 'Urgent');
    });

    it('edits and deletes a row', function () {
        $this->runTableAction(commentsTable($this->post), 'edit', $this->comment->id, ['body' => 'Changed'])->assertOk();
        expect($this->comment->fresh()->body)->toBe('Changed');

        $this->runTableAction(commentsTable($this->post), 'delete', $this->comment->id)->assertOk();
        expect(Comment::count())->toBe(0);
    });

    it('can\'t reach a row of another parent', function () {
        $other = Post::create(['title' => 'Other'])->comments()->create(['body' => 'Not mine']);

        $this->runTableAction(commentsTable($this->post), 'delete', $other->id)->assertNotFound();

        expect($other->fresh())->not->toBeNull();
    });

    it('is read-only on the view page', function () {
        $table = commentsTable($this->post, isReadOnly: true);

        $this->runTableAction($table, 'delete', $this->comment->id)->assertNotFound();

        expect($table->recordActions())->toBe([])->and($table->headerActions())->toBe([]);
    });

    it('needs update on the parent to change anything', function () {
        PostPolicy::$denied = ['update'];

        $this->runTableAction(commentsTable($this->post), 'delete', $this->comment->id)->assertNotFound();

        expect($this->comment->fresh())->not->toBeNull();
    });

    it('follows the related model\'s policy per action', function (string $ability, string $action, ?string $record) {
        RelatedPolicy::$denied = [$ability];

        $this->runTableAction(commentsTable($this->post), $action, $record === null ? null : $this->comment->id, ['body' => 'x'])->assertForbidden();
    })->with([
        'create' => ['create', 'create', null],
        'update' => ['update', 'edit', 'row'],
        'delete' => ['delete', 'delete', 'row'],
    ]);

    it('won\'t save a cell or move a row on a read-only table', function () {
        $table = RelationTable::for(PostResource::class, EditableCommentsManager::class, $this->post, true);

        expect($table->findEditableColumn('body'))->toBeNull()
            ->and($table->canReorder())->toBeFalse();

        $editable = RelationTable::for(PostResource::class, EditableCommentsManager::class, $this->post, false);

        expect($editable->findEditableColumn('body'))->not->toBeNull();
    });

    it('won\'t save a cell without update on the parent', function () {
        $table = RelationTable::for(PostResource::class, EditableCommentsManager::class, $this->post, false);
        PostPolicy::$denied = ['update'];

        expect($table->findEditableColumn('body'))->toBeNull();
    });

    it('leaves the table off the page without the related model\'s viewAny', function () {
        RelatedPolicy::$denied = ['viewAny'];

        $this->get("/admin/posts/{$this->post->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page->has('page.content', 1));
    });

    it('attaches an existing record, and offers only what isn\'t attached', function () {
        $table = RelationTable::for(PostResource::class, LabelsRelationManager::class, $this->post, false);
        $attached = Label::create(['name' => 'Attached']);
        $free = Label::create(['name' => 'Free']);
        $this->post->labels()->attach($attached);

        $this->runTableAction($table, 'attach', null, ['record' => $free->id])->assertOk();

        expect($this->post->labels()->pluck('name')->all())->toBe(['Attached', 'Free']);
    });

    it('can\'t link past the related resource\'s query', function () {
        $table = RelationTable::for(PostResource::class, ScopedLabelsManager::class, $this->post, false);
        $secret = Label::create(['name' => 'Secret']);
        $open = Label::create(['name' => 'Open']);

        $this->runTableAction($table, 'attach', null, ['record' => $secret->id])->assertStatus(422);
        $this->runTableAction($table, 'attach', null, ['record' => $open->id])->assertOk();

        expect($this->post->labels()->pluck('name')->all())->toBe(['Open']);
    });

    it('can\'t attach what the list didn\'t offer', function () {
        $table = RelationTable::for(PostResource::class, LabelsRelationManager::class, $this->post, false);
        $attached = Label::create(['name' => 'Attached']);
        $this->post->labels()->attach($attached);

        $this->runTableAction($table, 'attach', null, ['record' => $attached->id])->assertStatus(422);

        expect($this->post->labels()->count())->toBe(1);
    });

    it('detaches, and keeps the record', function () {
        $table = RelationTable::for(PostResource::class, LabelsRelationManager::class, $this->post, false);
        $label = Label::create(['name' => 'Mine']);
        $this->post->labels()->attach($label);

        $this->runTableAction($table, 'detach', $label->id)->assertOk();

        expect($this->post->labels()->count())->toBe(0)->and($label->fresh())->not->toBeNull();
    });

    it('associates a record with no parent, and can\'t take one from another parent', function () {
        $free = Comment::create(['body' => 'Free']);
        $taken = Post::create(['title' => 'Other'])->comments()->create(['body' => 'Taken']);

        $this->runTableAction(commentsTable($this->post), 'associate', null, ['record' => $taken->id])->assertStatus(422);
        $this->runTableAction(commentsTable($this->post), 'associate', null, ['record' => $free->id])->assertOk();

        expect($free->fresh()->post_id)->toBe($this->post->id)
            ->and($taken->fresh()->post_id)->not->toBe($this->post->id);
    });

    it('dissociates, and keeps the record', function () {
        $this->runTableAction(commentsTable($this->post), 'dissociate', $this->comment->id)->assertOk();

        expect($this->comment->fresh()->post_id)->toBeNull();
    });

    it('needs update on the parent to link anything', function () {
        PostPolicy::$denied = ['update'];
        $free = Comment::create(['body' => 'Free']);

        $this->runTableAction(commentsTable($this->post), 'associate', null, ['record' => $free->id])->assertNotFound();

        expect($free->fresh()->post_id)->toBeNull();
    });

    it('finds the parent once, however many rows there are', function () {
        foreach (range(1, 9) as $number) {
            $this->post->comments()->create(['body' => "Comment {$number}"]);
        }

        DB::enableQueryLog();
        $this->queryTable(commentsTable($this->post))->assertOk()->assertJsonCount(10, 'rows');

        $parentLookups = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query): bool => str_contains($query, 'from "posts"'));

        expect($parentLookups)->toHaveCount(1);
    });

    it('takes the rows with it when the parent leaves the resource', function () {
        $table = commentsTable($this->post);

        $this->post->update(['team' => 'blue']);

        $this->queryTable($table)->assertNotFound();
    });
});

describe('list tabs', function () {
    beforeEach(function () {
        Post::create(['title' => 'Published', 'status' => 'live']);
        Post::create(['title' => 'Scheduled', 'status' => 'scheduled']);
        Post::create(['title' => 'Other team', 'status' => 'live', 'team' => 'blue']);
    });

    it('lists the tabs, starting on the first', function () {
        $this->get('/admin/posts')->assertInertia(fn (Assert $page) => $page
            ->where('page.tabs.0.key', 'all')
            ->where('page.tabs.0.active', true)
            ->where('page.tabs.0.label', 'All')
            ->where('page.tabs.1.label', 'Published')
            ->where('page.tabs.1.badge', '2')
            ->where('page.tabs.1.url', url('/admin/posts?tab=live')));
    });

    it('shows only the tab\'s rows, and stays inside the resource\'s scope', function () {
        $this->get('/admin/posts?tab=live')->assertInertia(fn (Assert $page) => $page
            ->where('page.tabs.1.active', true)
            ->has('page.content.0.schema.rows', 2));
    });

    it('falls back to the first tab for a tab that does not exist', function () {
        $this->get('/admin/posts?tab=nope')->assertOk()->assertInertia(fn (Assert $page) => $page->where('page.tabs.0.active', true));
        $this->get('/admin/posts?tab[]=live')->assertOk();
    });

    it('holds a table to its tab on every later request', function () {
        $table = ResourceTable::for(PostResource::class, 'live');

        expect(rowTitles($this->queryTable($table), 'title'))->toBe(['Published', 'Scheduled'])
            ->and($table->getId())->toBe('posts-live-table');

        $this->runTableAction($table, 'delete', $this->post->id)->assertNotFound();
    });
});

describe('simple resources', function () {
    it('has one page, and no routes for the others', function () {
        $this->get('/admin/notes')->assertOk()->assertInertia(fn (Assert $page) => $page->where('page.headerActions.components.0.props.hasForm', true));
        $this->get('/admin/notes/create')->assertNotFound();
        $this->post('/admin/notes')->assertStatus(405);
    });

    it('stays out of the new menu, and search leads to the list', function () {
        Note::create(['name' => 'Groceries']);

        $this->get('/admin/notes')->assertInertia(fn (Assert $page) => $page->where('xelqunPanel.quickCreate', fn ($items) => collect($items)->pluck('url')->doesntContain(url('/admin/notes/create'))));

        $this->postJson('/admin/_search', ['q' => 'Groceries'])->assertJsonPath('groups.0.results.0.url', url('/admin/notes'));
    });

    it('edits a row in a modal, with the resource\'s hooks', function () {
        $note = Note::create(['name' => 'Old']);

        $this->runTableAction(ResourceTable::for(NoteResource::class), 'edit', $note->id, ['name' => 'New'])->assertOk();

        expect($note->fresh()->name)->toBe('New')->and(NoteResource::$calls)->toBe(['afterSave']);
    });

    it('sends rows nowhere, since there is no page to go to', function () {
        Note::create(['name' => 'Old']);

        $this->queryTable(ResourceTable::for(NoteResource::class))->assertJsonMissingPath('rows.0.url');
    });

    it('keeps the edit modal inside the resource\'s query', function () {
        $hidden = Note::create(['name' => 'Hidden']);

        $this->runTableAction(ResourceTable::for(NoteResource::class), 'edit', $hidden->id, ['name' => 'Mine now'])->assertNotFound();

        expect($hidden->fresh()->name)->toBe('Hidden');
    });
});

describe('infolists', function () {
    it('shows entries in place of the disabled form, formatted like a column', function () {
        PostResource::$usesInfolist = true;
        $this->post->update(['status' => 'live', 'created_at' => '2026-01-02 03:04:05']);

        $this->get("/admin/posts/{$this->post->id}")->assertInertia(fn (Assert $page) => $page
            ->where('page.content.0.schema.components.0.children.0.type', 'xelqun/entry')
            ->where('page.content.0.schema.components.0.children.0.props.cell.state', 'Mine')
            ->where('page.content.0.schema.components.0.children.0.props.cell.color', 'success')
            ->where('page.content.0.schema.components.0.children.1.props.column.label', 'Written by')
            ->where('page.content.0.schema.components.0.children.2.props.cell.state', 'Jan 2, 2026'));
    });

    it('leaves the edit page on the form', function () {
        PostResource::$usesInfolist = true;

        $this->get("/admin/posts/{$this->post->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('page.content.0.schema.components.0.type', 'text-input'));
    });

    it('repeats entries for each related record, and for each row of an array', function () {
        $entry = RepeatableEntry::make('comments')->schema([TextEntry::make('body')]);
        $tags = RepeatableEntry::make('tags')->schema([TextEntry::make('name')]);
        $this->post->update(['tags' => [['name' => 'Red'], ['name' => 'Blue']]]);

        expect($entry->toProps($this->post->fresh())['rows'][0][0]['cell']['state'])->toBe('First')
            ->and(collect($tags->toProps($this->post->fresh())['rows'])->pluck('0.cell.state')->all())->toBe(['Red', 'Blue']);
    });

    it('takes a text column\'s options, and its own hint, label, affixes and HTML', function () {
        $record = new Post(['title' => 'A rather long title', 'body' => '<p>Hi</p><script>x</script>', 'status' => '**bold**']);

        expect(TextEntry::make('title')->limit(8)->description('About it')->copyable()->hint('Public')->inlineLabel()->prefix('#')->suffix('!')->toProps($record))
            ->toMatchArray(['hint' => 'Public', 'inlineLabel' => true, 'prefix' => '#', 'suffix' => '!'])
            ->and(TextEntry::make('title')->limit(8)->toProps($record)['cell'])->toMatchArray(['state' => 'A rather...', 'tooltip' => 'A rather long title'])
            ->and(TextEntry::make('title')->description('About it')->toProps($record)['cell']['description'])->toBe('About it')
            ->and(TextEntry::make('secret')->placeholder('None yet')->toProps($record)['column']['props']->placeholder)->toBe('None yet')
            ->and(TextEntry::make('body')->html()->toProps($record)['html'])->toBe('<p>Hi</p>')
            ->and(TextEntry::make('status')->markdown()->toProps($record)['html'])->toContain('<strong>bold</strong>')
            ->and(TextEntry::make('title')->hint(fn (Post $record): string => "{$record->status} hint")->inlineLabel(fn (): bool => true)->toProps($record))
            ->toMatchArray(['hint' => '**bold** hint', 'inlineLabel' => true]);
    });

    it('lists an array as keys and values, and as code', function () {
        $record = new Post(['tags' => ['size' => 'L', 'stock' => 2]]);

        expect(KeyValueEntry::make('tags')->toProps($record)['pairs'])->toBe([
            ['key' => 'size', 'value' => 'L'],
            ['key' => 'stock', 'value' => '2'],
        ])->and(CodeEntry::make('tags')->toProps($record)['text'])->toContain('"size": "L"')
            ->and(KeyValueEntry::make('title')->toProps(new Post(['title' => 'Plain']))['pairs'])->toBe([]);
    });

    it('keeps the disabled form for a resource with no infolist', function () {
        $this->get("/admin/posts/{$this->post->id}")->assertInertia(fn (Assert $page) => $page
            ->where('page.content.0.schema.components.0.type', 'text-input'));
    });
});

describe('nested resources', function () {
    beforeEach(function () {
        $this->other = Post::create(['title' => 'Other']);
        $this->othersComment = $this->other->comments()->create(['body' => 'Not mine']);
        $this->blue = Post::create(['title' => 'Blue', 'team' => 'blue']);
        $this->bluesComment = $this->blue->comments()->create(['body' => 'Blue comment']);
    });

    it('lists only the parent\'s records', function () {
        $this->get("/admin/posts/{$this->post->id}/post-comments")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('page.content.0.schema.rows', 1)
            ->where('page.content.0.schema.rows.0.cells.body.state', 'First'));
    });

    it('finds the parent once a request, however many rows there are', function () {
        foreach (range(1, 9) as $number) {
            $this->post->comments()->create(['body' => "Reply {$number}"]);
        }

        $parentQueries = 0;
        DB::listen(function ($query) use (&$parentQueries): void {
            $parentQueries += (int) str_contains($query->sql, 'from "posts" where "team" = ? and "posts"."id" = ?');
        });

        $this->get("/admin/posts/{$this->post->id}/post-comments")->assertOk();

        expect($parentQueries)->toBe(1);
    });

    it('leads back up through the parent', function () {
        $this->get("/admin/posts/{$this->post->id}/post-comments/{$this->comment->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('page.breadcrumbs.0.label', 'Posts')
            ->where('page.breadcrumbs.1.label', 'Mine')
            ->where('page.breadcrumbs.2.label', 'Comments'));
    });

    it('can\'t open a record through another parent', function (string $path) {
        $this->get("/admin/posts/{$this->post->id}/post-comments/{$this->othersComment->id}{$path}")->assertNotFound();
    })->with(['view' => '', 'edit' => '/edit']);

    it('can\'t save a record through another parent', function () {
        $this->put("/admin/posts/{$this->post->id}/post-comments/{$this->othersComment->id}", ['data' => ['body' => 'Taken']])->assertNotFound();

        expect($this->othersComment->fresh()->body)->toBe('Not mine');
    });

    it('takes its children with a parent out of reach', function () {
        $this->get("/admin/posts/{$this->blue->id}/post-comments")->assertNotFound();
        $this->get("/admin/posts/{$this->blue->id}/post-comments/{$this->bluesComment->id}/edit")->assertNotFound();
    });

    it('needs view on the parent', function () {
        PostPolicy::$denied = ['view'];

        $this->get("/admin/posts/{$this->post->id}/post-comments")->assertNotFound();
    });

    it('puts a new record under the parent, whatever the browser sends', function () {
        $this->post("/admin/posts/{$this->post->id}/post-comments", ['data' => ['body' => 'New', 'post_id' => $this->other->id]])->assertRedirect();

        expect(Comment::where('body', 'New')->sole()->post_id)->toBe($this->post->id);
    });

    it('stays out of the sidebar, the new menu and search', function () {
        $this->get('/admin/posts')->assertInertia(fn (Assert $page) => $page
            ->where('xelqunPanel.navigation', fn ($groups): bool => collect($groups)->flatMap(fn (array $group): array => $group['items'])->pluck('label')->doesntContain('Comments'))
            ->where('xelqunPanel.quickCreate', fn ($items): bool => collect($items)->pluck('label')->doesntContain('Comment')));

        $this->postJson('/admin/_search', ['q' => 'First'])->assertOk();
    });

    it('keeps a token to its parent, on requests with no parent in the URL', function () {
        request()->attributes->set('xelqun-panels.parent', (string) $this->post->id);
        $table = ResourceTable::for(PostCommentResource::class);

        $this->queryTable($table)->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.cells.body.state', 'First');

        $this->runTableAction($table, 'delete', $this->othersComment->id)->assertNotFound();

        expect($this->othersComment->fresh())->not->toBeNull();
    });

    it('stops a token once its parent is out of reach', function () {
        request()->attributes->set('xelqun-panels.parent', (string) $this->post->id);
        $table = ResourceTable::for(PostCommentResource::class);

        $this->post->update(['team' => 'blue']);

        $this->queryTable($table)->assertNotFound();
    });

    it('offers tabs across a record\'s pages', function () {
        $this->get("/admin/posts/{$this->post->id}/post-comments/{$this->comment->id}")->assertInertia(fn (Assert $page) => $page
            ->where('page.subNavigation.0.label', 'Details'));
    });

    it('links a relation manager\'s rows to the nested pages', function () {
        $table = RelationTable::for(PostResource::class, NestedCommentsManager::class, $this->post, false);

        $this->queryTable($table)->assertJsonPath('rows.0.url', url("/admin/posts/{$this->post->id}/post-comments/{$this->comment->id}/edit"));
    });
});

describe('pages of a resource\'s own', function () {
    it('routes a page at its own path, ahead of the record pages', function () {
        expect(PostResource::getUrl('sort'))->toEndWith('/admin/posts/sort');

        $this->get('/admin/posts/sort')->assertOk()->assertInertia(fn (Assert $page) => $page->where('page.title', 'Sort posts'));
    });

    it('manages one relationship on a page under the record', function () {
        expect(PostResource::getUrl('comments', ['record' => $this->post]))->toEndWith("/admin/posts/{$this->post->id}/comments");

        $this->get("/admin/posts/{$this->post->id}/comments")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('page.title', 'Comments')
            ->where('page.content.0.type', 'table'));
    });

    it('refuses the related page for a record out of reach', function () {
        $this->get('/admin/posts/999999/comments')->assertNotFound();
    });
});

describe('how relation managers sit on the page', function () {
    afterEach(function () {
        CommentsRelationManager::$tuned = false;
        PostResource::$groupsRelations = false;
    });

    it('sends a badge, loads lazily, and can be changed on the view page', function () {
        CommentsRelationManager::$tuned = true;

        $this->get("/admin/posts/{$this->post->id}")->assertInertia(fn (Assert $page) => $page
            ->where('page.content.1.badge', '1')
            ->where('page.content.1.badgeColor', 'info')
            ->where('page.content.1.schema.deferred', true)
            ->has('page.content.1.schema.rows', 0)
            ->has('page.content.1.schema.headerActions', 2));
    });

    it('stays read-only on the view page unless the manager says otherwise', function () {
        $this->get("/admin/posts/{$this->post->id}")->assertInertia(fn (Assert $page) => $page
            ->has('page.content.1.schema.headerActions', 0)
            ->missing('page.content.1.badge'));
    });

    it('puts a group under one tab, and stacks the tables when asked', function () {
        PostResource::$groupsRelations = true;

        $this->get("/admin/posts/{$this->post->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('page.relationsLayout', 'stacked')
            ->where('page.content.1.group', 'Talk')
            ->where('page.content.2.group', 'Talk'));
    });
});
