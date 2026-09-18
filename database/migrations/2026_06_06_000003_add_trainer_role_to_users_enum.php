<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys=OFF');

        DB::statement("CREATE TABLE users_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            username VARCHAR(50) NOT NULL,
            password VARCHAR NOT NULL,
            name VARCHAR(100) NOT NULL,
            role VARCHAR NOT NULL CHECK (role IN ('مدير النظام', 'موظف الاستقبال', 'المحاسب', 'المدقق المالي', 'مدرب', 'مشترك')),
            member_id VARCHAR(10) NULL,
            created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE SET NULL
        )");

        DB::statement('CREATE UNIQUE INDEX users_new_username_unique ON users_new (username)');

        DB::statement('INSERT INTO users_new (id, username, password, name, role, member_id, created_at)
            SELECT id, username, password, name, role, member_id, created_at FROM users');

        DB::statement('DROP TABLE users');
        DB::statement('ALTER TABLE users_new RENAME TO users');

        DB::statement('PRAGMA foreign_keys=ON');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys=OFF');

        DB::statement("CREATE TABLE users_old (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            username VARCHAR(50) NOT NULL,
            password VARCHAR NOT NULL,
            name VARCHAR(100) NOT NULL,
            role VARCHAR NOT NULL CHECK (role IN ('مدير النظام', 'موظف الاستقبال', 'المحاسب', 'المدقق المالي', 'مشترك')),
            member_id VARCHAR(10) NULL,
            created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE SET NULL
        )");

        DB::statement('CREATE UNIQUE INDEX users_old_username_unique ON users_old (username)');

        DB::statement("INSERT INTO users_old (id, username, password, name, role, member_id, created_at)
            SELECT id, username, password, name,
                CASE WHEN role = 'مدرب' THEN 'موظف الاستقبال' ELSE role END,
                member_id, created_at
            FROM users");

        DB::statement('DROP TABLE users');
        DB::statement('ALTER TABLE users_old RENAME TO users');

        DB::statement('PRAGMA foreign_keys=ON');
    }
};
