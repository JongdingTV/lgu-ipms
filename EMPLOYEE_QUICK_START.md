# Employee Account Quick Start

## 🚀 Quickest Way to Add an Employee

### **Step 1:** Go to Employee Management Page
```
http://localhost/admin/manage-employees.php
```
(Must be logged in first)

### **Step 2:** Fill Out the Form

```
Employee ID:  1001
First Name:   John
Last Name:    Doe
Email:        john@lgu.gov.ph
Password:     SecurePass123
Role:         Employee
```

### **Step 3:** Click "Add Employee" ✅

### **Step 4:** Test It Works
1. Go to: http://localhost/public/admin-verify.php
2. Enter ID: 1001
3. Enter Password: SecurePass123
4. Verify identity ✓
5. Get code ✓
6. Enter code ✓
7. Access admin ✅

---

## 📋 Required Information

| Field | Example | Notes |
|-------|---------|-------|
| **Employee ID** | 1001 | Must be unique number |
| **First Name** | John | Text only |
| **Last Name** | Doe | Text only |
| **Email** | john@lgu.gov.ph | Must be valid email |
| **Password** | SecurePass123 | Min 6 chars, will be hashed |
| **Role** | Employee | Employee/Manager/Admin/Supervisor |

---

## 🔐 Password Rules

✅ **DO:** Use bcrypt-hashed passwords
✅ **DO:** At least 6 characters (8+ recommended)
✅ **DO:** Mix uppercase, lowercase, numbers
✅ **DO:** Update passwords every 90 days

❌ **DON'T:** Use plaintext passwords
❌ **DON'T:** Share passwords via email
❌ **DON'T:** Reuse old passwords
❌ **DON'T:** Use simple passwords like "123456"

---

## 💡 Tips

1. **Employee ID Suggestions:**
   - Managers: 100-199 (101, 102, 103...)
   - Supervisors: 200-299 (201, 202, 203...)
   - Staff: 300-999 (301, 302, 303...)
   - Admins: 1-99 (1, 2, 3...)

2. **Email Format:**
   - Use company domain: @lgu.gov.ph
   - Format: firstname.lastname@lgu.gov.ph
   - Example: john.doe@lgu.gov.ph

3. **Test Immediately:**
   - Add employee
   - Try logging in with 2FA
   - Verify it works before going live

---

## ❓ FAQ

**Q: Can I change an employee's password later?**
A: Not yet in the UI. Use SQL: 
```sql
UPDATE employees SET password = PASSWORD_HASH('newpass') WHERE id = 1001;
```

**Q: How do I delete an employee?**
A: Use the manage-employees.php page:
1. Go to "Employee List" tab
2. Click "Delete" button next to employee
3. Confirm deletion

**Q: What if I forget the password?**
A: Reset it in manage-employees.php or via SQL update

**Q: Can multiple employees use same email?**
A: No, email must be unique in database

**Q: What's the maximum number of employees?**
A: Database can handle 1,000+ employees easily

---

## 🆘 Troubleshooting

| Issue | Solution |
|-------|----------|
| "Employee not found" | Check ID is correct and unique |
| "Invalid password" | Ensure 6+ characters |
| "Invalid email" | Use format: name@domain.com |
| "Can't access manage page" | Login first at admin-verify.php |
| Employee can't login | Verify employee ID, email, password in database |

---

## 📱 Mobile Employees

If you're adding employees who work remotely:
1. Use same process
2. Ensure they have email access (for 2FA)
3. They'll receive 8-digit codes via email
4. 10-minute expiration allows time to check email

---

**That's it! Employees can now access the admin area with 3-factor security.** 🔐
