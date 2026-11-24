<?php
// Versi Spatie Laravel Permission dengan primary/foreign key CHAR berukuran tetap
// (roles = ROL01 -> 5, permissions = PRM001 -> 6, users = USR001 -> 6).
// JANGAN publish/jalankan migration bawaan Spatie; gunakan file ini.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->char('id_permission', 6)->primary();         // PRM001 (maks 999 permission)
            $table->string('name', 40);                          // mis. distributions.receive
            $table->string('guard_name', 20);
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->char('id_role', 5)->primary();               // ROL01 (maks 99 role)
            $table->string('name', 30);                          // validasi RoleController: maks 30
            $table->string('guard_name', 20);
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->char('permission_id', 6);
            $table->string('model_type', 40);                    // 'App\Models\User'
            $table->char('model_id', 6);                         // = users.id_user
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')->references('id_permission')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->char('role_id', 5);
            $table->string('model_type', 40);
            $table->char('model_id', 6);                         // = users.id_user
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')->references('id_role')->on('roles')->cascadeOnDelete();
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->char('permission_id', 6);
            $table->char('role_id', 5);
            $table->foreign('permission_id')->references('id_permission')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id_role')->on('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
