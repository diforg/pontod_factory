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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('part_id');
            $table->unsignedBigInteger('plate_id');
            $table->timestamp('created_at')->nullable();

            $table->foreign('part_id', 'fk_items_part')
                ->references('id')
                ->on('parts');
            $table->foreign('plate_id', 'fk_items_plate')
                ->references('id')
                ->on('plates');

            $table->index('part_id', 'idx_items_part');
            $table->index('plate_id', 'idx_items_plate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
