<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('timezone')->default('America/New_York');
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('clinic_id')->nullable()->constrained();
            $table->string('role')->default('receptionist');
            $table->text('mfa_secret')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->bigInteger('mfa_last_step')->nullable();
        });
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained();
            $table->string('mrn');
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->string('sex');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('insurance')->nullable();
            $table->text('allergies')->nullable();
            $table->text('conditions')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
            $table->unique(['clinic_id', 'mrn']);
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('provider_id')->constrained('users');
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('duration')->default(30);
            $table->string('type');
            $table->string('status')->default('Scheduled');
            $table->timestamps();
            $table->index(['clinic_id', 'starts_at']);
        });
        Schema::create('patient_user', function (Blueprint $table) {
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->primary(['patient_id', 'user_id']);
        });
        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->timestamp('signed_at');
            $table->timestamps();
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained();
            $table->foreignId('patient_id')->constrained();
            $table->string('number')->unique();
            $table->string('service');
            $table->unsignedInteger('amount_cents');
            $table->string('status');
            $table->date('due_date');
            $table->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['clinic_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'invoices', 'clinical_notes', 'patient_user', 'appointments', 'patients'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinic_id');
            $table->dropColumn(['role', 'mfa_secret', 'mfa_confirmed_at', 'mfa_last_step']);
        });
        Schema::dropIfExists('clinics');
    }
};
