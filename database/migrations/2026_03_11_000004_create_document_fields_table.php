<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_file_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('recipient_id')->constrained('document_recipients')->cascadeOnDelete();
            $table->string('type');
            $table->string('label')->nullable();
            $table->string('placeholder')->nullable();
            $table->unsignedInteger('page_number');
            $table->decimal('position_x', 8, 4);
            $table->decimal('position_y', 8, 4);
            $table->decimal('width', 8, 4);
            $table->decimal('height', 8, 4);
            $table->boolean('is_required')->default(true);
            $table->json('validation_rules')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['document_id', 'page_number']);
            $table->index('recipient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_fields');
    }
};
