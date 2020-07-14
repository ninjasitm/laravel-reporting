<?php

namespace Nitm\Reporting\Console;

use Illuminate\Console\Command;
use Nitm\Reporting\Contracts\ClearableRepository;

class ClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nitm-reporting:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all entries from Reporting';

    /**
     * Execute the console command.
     *
     * @param  \Nitm\Reporting\Contracts\ClearableRepository  $storage
     * @return void
     */
    public function handle(ClearableRepository $storage)
    {
        $storage->clear();

        $this->info('Reporting entries cleared!');
    }
}
