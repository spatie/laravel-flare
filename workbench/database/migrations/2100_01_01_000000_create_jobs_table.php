<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Laravel 11+ (and testbench 9+) ship the jobs table by default. Laravel 10 does
// not, so create it when missing to keep the database queue tests running.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jobs')) {
            return;
        }

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }
};
