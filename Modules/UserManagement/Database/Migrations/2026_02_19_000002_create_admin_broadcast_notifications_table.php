<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdminBroadcastNotificationsTable extends Migration
{
    public function up()
    {
        Schema::create('admin_broadcast_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->text('message');
            $table->string('image_path', 500)->nullable();
            $table->enum('target_type', ['all_customers', 'all_drivers', 'all_users', 'specific_user']);
            $table->uuid('target_user_id')->nullable(); // for specific_user
            $table->json('channels')->default('["push"]'); // ['push', 'in_app']
            $table->enum('status', ['sent', 'failed'])->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->uuid('sent_by')->nullable();  // admin user id
            $table->integer('total_recipients')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_broadcast_notifications');
    }
}
