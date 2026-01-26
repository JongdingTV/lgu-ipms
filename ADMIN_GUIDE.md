# Admin Employee Management - Quick Start Guide

## 🔐 How to Access the Admin Panel

### Step 1: Go to Admin Login Page
Navigate to: **https://ipms.infragovservices.com/public/admin-login.php**

### Step 2: Login with Your Credentials
- **Employee ID:** `1` (or your assigned ID)
- **Password:** `admin123` (or your set password)
- Click **"Login"**

### Step 3: Request Verification Code
- You'll see a confirmation of your identity
- Click **"Send Code to Email"**
- A **8-digit code** will be generated and sent to your registered email

### Step 4: Enter Verification Code
- Check your email for the verification code
- Enter the **8-digit code** in the form
- Click **"Verify Code"**
- ✅ You'll be redirected to the Employee Management page

---

## 👥 Managing Employees

### Add a New Employee
1. Go to the **"Add Employee"** tab
2. Fill in the form:
   - **Employee ID:** Unique numeric identifier (e.g., 2, 3, 4)
   - **First Name:** Employee's first name
   - **Last Name:** Employee's last name
   - **Email:** Valid email address (e.g., john@lgu.gov.ph)
   - **Password:** Temporary password (minimum 6 characters)
   - **Role:** Select from dropdown (Admin, Contractor Manager, Employee)
3. Click **"Add Employee"**
4. ✅ Employee account created successfully

### View All Employees
- Go to the **"Employee List"** tab
- See all registered employees with their details
- Delete employees using the trash icon (if needed)

### Reset Employee Password
If an employee forgets their password:
1. Delete the old employee account
2. Create a new account with the same ID and a new password
3. Send them the new credentials

---

## 🔑 Important Information

| Item | Value |
|------|-------|
| **Admin Login URL** | https://ipms.infragovservices.com/public/admin-login.php |
| **Employee Management URL** | https://ipms.infragovservices.com/admin/manage-employees.php |
| **Default Admin ID** | 1 |
| **Default Admin Password** | admin123 |
| **Verification Code Duration** | 10 minutes |
| **Max Code Attempts** | 5 attempts |

---

## ⚠️ Security Best Practices

1. **Change Default Password** - Immediately change the admin password from `admin123` to a strong password
2. **Keep Emails Updated** - Ensure your email is current for receiving verification codes
3. **Don't Share Credentials** - Each admin should have their own account
4. **Session Timeout** - Always log out when done (or session expires after inactivity)
5. **Code Expiration** - If you don't verify within 10 minutes, request a new code

---

## 🆘 Troubleshooting

### "Employee not found" error
- Check that you're using the correct Employee ID
- Ensure your account exists in the system

### "Invalid password" error
- Verify you're entering the correct password
- Passwords are case-sensitive
- Minimum 6 characters required

### "Code expired" message
- If you don't enter the code within 10 minutes, it expires
- Click "Start Over" to restart the login process
- Request a new code

### "Too many attempts" error
- You exceeded 5 incorrect code attempts
- Click "Start Over" to restart the login process
- Request a new code

---

## 📝 First-Time Setup Checklist

- [ ] Access admin login page successfully
- [ ] Complete 2FA verification process
- [ ] View employee management page
- [ ] Change admin password from default `admin123`
- [ ] Add your first new employee
- [ ] Test logging in with new employee credentials
- [ ] Confirm everything works as expected

---

**Need Help?** Contact your system administrator or check the system documentation.
