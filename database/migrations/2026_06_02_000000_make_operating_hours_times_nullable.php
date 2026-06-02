<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dias fechados não têm horário de abertura/fechamento. O componente de horários
     * grava null nesses campos quando is_closed = true, mas as colunas foram criadas
     * como NOT NULL — o que viola a constraint (quebra em MySQL/strict). Tornamos as
     * colunas nullable para refletir a realidade do domínio.
     */
    public function up(): void
    {
        Schema::table('operating_hours', function (Blueprint $table) {
            $table->time('opens_at')->nullable()->change();
            $table->time('closes_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('operating_hours', function (Blueprint $table) {
            $table->time('opens_at')->nullable(false)->change();
            $table->time('closes_at')->nullable(false)->change();
        });
    }
};
