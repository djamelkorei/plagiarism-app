<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeders.
     *
     * @return void
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            ['name' => 'super-admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'user', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]
        ]);

        DB::table('permissions')->insert([

            ['name' => 'users.index', 'class' => 'users', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'users.store', 'class' => 'users', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'users.update', 'class' => 'users', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'users.destroy', 'class' => 'users', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],

            ['name' => 'balances.index', 'class' => 'supports', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'balances.show', 'class' => 'supports', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'balances.store', 'class' => 'supports', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'balances.update', 'class' => 'supports', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'balances.destroy', 'class' => 'supports', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],

            ['name' => 'assignments.index', 'class' => 'supports', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'assignments.show', 'class' => 'supports', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'assignments.store', 'class' => 'supports', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'assignments.update', 'class' => 'supports', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'assignments.destroy', 'class' => 'supports', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'assignments.download', 'class' => 'supports', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'assignments.link', 'class' => 'supports', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]
        ]);

        DB::table('role_has_permissions')->insert([
            ['role_id' => 2, 'permission_id' => 11],
            ['role_id' => 2, 'permission_id' => 12],
            ['role_id' => 2, 'permission_id' => 13],
            ['role_id' => 2, 'permission_id' => 15],
        ]);
    }
}
