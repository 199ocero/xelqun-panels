<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Resources\RelationTable;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Resources\ResourceTable;
use Xelqun\Panels\Testing\InteractsWithPanels;
use Xelqun\Panels\Tests\Fixtures\Resources\RelatedPolicy;
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
});

describe('creating', function () {
    it('puts a new record in the tenant, whatever the browser sends', function () {
        saveProject($this->red, ['name' => 'New', 'team_id' => $this->blue->id])->assertRedirect();

        expect(Project::where('name', 'New')->sole()->team_id)->toBe($this->red->id);
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
