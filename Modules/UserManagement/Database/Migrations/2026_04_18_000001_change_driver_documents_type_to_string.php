<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeDriverDocumentsTypeToString extends Migration
{
    public function up()
    {
        Schema::table('driver_documents', function (Blueprint $table) {
            $table->string('document_type', 100)->change();
        });
    }

    public function down()
    {
        Schema::table('driver_documents', function (Blueprint $table) {
            $table->enum('document_type', [
                'national_id', 'passport', 'driving_license', 'work_permit',
                'vehicle_registration', 'vehicle_insurance', 'technical_inspection',
                'criminal_record',
            ])->change();
        });
    }
}
