<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Xelqun\Actions\Halt;
use Xelqun\Notifications\NotificationQueue;
use Xelqun\Panels\Actions\ImportAction;
use Xelqun\Panels\Actions\ImportColumn;
use Xelqun\Panels\Actions\ImportRecords;
use Xelqun\Panels\Http\Controllers\ExportController;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Tests\Fixtures\Note;
use Xelqun\Panels\Tests\Fixtures\Post;
use Xelqun\Panels\Tests\Fixtures\Resources\PostPolicy;
use Xelqun\Panels\Tests\Fixtures\Resources\PostResource;
use Xelqun\Panels\Tests\Fixtures\Resources\TaskPolicy;
use Xelqun\Panels\Tests\Fixtures\Resources\TaskResource;
use Xelqun\Panels\Tests\Fixtures\Task;
use Xelqun\Panels\Tests\Fixtures\User;

class ExposedImport extends ImportAction
{
    /**
     * @param  class-string<Xelqun\Panels\Resources\Resource>  $resource
     * @return array{imported: int, failed: int, skipped: int}
     */
    public static function importFile(string $resource, string $path): array
    {
        return static::importRows($resource, $path);
    }

    /**
     * @param  array{imported: int, failed: int, skipped?: int}  $counts
     */
    public static function reportCounts(array $counts): void
    {
        static::report($counts);
    }
}

class GuessingTaskResource extends TaskResource
{
    public static function getImportColumns(): array
    {
        return [ImportColumn::make('title')->guesses(['task name'])->rules(['min:3'])->requiredMapping()];
    }
}

function storedCsv(string $content): string
{
    Storage::disk(ImportAction::DISK)->put($path = 'xelqun-imports/'.Str::random(8).'.csv', $content);

    return $path;
}

class NarrowTaskResource extends TaskResource
{
    public static function getImportColumns(): array
    {
        return [];
    }
}

function csv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'import');
    file_put_contents($path, $content);

    return $path;
}

beforeEach(function () {
    Gate::policy(Task::class, TaskPolicy::class);
    Gate::policy(Post::class, PostPolicy::class);
    TaskPolicy::$denied = [];
    PostPolicy::$denied = [];
    $this->signIn();
    app(Panels::class)->enter(app(Panels::class)->get('admin'));
});

describe('exporting', function () {
    it('writes the table\'s columns, with a heading row', function () {
        Task::create(['title' => 'One']);

        expect($this->get('/admin/tasks/_export')->assertOk()->streamedContent())->toBe("Title\nOne\n");
    });

    it('stops a spreadsheet running a cell as a formula', function (string $title) {
        Task::create(['title' => $title]);

        expect($this->get('/admin/tasks/_export')->streamedContent())->toContain("'{$title}");
    })->with(['=1+1', '+SUM(A1)', '-2', '@cmd']);

    it('reaches only what the list reaches', function () {
        Post::create(['title' => 'Mine']);
        Post::create(['title' => 'Theirs', 'team' => 'blue']);

        $content = $this->get('/admin/posts/_export')->streamedContent();

        expect($content)->toContain('Mine')->not->toContain('Theirs');
    });

    it('keeps to the tab in the URL', function () {
        Task::create(['title' => 'Plain']);
        Task::create(['title' => 'Sluggy', 'slug' => 'a']);

        expect($this->get('/admin/tasks/_export?tab=sluggy')->streamedContent())->toContain('Sluggy')->not->toContain('Plain');
    });

    it('never writes a hidden column', function () {
        Task::create(['title' => 'One', 'slug' => 'secret-slug']);

        expect($this->get('/admin/tasks/_export')->streamedContent())->not->toContain('secret-slug');
    });

    it('writes an empty value as empty, and a record as its key', function () {
        $user = User::create(['name' => 'Bea', 'email' => 'bea@example.com', 'password' => 'x', 'email_verified_at' => null]);
        $post = Post::create(['title' => 'Mine', 'user_id' => $user->id])->load('author');
        $neutralize = new ReflectionMethod(ExportController::class, 'neutralize');

        expect($neutralize->invoke(null, $post->cover))->toBe('')
            ->and($neutralize->invoke(null, $post->author))->toBe((string) $user->id);
    });

    it('has no export for a resource that doesn\'t offer one', function () {
        Gate::policy(Note::class, TaskPolicy::class);

        $this->get('/admin/notes/_export')->assertNotFound();
    });

    it('starts on the first tab, as the list does', function () {
        Task::create(['title' => 'Plain']);

        expect($this->get('/admin/tasks/_export')->streamedContent())->toContain('Plain');
    });

    it('needs viewAny', function () {
        TaskPolicy::$denied = ['viewAny'];

        $this->get('/admin/tasks/_export')->assertForbidden();
    });
});

describe('importing', function () {
    it('creates a record for each row', function () {
        $counts = ExposedImport::importFile(TaskResource::class, csv("title\nOne\nTwo\n"));

        expect($counts)->toBe(['imported' => 2, 'failed' => 0, 'skipped' => 0])
            ->and(Task::pluck('title')->all())->toBe(['One', 'Two']);
    });

    it('takes a file saved with a byte order mark', function () {
        expect(ExposedImport::importFile(TaskResource::class, csv("\xEF\xBB\xBFtitle\nOne\n"))['imported'])->toBe(1);
    });

    it('counts a row that fails validation, and keeps the rest', function () {
        $counts = ExposedImport::importFile(TaskResource::class, csv("title\nGood\n\"\"\nAlso good\n"));

        expect($counts)->toBe(['imported' => 2, 'failed' => 1, 'skipped' => 0]);
    });

    it('counts a row with the wrong number of cells', function () {
        expect(ExposedImport::importFile(TaskResource::class, csv("title\nOne,extra\n"))['failed'])->toBe(1);
    });

    it('ignores columns that aren\'t listed, so a file can\'t set them', function () {
        ExposedImport::importFile(TaskResource::class, csv("title,slug,id\nOne,forced,999\n"));

        $task = Task::where('title', 'One')->sole();

        expect($task->slug)->toBeNull()->and($task->id)->not->toBe(999);
    });

    it('reads no column a resource leaves off its list, even one in the form', function () {
        expect(ExposedImport::importFile(NarrowTaskResource::class, csv("title\nOne\n")))->toBe(['imported' => 0, 'failed' => 1, 'skipped' => 0]);
    });

    it('runs the resource\'s rules', function () {
        expect(ExposedImport::importFile(PostResource::class, csv('title'."\n".str_repeat('a', 51)."\n"))['failed'])->toBe(1);
    });

    it('counts the rows past the limit instead of dropping them without a word', function () {
        // Rows with too many cells fail before any save, so a big file stays fast.
        $counts = ExposedImport::importFile(TaskResource::class, csv("title\n".str_repeat("One,extra\n", 1003)));

        expect($counts)->toBe(['imported' => 0, 'failed' => 1000, 'skipped' => 3]);
    });

    it('warns about the rows it skipped', function () {
        // A notification outside Xelqun's endpoints is flashed, which needs a session.
        request()->setLaravelSession(app('session.store'));
        ExposedImport::reportCounts(['imported' => 1000, 'failed' => 0, 'skipped' => 3]);

        expect(Inertia::getFlashed()[NotificationQueue::FLASH_KEY][0])->toMatchArray([
            'status' => 'warning',
            'body' => '3 rows after the first 1000 were not imported. Split the file and import the rest.',
        ]);
    });

    it('offers the form\'s fields by default', function () {
        expect(TaskResource::getImportColumns())->toBe(['title']);
    });
});

describe('matching headers', function () {
    it('matches a header by label or guess, whatever its case, spaces or dashes', function () {
        expect(ExposedImport::importFile(TaskResource::class, csv("TITLE\nOne\n"))['imported'])->toBe(1)
            ->and(ExposedImport::importFile(GuessingTaskResource::class, csv("Task-Name\nLong one\nAb\n")))->toBe(['imported' => 1, 'failed' => 1, 'skipped' => 0]);
    });

    it('refuses a file with no header for a required column, before reading a row', function () {
        $path = storedCsv("other\nOne\n");

        expect(fn () => ImportAction::for(GuessingTaskResource::class)->import(GuessingTaskResource::class, $path))->toThrow(Halt::class)
            ->and(Task::count())->toBe(0)
            ->and(Storage::disk(ImportAction::DISK)->exists($path))->toBeFalse();
    });
});

describe('queued importing', function () {
    it('queues the file for a job as the user, in the panel', function () {
        Queue::fake();
        $path = storedCsv("title\nOne\n");

        ImportAction::for(TaskResource::class)->queue()->import(TaskResource::class, $path);

        Queue::assertPushed(ImportRecords::class, fn (ImportRecords $job): bool => $job->resource === TaskResource::class
            && $job->path === $path
            && $job->panel === 'admin'
            && $job->userKey === auth()->id());
        expect(Task::count())->toBe(0)
            ->and(Storage::disk(ImportAction::DISK)->exists($path))->toBeTrue();
    });

    it('imports every row in the job, tells the user in their bell, and leaves no file or context behind', function () {
        $user = auth()->user();
        $path = storedCsv("title\n".implode("\n", array_map(fn (int $index): string => "Task {$index}", range(1, 1005)))."\n");
        app(Panels::class)->enter(app(Panels::class)->get('admin'));
        $before = app(Panels::class)->current();

        (new ImportRecords(ImportAction::class, TaskResource::class, $path, 'admin', $user->getKey(), null, null))->handle(app(Panels::class));

        expect(Task::count())->toBe(1005)
            ->and($user->notifications()->sole()->data['title'])->toBe('1005 imported, 0 failed.')
            ->and(Storage::disk(ImportAction::DISK)->exists($path))->toBeFalse()
            ->and(app(Panels::class)->current())->toBe($before);
    });

    it('imports nothing for a user who can no longer create records', function () {
        $user = auth()->user();
        TaskPolicy::$denied = ['create'];
        $path = storedCsv("title\nOne\n");

        (new ImportRecords(ImportAction::class, TaskResource::class, $path, 'admin', $user->getKey(), null, null))->handle(app(Panels::class));

        expect(Task::count())->toBe(0)
            ->and($user->notifications()->sole()->data['status'])->toBe('danger')
            ->and(Storage::disk(ImportAction::DISK)->exists($path))->toBeFalse();
    });

    it('leaves nothing found inside the job for the next one, such as an authorized parent', function () {
        $panels = app(Panels::class);

        $panels->within($panels->get('admin'), auth()->user(), null, '5', fn () => request()->attributes->set('xelqun-panels.parent.Projects.5', 'authorized for this user'));

        expect(request()->attributes->has('xelqun-panels.parent.Projects.5'))->toBeFalse();
    });

    it('imports nothing for a user who can no longer reach the panel', function () {
        User::$allowed = false;
        $path = storedCsv("title\nOne\n");

        try {
            (new ImportRecords(ImportAction::class, TaskResource::class, $path, 'admin', auth()->id(), null, null))->handle(app(Panels::class));
        } finally {
            User::$allowed = true;
        }

        expect(Task::count())->toBe(0)->and(Storage::disk(ImportAction::DISK)->exists($path))->toBeFalse();
    });

    it('tells the user when the job fails part way', function () {
        $path = storedCsv("title\nOne\n");

        (new ImportRecords(ImportAction::class, TaskResource::class, $path, 'admin', auth()->id(), null, null))->failed();

        expect(auth()->user()->notifications()->sole()->data['status'])->toBe('danger')
            ->and(Storage::disk(ImportAction::DISK)->exists($path))->toBeFalse();
    });

    it('stops for a user who is gone', function () {
        $path = storedCsv("title\nOne\n");

        (new ImportRecords(ImportAction::class, TaskResource::class, $path, 'admin', 999, null, null))->handle(app(Panels::class));

        expect(Task::count())->toBe(0)->and(Storage::disk(ImportAction::DISK)->exists($path))->toBeFalse();
    });
});
