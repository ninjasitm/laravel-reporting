<?php

namespace Nitm\Reporting\Contracts;

use Illuminate\Support\Collection;
use Nitm\Reporting\CompletedReport;
use Nitm\Reporting\Storage\EntryQueryOptions;

interface CompletedReportRepository
{
    /**
     * Return an entry with the given ID.
     *
     * @param  mixed  $id
     * @return \Nitm\Reporting\CompletedReport
     */
    public function find($id): CompletedReport;

    /**
     * Return all the entries of a given type.
     *
     * @param  string|null  $type
     * @param  \Nitm\Reporting\Storage\EntryQueryOptions  $options
     * @return \Illuminate\Support\Collection|\Nitm\Reporting\CompletedReport[]
     */
    public function get($type, EntryQueryOptions $options);

    /**
     * Store the given entries.
     *
     * @param  \Illuminate\Support\Collection|\Nitm\Reporting\ImcomingReport[]  $entries
     * @return void
     */
    public function store(Collection $entries);

    /**
     * Store the given entry updates.
     *
     * @param  \Illuminate\Support\Collection|\Nitm\Reporting\CompletedReportUpdate[]  $updates
     * @return void
     */
    public function update(Collection $updates);

    /**
     * Load the monitored tags from storage.
     *
     * @return void
     */
    public function loadMonitoredTags();

    /**
     * Determine if any of the given tags are currently being monitored.
     *
     * @param  array  $tags
     * @return bool
     */
    public function isMonitoring(array $tags);

    /**
     * Get the list of tags currently being monitored.
     *
     * @return array
     */
    public function monitoring();

    /**
     * Begin monitoring the given list of tags.
     *
     * @param  array  $tags
     * @return void
     */
    public function monitor(array $tags);

    /**
     * Stop monitoring the given list of tags.
     *
     * @param  array  $tags
     * @return void
     */
    public function stopMonitoring(array $tags);
}
