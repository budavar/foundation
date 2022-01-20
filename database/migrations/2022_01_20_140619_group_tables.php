<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class GroupTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedBigInteger('owner_id');
            $table->text('description');
            $table->char('visibility', 15);
            $table->char('allow_to_add_events', 10);
            $table->char('request_to_join_rule', 10);
            $table->string('photo')->nullable();
            $table->string('photo_history')->nullable();
            $table->json('rules')->nullable();
            $table->char('status', 15);
            $table->timestamps();
        });

        Schema::table('groups', function($table) {
            $table->foreign('owner_id')->references('id')->on('users');
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('group_id');
            $table->unsignedBigInteger('user_id');
            $table->char('role', 15);
            $table->char('status', 15);
            $table->timestamps();
        });

        Schema::table('group_members', function($table) {
            $table->foreign('group_id')->references('id')->on('groups');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
}
