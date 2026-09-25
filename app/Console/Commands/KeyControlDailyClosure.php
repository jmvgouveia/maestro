<?php

namespace App\Console\Commands;

use App\Services\KeyControlClosureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\KeyControlSetting;

class KeyControlDailyClosure extends Command
{
    protected $signature = 'key-control:daily-closure {--force : Executa imediatamente para testes, ignorando a hora e o bloqueio diário}';

    protected $description = 'Liberta salas e processa chaves não devolvidas no fecho diário';

    public function handle(KeyControlClosureService $service): int
    {
        $configuredTime = KeyControlSetting::value('daily_closure_time', '23:59');

        if (! $this->option('force') && (now()->format('H:i') < $configuredTime
            || ! Cache::add('key-control:daily-closure:'.now()->toDateString(), true, now()->endOfDay()))) {
            return self::SUCCESS;
        }

        $count = $service->dailyClosure();
        $this->info("{$count} movimento(s) fechado(s).");

        return self::SUCCESS;
    }
}
