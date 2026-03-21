<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('language', 10);
            $table->string('key');
            $table->text('current_value');
            $table->text('proposed_value');
            $table->text('context')->nullable();
            $table->uuid('proposed_by');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->integer('votes_for')->default(0);
            $table->integer('votes_against')->default(0);
            $table->uuid('reviewer_id')->nullable();
            $table->text('review_comment')->nullable();
            $table->timestamps();

            $table->foreign('proposed_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewer_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['language', 'status']);
            $table->index(['key', 'language']);
        });

        Schema::create('translation_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('proposal_id');
            $table->uuid('user_id');
            $table->enum('vote', ['up', 'down']);
            $table->timestamps();

            $table->foreign('proposal_id')->references('id')->on('translation_proposals')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['proposal_id', 'user_id']);
        });

        Schema::create('translation_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('language', 10);
            $table->string('key');
            $table->text('value');
            $table->timestamps();

            $table->unique(['language', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_votes');
        Schema::dropIfExists('translation_overrides');
        Schema::dropIfExists('translation_proposals');
    }
};
