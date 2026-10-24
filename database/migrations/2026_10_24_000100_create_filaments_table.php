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
        Schema::create('filaments', function (Blueprint $table) {
            $table->string('code', 64)->primary();
            $table->string('brand', 64);
            $table->string('material', 32);
            $table->string('color', 32);
            $table->decimal('weight', 10, 2);
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->decimal('balance', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['material', 'brand', 'color'], 'idx_filaments_material_brand_color');
            $table->index('is_active', 'idx_filaments_is_active');
        });

        DB::statement('ALTER TABLE filaments ADD CONSTRAINT chk_filaments_balance_positive CHECK (balance >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('filaments')) {
            DB::statement('ALTER TABLE filaments DROP CHECK chk_filaments_balance_positive');
        }

        Schema::dropIfExists('filaments');
    }
};
