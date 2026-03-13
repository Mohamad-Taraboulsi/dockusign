<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('document_field_id')->constrained('document_fields')->cascadeOnDelete();
            $table->foreignUuid('recipient_id')->constrained('document_recipients')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();

            $table->unique(['document_field_id', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_field_values');
    }
};
