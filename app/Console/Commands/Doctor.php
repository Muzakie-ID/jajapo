<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('jajapo:doctor')]
#[Description('Memeriksa kesiapan server: env, database, gateway, antrean, scheduler, email, push, dan login Google')]
class Doctor extends Command
{
    /** @var list<array{0:string,1:string,2:string}> */
    private array $rows = [];

    private bool $failed = false;

    public function handle(): int
    {
        $prod = app()->environment('production');

        $this->check('APP_ENV production', $prod, 'Sekarang '.app()->environment().'.', critical: false);
        $this->check('APP_DEBUG mati', ! config('app.debug'), 'Matikan APP_DEBUG supaya detail error tidak terlihat pengunjung.');
        $this->check('APP_URL memakai HTTPS', str_starts_with((string) config('app.url'), 'https://'), 'PWA, web push, dan login Google butuh HTTPS.');
        $this->check('APP_KEY terisi', filled(config('app.key')), 'Jalankan php artisan key:generate.');

        try {
            DB::connection()->getPdo();
            $this->check('Database terhubung ('.DB::connection()->getDriverName().')', true);
            $this->check('Database PostgreSQL', DB::connection()->getDriverName() === 'pgsql', 'Produksi dirancang untuk PostgreSQL.', critical: false);
            $pending = $this->pendingMigrations();
            $this->check('Semua migrasi sudah jalan', $pending === 0, "{$pending} migrasi belum jalan. Jalankan php artisan migrate --force.");
            $this->check('Ada super admin', User::query()->where('is_super_admin', true)->exists(), 'Masuk dengan Google, lalu php artisan jajapo:super-admin email@kamu.');
            $failedJobs = DB::table('failed_jobs')->count();
            $this->check('Tidak ada job gagal', $failedJobs === 0, "{$failedJobs} job gagal. Lihat php artisan queue:failed.", critical: false);
        } catch (Throwable $e) {
            $this->check('Database terhubung', false, $e->getMessage());
        }

        $gateway = config('services.payment_gateway');
        $this->check('Payment gateway driver http', ($gateway['driver'] ?? null) === 'http', 'Isi PAYMENT_GATEWAY_DRIVER=http.');
        $this->check('Kredensial gateway lengkap', filled($gateway['base_url'] ?? null) && filled($gateway['api_key'] ?? null) && filled($gateway['webhook_secret'] ?? null), 'Isi PAYMENT_GATEWAY_URL, PAYMENT_GATEWAY_KEY, dan PAYMENT_GATEWAY_WEBHOOK_SECRET.');

        $this->check('Antrean bukan sync', config('queue.default') !== 'sync', 'Pakai QUEUE_CONNECTION=database dan jalankan worker.');
        $beat = Cache::get('jajapo:scheduler-heartbeat');
        $this->check('Scheduler berjalan', $beat && Carbon::parse($beat)->gt(now()->subMinutes(5)), 'Pasang cron: * * * * * php artisan schedule:run.');

        $this->check('Email terkirim sungguhan', ! in_array(config('mail.default'), ['log', 'array'], true), 'MAIL_MAILER masih '.config('mail.default').'.', critical: false);
        $this->check('Kunci web push (VAPID)', filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key')), 'Jalankan php artisan webpush:vapid.', critical: false);
        $this->check('Login Google diatur', filled(config('services.google.client_id')) && filled(config('services.google.client_secret')), 'Isi GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET.');

        $this->check('Folder storage tertaut', is_link(public_path('storage')) || is_dir(public_path('storage')), 'Jalankan php artisan storage:link.');
        $this->check('Aset sudah di-build', is_file(public_path('build/manifest.json')), 'Jalankan npm ci && npm run build.');

        $this->table(['Pemeriksaan', 'Hasil', 'Yang perlu dilakukan'], $this->rows);

        if ($this->failed) {
            $this->error('Ada yang wajib dibenahi sebelum Jajapo dibuka untuk umum.');

            return self::FAILURE;
        }
        $this->info('Siap.');

        return self::SUCCESS;
    }

    private function check(string $label, bool $ok, string $fix = '', bool $critical = true): void
    {
        $this->rows[] = [$label, $ok ? 'OK' : ($critical ? 'PERLU' : 'SARAN'), $ok ? '' : $fix];
        if (! $ok && $critical) {
            $this->failed = true;
        }
    }

    private function pendingMigrations(): int
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            return count($migrator->getMigrationFiles(database_path('migrations')));
        }
        $ran = $migrator->getRepository()->getRan();

        return collect($migrator->getMigrationFiles(database_path('migrations')))->keys()->diff($ran)->count();
    }
}
