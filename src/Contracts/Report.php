<?php

namespace Nitm\Reporting\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface Report
{
    /**
     * Get the filters supported for this report
     *
     * [
     *     filterName => [
     *         ...options
     *     ]
     * ]
     * @return array
     */
    public function getFilters() : array;

    /**
     * Get the base query for the query used for generating the reports
     *
     * @return void
     */
    public function getQuery($range = []) : object;

    /**
     * Get the date range for the query
     * If empty then the report will be aggregated across all time
     * @return array
     */
    public function getRange() : array;

    /**
     * Get the date range for the query
     * [
     *     start => \Carbon\Carbon,
     *     end => \Carbon\Carbon
     * ]
     * @return Builder
     */
    public function setRange($query);

    /**
     * Get the type of the report
     *
     * @return string
     */
    public function getType() : string;

    /**
     * Get the report data
     * [
     *     name => value,
     *     name2 => []
     * ]
     * @return array
     */
    public function getData() : object;

    /**
     * Get the report summary
     * [
     *     name => value,
     *     name2 => []
     * ]
     * @return array
     */
    public function getSummary(Collection $result) : array;

    /**
     * Get the report stats
     * [
     *     name => value,
     *     name2 => []
     * ]
     * @return array
     */
    public function getStats(Collection $result) : array;

    /**
     * Get the model class
     *
     * @return string
     */
    public function modelClass() : string;

    /**
     * Get an instance of the model being used for the report
     *
     * @return object
     */
    public function makeModel() : object;
}
