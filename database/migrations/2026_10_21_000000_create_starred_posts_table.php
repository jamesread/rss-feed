<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('starred_posts', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048)->unique();
            $table->string('title', 1000);
            $table->text('description');
            $table->integer('published_at')->default(0);
            $table->string('thumbnail')->default('');
            $table->string('source', 250)->default('');
            $table->json('feed');
            $table->json('feeds');
            $table->integer('starred_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('starred_posts');
    }
};
