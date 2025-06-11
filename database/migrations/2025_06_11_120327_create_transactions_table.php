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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Foreign key to users table
            $table->unsignedBigInteger('user_id');

            // Transaction fields
            $table->date('date');
            $table->decimal('amount', 15, 2); // 15 digits total, 2 after decimal
            $table->string('type'); // e.g. credit, debit, etc.
            $table->string('payee'); // or beneficiary
            $table->text('details')->nullable(); // optional
            $table->decimal('balance', 15, 2);

            $table->timestamps();

            // Set up foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
