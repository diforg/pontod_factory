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
        Schema::create('filament_movements', function (Blueprint $table) {
            $table->id();
            $table->string('filament_code', 64);
            $table->enum('type', ['ENTRY', 'CONSUMPTION', 'REVERSAL', 'RECONCILIATION_ADJUSTMENT']);
            $table->decimal('quantity_g', 10, 2);
            $table->decimal('previous_balance_g', 10, 2);
            $table->decimal('new_balance_g', 10, 2);
            $table->enum('source_type', ['ENTRY', 'PLATE', 'RECONCILIATION']);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->dateTime('occurred_at');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('filament_code', 'fk_filament_movements_filament')
                ->references('code')
                ->on('filaments');
            $table->foreign('user_id', 'fk_filament_movements_user')
                ->references('id')
                ->on('users');

            $table->index(['filament_code', 'occurred_at'], 'idx_filament_movements_filament_time');
            $table->index(['source_type', 'source_id'], 'idx_filament_movements_source');
            $table->index('type', 'idx_filament_movements_type');
        });

        DB::statement('ALTER TABLE filament_movements ADD CONSTRAINT chk_filament_movements_quantity_nonzero CHECK (quantity_g <> 0)');
        DB::statement('ALTER TABLE filament_movements ADD CONSTRAINT chk_filament_movements_balance_invariant CHECK (new_balance_g = previous_balance_g + quantity_g)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('filament_movements')) {
            DB::statement('ALTER TABLE filament_movements DROP CHECK chk_filament_movements_quantity_nonzero');
            DB::statement('ALTER TABLE filament_movements DROP CHECK chk_filament_movements_balance_invariant');
        }

        Schema::dropIfExists('filament_movements');
    }
};
