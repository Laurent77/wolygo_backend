<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSexDobCountryToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sex')->nullable()->after('last_name');
            $table->date('date_of_birth')->nullable()->after('sex');
            $table->string('country')->nullable()->after('date_of_birth');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sex', 'date_of_birth', 'country']);
        });
    }
}
