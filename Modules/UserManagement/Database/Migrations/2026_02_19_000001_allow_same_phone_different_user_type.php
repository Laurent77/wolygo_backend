<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AllowSamePhoneDifferentUserType extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the existing simple unique constraint on phone
            $table->dropUnique(['phone']);

            // Add a composite unique constraint: same phone allowed for different user_type
            // e.g. one 'customer' + one 'driver' with the same phone number
            $table->unique(['phone', 'user_type'], 'users_phone_user_type_unique');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_user_type_unique');
            $table->unique('phone');
        });
    }
}
