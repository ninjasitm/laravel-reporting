<?php

namespace Nitm\Reporting\Traits;

use Carbon\Carbon;
use Illuminate\Support\Arr;

/**
 * @author Malcolm Paul <malcolm@ninjasitm.com>
 * Report specific methods
 */
trait HasReports
{
    /**
     * Set a range on a query
     *
     * @param [type] $query
     * @param mixed $range
     * @param string $prefix
     * @param string $column The date column we're using
     * @return void
     */
    public function scopeReportRange($query, $range = null, $prefix = null, $column = 'created_at')
    {
        $prefix = $prefix ?: $query->getModel()->getTable();
        $query->where(function ($query) use ($range, $prefix, $column) {
            $column = ($prefix ? $prefix . '.' : '') . $column;
            if (!empty($range)) {
                if (is_array($range) && !Arr::isAssoc($range)) {
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
                } elseif (is_array($range) && Arr::isAssoc($range)) {
                    if ($start = array_get($range, 'start')) {
                        $query->where($column, '>', $start->toDateTimeString());
                    }
                    if ($end = array_get($range, 'end')) {
                        $query->where($column, '<', $end->toDateTimeString());
                    }
                } elseif (is_string($range) && strtotime($range)) {
                    $start = new Carbon($range);
                    $query->where($column, '>', $start->toDateTimeString());
                }
            }
        });
    }
}
