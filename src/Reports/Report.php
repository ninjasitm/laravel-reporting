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

    /**
     * __construct
     *
     * @param  mixed $report
     * @return void
     */
    public function __construct(ReportContract $report)
    {
        $this->report = $report;
    }

    /**
     * Run
     *
     * @return void
     */
    public function run()
    {
        return $this->report->getData();
    }

    /**
     * Run Report
     *
     * @param string $report The report to run from the report class
     * @return void
     */
    public function runReport(string $report) {
        if(!method_exists($this->report, $report)) {
            throw new \Exception ("$report does not exist on ".get_class($this->report));
        }
        return $this->report->$report();
    }
}
