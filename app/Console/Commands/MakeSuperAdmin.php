<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('jajapo:super-admin {email : Email akun Google yang dijadikan super admin} {--cabut : Mencabut hak super admin}')]
#[Description('Menjadikan (atau mencabut) super admin. Orangnya harus sudah pernah masuk dengan Google.')]
class MakeSuperAdmin extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error("Belum ada akun {$email}. Minta orangnya masuk dengan Google dulu, lalu jalankan perintah ini lagi.");

            return self::FAILURE;
        }

        $grant = ! $this->option('cabut');
        if (! $grant && User::query()->where('is_super_admin', true)->count() === 1 && $user->is_super_admin) {
            $this->error('Ini super admin terakhir. Angkat super admin lain dulu sebelum mencabut.');

            return self::FAILURE;
        }

        $user->forceFill(['is_super_admin' => $grant])->save();
        AuditLog::record(null, $grant ? 'user.super_admin_granted' : 'user.super_admin_revoked', $user, ['via' => 'artisan']);
        $this->info($grant ? "{$user->name} ({$user->email}) sekarang super admin." : "Hak super admin {$user->name} dicabut.");

        return self::SUCCESS;
    }
}
