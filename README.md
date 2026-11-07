# Shoheed Private Schools Management System

Official public website and role-based school management portal for **Shoheed Private Schools** (Lagos & Ogun State, Nigeria).

## Technology Stack

- **Backend:** Native PHP 8.2+ (procedural controllers with centralized `core/` domain services)
- **Database:** MySQL / MariaDB via PDO (`ATTR_EMULATE_PREPARES => false`, strict UTF-8 `utf8mb4`)
- **Frontend:** Semantic HTML5, compiled Tailwind CSS (`assets/css/tailwind.min.css`), Vanilla JavaScript (`assets/js/`)
- **Typography & Theme:** Contemporary Academic Editorial (`DM Serif Display` + `Manrope`, School Navy `#111B3F`, School Blue `#263B8F`, Crimson `#C62828`, Gold `#E5B926`, Warm Ivory `#F5F1E8`)

---

## Core Portal Modules

1. **Public Website (`index.php`, `staff.php`)**
   - Institutional overview, academic pathway (Early Years, Foundation, Secondary), campus directions, and active Staff Directory with secure photo streaming (`media/staff-profile.php`).
2. **Authentication & Role-Based Access Control (`core/auth.php`, `core/authorization.php`)**
   - Supports **Administrator**, **Principal**, **Class Teacher**, **Subject Teacher**, **Bursar**, and **Student** roles, plus capability-based teaching assignments (`can_teach_subjects`, `can_be_class_teacher`).
   - Session fixation protection, per-request active account verification, CSRF tokens on all mutations, and dual username + IP login rate limiting (`login_attempts`).
3. **Academic & Nigerian SDBA Result Workflow (`core/results.php`, `teacher/results/`, `principal/results/`)**
   - Multi-stage result governance: `DRAFT` -> `SUBMITTED` -> `CLASS_TEACHER_VERIFIED` -> `PRINCIPAL_APPROVED` -> `PUBLISHED` (with revision requests).
   - Server-computed Continuous Assessment (`0–40`) + Examination (`0–60`) totals, Nigerian 20/20/60 cumulative term weighting, 14-point SDBA behavioural/psychomotor ratings, and print-ready report cards & broadsheets.
4. **School Fees, Invoices & Bank Transfer Verification (`core/finance.php`, `bursar/`, `student/fees.php`)**
   - Term fee structures with historical integrity protection, bulk & supplementary invoice generation, student bank-transfer proof uploads (`finfo` MIME-verified), transactional Bursar verification (`FOR UPDATE` row locking), overpayment prevention, and printable official receipts.
5. **Student & Staff Administration (`admin/students/`, `admin/staff/`, `core/students.php`)**
   - Searchable, paginated student directory, one-time 6-digit PIN login slip generation, guardian linking, end-of-session promotion eligibility analysis (`>= 45%`), announcements, and system audit logs (`admin/reports/audit.php`).

---

## Local Setup (XAMPP / Apache + MySQL)

1. **Clone / Place in Web Root**
   Place the project inside Apache's document root (e.g. `C:\xampp\htdocs\Shoheed schools`). Subdirectory deployments are supported automatically via centralized `url()` and `redirect()` helpers in `core/url.php`.

2. **Configure Environment Variables**
   Copy `.env.example` to `.env` and set your database credentials and environment:
   ```ini
   APP_ENV=local
   APP_URL=
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=shoheed_db
   DB_USER=root
   DB_PASS=
   ```
   *(Never commit `.env` to version control.)*

3. **Initialize Database Schema**
   Create the `shoheed_db` database in MySQL (`utf8mb4_unicode_ci`) and import the canonical schema:
   ```bash
   mysql -u root -p shoheed_db < database/schema.sql
   ```
   Incremental migrations for existing databases are stored in `database/migrations/` (through `016_audit_remediation.sql`).

4. **Optional Local Development Seed (CLI Only)**
   Database seeding scripts are restricted to CLI execution in non-production environments:
   ```bash
   php database/seed.php
   ```

5. **Run Automated Verification Suite (CLI)**
   ```bash
   php tests/verify_remediation.php
   ```

---

## Security & Deployment Notes

- Ensure Apache `AllowOverride All` is enabled so `.htaccess` rules protect `.env`, `config/`, `core/`, `database/`, `templates/`, `tests/`, and `storage/`.
- Uploaded payment proofs (`storage/payment_proofs/`) and staff photos (`storage/staff_profiles/`) have PHP script execution disabled and are streamed exclusively through authenticated/authorized PHP endpoints (`bursar/payments/proof.php` and `media/staff-profile.php`) with `Content-Security-Policy` sandboxing and `X-Content-Type-Options: nosniff`.
