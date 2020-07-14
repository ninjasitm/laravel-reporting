<?php

namespace Nitm\Reporting\Contracts;

interface TerminableRepository
{
    /**
     * Perform any clean-up tasks needed after storing Reporting  entries.
     *
     * @return void
     */
    public function terminate();
}
