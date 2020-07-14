<?php

namespace Nitm\Reporting\Console;

use Illuminate\Console\Command;
use Nitm\Reporting\Contracts\PrunableRepository;

class PruneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nitm-reporting:prune {--hours=24 : The number of hours to retain Reporting  data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune stale entries from the Reporting  database';

    /**
     * Execute the console command.
     *
     * @param  \Nitm\Reporting \Contracts\PrunableRepository  $repository
     * @return void
     */
    public function handle(PrunableRepository $repository)
    {
        $this->info($repository->prune(now()->subHours($this->option('hours'))) . ' entries pruned.');
    }
}
