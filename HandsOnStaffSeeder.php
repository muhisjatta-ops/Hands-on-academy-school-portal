<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Hands-On Academy staff, as given by the school.
 *
 * Creates a User account for each member of staff and assigns the portal
 * role they need. It does NOT create teaching assignments — who teaches
 * which class and subject is a separate table, because "teacher" is a role
 * but "may enter Grade 7 Mathematics marks" is a fact about a row.
 *
 *     php artisan db:seed --class=HandsOnStaffSeeder
 *
 * Every account is created with a random password and must_change_password
 * set. Nobody should be handed a password this file could reveal, and a
 * shared default password across twelve accounts is how a school's records
 * end up readable by whoever was standing nearby.
 */
class HandsOnStaffSeeder extends Seeder
{
    /**
     * [name, position, part of the school, portal role]
     *
     * Only the Proprietress is a school administrator, as instructed. The
     * Principal holds the `principal` role, which adds the staff-attendance
     * register on top of a teacher's permissions. The Head Master and ECD
     * Heads are teachers here — if they need the register for their own
     * section, or school-wide reports, change their role below.
     */
    private const STAFF = [
        ['Horija Touray Dibba', 'Proprietress', 'Administration', 'admin'],

        ['Fatou Touray',        'Principal, Upper Basic School', 'Leadership', 'principal'],
        ['Habibou Nyassi',      'Head Master, Lower Basic School', 'Leadership', 'teacher'],
        ['Fabakary Cham',       'ECD Head', 'Leadership', 'teacher'],
        ['Binta Saidy',         'ECD Head', 'Leadership', 'teacher'],

        ['Lamin Camara',        'Senior Teacher · Mathematics', 'Departments', 'teacher'],
        ['Fatoumatta Cham',     'Head of English Department', 'Departments', 'teacher'],
        ['Fatima Kanuteh',      'Head of French Department', 'Departments', 'teacher'],
        ['Kutubo Ceesay',       'Head of Islamic Studies Department', 'Departments', 'teacher'],
        ['Sarja Badjie',        'Head of Technical Department', 'Departments', 'teacher'],
        ['Abdoulie Bojang',     'Head of Accounts Department', 'Departments', 'teacher'],
        ['Abdou Saye',          'Head of Science Department', 'Departments', 'teacher'],
    ];

    public function run(): void
    {
        $created = 0;
        $skipped = 0;

        foreach (self::STAFF as [$name, $position, $unit, $role]) {
            $username = $this->usernameFor($name);

            // Matching on username keeps a re-run from duplicating anyone,
            // and from overwriting a password someone has already set.
            $user = User::where('username', $username)->first();

            if ($user) {
                // Title and unit are refreshed on a re-run; the password and
                // anything the person set for themselves are left alone.
                $user->forceFill(['position' => $position, 'unit' => $unit])->save();
                $user->syncRoles([$role]);
                $skipped++;
                continue;
            }

            $user = User::create([
                'name'                 => $name,
                'position'             => $position,
                'unit'                 => $unit,
                'username'             => $username,
                'email'                => $username . '@handsonacademy.gm',
                'password'             => Str::password(16),
                'is_active'            => true,
                'must_change_password' => true,
            ]);

            $user->assignRole($role);
            $created++;

            $this->command->line(sprintf('  %-22s %-36s %s', $name, $position, $role));
        }

        $this->command->info("{$created} account(s) created, {$skipped} already existed.");
        $this->command->warn(
            'Each account has a random password and must change it at first '
            . 'sign-in. Issue a reset link per person rather than sharing one password.'
        );
        $this->command->warn(
            'The Proprietress is the only administrator; the Principal keeps '
            . 'staff attendance. The `principal` role needs the '
            . 'staff-attendance.view and staff-attendance.record permissions, '
            . 'and the administrator needs staff-attendance.view — add both to '
            . 'RolePermissionSeeder before running this.'
        );
        $this->command->warn(
            'Email addresses are guessed from names — correct them before any '
            . 'password reset is sent.'
        );
    }

    /** "Fatoumatta Cham" -> "fcham", disambiguated if that is taken. */
    private function usernameFor(string $name): string
    {
        $parts = preg_split('/\s+/', Str::lower(Str::ascii($name)));
        $base = Str::substr($parts[0], 0, 1) . end($parts);
        $base = preg_replace('/[^a-z0-9]/', '', $base);

        $username = $base;
        $n = 1;

        while (User::where('username', $username)->exists()
            && ! $this->belongsTo($username, $name)) {
            $username = $base . (++$n);
        }

        return $username;
    }

    private function belongsTo(string $username, string $name): bool
    {
        return User::where('username', $username)->where('name', $name)->exists();
    }
}
