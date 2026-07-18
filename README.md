# TAM-HUB

**FEU Tech Resource Management & Scheduling System**

A dual-role PHP/MySQL web application for managing lab resources, reservations, and class schedules at FEU Institute of Technology.

---

## Features

### Student Portal
- **Resource Reservations** — Book laptops, projectors, rooms, equipment with conflict detection
- **Weekly Schedule** — Visual grid view (Mon-Sat, 7AM-9PM) with enrolled classes
- **Schedule Changes** — Request room/day/time changes with admin approval workflow
- **Reservation History** — Track pending, approved, rejected, cancelled, completed bookings

### Admin Portal
- **Reservation Approvals** — Review queue with Approve/Reject/Complete actions
- **Resource Management** — Full CRUD for lab resources (quantity, status, categories)
- **Schedule Management** — Create/edit master class schedules with room conflict prevention
- **Schedule Change Approvals** — Review student change requests (side-by-side comparison)
- **User Management** — Add students/admins, upgrade/downgrade roles
- **Reports** — Activity feed and daily summary statistics

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 7.4+ (procedural + MySQLi prepared statements) |
| Database | MySQL/MariaDB 10.4+ (InnoDB) |
| Auth | Session-based, bcrypt password hashing |
| Frontend | HTML5, CSS3 (custom properties), vanilla JS, FontAwesome 6 |
| Timezone | Asia/Manila (FEU Tech local) |
| Server | XAMPP (Apache + MySQL) |

---

## Quick Start

### Prerequisites
- XAMPP (or Apache + MySQL + PHP 7.4+)
- Web browser

### Installation

1. **Clone/Extract** to your XAMPP htdocs:
   ```bash
   # Example path
   /Applications/XAMPP/xamppfiles/htdocs/TAM_HUB/
   ```

2. **Create Database** and import schemas:
   ```sql
   CREATE DATABASE tam_hub;
   USE tam_hub;
   SOURCE tam_hub.sql;
   SOURCE schedule_schema.sql;
   ```

3. **Configure Database** (`db_connect.php`):
   ```php
   $hostname = "localhost";
   $dbuser = "root";
   $dbpass = "";          // Default XAMPP has no password
   $dbname = "tam_hub";
   ```

4. **Start XAMPP** (Apache + MySQL)

5. **Access** in browser:
   ```
   http://localhost/TAM_HUB/login.php
   ```

### Default Accounts
- **Admin**: Create via registration, then manually update `role='admin'` in `users` table, OR use Admin Dashboard → User Management → "Add Admin"
- **Student**: Register at `login.php` → Sign Up tab (requires `@fit.edu.ph` email)

---

## Project Structure

```
TAM_HUB/
├── Core
│   ├── db_connect.php        # MySQLi connection
│   ├── login.php             # Login + Register (toggle UI)
│   ├── register.php          # Registration handler
│   └── logout.php            # Session destroy
│
├── Student Portal
│   ├── student_dashboard.php # Main hub (sections: dashboard, resources, history)
│   ├── student_schedule.php  # Weekly grid + enrollment + change requests
│   ├── reserve_resource.php  # Booking form with conflict check
│   └── student_sidebar.php   # Shared navigation
│
├── Admin Portal
│   ├── admin_dashboard.php   # Stats, approvals, users, resources, reports
│   ├── admin_schedule.php    # Master schedule CRUD
│   ├── admin_schedule_approvals.php  # Student change request review
│   ├── add_resource.php      # Create resource
│   ├── edit_resource.php     # Edit resource
│   └── admin_sidebar.php     # Shared navigation
│
├── Database
│   ├── tam_hub.sql           # Core: users, resources, reservations
│   └── schedule_schema.sql   # Schedules + student_schedules
│
├── Styles
│   ├── dashboardstyle.css    # Student portal
│   ├── dashboardstyle_admin.css  # Admin portal
│   └── loginstyle.css        # Login page
│
└── Scripts
    ├── dashboardscript.js    # Shared (sidebar toggle, modals)
    ├── dashboardscript_admin.js  # Admin-specific
    └── loginscript.js        # Login toggle animation
```

---

## Database Schema (Core Tables)

```
users
├── email (PK)          # @fit.edu.ph
├── password            # bcrypt hash
├── first_name, last_name
├── student_number      # 9 digits (empty for admins)
├── role                # 'student' | 'admin'
└── timestamps

resources
├── id (PK)
├── name, category
├── quantity, status    # Available | Reserved | Repair | Unavailable
└── timestamps

reservations
├── id (PK)
├── user_email (FK)     → users
├── resource_id (FK)    → resources
├── purpose, start/end datetime
├── status              # Pending | Approved | Rejected | Cancelled | Completed
└── timestamps

schedules (master)
├── id (PK)
├── subject_code, name, room
├── day_of_week, start/end time
├── semester, status    # Active | Inactive
└── timestamps

student_schedules (enrollments)
├── id (PK)
├── student_email (FK)  → users
├── schedule_id (FK)    → schedules
├── status              # Enrolled | Pending Change | Dropped
├── requested_*         # Change request fields
└── timestamps
```

---

## User Flows

### Student
```
Login → Dashboard
  ├── View active reservations (cancel pending)
  ├── Browse resources → Reserve (auto conflict check)
  ├── View history
  └── My Schedule
        ├── Weekly grid view
        ├── Enroll in available classes
        ├── Request schedule changes (admin approval)
        └── Drop classes
```

### Admin
```
Login → Admin Dashboard
  ├── Approve/Reject/Complete reservations
  ├── Manage Resources (CRUD + status)
  ├── Manage Schedules (CRUD + room conflict check)
  ├── Review Schedule Change Requests
  ├── User Management (add/upgrade/downgrade)
  └── Reports (activity + stats)
```

---

## Security Highlights

- ✅ **Prepared Statements** — 100% parameterized queries (no SQL injection)
- ✅ **bcrypt Passwords** — `password_hash()` / `password_verify()`
- ✅ **Session Regeneration** — `session_regenerate_id(true)` on login
- ✅ **Role-Based Access** — Every page verifies `$_SESSION['role']`
- ✅ **Output Encoding** — `htmlspecialchars()` on all echoed data
- ⚠️ **No CSRF Tokens** — Relies on SameSite=Lax cookies
- ⚠️ **No Rate Limiting** — Login/register endpoints open

---

## Key Implementation Patterns

| Pattern | Where Used |
|---------|------------|
| **PRG (Post-Redirect-Get)** | All POST handlers redirect with `?success=1` |
| **Section-Based Nav** | Single dashboard file, `?section=` switches content |
| **Shared Includes** | `student_sidebar.php`, `admin_sidebar.php` with `$current_page` |
| **Modal Forms** | Bootstrap-style modals for add/edit (no page navigation) |
| **GET Action Params** | `?approve=ID`, `?reject=ID`, `?cancel=ID` for state changes |

---

## Configuration

### Timezone
All datetime operations use **Asia/Manila** (UTC+8):
```php
$tz = new DateTimeZone('Asia/Manila');
```

### Database Connection (`db_connect.php`)
```php
$hostname = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "tam_hub";
```

For production, use environment variables:
```php
$hostname = getenv('DB_HOST') ?: 'localhost';
$dbuser = getenv('DB_USER') ?: 'root';
$dbpass = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'tam_hub';
```

---

## Development Notes

### Adding a New Resource Category
No code changes — `category` is `VARCHAR(50)`. Just update form dropdowns.

### Adding a New Reservation Status
1. `ALTER TABLE reservations MODIFY status ENUM('Pending','Approved',...,'NewStatus')`
2. Update status-checking queries
3. Add CSS badge class: `.status-newstatus`

### Multi-Semester Support
Built-in via `semester` column in `schedules` and `student_schedules`. Filter persists via URL.

---

## Testing Checklist

- [ ] Login / Register / Logout
- [ ] Role-based redirects (student vs admin)
- [ ] Resource reservation with conflict detection
- [ ] Admin approve/reject/complete flow
- [ ] Student cancel own pending reservation
- [ ] Schedule CRUD with room conflict check
- [ ] Student enroll/drop/change request
- [ ] Admin approve/reject schedule changes
- [ ] User management (add/upgrade/downgrade)
- [ ] SQL injection attempts (should fail safely)

---

## License

Educational project for CS0043 - FEU Institute of Technology.

---

## Support

For issues or questions, contact the development team or check the documentation files:
- `overview.txt` — High-level project concept & flow
- `backendoverview.txt` — Detailed backend architecture