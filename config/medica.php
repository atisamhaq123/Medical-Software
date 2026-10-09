<?php

return [
    'require_mfa' => env('MEDICA_REQUIRE_MFA', true),
    'roles' => [
        'owner' => ['patients.read', 'patients.write', 'clinical.read', 'clinical.write', 'appointments.write', 'billing.read', 'team.read', 'audit.read'],
        'doctor' => ['patients.read', 'clinical.read', 'clinical.write', 'appointments.write'],
        'nurse' => ['patients.read', 'clinical.read', 'appointments.write'],
        'receptionist' => ['patients.read', 'patients.write', 'appointments.write'],
        'accountant' => ['billing.read'],
        'auditor' => ['audit.read'],
    ],
];
