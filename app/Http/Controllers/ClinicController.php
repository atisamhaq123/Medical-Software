<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\ClinicalNote;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClinicController extends Controller
{
    private function allow(string $permission): void
    {
        abort_unless(auth()->user()->permits($permission), 403);
    }

    private function clinic(): int
    {
        return auth()->user()->clinic_id;
    }

    private function patients()
    {
        $query = Patient::where('clinic_id', $this->clinic());
        if (in_array(auth()->user()->role, ['doctor', 'nurse'])) {
            $query->where(fn ($q) => $q->whereHas('appointments', fn ($q) => $q->where('provider_id', auth()->id()))->orWhereHas('careTeam', fn ($q) => $q->where('users.id', auth()->id())));
        }

        return $query;
    }

    private function audit(string $action, ?string $type = null, ?int $id = null): void
    {
        AuditEvent::create(['clinic_id' => $this->clinic(), 'user_id' => auth()->id(), 'action' => $action, 'subject_type' => $type, 'subject_id' => $id]);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:255']);
        $key = hash('sha256', strtolower($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in a minute.']);
        }
        if (! Auth::attempt($data) || ! auth()->user()?->clinic_id) {
            Auth::logout();
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The credentials do not match our records.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->audit('session.password_verified');

        return redirect()->route(config('medica.require_mfa') ? 'mfa' : 'dashboard');
    }

    public function logout(Request $request)
    {
        $this->audit('session.logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function dashboard()
    {
        $user = auth()->user();
        $appointments = collect();
        $patientCount = null;
        if ($user->permits('patients.read')) {
            $patientCount = $this->patients()->count();
            $appointments = Appointment::with(['patient', 'provider'])->where('clinic_id', $this->clinic())->whereIn('patient_id', $this->patients()->select('id'))->whereBetween('starts_at', [now('America/New_York')->startOfDay()->utc(), now('America/New_York')->endOfDay()->utc()])->orderBy('starts_at')->get();
        }
        $revenue = $user->permits('billing.read') ? DB::table('invoices')->where('clinic_id', $this->clinic())->where('status', 'Paid')->sum('amount_cents') : null;
        $this->audit('dashboard.viewed');

        return view('dashboard', compact('appointments', 'patientCount', 'revenue'));
    }

    public function patientIndex(Request $request)
    {
        $this->allow('patients.read');
        $search = mb_substr((string) $request->query('q', ''), 0, 80);
        $patients = $this->patients()->when($search, fn ($q) => $q->where(fn ($q) => $q->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')->orWhere('mrn', 'like', '%'.$search.'%')))->orderBy('last_name')->paginate(12)->withQueryString();
        $this->audit('patients.list');

        return view('patients.index', compact('patients', 'search'));
    }

    public function patientCreate()
    {
        $this->allow('patients.write');

        return view('patients.create');
    }

    public function patientStore(Request $request)
    {
        $this->allow('patients.write');
        $data = $request->validate(['first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80', 'date_of_birth' => 'required|date|before_or_equal:today', 'sex' => ['required', Rule::in(['Female', 'Male', 'Other', 'Unknown'])], 'email' => 'nullable|email|max:255', 'phone' => 'required|string|max:30', 'insurance' => 'nullable|string|max:120']);
        $patient = DB::transaction(function () use ($data) {
            $patient = Patient::create($data + ['clinic_id' => $this->clinic(), 'mrn' => 'MR-'.strtoupper(bin2hex(random_bytes(4)))]);
            $this->audit('patient.created', 'patient', $patient->id);

            return $patient;
        });

        return redirect()->route('patients.show', $patient)->with('success', 'Patient registered successfully.');
    }

    public function patientShow(int $patient)
    {
        $this->allow('patients.read');
        $patient = $this->patients()->findOrFail($patient);
        $appointments = $patient->appointments()->with('provider')->orderByDesc('starts_at')->get();
        $notes = auth()->user()->permits('clinical.read') ? $patient->notes()->with('author')->latest()->get() : collect();
        $this->audit('patient.viewed', 'patient', $patient->id);

        return view('patients.show', compact('patient', 'appointments', 'notes'));
    }

    public function noteStore(Request $request, int $patient)
    {
        $this->allow('clinical.write');
        $patient = $this->patients()->findOrFail($patient);
        $data = $request->validate(['body' => 'required|string|min:10|max:10000']);
        DB::transaction(function () use ($data, $patient) {
            $note = ClinicalNote::create($data + ['clinic_id' => $this->clinic(), 'patient_id' => $patient->id, 'author_id' => auth()->id(), 'signed_at' => now()]);
            $this->audit('note.signed', 'clinical_note', $note->id);
        });

        return back()->with('success', 'Clinical note signed and saved. Signed notes cannot be edited.');
    }

    public function appointmentIndex(Request $request)
    {
        $this->allow('appointments.write');
        $date = $request->validate(['date' => 'nullable|date_format:Y-m-d'])['date'] ?? now('America/New_York')->toDateString();
        $start = Carbon::parse($date, 'America/New_York')->startOfDay()->utc();
        $end = Carbon::parse($date, 'America/New_York')->endOfDay()->utc();
        $appointments = Appointment::with(['patient', 'provider'])->where('clinic_id', $this->clinic())->whereIn('patient_id', $this->patients()->select('id'))->whereBetween('starts_at', [$start, $end])->orderBy('starts_at')->get();
        $patients = $this->patients()->orderBy('last_name')->get();
        $providers = User::where('clinic_id', $this->clinic())->where('role', 'doctor')->get();
        $this->audit('appointments.viewed');

        return view('appointments', compact('appointments', 'patients', 'providers', 'date'));
    }

    public function appointmentStore(Request $request)
    {
        $this->allow('appointments.write');
        $data = $request->validate(['patient_id' => 'required|integer', 'provider_id' => 'required|integer', 'starts_at' => 'required|date', 'duration' => ['required', Rule::in([15, 30, 45, 60])], 'type' => ['required', Rule::in(['Annual wellness', 'Follow-up', 'New patient', 'Consultation'])]]);
        $this->patients()->findOrFail($data['patient_id']);
        User::where('clinic_id', $this->clinic())->where('role', 'doctor')->findOrFail($data['provider_id']);
        $data['starts_at'] = Carbon::parse($data['starts_at'], 'America/New_York')->utc();
        if ($data['starts_at']->isPast()) {
            throw ValidationException::withMessages(['starts_at' => 'Choose a future appointment time.']);
        }
        DB::transaction(function () use ($data) {
            DB::table('clinics')->where('id', $this->clinic())->lockForUpdate()->first();
            $end = $data['starts_at']->copy()->addMinutes((int) $data['duration']);
            $overlap = Appointment::where('clinic_id', $this->clinic())->where(fn ($query) => $query->where('provider_id', $data['provider_id'])->orWhere('patient_id', $data['patient_id']))->whereNotIn('status', ['Cancelled', 'Completed'])->where('starts_at', '<', $end)->where('starts_at', '>', $data['starts_at']->copy()->subMinutes(60))->get()->contains(fn ($a) => $a->starts_at->copy()->addMinutes($a->duration)->gt($data['starts_at']));
            if ($overlap) {
                throw ValidationException::withMessages(['starts_at' => 'The provider or patient already has an appointment during that time.']);
            }
            $appointment = Appointment::create($data + ['clinic_id' => $this->clinic()]);
            $this->audit('appointment.created', 'appointment', $appointment->id);
        });

        return back()->with('success', 'Appointment booked.');
    }

    public function appointmentStatus(Request $request, int $appointment)
    {
        $this->allow('appointments.write');
        $data = $request->validate(['status' => ['required', Rule::in(['Checked in', 'In progress', 'Completed', 'Cancelled'])]]);
        DB::transaction(function () use ($appointment, $data) {
            $appointment = Appointment::where('clinic_id', $this->clinic())->whereIn('patient_id', $this->patients()->select('id'))->lockForUpdate()->findOrFail($appointment);
            $transitions = ['Scheduled' => ['Checked in', 'Cancelled'], 'Checked in' => ['In progress', 'Cancelled'], 'In progress' => ['Completed']];
            abort_unless(in_array($data['status'], $transitions[$appointment->status] ?? []), 422);
            $appointment->update($data);
            $this->audit('appointment.status_changed', 'appointment', $appointment->id);
        });

        return back()->with('success', 'Appointment updated.');
    }

    public function billing()
    {
        $this->allow('billing.read');
        $invoices = DB::table('invoices')->join('patients', 'patients.id', '=', 'invoices.patient_id')->where('invoices.clinic_id', $this->clinic())->select('invoices.*', 'patients.first_name', 'patients.last_name')->orderByDesc('invoices.id')->get();
        $this->audit('billing.viewed');

        return view('billing', compact('invoices'));
    }

    public function team()
    {
        $this->allow('team.read');
        $users = User::where('clinic_id', $this->clinic())->get();

        return view('team', compact('users'));
    }

    public function auditIndex()
    {
        $this->allow('audit.read');
        $events = AuditEvent::with('actor')->where('clinic_id', $this->clinic())->latest('id')->paginate(20);

        return view('audit', compact('events'));
    }
}
