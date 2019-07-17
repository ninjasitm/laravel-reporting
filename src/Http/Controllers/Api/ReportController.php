<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Reports\Report;
use App\Http\Requests;
use Prettus\Validator\Contracts\ValidatorInterface;
use Prettus\Validator\Exceptions\ValidatorException;
use App\Http\Requests\ReportCreateRequest;
use App\Http\Requests\ReportUpdateRequest;
use App\Http\Requests\ReportRequest;
use App\Repositories\ReportRepository;
use App\Validators\ReportValidator;

/**
* Class ReportsController.
*
* @package namespace App\Http\Controllers;
*/
class ReportController extends ApiBaseController
{
    protected $_supported = ['survey', 'meeting', 'goal', 'group'];

    /**
     * Run the report
     *
     * @param string $type
     * @return array
     */
    public function run(ReportRequest $request, $type)
    {
        $type = str_singular($type);
        if ($this->isSupported($type) && is_callable([$this, $type])) {
            return call_user_func([$this, $type], $request);
        } else {
            abort('404', "$type reports not found");
        }
    }

    /**
    * Get the survey reports.
    *
    * @return \Illuminate\Http\Response
    */
    protected function survey(ReportRequest $request)
    {
        return $this->getReport('survey', $request)->run();
    }

    /**
    * Get the goal reports.
    *
    * @return \Illuminate\Http\Response
    */
    protected function goal(ReportRequest $request)
    {
        return $this->getReport('goal', $request)->run();
    }

    /**
    * Get the group reports.
    *
    * @return \Illuminate\Http\Response
    */
    protected function group(ReportRequest $request)
    {
        return $this->getReport('group', $request)->run();
    }

    /**
    * Get the meeting reports.
    *
    * @return \Illuminate\Http\Response
    */
    protected function meeting(ReportRequest $request)
    {
        return $this->getReport('meeting', $request)->run();
    }

    /**
     * Is this report type supported?
     *
     * @param [type] $type
     * @return boolean
     */
    protected function isSupported($type) : bool
    {
        return in_array($type, $this->_supported);
    }

    /**
     * Get the report helper
     *
     * @param string $type
     * @return object
     */
    protected function getReport(string $type, ReportRequest $request) : object
    {
        $type = title_case($type).'Report';
        $reportClass = '\\App\\Reports\\'.$type;
        return new Report(new $reportClass($request));
    }
}
