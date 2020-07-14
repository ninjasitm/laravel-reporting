<?php

namespace Nitm\Reporting;

use Closure;
use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\EventFake;
use Nitm\Reporting\Contracts\EntriesRepository;
use Nitm\Reporting\Contracts\TerminableRepository;
use RuntimeException;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Throwable;

class Report
{
    use AuthorizesRequests,
        ListensForStorageOpportunities;

    /**
     * The callbacks that filter the entries that should be recorded.
     *
     * @var array
     */
    public static $filterUsing = [];

    /**
     * The callbacks that filter the batches that should be recorded.
     *
     * @var array
     */
    public static $filterBatchUsing = [];

    /**
     * The callback executed after queuing a new entry.
     *
     * @var \Closure
     */
    public static $afterRecordingHook;

    /**
     * The callbacks executed after storing the entries.
     *
     * @var \Closure
     */
    public static $afterStoringHooks = [];

    /**
     * The callbacks that add tags to the record.
     *
     * @var \Closure[]
     */
    public static $tagUsing = [];

    /**
     * The list of queued entries to be stored.
     *
     * @var array
     */
    public static $entriesQueue = [];

    /**
     * The list of queued entry updates.
     *
     * @var array
     */
    public static $updatesQueue = [];

    /**
     * Indicates if Reporting should use the dark theme.
     *
     * @var bool
     */
    public static $useDarkTheme = false;

    /**
     * Indicates if Reporting should record entries.
     *
     * @var bool
     */
    public static $shouldRecord = false;

    /**
     * Indicates if Reporting migrations will be run.
     *
     * @var bool
     */
    public static $runsMigrations = true;

    /**
     * Start recording entries.
     *
     * @return void
     */
    public static function startRecording()
    {
        app(EntriesRepository::class)->loadMonitoredTags();

        static::$shouldRecord = !cache('nitm-reporting:pause-recording');
    }

    /**
     * Stop recording entries.
     *
     * @return void
     */
    public static function stopRecording()
    {
        static::$shouldRecord = false;
    }

    /**
     * Execute the given callback without recording Reporting entries.
     *
     * @param  callable  $callback
     * @return void
     */
    public static function withoutRecording($callback)
    {
        $shouldRecord = static::$shouldRecord;

        static::$shouldRecord = false;

        call_user_func($callback);

        static::$shouldRecord = $shouldRecord;
    }

    /**
     * Determine if Reporting is recording.
     *
     * @return bool
     */
    public static function isRecording()
    {
        return static::$shouldRecord && !app('events') instanceof EventFake;
    }

    /**
     * Record the given entry.
     *
     * @param  string  $type
     * @param  \Nitm\Reporting\IncomingEntry  $entry
     * @return void
     */
    protected static function record(string $type, IncomingEntry $entry)
    {
        if (!static::isRecording()) {
            return;
        }

        $entry->type($type)->tags(Arr::collapse(array_map(function ($tagCallback) use ($entry) {
            return $tagCallback($entry);
        }, static::$tagUsing)));

        try {
            if (Auth::hasResolvedGuards() && Auth::hasUser()) {
                $entry->user(Auth::user());
            }
        } catch (Throwable $e) {
            // Do nothing.
        }

        static::withoutRecording(function () use ($entry) {
            if (collect(static::$filterUsing)->every->__invoke($entry)) {
                static::$entriesQueue[] = $entry;
            }

            if (static::$afterRecordingHook) {
                call_user_func(static::$afterRecordingHook, new static, $entry);
            }
        });
    }

    /**
     * Record the given entry update.
     *
     * @param  \Nitm\Reporting\EntryUpdate  $update
     * @return void
     */
    public static function recordUpdate(EntryUpdate $update)
    {
        if (static::$shouldRecord) {
            static::$updatesQueue[] = $update;
        }
    }

    /**
     * Record the given entry.
     *
     * @param  \Nitm\Reporting\IncomingEntry  $entry
     * @return void
     */
    public static function recordReport(IncomingEntry $entry)
    {
        static::record(EntryType::REPORT, $entry);
    }

    /**
     * Record the given entry.
     *
     * @param  \Nitm\Reporting\IncomingEntry  $entry
     * @return void
     */
    public static function recordImport(IncomingEntry $entry)
    {
        static::record(EntryType::IMPORT, $entry);
    }

    /**
     * Record the given entry.
     *
     * @param  \Nitm\Reporting\IncomingEntry  $entry
     * @return void
     */
    public static function recordExport(IncomingEntry $entry)
    {
        static::record(EntryType::EXPORT, $entry);
    }

    /**
     * Flush all entries in the queue.
     *
     * @return static
     */
    public static function flushEntries()
    {
        static::$entriesQueue = [];

        return new static;
    }

    /**
     * Record the given exception.
     *
     * @param  \Throwable|\Exception  $e
     * @param  array  $tags
     * @return void
     */
    public static function catch($e, $tags = [])
    {
        if ($e instanceof Throwable && !$e instanceof Exception) {
            $e = new FatalThrowableError($e);
        }

        event(new MessageLogged('error', $e->getMessage(), [
            'exception' => $e,
            'nitm-reporting' => $tags,
        ]));
    }

    /**
     * Set the callback that filters the entries that should be recorded.
     *
     * @param  \Closure  $callback
     * @return static
     */
    public static function filter(Closure $callback)
    {
        static::$filterUsing[] = $callback;

        return new static;
    }

    /**
     * Set the callback that filters the batches that should be recorded.
     *
     * @param  \Closure  $callback
     * @return static
     */
    public static function filterBatch(Closure $callback)
    {
        static::$filterBatchUsing[] = $callback;

        return new static;
    }

    /**
     * Set the callback that will be executed after an entry is recorded in the queue.
     *
     * @param  \Closure  $callback
     * @return static
     */
    public static function afterRecording(Closure $callback)
    {
        static::$afterRecordingHook = $callback;

        return new static;
    }

    /**
     * Add a callback that will be executed after an entry is stored.
     *
     * @param  \Closure  $callback
     * @return static
     */
    public static function afterStoring(Closure $callback)
    {
        static::$afterStoringHooks[] = $callback;

        return new static;
    }

    /**
     * Add a callback that adds tags to the record.
     *
     * @param  \Closure  $callback
     * @return static
     */
    public static function tag(Closure $callback)
    {
        static::$tagUsing[] = $callback;

        return new static;
    }

    /**
     * Store the queued entries and flush the queue.
     *
     * @param  \Nitm\Reporting\Contracts\EntriesRepository  $storage
     * @return void
     */
    public static function store(EntriesRepository $storage)
    {
        if (empty(static::$entriesQueue) && empty(static::$updatesQueue)) {
            return;
        }

        static::withoutRecording(function () use ($storage) {
            if (!collect(static::$filterBatchUsing)->every->__invoke(collect(static::$entriesQueue))) {
                static::flushEntries();
            }

            try {
                $batchId = Str::orderedUuid()->toString();

                $storage->store(static::collectEntries($batchId));
                $storage->update(static::collectUpdates($batchId));

                if ($storage instanceof TerminableRepository) {
                    $storage->terminate();
                }

                collect(static::$afterStoringHooks)->every->__invoke(static::$entriesQueue, $batchId);
            } catch (Exception $e) {
                app(ExceptionHandler::class)->report($e);
            }
        });

        static::$entriesQueue = [];
        static::$updatesQueue = [];
    }

    /**
     * Collect the entries for storage.
     *
     * @param  string  $batchId
     * @return \Illuminate\Support\Collection
     */
    protected static function collectEntries($batchId)
    {
        return collect(static::$entriesQueue)
            ->each(function ($entry) use ($batchId) {
                $entry->batchId($batchId);
            });
    }

    /**
     * Collect the updated entries for storage.
     *
     * @param  string  $batchId
     * @return \Illuminate\Support\Collection
     */
    protected static function collectUpdates($batchId)
    {
        return collect(static::$updatesQueue)
            ->each(function ($entry) use ($batchId) {
                $entry->change(['updated_batch_id' => $batchId]);
            });
    }

    /**
     * Specifies that Reporting should use the dark theme.
     *
     * @return static
     */
    public static function night()
    {
        static::$useDarkTheme = true;

        return new static;
    }

    /**
     * Get the default JavaScript variables for Reporting.
     *
     * @return array
     */
    public static function scriptVariables()
    {
        return [
            'path' => config('nitm-reporting.path'),
            'timezone' => config('app.timezone'),
            'recording' => !cache('nitm-reporting:pause-recording'),
        ];
    }

    /**
     * Check if assets are up-to-date.
     *
     * @return bool
     *
     * @throws \RuntimeException
     */
    public static function assetsAreCurrent()
    {
        $publishedPath = public_path('vendor/nitm-reporting/mix-manifest.json');

        if (!File::exists($publishedPath)) {
            throw new RuntimeException('The Reporting assets are not published. Please run: php artisan nitm-reporting:publish');
        }

        return File::get($publishedPath) === File::get(__DIR__ . '/../public/mix-manifest.json');
    }
}