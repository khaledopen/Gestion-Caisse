<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_admin')->index();
        });

        // On an existing installation, the first account becomes the initial administrator.
        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        if ($firstUserId) DB::table('users')->where('id', $firstUserId)->update(['is_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_admin', 'is_active']));
    }
};
