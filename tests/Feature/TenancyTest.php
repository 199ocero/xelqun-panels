<?php

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Actions\ImportAction;
use Xelqun\Panels\Actions\ImportRecords;
use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Resources\RelationTable;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Resources\ResourceForm;
use Xelqun\Panels\Resources\ResourceTable;
use Xelqun\Panels\Testing\InteractsWithPanels;
use Xelqun\Panels\Tests\Fixtures\Resources\RelatedPolicy;
use Xelqun\Panels\Tests\Fixtures\TagsResponse;
use Xelqun\Panels\Tests\Fixtures\Team;
use Xelqun\Panels\Tests\Fixtures\Tenancy\Client;
use Xelqun\Panels\Tests\Fixtures\Tenancy\ClientProjectsManager;
use Xelqun\Panels\Tests\Fixtures\Tenancy\ClientResource;
use Xelqun\Panels\Tests\Fixtures\Tenancy\Folder;
use Xelqun\Panels\Tests\Fixtures\Tenancy\FolderResource;
use Xelqun\Panels\Tests\Fixtures\Tenancy\Project;
use Xelqun\Panels\Tests\Fixtures\Tenancy\ProjectResource;
use Xelqun\Panels\Tests\Fixtures\Tenancy\TeamForm;

class RelationlessResource extends Resource
{
    protected static string $model = Team::class;

    public static function form(): array
    {
        return [];
    }

    public static function columns(): array
    {
        return [];
    }
}

class UnscopedProjectResource extends Resource
{
    protected static string $model = Project::class;

    protected static bool $isScopedToTenant = false;

    public static function form(): array
    {
        return [];
    }

    public static function columns(): array
    {
        return [];
    }
}

class TeamFieldProjectResource extends ProjectResource
{
    public static function form(): array
    {
        return [TextInput::make('name'), TextInput::make('team_id')];
    }
}

/** Stands in for an app's own check, such as an active subscription, on one group of the panel's middleware. */
class RefusesAfterTag
{
    public static string $refused = 'none';

    public function handle(Request $request, Closure $next, string $tag): Response
    {
        return $tag === self::$refused ? redirect('/billing') : $next($request);
    }
}

function inTeam(Team $team): void
{
    $panels = app(Panels::class);
    $panels->enter($panels->get('team'));
    $panels->enterTenant($team);
}

function saveProject(Team $team, array $data, string $method = 'post', ?Project $project = null): TestResponse
{
    $url = "/team/{$team->slug}/projects".($project === null ? '' : "/{$project->id}");

    return test()->{$method}($url, ['data' => $data]);
}

beforeEach(function () {
    Gate::policy(Project::class, RelatedPolicy::class);
    Gate::policy(Folder::class, RelatedPolicy::class);
    Gate::policy(Client::class, RelatedPolicy::class);
    RelatedPolicy::$denied = [];

    $this->user = $this->signIn();
    $this->red = Team::create(['name' => 'Red', 'slug' => 'red']);
    $this->blue = Team::create(['name' => 'Blue', 'slug' => 'blue']);
    $this->green = Team::create(['name' => 'Green', 'slug' => 'green']);
    $this->user->teams()->attach([$this->red->id, $this->blue->id]);

    $this->redProject = Project::create(['team_id' => $this->red->id, 'name' => 'Red project']);
    $this->blueProject = Project::create(['team_id' => $this->blue->id, 'name' => 'Blue project']);
    $this->greenProject = Project::create(['team_id' => $this->green->id, 'name' => 'Green project']);
});

describe('getting in', function () {
    it('sends the panel\'s path to the user\'s first tenant', function () {
        $this->get('/team')->assertRedirect('/team/blue');
    });

    it('sends someone with no tenants to start one', function () {
        $this->user->teams()->detach();

        $this->get('/team')->assertRedirect('/team/new-tenant');
    });

    it('refuses someone with no tenants when a panel doesn\'t let them start one', function () {
        $panels = app(Panels::class);
        $this->user->teams()->detach();
        $panels->get('team')->tenantRegistration(null);

        $this->get('/team')->assertForbidden();
    });

    it('sends a guest to sign in', function () {
        auth('admin')->logout();

        $this->get('/team/red')->assertRedirect('/team/login');
    });

    it('answers the same for a tenant that is someone else\'s or doesn\'t exist', function (string $slug) {
        $this->get("/team/{$slug}/projects")->assertNotFound();
    })->with(['green', 'nope']);
});

describe('what a tenant can see', function () {
    it('lists only its own records', function () {
        $this->get('/team/red/projects')->assertInertia(fn (Assert $page) => $page
            ->has('page.content.0.schema.rows', 1)
            ->where('page.content.0.schema.rows.0.cells.name.state', 'Red project'));
    });

    it('can\'t open another tenant\'s record by its id', function (string $path) {
        $this->get("/team/red/projects/{$this->blueProject->id}{$path}")->assertNotFound();
    })->with(['view' => '', 'edit' => '/edit']);

    it('can\'t save over another tenant\'s record', function () {
        saveProject($this->red, ['name' => 'Taken'], 'put', $this->blueProject)->assertNotFound();

        expect($this->blueProject->fresh()->name)->toBe('Blue project');
    });

    it('searches only this tenant', function () {
        $this->postJson('/team/red/_search', ['q' => 'project'])->assertOk()
            ->assertJsonCount(1, 'groups.0.results')
            ->assertJsonPath('groups.0.results.0.title', 'Red project');
    });

    it('scopes a many-to-many tenant too', function () {
        $folder = Folder::create(['name' => 'Shared']);
        $folder->teams()->attach($this->red);
        Folder::create(['name' => 'Elsewhere'])->teams()->attach($this->green);

        $this->get('/team/red/folders')->assertInertia(fn (Assert $page) => $page->has('page.content.0.schema.rows', 1));
    });

    it('stops at the tenant in every query of the request, and nowhere else', function () {
        inTeam($this->red);
        expect(Project::count())->toBe(1);

        request()->attributes->remove('xelqun-panels.tenant');
        expect(Project::count())->toBe(3);
    });

    it('keeps one panel\'s tenant scope out of another panel', function () {
        inTeam($this->red);
        expect(Project::count())->toBe(1);

        $panels = app(Panels::class);
        $panels->enter(Panel::make()->id('other')->path('other')->tenant(Team::class)->resources([UnscopedProjectResource::class]));
        $panels->enterTenant($this->blue);

        expect(Project::count())->toBe(3);
    });
});

describe('creating', function () {
    it('puts a new record in the tenant, whatever the browser sends', function () {
        saveProject($this->red, ['name' => 'New', 'team_id' => $this->blue->id])->assertRedirect();

        expect(Project::where('name', 'New')->sole()->team_id)->toBe($this->red->id);
    });

    it('keeps a record in its tenant when the form has a field for the tenant\'s key', function () {
        inTeam($this->red);

        $created = ResourceForm::for(TeamFieldProjectResource::class, ResourceForm::CREATE)->persist(['name' => 'Moved', 'team_id' => $this->blue->id]);
        ResourceForm::for(TeamFieldProjectResource::class, ResourceForm::EDIT, $this->redProject)->persist(['name' => 'Red project', 'team_id' => $this->blue->id]);

        expect($created->fresh()->team_id)->toBe($this->red->id)
            ->and($this->redProject->fresh()->team_id)->toBe($this->red->id);
    });

    it('attaches a new record to a many-to-many tenant', function () {
        $this->post('/team/red/folders', ['data' => ['name' => 'Fresh']])->assertRedirect();

        expect(Folder::where('name', 'Fresh')->sole()->teams->pluck('id')->all())->toBe([$this->red->id]);
    });

    it('checks scopedUnique inside the tenant only', function () {
        saveProject($this->red, ['name' => 'Blue project'])->assertRedirect();

        saveProject($this->red, ['name' => 'Red project'])->assertSessionHasErrors('data.name');

        expect(Project::where('team_id', $this->red->id)->where('name', 'Red project')->count())->toBe(1);
    });
});

describe('tokens', function () {
    it('carry the tenant, so a later request stays in it', function () {
        inTeam($this->red);
        $table = ResourceTable::for(ProjectResource::class);

        $this->queryTable($table)->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.cells.name.state', 'Red project');
    });

    it('stop working once the user leaves the tenant', function () {
        inTeam($this->red);
        $table = ResourceTable::for(ProjectResource::class);
        $this->user->teams()->detach($this->red);

        $this->queryTable($table)->assertForbidden();
    });

    it('can\'t reach another tenant\'s row from an action', function () {
        inTeam($this->red);

        $this->runTableAction(ResourceTable::for(ProjectResource::class), 'delete', $this->blueProject->id)->assertNotFound();

        expect($this->blueProject->fresh())->not->toBeNull();
    });

    it('reach no records without a tenant on a panel that needs one', function () {
        $panels = app(Panels::class);
        $panels->enter($panels->get('team'));

        $this->queryTable(ResourceTable::for(FolderResource::class))->assertNotFound();
    });

    it('work outside a tenant, for forms that sit there', function () {
        $panels = app(Panels::class);
        $panels->enter($panels->get('team'));
        $form = TeamForm::make();

        $this->updateForm($form, ['data.name' => 'Green'])->assertOk();
    });
});

describe('starting a tenant', function () {
    it('saves it, adds the user and goes there', function () {
        $this->post('/team/new-tenant', ['data' => ['name' => 'Green house', 'slug' => 'green-house']])->assertRedirect('/team/green-house');

        expect($this->user->teams()->where('slug', 'green-house')->exists())->toBeTrue();
    });

    it('shows a form to a guest of no tenant', function () {
        $this->get('/team/new-tenant')->assertOk();
    });

    it('sends a guest to sign in', function () {
        auth('admin')->logout();

        $this->get('/team/new-tenant')->assertRedirect('/team/login');
    });
});

describe('relation managers in a tenant', function () {
    it('puts a new related record in the tenant', function () {
        inTeam($this->red);
        $owner = Client::create(['name' => 'Acme']);
        $table = RelationTable::for(ClientResource::class, ClientProjectsManager::class, $owner, false);

        $this->runTableAction($table, 'create', null, ['name' => 'Fresh'])->assertOk();

        expect(Project::withoutGlobalScopes()->where('name', 'Fresh')->sole()->team_id)->toBe($this->red->id);
    });

    it('only offers records of the tenant to link', function () {
        inTeam($this->red);
        $owner = Client::create(['name' => 'Acme']);
        $this->blueProject->update(['client_id' => null]);
        $table = RelationTable::for(ClientResource::class, ClientProjectsManager::class, $owner, false);

        $this->runTableAction($table, 'associate', null, ['record' => $this->blueProject->id])->assertStatus(422);

        expect(Project::withoutGlobalScopes()->find($this->blueProject->id)->client_id)->toBeNull();
    });
});

describe('a tenant\'s profile', function () {
    it('needs update in the tenant\'s policy', function () {
        $this->get('/team/red/tenant-profile')->assertForbidden();
        $this->put('/team/red/tenant-profile', ['data' => ['name' => 'Taken', 'slug' => 'red']])->assertForbidden();

        expect($this->red->fresh()->name)->toBe('Red');
    });

    it('saves, and follows a changed slug', function () {
        Gate::policy(Team::class, RelatedPolicy::class);

        $this->get('/team/red/tenant-profile')->assertOk();
        $this->put('/team/red/tenant-profile', ['data' => ['name' => 'Crimson', 'slug' => 'crimson']])->assertRedirect('/team/crimson');

        expect($this->red->fresh()->name)->toBe('Crimson');
    });

    it('can\'t change a tenant the user isn\'t in', function () {
        Gate::policy(Team::class, RelatedPolicy::class);

        $this->put('/team/green/tenant-profile', ['data' => ['name' => 'Taken', 'slug' => 'green']])->assertNotFound();

        expect($this->green->fresh()->name)->toBe('Green');
    });
});

it('has a helper for tests that need a panel and a tenant', function () {
    $helper = new class($this->app)
    {
        use InteractsWithPanels;

        public function __construct(private $app) {}

        public function actingAs($user, $guard = null)
        {
            $this->app['auth']->guard($guard)->setUser($user);

            return $this;
        }
    };

    $helper->actingInPanel($this->user, 'team')->enterPanel('team', $this->red);

    expect(app(Panels::class)->current()->getId())->toBe('team')
        ->and(app(Panels::class)->tenant()->is($this->red))->toBeTrue();
});

describe('the shell', function () {
    it('lists a handful of tenants, with this one, and finds the rest by name', function () {
        foreach (range(1, Panel::TENANT_LIST_LIMIT + 5) as $number) {
            $this->user->teams()->attach(Team::create(['name' => sprintf('Team %02d', $number), 'slug' => "team-{$number}"])->id);
        }

        $this->get('/team/team-25')->assertInertia(fn (Assert $page) => $page
            ->has('xelqunPanel.tenant.tenants', Panel::TENANT_LIST_LIMIT)
            ->where('xelqunPanel.tenant.tenants.'.(Panel::TENANT_LIST_LIMIT - 1), ['name' => 'Team 25', 'url' => url('/team/team-25'), 'active' => true])
            ->where('xelqunPanel.tenant.searchUrl', url('/team/team-25/_tenants')));

        $this->postJson('/team/red/_tenants', ['q' => 'team 25'])
            ->assertOk()
            ->assertJsonPath('tenants', [['name' => 'Team 25', 'url' => url('/team/team-25'), 'active' => false]]);
    });

    it('never finds another user\'s tenant', function () {
        $this->postJson('/team/red/_tenants', ['q' => 'Green'])->assertOk()->assertJsonPath('tenants', []);
    });

    it('lists the user\'s tenants, marks this one, and links inside it', function () {
        $this->get('/team/red')->assertInertia(fn (Assert $page) => $page
            ->where('xelqunPanel.url', url('/team/red'))
            ->where('xelqunPanel.tenant.name', 'Red')
            ->where('xelqunPanel.tenant.tenants.0.name', 'Blue')
            ->where('xelqunPanel.tenant.tenants.1.active', true)
            ->where('xelqunPanel.navigation.0.items.1.url', url('/team/red/projects'))
            ->where('xelqunPanel.searchUrl', url('/team/red/_search')));
    });

    it('stays out of a panel with no tenants', function () {
        $this->get('/admin')->assertInertia(fn (Assert $page) => $page->where('xelqunPanel.tenant', null));
    });
});

describe('defining it', function () {
    it('explains a model that can\'t belong to a tenant', function () {
        $panels = app(Panels::class);

        RelationlessResource::scopeToTenants($panels->get('team'));
    })->throws(PanelDefinitionException::class, 'needs a [team] relationship');

    it('backstops a query with no tenant, rather than showing every tenant\'s records', function () {
        $panels = app(Panels::class);
        $panels->enter($panels->get('team'));

        ProjectResource::getEloquentQuery();
    })->throws(HttpException::class);
});

describe('panel middleware and tenant routes', function () {
    it('runs the panel\'s middleware, then the signed-in middleware, then the tenant\'s', function () {
        auth('admin')->logout();

        expect($this->get('/team/login')->headers->get('X-Xq-Middleware'))->toBe('panel');

        $this->actingAs($this->user, 'admin');

        expect($this->get('/team/new-tenant')->headers->get('X-Xq-Middleware'))->toBe('panel,auth')
            ->and($this->get('/team/red/projects')->headers->get('X-Xq-Middleware'))->toBe('panel,auth,tenant');
    });

    it('puts the tenant after a prefix, or in the host', function () {
        $panel = Panel::make()->id('prefixed')->path('app')->tenant(Team::class, slugAttribute: 'slug');

        expect($panel->getTenantRoutePrefix())->toBe('{tenant}')
            ->and($panel->tenantRoutePrefix('team')->getTenantRoutePrefix())->toBe('team/{tenant}')
            ->and($panel->getTenantUrl($this->red))->toEndWith('/app/team/red')
            ->and($panel->tenantDomain('{tenant}.example.test')->getTenantRoutePrefix())->toBe('')
            ->and($panel->getTenantUrl($this->red))->toBe('http://red.example.test/app');
    });

    it('runs the panel\'s middleware on the form and table endpoints too', function () {
        $token = $this->get('/team/red/projects')->viewData('page')['props']['page']['content'][0]['schema']['token'];
        app()->bind(TagsResponse::class, RefusesAfterTag::class);
        RefusesAfterTag::$refused = 'tenant';

        $this->postJson(route('xelqun.tables.query'), ['token' => $token, 'query' => []])->assertForbidden();
        $this->postJson(route('xelqun.tables.action'), ['token' => $token, 'action' => 'delete', 'kind' => 'record', 'phase' => 'run', 'record' => (string) $this->redProject->id])->assertForbidden();
        expect(Project::withoutGlobalScopes()->find($this->redProject->id))->not->toBeNull();

        RefusesAfterTag::$refused = 'none';
        $this->postJson(route('xelqun.tables.query'), ['token' => $token, 'query' => []])->assertOk();
    });

    it('lists more links in the tenant menu', function () {
        $this->get('/team/red/projects')->assertInertia(fn (Assert $page) => $page
            ->where('xelqunPanel.tenant.menuItems.0.label', 'Billing')
            ->where('xelqunPanel.tenant.menuItems.0.url', '/billing'));
    });
});

describe('a queued import', function () {
    it('creates the rows in the tenant it started in, and leaves no tenant behind', function () {
        Storage::disk(ImportAction::DISK)->put($path = 'xelqun-imports/team.csv', "name\nBlue project\n");

        (new ImportRecords(ImportAction::class, ProjectResource::class, $path, 'team', $this->user->getKey(), $this->red->getKey(), null))->handle(app(Panels::class));

        // The same name as Blue's project, which only clashes inside one tenant.
        expect(Project::where('team_id', $this->red->id)->where('name', 'Blue project')->exists())->toBeTrue()
            ->and(app(Panels::class)->tenant())->toBeNull();
    });

    it('imports nothing without a tenant on a panel that has them', function () {
        Storage::disk(ImportAction::DISK)->put($path = 'xelqun-imports/team.csv', "name\nTenantless\n");

        (new ImportRecords(ImportAction::class, ProjectResource::class, $path, 'team', $this->user->getKey(), null, null))->handle(app(Panels::class));

        expect(Project::where('name', 'Tenantless')->exists())->toBeFalse();
    });

    it('imports nothing into a tenant the user has left', function () {
        Storage::disk(ImportAction::DISK)->put($path = 'xelqun-imports/team.csv', "name\nIntruder\n");

        (new ImportRecords(ImportAction::class, ProjectResource::class, $path, 'team', $this->user->getKey(), $this->green->getKey(), null))->handle(app(Panels::class));

        expect(Project::where('name', 'Intruder')->exists())->toBeFalse()
            ->and($this->user->notifications()->sole()->data['status'])->toBe('danger');
    });
});
