# US production readiness

This repository is an initial clinic-management implementation with synthetic demonstration data. It is not a certified EHR, does not attest to HIPAA compliance, and is not ready for live patient information until the following work is completed and reviewed.

## Current controls

- Server-side, deny-by-default role permissions and clinic-scoped patient, appointment, billing, and audit queries.
- Doctor and nurse access limited to patient care-team assignments or provider appointments. Owners have clinic-wide clinical access in this initial policy; validate this policy with each customer.
- Password hashing, generic failed-login messages, account/IP login limits, and MFA attempt limits.
- Authenticator-based TOTP enrollment and verification, encrypted MFA secrets, code replay prevention, and MFA required by default.
- Database-backed encrypted sessions, 15-minute inactivity expiration, HTTP-only and SameSite cookies.
- CSRF protection, output escaping, restrictive same-origin content security policy, anti-framing headers, authenticated response caching disabled, HSTS over HTTPS.
- Clinical notes, allergy descriptions, and conditions encrypted using Laravel's application encryption key. Demographics and searchable identifiers are not application-encrypted; encrypted storage is still required.
- Signed notes have no edit or delete endpoint. Actions and record views create audit events without copying clinical text into the log.
- Provider overlap validation, appointment status transition rules, and transactionally recorded mutation audit events.

## Before any production patient data

1. Perform a HIPAA risk analysis and security assessment with qualified reviewers. Define privacy policies, minimum-necessary access, consent requirements, training, and incident response responsibilities.
2. Execute required customer and subcontractor business associate agreements. Review every provider that creates, receives, maintains, or transmits ePHI, including hosting, storage, backups, monitoring, and messaging. HHS guidance: https://www.hhs.gov/hipaa/for-professionals/special-topics/health-information-technology/cloud-computing/index.html
3. Use US hosting appropriate for the customer's contracts and requirements, HTTPS, private networks, encrypted disks/databases/backups, managed key storage, and restricted infrastructure administration. Hosting location alone does not establish HIPAA compliance.
4. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, `MEDICA_REQUIRE_MFA=true`. Generate a unique APP_KEY and preserve it in a secrets manager; losing it makes encrypted fields unreadable. Establish a tested key rotation procedure.
5. Replace SQLite with a production PostgreSQL or MySQL database. Use a shared cache for distributed rate limiting. Test concurrent appointment booking and transaction locking against the selected production database.
6. Build controlled provisioning, staff suspension and immediate session revocation, time-limited invitations, permission change approval, MFA recovery/reset with identity verification, and periodic access reviews. There is intentionally no public registration or account recovery bypass.
7. Protect audit records with independent restricted storage and tamper-evident retention. The current application exposes read-only audit access, but a database administrator can modify its records. Add centralized monitoring and alerts for bulk access, unexpected access, and suspicious authentication.
8. Add verified backup/restore and disaster recovery procedures with explicit recovery time/point objectives. Restrict backup access and test restoration of both database and encryption keys.
9. Perform an independent penetration test and verify security requirements against OWASP ASVS, including cross-tenant access, authorization, uploads, session handling, and infrastructure. Reference: https://owasp.org/www-project-application-security-verification-standard/
10. Review state-specific requirements, retention schedules, sensitive-record segmentation, minor/guardian access, patient access/amendment procedures, and breach notification obligations. Implement clinical amendments that preserve signed originals and document authorship.
11. Complete clinical validation and accessibility testing with real clinic staff. Validate medication/prescription workflows before introducing them. Electronic prescribing, controlled substances, laboratory interfaces, payer claims, and certified EHR/FHIR requirements require separate implementation and review.
12. Remove demo users/data, disable debug tools, configure production log redaction and retention, restrict health-check visibility where required, and prevent production data from reaching development environments.

## Current scope and limits

Working workflows: local sign-in and MFA, patient registration/search/profile, care-team scoped clinical access, signed notes, appointment creation and visit progression. Billing, staff roles, and audit screens provide read-only overviews. Appointment scheduling currently uses Eastern Time; configurable clinic timezones must be added before supporting other regions. Demo statistics are calculated from database records; no performance uplift claims are generated.

Not yet implemented: staff provisioning UI, user permission editing, patient merge/deduplication, prescriptions, clinical amendments, vitals, attachments, patient portal, insurance claims/eligibility, payment collection/refunds, inventory, lab result integrations, emergency access, consent management, notifications, advanced monitoring, and disaster recovery automation.
