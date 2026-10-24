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
        Schema::create('part_product', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('part_id');
            $table->integer('quantity')->default(1);

            $table->primary(['product_id', 'part_id']);

            $table->foreign('product_id', 'fk_part_product_product')
                ->references('id')
                ->on('products');
            $table->foreign('part_id', 'fk_part_product_part')
                ->references('id')
                ->on('parts');

            $table->index('part_id', 'idx_part_product_part_id');
        });

        DB::statement('ALTER TABLE part_product ADD CONSTRAINT chk_part_product_quantity_positive CHECK (quantity > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('part_product')) {
            DB::statement('ALTER TABLE part_product DROP CHECK chk_part_product_quantity_positive');
        }

        Schema::dropIfExists('part_product');
    }
};
