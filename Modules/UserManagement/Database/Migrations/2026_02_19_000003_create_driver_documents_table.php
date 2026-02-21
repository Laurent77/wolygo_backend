<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDriverDocumentsTable extends Migration
{
    public function up()
    {
        Schema::create('driver_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('driver_id');
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');

            $table->enum('document_type', [
                'national_id',
                'passport',
                'driving_license',
                'work_permit',
                'vehicle_registration',
                'vehicle_insurance',
                'technical_inspection',
                'criminal_record',
            ]);

            $table->string('document_number', 100)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();

            // Files
            $table->string('front_image_path', 500)->nullable();  // recto
            $table->string('back_image_path', 500)->nullable();   // verso
            $table->string('pdf_path', 500)->nullable();          // PDF

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'expired',
                'expiring_soon',
            ])->default('pending');

            $table->text('rejection_reason')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['driver_id', 'document_type']);
            $table->index('expires_at');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('driver_documents');
    }
}
