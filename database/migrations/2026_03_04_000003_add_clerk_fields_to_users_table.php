<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'clerk_user_id')) {
                $table->string('clerk_user_id')->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'avatar_url')) {
                $table->text('avatar_url')->nullable()->after('clerk_user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'avatar_url')) {
                $table->dropColumn('avatar_url');
            }

            if (Schema::hasColumn('users', 'clerk_user_id')) {
                $table->dropColumn('clerk_user_id');
            }
        });
    }
};
