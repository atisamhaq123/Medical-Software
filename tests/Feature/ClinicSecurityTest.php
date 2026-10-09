<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuthenticatorQrCode;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClinicSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['medica.require_mfa' => false]);
    }

    private function staff(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    public function test_all_main_screens_render_for_owner(): void
    {
        $this->actingAs($this->staff('owner'));
        foreach (['/', '/patients', '/patients/create', '/patients/1', '/appointments', '/billing', '/team', '/audit'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_reception_cannot_read_or_write_clinical_notes_or_finances(): void
    {
        $this->actingAs($this->staff('receptionist'));
        $this->get('/patients/1')->assertOk()->assertDontSee('SYNTHETIC DEMONSTRATION RECORD')->assertDontSee('Penicillin');
        $this->get('/billing')->assertForbidden();
        $this->get('/audit')->assertForbidden();
        $this->get('/team')->assertForbidden();
        $this->post('/patients/1/notes', ['body' => 'An unauthorized clinical entry.'])->assertForbidden();
    }

    public function test_doctor_only_sees_assigned_patients(): void
    {
        $doctor = $this->staff('doctor');
        $this->actingAs($doctor);
        $this->get('/patients/1')->assertOk();
        $this->get('/patients/2')->assertNotFound();
        $this->post('/patients/2/notes', ['body' => 'Unauthorized clinical entry.'])->assertNotFound();
    }

    public function test_nurse_care_team_assignment_allows_patient_read_but_not_note_signing(): void
    {
        $this->actingAs($this->staff('nurse'))->get('/patients/1')->assertOk()->assertSee('Clinical overview');
        $this->post('/patients/1/notes', ['body' => 'Cannot sign doctor note.'])->assertForbidden();
    }

    public function test_another_clinic_patient_cannot_be_viewed_or_used_in_booking(): void
    {
        $clinic = DB::table('clinics')->insertGetId(['name' => 'Other clinic', 'timezone' => 'America/New_York']);
        $other = Patient::create(['clinic_id' => $clinic, 'mrn' => 'OTHER-1', 'first_name' => 'Hidden', 'last_name' => 'Patient', 'date_of_birth' => '1990-01-01', 'sex' => 'Unknown', 'phone' => '555-0100']);
        $this->actingAs($this->staff('owner'))->get('/patients/'.$other->id)->assertNotFound();
        $this->get('/patients')->assertDontSee('Hidden');
        $this->post('/appointments', ['patient_id' => $other->id, 'provider_id' => $this->staff('doctor')->id, 'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'duration' => 30, 'type' => 'Follow-up'])->assertNotFound();
    }

    public function test_patient_registration_persists_and_creates_audit_event(): void
    {
        $this->actingAs($this->staff('receptionist'))->post('/patients', ['first_name' => 'Test', 'last_name' => 'Registration', 'date_of_birth' => '1992-05-20', 'sex' => 'Female', 'phone' => '(202) 555-0199'])->assertRedirect();
        $patient = Patient::where('last_name', 'Registration')->firstOrFail();
        $this->assertEquals($this->staff('owner')->clinic_id, $patient->clinic_id);
        $this->assertDatabaseHas('audit_events', ['action' => 'patient.created', 'subject_id' => $patient->id]);
    }

    public function test_signed_notes_are_encrypted_and_audited(): void
    {
        $body = 'Sensitive assessment for encryption verification.';
        $this->actingAs($this->staff('doctor'))->post('/patients/1/notes', ['body' => $body])->assertRedirect();
        $raw = DB::table('clinical_notes')->orderByDesc('id')->first();
        $this->assertStringNotContainsString($body, $raw->body);
        $this->assertDatabaseHas('audit_events', ['action' => 'note.signed', 'subject_id' => $raw->id]);
    }

    public function test_provider_overlap_is_rejected_and_valid_booking_is_saved(): void
    {
        $this->actingAs($this->staff('owner'));
        $data = ['patient_id' => 1, 'provider_id' => $this->staff('doctor')->id, 'starts_at' => now('America/New_York')->addDays(2)->setTime(10, 0)->format('Y-m-d\TH:i'), 'duration' => 30, 'type' => 'Follow-up'];
        $this->post('/appointments', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/appointments', array_replace($data, ['starts_at' => now('America/New_York')->addDays(2)->setTime(10, 15)->format('Y-m-d\TH:i')]))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseHas('audit_events', ['action' => 'appointment.created']);
    }

    public function test_completed_appointment_cannot_be_reopened(): void
    {
        $appointment = Appointment::where('status', 'Completed')->firstOrFail();
        $this->actingAs($this->staff('owner'))->patch('/appointments/'.$appointment->id, ['status' => 'Checked in'])->assertStatus(422);
    }

    public function test_mfa_blocks_access_until_verified_and_secret_is_encrypted(): void
    {
        config(['medica.require_mfa' => true]);
        $user = $this->staff('owner');
        $totp = new Totp;
        $this->actingAs($user)->get('/patients')->assertRedirect('/mfa');
        $this->get('/mfa')->assertOk();
        $secret = session('mfa_setup_secret');
        $this->post('/mfa', ['code' => $totp->code($secret, intdiv(time(), 30))])->assertRedirect('/');
        $this->get('/patients')->assertOk();
        $this->assertNotEquals($secret, DB::table('users')->where('id', $user->id)->value('mfa_secret'));
        $this->assertNotNull($user->fresh()->mfa_confirmed_at);
    }

    public function test_enrollment_displays_a_local_qr_code_for_the_current_session_secret(): void
    {
        $user = $this->staff('doctor');
        $this->actingAs($user);
        $response = $this->get('/mfa')->assertOk()->assertSee('class="authenticator-qr"', false)->assertHeader('Cache-Control', 'no-store, private');
        $secret = session('mfa_setup_secret');
        $qrCode = new AuthenticatorQrCode;
        $response->assertViewHas('qrCodeDataUri', $qrCode->dataUri($user->email, $secret));
        $this->get('/mfa')->assertViewHas('secret', $secret);

        $uri = $qrCode->provisioningUri($user->email, $secret);
        $this->assertSame('otpauth://totp/Medica:'.rawurlencode($user->email), explode('?', $uri)[0]);
        parse_str(parse_url($uri, PHP_URL_QUERY), $parameters);
        $this->assertSame(['secret' => $secret, 'issuer' => 'Medica', 'algorithm' => 'SHA1', 'digits' => '6', 'period' => '30'], $parameters);
        $svg = base64_decode(substr($response->viewData('qrCodeDataUri'), strlen('data:image/svg+xml;base64,')), true);
        $this->assertNotFalse($svg);
        $xml = simplexml_load_string($svg);
        $this->assertSame('svg', $xml->getName());
        $this->assertSame('256', (string) $xml['width']);
    }

    public function test_confirmed_authenticator_never_displays_the_setup_secret_or_qr_code(): void
    {
        $user = $this->staff('doctor');
        $secret = (new Totp)->generateSecret();
        $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now()])->save();
        $this->actingAs($user)->get('/mfa')->assertOk()->assertViewHas('qrCodeDataUri', null)->assertViewHas('secret', null)->assertDontSee('class="authenticator-qr"', false)->assertDontSee($secret);
    }

    public function test_login_rate_limit_and_session_logout(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'owner@medica.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'owner@medica.test', 'password' => 'DemoCare!2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($this->staff('owner'))->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_sensitive_pages_have_security_headers_and_no_store(): void
    {
        $this->actingAs($this->staff('owner'))->get('/patients/1')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_patient_cannot_be_double_booked_with_a_different_provider(): void
    {
        $this->actingAs($this->staff('owner'));
        $providers = User::where('role', 'doctor')->get();
        $data = ['patient_id' => 1, 'provider_id' => $providers[0]->id, 'starts_at' => now('America/New_York')->addDays(3)->setTime(10, 0)->format('Y-m-d\TH:i'), 'duration' => 30, 'type' => 'Follow-up'];
        $this->post('/appointments', $data)->assertSessionHasNoErrors();
        $this->post('/appointments', array_replace($data, ['provider_id' => $providers[1]->id]))->assertSessionHasErrors('starts_at');
    }

    public function test_accountant_cannot_open_patient_directory(): void
    {
        $this->actingAs($this->staff('accountant'))->get('/patients')->assertForbidden();
        $this->get('/billing')->assertOk();
        $this->get('/')->assertOk()->assertDontSee('Emma Thompson');
    }

    public function test_accounts_without_clinic_membership_cannot_authenticate(): void
    {
        User::forceCreate(['name' => 'Unassigned', 'email' => 'unassigned@example.com', 'password' => 'ExamplePassword!2026']);
        $this->post('/login', ['email' => 'unassigned@example.com', 'password' => 'ExamplePassword!2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
