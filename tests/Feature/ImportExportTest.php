<?php

use Illuminate\Support\Facades\Gate;
use Xelqun\Panels\Actions\ImportAction;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Tests\Fixtures\Post;
use Xelqun\Panels\Tests\Fixtures\Resources\PostPolicy;
use Xelqun\Panels\Tests\Fixtures\Resources\PostResource;
use Xelqun\Panels\Tests\Fixtures\Resources\TaskPolicy;
use Xelqun\Panels\Tests\Fixtures\Resources\TaskResource;
use Xelqun\Panels\Tests\Fixtures\Task;

class ExposedImport extends ImportAction
{
    /**
     * @param  class-string<Xelqun\Panels\Resources\Resource>  $resource
     * @return array{imported: int, failed: int}
     */
    public static function importFile(string $resource, string $path): array
    {
        return static::importRows($resource, $path);
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

    it('needs viewAny', function () {
        TaskPolicy::$denied = ['viewAny'];

        $this->get('/admin/tasks/_export')->assertForbidden();
    });
});

describe('importing', function () {
    it('creates a record for each row', function () {
        $counts = ExposedImport::importFile(TaskResource::class, csv("title\nOne\nTwo\n"));

        expect($counts)->toBe(['imported' => 2, 'failed' => 0])
            ->and(Task::pluck('title')->all())->toBe(['One', 'Two']);
    });

    it('takes a file saved with a byte order mark', function () {
        expect(ExposedImport::importFile(TaskResource::class, csv("\xEF\xBB\xBFtitle\nOne\n"))['imported'])->toBe(1);
    });

    it('counts a row that fails validation, and keeps the rest', function () {
        $counts = ExposedImport::importFile(TaskResource::class, csv("title\nGood\n\"\"\nAlso good\n"));

        expect($counts)->toBe(['imported' => 2, 'failed' => 1]);
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
        expect(ExposedImport::importFile(NarrowTaskResource::class, csv("title\nOne\n")))->toBe(['imported' => 0, 'failed' => 1]);
    });

    it('runs the resource\'s rules', function () {
        expect(ExposedImport::importFile(PostResource::class, csv('title'."\n".str_repeat('a', 51)."\n"))['failed'])->toBe(1);
    });

    it('offers the form\'s fields by default', function () {
        expect(TaskResource::getImportColumns())->toBe(['title']);
    });
});
