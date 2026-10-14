<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable();
            $table->timestamp('handled_at')->nullable()->index();
        });

        // Administrators pass every check; this lets you grant the inbox to other roles.
        Permission::findOrCreate('manage contact messages', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'manage contact messages')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropIndex(['handled_at']);
            $table->dropColumn(['read_at', 'handled_at']);
        });
    }
};
