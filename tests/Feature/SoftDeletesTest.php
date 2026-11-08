<?php

use Illuminate\Support\Facades\Gate;
use Xelqun\Actions\ActionGroup;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Resources\ResourceTable;
use Xelqun\Panels\Tests\Fixtures\Resources\TaskPolicy;
use Xelqun\Panels\Tests\Fixtures\Resources\TaskResource;
use Xelqun\Panels\Tests\Fixtures\Task;
use Xelqun\Tables\Actions\BulkAction;
use Xelqun\Tables\State\TableState;

/**
 * @return array<int, string>
 */
function listedTasks(array $filters = []): array
{
    return collect(test()->queryTable(ResourceTable::for(TaskResource::class), $filters === [] ? [] : ['filters' => $filters])->assertOk()->json('rows'))
        ->pluck('cells.title.state')->all();
}

beforeEach(function () {
    Gate::policy(Task::class, TaskPolicy::class);
    TaskPolicy::$denied = [];
    TaskResource::$groupsBulkActions = false;

    $this->signIn();
    app(Panels::class)->enter(app(Panels::class)->get('admin'));
    $this->task = Task::create(['title' => 'Live', 'slug' => 'live']);
    $this->trashed = Task::create(['title' => 'Gone']);
    $this->trashed->delete();
});

describe('the list', function () {
    it('hides deleted records until asked', function () {
        expect(listedTasks())->toBe(['Live'])
            ->and(listedTasks(['trashed' => '1']))->toEqualCanonicalizing(['Live', 'Gone'])
            ->and(listedTasks(['trashed' => '0']))->toBe(['Gone']);
    });

    it('leaves deleted records out of the pages', function () {
        $this->get('/admin/tasks')->assertInertia(fn ($page) => $page->has('page.content.0.schema.rows', 1));
        $this->get("/admin/tasks/{$this->trashed->id}/edit")->assertNotFound();
    });

    it('sends a deleted row nowhere, and offers only restore and force delete', function () {
        $row = collect($this->queryTable(ResourceTable::for(TaskResource::class), ['filters' => ['trashed' => '0']])->json('rows'))->first();
        $actions = collect($row['actions'])->pluck('name');

        expect($row)->not->toHaveKey('url')
            ->and($actions)->toContain('restore', 'forceDelete')
            ->and($actions)->not->toContain('edit', 'delete');
    });

    it('offers restore and force delete on a selection only while deleted rows show', function (array $filters, array $expected) {
        $table = ResourceTable::for(TaskResource::class);
        $table->toResult(TableState::from(['filters' => $filters], $table));

        expect(array_map(fn (BulkAction $action) => $action->getName(), $table->bulkActions()))->toBe($expected);
    })->with([
        'without deleted' => [[], ['delete']],
        'with deleted' => [['trashed' => '1'], ['delete', 'restore', 'forceDelete']],
        'only deleted' => [['trashed' => '0'], ['delete', 'restore', 'forceDelete']],
    ]);

    it('keeps bulk actions a resource put in a group', function () {
        TaskResource::$groupsBulkActions = true;

        $table = ResourceTable::for(TaskResource::class);
        $table->toResult(TableState::from([], $table));
        $actions = $table->bulkActions();

        expect($actions)->toHaveCount(1)
            ->and($actions[0])->toBeInstanceOf(ActionGroup::class);
    });
});

describe('actions', function () {
    it('deletes softly', function () {
        $this->runTableAction(ResourceTable::for(TaskResource::class), 'delete', $this->task->id)->assertOk();

        expect($this->task->fresh()->trashed())->toBeTrue();
    });

    it('restores', function () {
        $this->runTableAction(ResourceTable::for(TaskResource::class), 'restore', $this->trashed->id)->assertOk();

        expect($this->trashed->fresh()->trashed())->toBeFalse();
    });

    it('deletes for good', function () {
        $this->runTableAction(ResourceTable::for(TaskResource::class), 'forceDelete', $this->trashed->id)->assertOk();

        expect(Task::withTrashed()->find($this->trashed->id))->toBeNull();
    });

    it('needs its own ability in the policy', function (string $ability, string $record) {
        TaskPolicy::$denied = [$ability];

        $this->runTableAction(ResourceTable::for(TaskResource::class), $ability, $this->{$record}->id)->assertForbidden();
    })->with([
        'restore' => ['restore', 'trashed'],
        'force delete' => ['forceDelete', 'trashed'],
        'replicate' => ['replicate', 'task'],
    ]);

    it('can\'t restore a record that isn\'t deleted', function () {
        $this->runTableAction(ResourceTable::for(TaskResource::class), 'forceDelete', $this->task->id)->assertForbidden();

        expect($this->task->fresh())->not->toBeNull();
    });

    it('only deletes for good what\'s already deleted, even in a selection', function () {
        $this->runBulkTableAction(ResourceTable::for(TaskResource::class), 'forceDelete', [$this->task->id, $this->trashed->id])->assertOk();

        expect($this->task->fresh())->not->toBeNull()
            ->and(Task::withTrashed()->find($this->trashed->id))->toBeNull();
    });

    it('works on a selection', function () {
        $this->runBulkTableAction(ResourceTable::for(TaskResource::class), 'restore', [$this->trashed->id])->assertOk();

        expect($this->trashed->fresh()->trashed())->toBeFalse();
    });
});

it('duplicates a record, leaving out what must stay unique', function () {
    $this->runTableAction(ResourceTable::for(TaskResource::class), 'replicate', $this->task->id)->assertOk();

    $copy = Task::where('title', 'Live (copy)')->sole();

    expect($copy->id)->not->toBe($this->task->id)
        ->and($copy->slug)->toBeNull();
});
