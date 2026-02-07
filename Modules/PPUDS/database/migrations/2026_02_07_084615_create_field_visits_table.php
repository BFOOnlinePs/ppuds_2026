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
        Schema::create(config('ppuds.table_prefix') . 'field_visits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_company_id')
                ->index()
                ->constrained(config('ppuds.table_prefix') . 'students_companies')
                ->cascadeOnDelete();
            
            $table->foreignId('supervisor_id')
                ->index()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('visiting_place')->nullable();
            
            $table->date('visit_date');
            $table->time('visit_time');
            
            // Duration in minutes
            $table->integer('visit_duration')->comment('Duration in minutes');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ppuds.table_prefix') . 'field_visits');
    }
};
