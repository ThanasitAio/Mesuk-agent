<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DeployController extends Controller
{
    private const ALLOWED_AGENT = '0000390';

    private function authorize(): void
    {
        if (session('agent_code') !== self::ALLOWED_AGENT) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        }
    }

    public function show()
    {
        $this->authorize();

        return view('deploy.index', [
            'migrationStatus' => $this->getMigrationStatus(),
        ]);
    }

    public function run()
    {
        $this->authorize();

        $allSuccess = false;
        $errorMessage = null;

        try {
            $exitCode = Artisan::call('optimize:clear');
            $allSuccess = $exitCode === 0;
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
        }

        logSystem('agent', session('agent_id'), 'Deploy', 'CACHE_CLEAR', 'ล้าง Cache ทั้งหมด');

        return redirect()->route('deploy.show')->with('cache_results', [
            'success'  => $allSuccess,
            'error'    => $errorMessage,
            'time'     => now()->format('H:i:s น. d/m/Y'),
        ]);
    }

    public function runMigrations(Request $request)
    {
        $this->authorize();

        if ($request->input('confirm_migrate') !== 'RUN_MIGRATIONS') {
            return redirect()->route('deploy.show')->with('error', 'รหัสยืนยันไม่ถูกต้อง');
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output()) ?: 'Nothing to migrate.';
            logSystem('agent', session('agent_id'), 'Deploy', 'MIGRATE', 'รัน Database Migrations');
            return redirect()->route('deploy.show')->with('migration_output', $output);
        } catch (\Throwable $e) {
            return redirect()->route('deploy.show')->with('error', 'Migration ล้มเหลว: ' . $e->getMessage());
        }
    }

    private function getMigrationStatus(): array
    {
        try {
            $files = glob(database_path('migrations/*.php'));
            $fileNames = array_map(fn($f) => pathinfo($f, PATHINFO_FILENAME), $files);

            $ran = DB::table('migrations')->pluck('batch', 'migration')->toArray();

            $pending   = array_values(array_filter($fileNames, fn($f) => !isset($ran[$f])));
            $completed = array_values(array_filter($fileNames, fn($f) =>  isset($ran[$f])));

            return [
                'pending'   => $pending,
                'completed' => $completed,
                'ran'       => $ran,
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage(), 'pending' => [], 'completed' => [], 'ran' => []];
        }
    }
}
