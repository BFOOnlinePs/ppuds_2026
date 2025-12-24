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
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();

            $table->string('recipient'); // المرسل إليه
            $table->text('subject')->nullable();
            $table->string('mailable_class'); // اسم الكلاس للفلترة لاحقاً
            $table->nullableMorphs('emailable');
            $table->integer('status')->index();
            $table->text('error_message')->nullable(); // سبب الفشل
            $table->integer('tries')->default(0); // عدد المحاولات

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
