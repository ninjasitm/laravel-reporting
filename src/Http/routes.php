<?php

/**
* Router for api nitm-reporting callback
*/

Route::group(
  config('nitm-reporting.api-route'), 
  function () {
    Route::apiResource(
      config('nitm-reporting.api-route.name', 'reporting'), 
      config('nitm-reporting.api-route.controller', 'Api\ReportingController')
    );
  }
);
