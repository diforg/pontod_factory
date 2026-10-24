<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plates', function (Blueprint $table) {
            $table->id();
            $table->string('filament_code', 64);
            $table->enum('status', ['CREATED', 'QUEUED', 'PRINTING', 'COMPLETED'])->default('CREATED');
            $table->integer('sort_order');
            $table->decimal('consumed_filament_g', 10, 2)->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('filament_code', 'fk_plates_filament')
                ->references('code')
                ->on('filaments');

            $table->index(['status', 'sort_order'], 'idx_plates_status_sort');
            $table->index('filament_code', 'idx_plates_filament');
        });

        DB::statement('ALTER TABLE plates ADD CONSTRAINT chk_plates_consumed_nonnegative CHECK (consumed_filament_g IS NULL OR consumed_filament_g >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('plates')) {
            DB::statement('ALTER TABLE plates DROP CHECK chk_plates_consumed_nonnegative');
        }

        Schema::dropIfExists('plates');
    }
};
