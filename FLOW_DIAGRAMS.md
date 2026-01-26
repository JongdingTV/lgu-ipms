# Enhanced Admin 2FA - Process Flow Diagrams

## Complete User Journey

```
START: User wants to access admin area
   │
   ├─→ Clicks "Employee Access" button on /public/index.php
   │
   ├─→ Redirected to /public/admin-verify.php
   │
   ├─→ Presented with STEP 1 form
   │   ┌──────────────────────────────────┐
   │   │ Employee ID: [_______________]   │
   │   │ Password: [__________________]   │
   │   │ [Verify Identity Button]         │
   │   └──────────────────────────────────┘
   │
   ├─→ User enters credentials
   │
   └─→ System processes...
       │
       ├─ Query Database: SELECT FROM employees WHERE id=?
       │  │
       │  ├─ NOT FOUND ❌ → Show error, stay on STEP 1
       │  │
       │  └─ FOUND ✅ → Continue
       │
       ├─ Verify password_verify($input, $db_hash)
       │  │
       │  ├─ INVALID ❌ → Show error, stay on STEP 1
       │  │
       │  └─ VALID ✅ → Continue
       │
       ├─ Store in session:
       │  ├─ temp_employee_id
       │  ├─ temp_employee_email
       │  └─ temp_employee_name
       │
       └─ Redirect to STEP 2
           │
           ├─→ Display verified employee info
           │
           ├─→ Show "Send Verification Code" button
           │
           ├─→ User clicks button
           │
           └─→ System processes...
               │
               ├─ Generate code: rand(10000000, 99999999)
               │
               ├─ Store in session:
               │  ├─ admin_verification_code
               │  ├─ admin_verification_time
               │  └─ admin_verification_attempts = 0
               │
               ├─ [DEMO: Show code on screen]
               │  [PROD: Send via email]
               │
               └─ Redirect to STEP 3
                   │
                   ├─→ Display code entry form
                   │   ┌──────────────────────────────────┐
                   │   │ Verification Code: [________]    │
                   │   │ (8 digits)                       │
                   │   │ [Verify Code Button]             │
                   │   │ [Request New Code Button]        │
                   │   └──────────────────────────────────┘
                   │
                   ├─→ User enters code
                   │
                   ├─→ Code auto-submits when 8 digits entered
                   │   (or user clicks Verify button)
                   │
                   └─→ System processes...
                       │
                       ├─ Check: Code exists in session?
                       │  └─ NO ❌ → "Please start over"
                       │
                       ├─ Check: Code expired?
                       │  │  (time() - stored_time > 600)
                       │  └─ YES ❌ → "Code expired, request new"
                       │
                       ├─ Check: Too many attempts?
                       │  │  (attempts > 5)
                       │  └─ YES ❌ → "Too many attempts, start over"
                       │
                       ├─ Check: Code matches?
                       │  │  (entered_code === session_code)
                       │  │
                       │  ├─ NO ❌ → Increment attempts
                       │  │            Show remaining attempts
                       │  │            Stay on STEP 3
                       │  │
                       │  └─ YES ✅ → Continue
                       │
                       ├─ Clean up temporary session vars
                       │  ├─ Remove temp_employee_*
                       │  ├─ Remove admin_verification_code
                       │  └─ Remove admin_verification_time
                       │
                       ├─ Set final verification flags:
                       │  ├─ admin_verified = true
                       │  └─ verified_employee_id = [id]
                       │
                       └─ Redirect to /admin/index.php
                           │
                           ├─→ Admin login page checks:
                           │   if (admin_verified && verified_employee_id)
                           │   ✅ Allow access
                           │
                           ├─→ Display admin login form
                           │   (email/password for actual admin login)
                           │
                           ├─→ User enters admin credentials
                           │
                           ├─→ System verifies admin account
                           │
                           └─→ ✅ ADMIN DASHBOARD UNLOCKED


END: User has full admin access
```

---

## Security Validation Flow

```
REQUEST COMES IN
├─ Has admin_verified=true?
│  ├─ NO → Redirect to /public/admin-verify.php ❌
│  └─ YES → Continue ✅
│
├─ Has verified_employee_id?
│  ├─ NO → Redirect to verification ❌
│  └─ YES → Continue ✅
│
└─ Has employee_id (from actual login)?
   ├─ NO → Show login form (user is verified but not logged in)
   ├─ YES → Full admin access ✅
```

---

## Database Query Sequence

```
STEP 1: Verify Credentials
├─ Input: Employee ID "john123", Password "secret"
│
├─ SQL Query:
│  SELECT id, password, email, first_name, last_name 
│  FROM employees 
│  WHERE id = 'john123' OR email = 'john123'
│
└─ Result: 
   ├─ IF Not Found → Error: "Employee not found"
   ├─ IF password_verify('secret', hash) = false → Error: "Invalid password"
   └─ IF password_verify('secret', hash) = true ✅ → PROCEED TO STEP 2

STEP 2: Send Code
├─ Input: Verified employee from Step 1
│
├─ Code Generation:
│  rand(10000000, 99999999) = 47382516
│  Stored: $_SESSION['admin_verification_code'] = '47382516'
│
└─ Email Send (Optional):
   FROM: noreply@lgu-ipms.com
   TO: (from database)
   Subject: Your IPMS Admin Verification Code
   Body: Your verification code is: 47382516
         This code expires in 10 minutes.

STEP 3: Verify Code
├─ Input: User enters "47382516"
│
├─ Validations:
│  ├─ 'admin_verification_code' in session?
│  ├─ time() - $_SESSION['admin_verification_time'] <= 600?
│  ├─ $_SESSION['admin_verification_attempts'] <= 5?
│  └─ '47382516' === $_SESSION['admin_verification_code']?
│
└─ Result:
   ├─ ALL VALID ✅ → Set admin_verified = true
   └─ ANY FAIL ❌ → Show error, increment attempts
```

---

## Session State Transitions

```
┌─────────────────────────────────────────────────────────────────┐
│ INITIAL STATE: No admin session                                 │
│ admin_verified = FALSE (or not set)                             │
│ employee_id = NOT SET                                           │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ User visits /public/admin-verify.php
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ STEP 1 STATE: Credential Entry                                  │
│ temp_employee_id = NULL (waiting for input)                    │
│ admin_verification_code = NOT SET                               │
│ admin_verified = FALSE                                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ User submits valid credentials
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ STEP 2 STATE: Code Requested                                    │
│ temp_employee_id = "john123"                                   │
│ temp_employee_email = "john@lgu.gov.ph"                        │
│ admin_verification_code = "47382516"                           │
│ admin_verification_time = 1706262300                           │
│ admin_verification_attempts = 0                                │
│ admin_verified = FALSE                                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ User enters correct code
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ STEP 3 STATE: Verified & Redirecting                            │
│ admin_verified = TRUE ✅                                         │
│ verified_employee_id = "john123"                               │
│ admin_verification_code = CLEARED                              │
│ temp_employee_* = CLEARED                                      │
│ employee_id = NOT SET YET (needs actual login)                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ Redirected to /admin/index.php
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ ADMIN LOGIN STATE: Awaiting Admin Login                         │
│ admin_verified = TRUE ✅                                         │
│ verified_employee_id = "john123" ✅                             │
│ employee_id = NOT SET (needs login)                            │
│ Display: Admin login form (email/password)                      │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ User submits admin login credentials
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ FINAL STATE: Admin Authenticated & Authorized                   │
│ admin_verified = TRUE ✅                                         │
│ verified_employee_id = "john123" ✅                             │
│ employee_id = "john123" ✅                                      │
│ user_role = "admin" ✅                                          │
│ user_name = "John Doe" ✅                                       │
│ Display: Admin Dashboard with full access                       │
└─────────────────────────────────────────────────────────────────┘
```

---

## Error Handling Flowchart

```
USER ENTERS STEP 1 CREDENTIALS
│
├─ Credentials Valid?
│  │
│  ├─ NO → Display Error
│  │      ├─ "Employee not found" (ID doesn't exist)
│  │      ├─ "Invalid password" (wrong password)
│  │      └─ Remain on STEP 1 (user retries)
│  │
│  └─ YES → Proceed to STEP 2 ✅
│
USER REQUESTS VERIFICATION CODE
│
├─ Credentials still valid in session?
│  │
│  ├─ NO → Display Error "Session expired"
│  │      └─ Redirect back to STEP 1
│  │
│  └─ YES → Generate & Send Code ✅
│           └─ Proceed to STEP 3
│
USER ENTERS VERIFICATION CODE
│
├─ Code exists in session?
│  │
│  ├─ NO → Display Error "Session expired"
│  │      └─ Request new code
│  │
│  └─ YES → Continue validation
│
├─ Code NOT expired? (< 10 minutes)
│  │
│  ├─ NO → Display Error "Code expired"
│  │      └─ Button to request new code
│  │
│  └─ YES → Continue validation
│
├─ Attempts NOT exceeded? (≤ 5 attempts)
│  │
│  ├─ NO → Display Error "Too many attempts"
│  │      └─ Force restart from STEP 1
│  │
│  └─ YES → Continue validation
│
├─ Code matches?
│  │
│  ├─ NO → Increment attempt counter
│  │      └─ Display "Invalid code (X/5 attempts remaining)"
│  │      └─ Remain on STEP 3 (user retries)
│  │
│  └─ YES → SUCCESS ✅
│           ├─ Clear temporary variables
│           ├─ Set admin_verified = true
│           └─ Redirect to /admin/index.php
```

---

## Password Verification (Bcrypt) Process

```
DATABASE SETUP:
┌────────────────────────────────────────┐
│ Employee Record:                       │
│ id: "john123"                          │
│ password: "$2y$10$abcdef...xyz123"     │
│ (bcrypt hash - not reversible)         │
└────────────────────────────────────────┘

USER ENTERS PASSWORD:
┌────────────────────────────────────────┐
│ User types: "MySecretPassword"         │
│ (plaintext - never stored)             │
└────────────────────────────────────────┘

VERIFICATION PROCESS:
  password_verify("MySecretPassword", "$2y$10$abcdef...xyz123")
  │
  ├─ Hash the input using stored salt
  │  "$2y$10$abcdef..."
  │
  ├─ Compare generated hash with stored hash
  │  Generated: $2y$10$abcdef...ABC
  │  Stored:    $2y$10$abcdef...ABC
  │
  └─ Return: true (password matches) ✅
     or: false (password doesn't match) ❌

SECURITY PROPERTIES:
✓ Plaintext password never stored
✓ Hash cannot be reversed
✓ Same password produces same hash (deterministic)
✓ Different password produces different hash
✓ Each account has unique salt
✓ Bcrypt automatically includes salt in hash
✓ Even with DB breach, passwords are protected
```

---

## Code Flow Timeline (Happy Path)

```
T=0s     User clicks "Employee Access" button
         └─ Navigates to /public/admin-verify.php

T=5s     STEP 1 form loads
         └─ User enters Employee ID: "john123"
         └─ User enters Password: "secret"

T=10s    User clicks "Verify Identity" button
         ├─ POST request to /public/admin-verify.php
         ├─ Process Step 1:
         │  ├─ Query database for employee john123
         │  ├─ Verify password with bcrypt
         │  └─ Store temp variables in session
         └─ Redirect to STEP 2

T=12s    STEP 2 form loads
         ├─ Display: "Credentials verified!"
         ├─ Display: "Employee: John Doe"
         └─ Show: "Send Verification Code" button

T=15s    User clicks "Send Verification Code"
         ├─ POST request to /public/admin-verify.php
         ├─ Process Step 2:
         │  ├─ Generate 8-digit code: "47382516"
         │  ├─ Store in session
         │  ├─ Record current time (T=15)
         │  └─ [DEMO] Show code on screen
         │       [PROD] Send via email
         └─ Redirect to STEP 3

T=17s    STEP 3 form loads
         ├─ Display: "Code sent to john@***"
         ├─ Display: Code entry field (8 digits)
         └─ Show: "Verify Code" and "Request New Code" buttons

T=22s    User checks email, sees code: "47382516"

T=28s    User enters code: "47382516"
         ├─ After 8 digits, form auto-submits
         ├─ POST request to /public/admin-verify.php
         ├─ Process Step 3:
         │  ├─ Check code exists ✓
         │  ├─ Check not expired (28-15=13s < 600s) ✓
         │  ├─ Check attempts (0 < 5) ✓
         │  ├─ Check match: "47382516" == "47382516" ✓
         │  ├─ Clear temp variables
         │  ├─ Set admin_verified = true
         │  └─ Set verified_employee_id = "john123"
         └─ Redirect to /admin/index.php

T=30s    Admin login page loads
         ├─ Check: admin_verified? YES ✓
         ├─ Check: verified_employee_id? YES ✓
         ├─ Display: Admin login form
         │  ├─ Email: [___________@lgu.gov.ph]
         │  ├─ Password: [_________________]
         │  └─ [Login] button
         └─ User sees: "You've been verified! Please log in."

T=35s    User enters admin account:
         ├─ Email: "admin@lgu.gov.ph"
         ├─ Password: "admin123"
         └─ Clicks "Login"

T=38s    Admin authentication
         ├─ Query database for admin account
         ├─ Verify password
         ├─ Check admin role
         ├─ Set employee_id = "admin_id"
         ├─ Set user_role = "admin"
         └─ Set user_name = "Administrator"

T=40s    ✅ ADMIN DASHBOARD LOADED
         └─ Full admin access granted


TOTAL TIME: ~40 seconds from start to admin dashboard
VERIFICATION TIME: 30 seconds (includes email)
```

---

## Attack Scenario Timelines

### Scenario A: Attacker Knows Email

```
T=0     Attacker tries /public/admin-verify.php
T=5     Enters: "john@lgu.gov.ph" as Employee ID
        System: ❌ "Employee not found"
        Reason: Email is not the ID, ID is different

T=10    Attacker guesses: "john123" as Employee ID
        System: ✓ "Employee found, enter password"

T=15    Attacker guesses password: "password123"
        System: ❌ "Invalid password"
        Attempt 1/5

T=20    Attacker guesses: "123456"
        System: ❌ "Invalid password"
        Attempt 2/5

T=25    Attacker guesses: "john123"
        System: ❌ "Invalid password"
        Attempt 3/5

T=30    Attacker guesses: "admin123"
        System: ❌ "Invalid password"
        Attempt 4/5

T=35    Attacker guesses: "password"
        System: ❌ "Invalid password"
        Attempt 5/5

T=40    Attacker tries again
        System: ❌ "Too many attempts. Start over."
                   Forces redirect to STEP 1

RESULT: ❌ ATTACKER BLOCKED
        Without password, cannot proceed
        Even with email access, cannot bypass Step 1
```

### Scenario B: Attacker Compromises Email

```
T=0     Attacker has John's email password
T=5     Attacker goes to /public/admin-verify.php
T=10    WITHOUT knowing password:
        Tries to guess Employee ID: "john123"
        Enters password: "password123"
        System: ❌ "Invalid password"
        Blocked at STEP 1

RESULT: ❌ ATTACKER BLOCKED
        Email access alone is insufficient
        Must also know Employee ID + Password
        Defense in depth prevents escalation
```

---

## Comparison: Old vs New System

```
OLD SYSTEM (Email-Only):
┌─────────────────────┐
│ Email: john@lgu.gov │
│ [Send Code]         │
└──────────┬──────────┘
           │
           ├─ If attacker knows email ✓
           ├─ If attacker has email access ✓
           └─ ✅ Attacker gets code
                └─ ✅ Attacker gets admin access

ATTACK VECTOR: Email knowledge + Email breach

NEW SYSTEM (3-Factor):
┌──────────────────────┐
│ Emp ID: john123      │
│ Password: ••••••••   │
│ [Verify]             │
└──────────┬───────────┘
           │
           ├─ If attacker knows email? ❌ Not enough
           ├─ If attacker has email? ❌ Still blocked
           └─ ❌ Attacker blocked at STEP 1

REQUIRED TO PROCEED:
- Know Employee ID (hard, internal)
- Know Password (private)
- Have Email Access (now just bonus)

ATTACK VECTOR CLOSED: Three-factor requirement
```

---

## Recovery Scenarios

```
USER LOSES EMAIL ACCESS:
T=5     Admin tries to access but email account compromised
T=10    Goes through STEP 1 successfully (knows ID + password)
T=15    STEP 2: Code sent to email... but can't access email
T=20    Cannot get code (email compromised or lost)
        
SOLUTION: 
- System admin manually resets password
- User gets new email account
- Retry from STEP 1

USER FORGETS PASSWORD:
T=5     Admin tries STEP 1 with ID + password
T=10    Forgot password ❌
        
SOLUTION:
- Click "Forgot Password" link (if implemented)
- Password reset email sent
- User changes password
- Retry from STEP 1

USER GETS LOCKED OUT (5 failed attempts):
T=5-40  User enters wrong code 5 times
T=45    System: "Too many attempts. Start over."
        
SOLUTION:
- Click "Request New Code" to restart
- System clears session
- Begins fresh from STEP 1
```

---

**These diagrams show the complete security architecture of the enhanced 2FA system.**
