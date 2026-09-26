<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedAdmin();

        // Gives the dashboard, analytics and DSS pages real data to render.
        // Skipped automatically once readings exist, so deploys stay safe.
        $this->call(DemoDataSeeder::class);
    }

    /**
     * Guarantee a working admin login on every deploy.
     *
     * Deliberately an update, not a firstOrCreate: an account that already
     * exists (someone registered admin@gmail.com by hand on an earlier deploy)
     * would otherwise keep whatever password it was given, and the documented
     * credentials would stop working with no way in.
     *
     * The password is only re-applied when ADMIN_PASSWORD is set, so an
     * operator who wants to keep a hand-chosen password can clear that
     * variable and the account is left alone from then on. ADMIN_EMAIL and
     * ADMIN_PASSWORD belong in .env, not in this file, so they are visible and
     * not baked into version control.
     */
    protected function seedAdmin(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@gmail.com');
        $password = env('ADMIN_PASSWORD');

        $admin = User::firstOrNew(['email' => $email]);

        $admin->name = $admin->name ?: 'Administrator';
        $admin->role = 'admin';

        if (filled($password)) {
            $admin->password = $password;
        } elseif (! $admin->exists) {
            // No ADMIN_PASSWORD configured and no account yet: fall back to
            // the documented demo password rather than creating an account
            // that cannot be logged into.
            $admin->password = 'admin123';
        }

        $admin->save();

        $this->command?->info('Admin account ready: '.$email);
    }
}

