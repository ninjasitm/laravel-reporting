<?php

namespace Nitm\Reporting\Reports;

use Nitm\Reporting\Reports\Contracts\Report as ReportContract;

/**
 * Report class to generate reports
 */
class Report
{
    /**
     * Undocumented variable
     *
     * @var ReportContract
     */
    protected $report;

    protected $result;

    public function __construct(ReportContract $report)
    {
        $this->report = $report;
    }

    public function run()
    {
        return $this->report->getData();
    }
}
