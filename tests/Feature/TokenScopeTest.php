<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Xelqun\Actions\Action;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Panels\Tests\Fixtures\User;
use Xelqun\Tables\Columns\TextColumn;
use Xelqun\Tables\Table;

class GuardedUsersTable extends Table
{
    public function query(): Builder
    {
        return User::query();
    }

    public function authorize(?Authenticatable $user): bool
    {
        return $user !== null;
    }

    public function columns(): array
    {
        return [TextColumn::make('name')];
    }

    public function recordActions(): array
    {
        return [
            Action::make('rename')->authorize(true)->schema([
                TextInput::make('name')->live(),
                FileUpload::make('photo')->disk('local'),
            ])->action(fn (User $record, array $data) => $record->update(['name' => $data['name']])),
        ];
    }
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
    $this->allowed = true;

    app(FormTokenEncrypter::class)->scopeUsing(
        fn (): string => 'admin-panel',
        function (?string $scope): void {
            expect($scope)->toBe('admin-panel');
            Auth::shouldUse('admin');
            abort_unless($this->allowed, 403);
        },
    );

    $this->actingAs($this->user, 'admin');

    // Issued on a panel page, so everything below starts from what the endpoints start from.
    $table = GuardedUsersTable::make();
    $this->tableToken = $table->makeToken();
    $this->modalToken = $this->mountTableAction($table, 'rename', $this->user->id)->assertOk()->json('form.token');
    Auth::shouldUse('web');
});

function queryAsBrowser(): TestResponse
{
    return test()->postJson(route('xelqun.tables.query'), ['token' => test()->tableToken, 'query' => []]);
}

function runAsBrowser(): TestResponse
{
    return test()->postJson(route('xelqun.tables.action'), [
        'token' => test()->tableToken,
        'action' => 'rename',
        'kind' => 'record',
        'record' => (string) test()->user->id,
        'phase' => 'run',
        'data' => ['name' => 'Grace'],
    ]);
}

it('answers a table query for a user on another guard', function () {
    queryAsBrowser()->assertOk()->assertJsonPath('rows.0.cells.name.state', 'Ada');
});

it('runs a table action for a user on another guard', function () {
    runAsBrowser()->assertOk();

    expect($this->user->fresh()->name)->toBe('Grace');
});

it('accepts live updates and uploads from a nested modal', function () {
    $this->postJson(route('xelqun.forms.update'), [
        'token' => $this->modalToken,
        'seq' => 1,
        'state' => ['data' => ['name' => 'Grace']],
        'changed' => ['data.name'],
        'deps' => (object) [],
    ])->assertOk();

    $this->post(route('xelqun.forms.upload'), [
        'token' => $this->modalToken,
        'path' => 'data.photo',
        'file' => UploadedFile::fake()->image('a.png'),
        'state' => json_encode(['data' => []]),
    ], ['Accept' => 'application/json'])->assertOk();
});

it('refuses every endpoint once access is gone, without waiting for the token to expire', function () {
    $this->allowed = false;

    queryAsBrowser()->assertForbidden();
    runAsBrowser()->assertForbidden();
    $this->postJson(route('xelqun.forms.update'), [
        'token' => $this->modalToken,
        'seq' => 1,
        'state' => ['data' => []],
        'changed' => [],
        'deps' => (object) [],
    ])->assertForbidden();
    $this->post(route('xelqun.forms.upload'), [
        'token' => $this->modalToken,
        'path' => 'data.photo',
        'file' => UploadedFile::fake()->image('a.png'),
        'state' => json_encode(['data' => []]),
    ], ['Accept' => 'application/json'])->assertForbidden();

    expect($this->user->fresh()->name)->toBe('Ada');
});
