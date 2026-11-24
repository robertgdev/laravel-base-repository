<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

/*
 * Every test runs against a Testbench application with a fresh in-memory
 * SQLite database. Tables are created per test via these helpers.
 */
uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

function makePostsTable(): void
{
    Schema::create('posts', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable()->unique();
        $table->string('status')->default('active');
        $table->boolean('active')->default(true);
        $table->unsignedInteger('views')->default(0);
        $table->timestamps();
    });
}

function makeArticlesTable(): void
{
    Schema::create('articles', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('status')->default('draft');
        $table->timestamps();
        $table->softDeletes();
    });
}

function makeArchivesTable(): void
{
    Schema::create('archives', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
        $table->softDeletes('removed_at');
    });
}
