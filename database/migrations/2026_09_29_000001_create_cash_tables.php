<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('email')->unique(); $t->string('password'); $t->rememberToken(); $t->timestamps(); });
        Schema::create('cash_accounts', function (Blueprint $t) { $t->id(); $t->bigInteger('balance_minor')->default(0); $t->timestamps(); });
        DB::table('cash_accounts')->insert(['id' => 1, 'balance_minor' => 0, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('transactions', function (Blueprint $t) {
            $t->id(); $t->uuid('request_key')->unique(); $t->foreignId('user_id')->constrained();
            $t->string('type', 30)->index(); $t->unsignedBigInteger('amount_minor'); $t->string('payment_method', 30);
            $t->string('description', 255); $t->text('justification')->nullable(); $t->date('occurred_on')->index();
            $t->timestamp('cancelled_at')->nullable(); $t->foreignId('cancelled_by')->nullable()->constrained('users');
            $t->string('cancellation_reason')->nullable(); $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('transactions'); Schema::dropIfExists('cash_accounts'); Schema::dropIfExists('users'); }
};
