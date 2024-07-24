<?php

namespace BookStack\Console\Commands;

use BookStack\References\ReferenceStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegenerateReferencesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookstack:regenerate-references
                            {--database= : The database connection to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate all the cross-item model reference index';

    /**
     * Execute the console command.
     */
    public function handle(ReferenceStore $references): int
    {
        $connection = DB::getDefaultConnection();

        if ($this->option('database')) {
            DB::setDefaultConnection($this->option('database'));
        }

        $references->updateForAll();

        DB::setDefaultConnection($connection);

        $this->comment('References have been regenerated');

        return 0;
    }
}
