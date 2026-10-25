<?php

namespace App\Console\Commands;

use App\Actions\ReconcileFilaments as ReconcileFilamentsAction;
use Illuminate\Console\Command;

class ReconcileFilaments extends Command
{
    protected $signature = 'filaments:reconcile {--apply : Apply reconciliation adjustments to divergent filaments}';

    protected $description = 'Reconcile filament balances with the ledger (dry-run by default)';

    public function handle(ReconcileFilamentsAction $action): int
    {
        $dryRun = ! $this->option('apply');

        $this->info($dryRun
            ? 'Running in DRY-RUN mode (no changes will be made).'
            : 'Running in APPLY mode (corrections will be persisted).');

        $result = $action->execute($dryRun);

        $this->newLine();
        $this->info("Checked: {$result['checked']} filament(s)");

        if (count($result['divergences']) === 0) {
            $this->info('All filaments are consistent with the ledger.');

            return self::SUCCESS;
        }

        $this->table(
            ['Filament', 'Current', 'Expected', 'Diff'],
            collect($result['divergences'])->map(fn (array $d) => [
                $d['filament_code'],
                number_format($d['current'], 2),
                number_format($d['expected'], 2),
                ($d['diff'] >= 0 ? '+' : '').number_format($d['diff'], 2),
            ])->all()
        );

        if ($dryRun) {
            $this->warn('Dry-run: no corrections were applied. Run with --apply to fix.');

            return self::SUCCESS;
        }

        $this->info('Corrected: '.count($result['corrected']).' filament(s)');

        return self::SUCCESS;
    }
}
