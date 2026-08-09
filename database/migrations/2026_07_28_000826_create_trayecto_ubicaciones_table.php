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
        Schema::create('trayecto_ubicaciones', function (Blueprint $table) {
            $table->id();
            // trayectos.id es int(11) normal (tabla creada fuera de Laravel),
            // no bigint unsigned, por eso no se usa foreignId()->constrained().
            $table->integer('trayecto_id');
            $table->foreign('trayecto_id')->references('id')->on('trayectos')->cascadeOnDelete();
            $table->decimal('latitud', 10, 7);
            $table->decimal('longitud', 10, 7);
            $table->timestamp('registrado_en')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trayecto_ubicaciones');
    }
};
