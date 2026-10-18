<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_admin')->default(false);
        });
        Schema::table('candidates', function (Blueprint $t) {
            $t->string('name')->default('');
            $t->string('group')->default('');
            $t->string('photo')->nullable();
            $t->text('program')->nullable();
            $t->boolean('active')->default(true);
            $t->unsignedBigInteger('votes')->default(0);
            $t->unsignedBigInteger('adjustment')->default(0);
        });
        Schema::create('elections', function (Blueprint $t) {
            $t->id();
            $t->string('status')->default('draft');
            $t->timestamps();
        });
        DB::table('elections')->insert(['id' => 1, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('ballots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('election_id')->constrained();
            $t->foreignId('candidate_id')->constrained();
            $t->string('device_hash', 64);
            $t->timestamp('created_at');
            $t->unique(['election_id', 'device_hash']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('action');
            $t->json('details');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('ballots');
        Schema::dropIfExists('elections');
        Schema::table('candidates', fn (Blueprint $t) => $t->dropColumn(['name', 'group', 'photo', 'program', 'active', 'votes', 'adjustment']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
