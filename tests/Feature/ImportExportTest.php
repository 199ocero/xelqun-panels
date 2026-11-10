<?php

use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Xelqun\Notifications\NotificationQueue;
use Xelqun\Panels\Actions\ImportAction;
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
