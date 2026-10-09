<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\ClinicalNote;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo records may only be seeded in local or testing environments.');
        }
        $clinic = DB::table('clinics')->insertGetId(['name' => 'Westbrook Medical', 'timezone' => 'America/New_York', 'created_at' => now(), 'updated_at' => now()]);
        $staff = [];
        foreach ([['Alex Morgan', 'owner'], ['Dr. Amelia Chen', 'doctor'], ['Dr. James Wilson', 'doctor'], ['Sophie Reed', 'nurse'], ['Olivia Parker', 'receptionist'], ['Daniel Brooks', 'accountant'], ['Grace Miller', 'auditor']] as $index => [$name, $role]) {
            $email = match ($index) {
                1 => 'amelia', 2 => 'james', default => $role
            }.'@medica.test';
            $staff[] = User::forceCreate(['name' => $name, 'email' => $email, 'password' => 'DemoCare!2026', 'clinic_id' => $clinic, 'role' => $role, 'email_verified_at' => now()]);
        }
        $names = [['Emma', 'Thompson', 'Female'], ['Liam', 'Anderson', 'Male'], ['Olivia', 'Martinez', 'Female'], ['Noah', 'Williams', 'Male'], ['Ava', 'Robinson', 'Female'], ['Ethan', 'Davis', 'Male'], ['Sophia', 'Clark', 'Female'], ['Mason', 'Lewis', 'Male'], ['Isabella', 'Walker', 'Female'], ['Lucas', 'Hall', 'Male'], ['Mia', 'Young', 'Female'], ['Benjamin', 'Allen', 'Male'], ['Charlotte', 'King', 'Female'], ['Henry', 'Wright', 'Male'], ['Amelia', 'Scott', 'Female'], ['Jack', 'Green', 'Male']];
        foreach ($names as $i => [$first, $last, $sex]) {
            $patient = Patient::create(['clinic_id' => $clinic, 'mrn' => 'MR-'.str_pad((string) ($i + 1001), 6, '0', STR_PAD_LEFT), 'first_name' => $first, 'last_name' => $last, 'date_of_birth' => sprintf('%d-%02d-%02d', 1962 + $i * 3, $i % 12 + 1, $i % 25 + 1), 'sex' => $sex, 'email' => strtolower($first.'.'.$last).'@example.com', 'phone' => sprintf('(202) 555-%04d', 100 + $i), 'insurance' => ['Aetna', 'Blue Cross Blue Shield', 'UnitedHealthcare', 'Cigna'][$i % 4], 'allergies' => $i % 3 === 0 ? 'Penicillin — rash (demonstration)' : 'No known drug allergies (demonstration)', 'conditions' => $i % 2 ? 'Hypertension — routine monitoring (demonstration)' : 'No active chronic conditions documented (demonstration)']);
            $doctor = $staff[$i % 2 + 1];
            $patient->careTeam()->attach([$doctor->id, $staff[3]->id]);
            $start = now('America/New_York')->startOfDay()->addHours(9)->addMinutes($i * 30);
            Appointment::create(['clinic_id' => $clinic, 'patient_id' => $patient->id, 'provider_id' => $doctor->id, 'starts_at' => $start->utc(), 'duration' => 30, 'type' => ['Annual wellness', 'Follow-up', 'New patient', 'Consultation'][$i % 4], 'status' => ['Completed', 'Completed', 'In progress', 'Checked in', 'Scheduled', 'Scheduled'][$i % 6]]);
            ClinicalNote::create(['clinic_id' => $clinic, 'patient_id' => $patient->id, 'author_id' => $doctor->id, 'body' => "SYNTHETIC DEMONSTRATION RECORD\n\nSubjective: Patient presents for a routine wellness follow-up.\nObjective: Demonstration encounter; no real clinical observations.\nAssessment: Routine preventive care review.\nPlan: Discuss preventive screening and schedule follow-up. This is sample content only.", 'signed_at' => now()->subDays(14)]);
            DB::table('invoices')->insert(['clinic_id' => $clinic, 'patient_id' => $patient->id, 'number' => 'INV-'.(2026001 + $i), 'service' => ['Office consultation', 'Annual wellness visit', 'Follow-up consultation'][$i % 3], 'amount_cents' => [15000, 22500, 9500][$i % 3], 'status' => ['Paid', 'Paid', 'Pending', 'Overdue'][$i % 4], 'due_date' => now()->addDays($i - 4)->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        }
        AuditEvent::create(['clinic_id' => $clinic, 'user_id' => $staff[0]->id, 'action' => 'workspace.demo_initialized']);
    }
}
