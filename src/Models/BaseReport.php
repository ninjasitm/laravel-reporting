<?php

namespace App\Reports;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use App\Http\Requests\ReportRequest;

/**
 * Base report that defines some basic functionality
 */
abstract class BaseReport
{
    /**
     * Request criteria for filtering the report
     *
     * @var [type]
     */
    protected $request;

    public function __construct(ReportRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Get the report data
     *
     * @return object
     */
    public function getData() : object
    {
        $reportType = $this->request->input('type');
        $period = $this->getRange();
        $query = $this->getQuery($period);
        $collection = $this->createCollection($query->paginate());
        $response = $collection->toResponse($this->request)->getData();
        $data = $response->data;
        $meta = $response->meta;
        $summary = $this->extendSummary($collection);
        $filters = $this->getFilters();
        $type = $this->getType();
        $stats = $this->getStats($collection);
        return collect(compact('type', 'data', 'summary', 'filters', 'period', 'meta', 'stats'));
    }

    /**
     * Create the underlying report model
     *
     * @return object
     */
    public function makeModel() : object
    {
        $modelClass = $this->modelClass();
        return new $modelClass;
    }

    /**
     * Create a resource collection
     *
     * @param array $data
     * @return void
     */
    public function createCollection($data)
    {
        $class = '\\App\\Http\\Resources\\Reports\\'.class_basename($this)."Collection";
        return new $class($data);
    }

    /**
     * Get the query used for generating the report
     *
     * @return object
     */
    public function getQuery() : object
    {
        return $this->makeModel()->newQueryWithoutScopes()
            ->setEagerLoads([]);
    }

    /**
     * Get the report filters
     *
     * @return array
     */
    public function getFilters() : array
    {
        return $this->makeModel()->getFilterOptions();
    }

    /**
     * Get the date range configured for the report
     *
     * @return array
     */
    public function getRange() : array
    {
        $start = $this->request->input('start');
        $end = $this->request->input('end');

        /**
         * We only want valid dates
         */
        if (strtotime($start) === false) {
            $start = null;
        }

        if (strtotime($end) === false) {
            $end = null;
        }

        return array_filter([
            'start' => $start ? new Carbon($start) : Carbon::now()->subMonth(1),
            'end' => $end ? new Carbon($end) : Carbon::now()
        ]);
    }

    /**
     * Set the time range on a querey
     *
     * @param Builder $query
     * @param mixed $range
     * @param string $prefix Most likely the table name
     * @param string $column THe date column
     * @return void
     */
    public function setRange($query, $range = null, string $prefix=null, $column = 'created_at')
    {
        $query->where(function ($query) use ($range, $prefix, $column) {
            $range = $range ?: $this->getRange();
            $column = ($prefix ? $prefix.'.' : '').$column;
            if (!empty($range) && count($range) == 2) {
                $query->whereBetween(
                    $column,
                    array_values(
                        array_map(
                            function ($dt) {
                                return $dt->toDateTimeString();
                            },
                            $range
                        )
                    )
                );
            } else {
                if ($start = array_get($range, 'start')) {
                    $query->where($column, '>', $start->toDateTimeString());
                } elseif ($end = array_get($range, 'end')) {
                    $query->where($column, '<', $end->toDateTimeString());
                }
            }
        });
    }

    /**
     * Group the data by a time series on a querey
     *
     * @param Builder $query
     * @param mixed $interval [int, month|day|week|year]
     * @param string $prefix Most likely the table name
     * @param string $column THe date column
     * @return void
     */

    protected function toTimeSeries($query, $interval = [1, 'day'], string $prefix=null, $column = 'created_at')
    {
    }

    /**
     * Extend the report summary information
     *
     * @param mixed $data
     * @return void
     */
    protected function extendSummary($data)
    {
        $summary = $this->getSummary($data);
        $period = $this->getRange();
        if (sizeof($period) == 2) {
            $period['title'] = 'From '.$period['start']->format('M jS, Y').' to '.$period['end']->format('M jS, Y');
            $period['interval'] = $period['start']->diffForHumans($period['end']);
        } elseif (sizeof($period) == 1) {
            $period['title'] = isset($period['start']) ? 'Since ' : 'Up To ';
            $date = isset($period['start']) ? $period['start'] : $period['end'];
            $end = $period['end'];
            $period['title'] .= $date->format('M jS, Y');
            $period['interval'] = [
                "fromNow" => $date->diffForHumans(Carbon::now()),
                "range" => "{$data->toFormattedDateString()} to {$end->toFormattedDateString()}"
            ];
        } else {
            $period['title'] = 'Since '.Carbon::parse($data->min('created_at'))->format('M jS, Y');
        }
        return array_merge($period, $summary);
    }

    /**
     * Format pie chart dtaa in a standard format
     *
     * @param string $type The chart type
     * @param mixed $title
     * @param array $data
     * @param array $labels
     * @param array $options
     * @return array
     */
    protected function formatChartData(string $type, $title, $data, $labels, $options = []): array
    {
        $args = func_get_args();
        // Remove the type parameter
        array_shift($args);
        switch ($type) {
            case 'pie':
            case 'donut':
            return $this->formatPieChartData($title, $data, $labels, $options, $type);
            break;

            case 'bar':
            case 'column':
            case 'horizontal-bar':
            return $this->formatBarChartData($title, $data, $labels, $options, $type);
            break;

            case 'line':
            return $this->formatLineChartData($title, $data, $labels, $options, $type);
            break;
        }
    }

    /**
     * Format pie chart dtaa in a standard format
     *
     * @param mixed $title
     * @param array $data
     * @param array $labels
     * @param array $options
     * @param  string $type The chart type [pie|donut]
     * @return array
     */
    protected function formatPieChartData($title, $data, $labels, $options = [], $type = 'pie'): array
    {
        list($title, $yLabel) = array_pad((array)$title, 2, null);

        return [
            'title' => $title,
            'series' => $data,
            'type' => $type === 'pie' ? 'pie' : 'donut',
            'options' => array_merge(
                [
                    'labels' => $labels,
                    'title' => [
                        'text' => $yLabel ?: $title
                    ],
                    'responsive' => [
                        [
                            'breakpoint' => 480,
                            'options' => [
                                'chart' => [
                                    'width' => 300
                                ],
                                'legend' => [
                                    'position' => 'bottom'
                                ]
                            ]
                        ]
                    ]
                ],
                $options
            )
        ];
    }

    /**
     * Format bar chart data in a standard format
     *
     * @param mixed $title
     * @param array $data
     * @param array $labels
     * @param array $options
     * @param string $alignment The chart alignment [vertical|horizontal]
     * @return array
     */
    protected function formatBarChartData($title, $data, $labels, $options = [], $alignment = 'vertical'): array
    {
        list($title, $yLabel) = array_pad((array)$title, 2, null);
        return [
            'title' => $title,
            'series' => $this->prepareSeriesData($data),
            'labels' => $labels,
            'type' => 'bar',
            'options' => array_merge([
                'xaxis' => [
                    'categories' => $labels
                ],
                'yaxis' => [
                    'title' => [
                        'text' => $yLabel ?: ''
                    ]
                ],
                'plotOptions' => [
                    'bar' => [
                        'horizontal' => $alignment === 'horizontal-bar
                        '
                    ]
                ]
            ], $options)
        ];
    }

    /**
     * Format bar chart data in a standard format
     *
     * @param mixed $title
     * @param array $data
     * @param array $labels
     * @param array $options
     * @param string $alignment The chart alignment [vertical|horizontal]
     * @return array
     */
    protected function formatLineChartData($title, $data, $labels, $options = [], $alignment = 'vertical'): array
    {
        $args = func_get_args();
        $result = $this->formatBarChartData(...$args);
        $result['type'] = 'line';
        $result['options']['chart'] = [
            'stacked' => false,
            'zoom' => [
              'type' => 'x',
              'enabled' => true
            ],
            'toolbar' => [
              'autoSelected' => 'zoom'
            ]
        ];
        $result['options']['yaxis'] = [
            'min' => 0,
            'max' => collect($result['series'])->reduce(
                function ($carry, $item) {
                    $max = $item['data']->max();
                    return $carry > $max ? $carry : $max;
                },
                0
            )
        ];
        return $result;
    }

    /**
     * Return a properly wrapped series data
     *
     * @param array $data
     * @return void
     */
    protected function prepareSeriesData(array $data): array
    {
        return Arr::isAssoc($data) ? [$data] : $data;
    }
}
