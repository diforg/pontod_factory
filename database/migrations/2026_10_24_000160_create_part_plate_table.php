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
        Schema::create('part_plate', function (Blueprint $table) {
            $table->unsignedBigInteger('plate_id');
            $table->unsignedBigInteger('part_id');
            $table->integer('quantity');
            $table->decimal('consumed_filament_g', 10, 2)->nullable();

            $table->primary(['plate_id', 'part_id']);

            $table->foreign('plate_id', 'fk_part_plate_plate')
                ->references('id')
                ->on('plates');
            $table->foreign('part_id', 'fk_part_plate_part')
                ->references('id')
                ->on('parts');

            $table->index('part_id', 'idx_part_plate_part_id');
        });

        DB::statement('ALTER TABLE part_plate ADD CONSTRAINT chk_part_plate_quantity_positive CHECK (quantity > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('part_plate')) {
            DB::statement('ALTER TABLE part_plate DROP CHECK chk_part_plate_quantity_positive');
        }

        Schema::dropIfExists('part_plate');
    }
};
