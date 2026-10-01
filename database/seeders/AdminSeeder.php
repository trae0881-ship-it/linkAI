<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates the first admin account from environment variables so no
 * credential ever lives in source control. Idempotent: an existing
 * user's password is never overwritten by re-running the seeder.
 */
final class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = Str::lower(trim((string) config('admin.email')));
        $password = (string) config('admin.password');

        if ($email === '' || $password === '') {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set - skipping admin creation.');

            return;
        }

        // Same floor as the public registration rules.
        if (mb_strlen($password) < 12) {
            throw new \RuntimeException('ADMIN_PASSWORD must be at least 12 characters. Admin was NOT created.');
        }

        DB::transaction(function () use ($email, $password): void {
            $user = User::query()->firstOrNew(['email' => $email]);

            if (! $user->exists) {
                $user->name = (string) config('admin.name', 'Admin');
                $user->password = $password; // hashed by the model cast
            }

            $user->email_verified_at ??= now();
            $user->forceFill(['is_platform_admin' => true])->save(); // not mass-assignable; set explicitly

            if (! $user->workspaces()->exists()) {
                $workspace = Workspace::query()->create([
                    'name' => 'Admin Workspace',
                    'slug' => 'admin-'.Str::lower(Str::random(6)),
                    'owner_id' => $user->id,
                ]);
                $workspace->members()->attach($user->id, ['id' => (string) Str::uuid(), 'role' => 'OWNER']);
            }
        });

        $this->command?->info("Admin ready: {$email}");
    }
}
