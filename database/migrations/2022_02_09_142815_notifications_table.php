<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class NotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('visibility');
            $table->boolean('publish_in_newsfeed')->default(true);
            $table->unsignedBigInteger('by_user_id');
            $table->uuidMorphs('primary_entity');
            $table->nullableUuidMorphs('scoping_entity');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->nullable();
            $table->uuid('activity_id')->nullable();
            $table->unsignedBigInteger('to_user_id');
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->string('deeplink_url')->nullable();
            $table->boolean('read');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('notifications');
    }
}
