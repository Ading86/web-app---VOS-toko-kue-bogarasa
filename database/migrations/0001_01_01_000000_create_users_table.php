<?php
// MENGGANTIKAN migration users bawaan Laravel (nama file disamakan agar menimpa yang lama).
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->char('id_user', 6)->primary();                // USR001 (maks 999 pengguna)
            $table->char('branch_id', 5)->nullable()->index();   // BRN01; FK ditambahkan setelah tabel branches ada
            $table->string('name', 60);
            $table->string('email', 100)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 100);                     // hash bcrypt 60 / argon2 ~97 karakter
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->string('remember_token', 60)->nullable();    // token Laravel selalu 60 karakter
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 100)->primary();             // sama dengan users.email
            $table->string('token', 100);                        // hash token
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id', 40)->primary();                 // ID session Laravel = 40 karakter
            $table->char('user_id', 6)->nullable()->index();     // = users.id_user
            $table->string('ip_address', 45)->nullable();        // 45 = IPv6 terpanjang
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
