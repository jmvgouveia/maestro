<?php

namespace App\Console\Commands;

use App\Models\EmailAudit;
use Illuminate\Console\Command;

class PurgeEmailAudits extends Command
{
    protected $signature = 'email-audit:purge';
    protected $description = 'Remove auditorias de emails antigas';

    public function handle(): int
    {
        $retentionDays = max(1, (int) config('email-audit.retention_days', 180));
        $deleted = EmailAudit::query()
            ->where('attempted_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->info("{$deleted} auditorias removidas.");

        return self::SUCCESS;
    }
}
