# 🆘 Employee Account Creation - Troubleshooting

## I've Fixed Common Issues

The manage-employees.php has been updated with:
✅ Better database connection handling
✅ More detailed error messages
✅ Improved form validation
✅ Better error reporting

---

## 🔍 Common Issues & Solutions

### **Issue 1: Page Doesn't Load**
**Error:** "Cannot access manage-employees.php"

**Solution:**
1. Make sure you're logged in first
2. Go to: http://localhost/public/admin-verify.php
3. Complete the 2FA login
4. THEN go to: http://localhost/admin/manage-employees.php

---

### **Issue 2: All Fields Required Error**
**Error:** "All fields are required"

**Solution:**
- Make sure EVERY field has something:
  - ✅ Employee ID: (e.g., 1001)
  - ✅ First Name: (e.g., John)
  - ✅ Last Name: (e.g., Doe)
  - ✅ Email: (e.g., john@lgu.gov.ph)
  - ✅ Password: (e.g., MyPassword123)
  - ✅ Role: (Select from dropdown)

---

### **Issue 3: Invalid Email Error**
**Error:** "Invalid email format"

**Solution:**
Check your email format - must include @ symbol:
```
✅ CORRECT:  john@lgu.gov.ph
❌ WRONG:    john.lgu.gov.ph
❌ WRONG:    @lgu.gov.ph
❌ WRONG:    john@
```

---

### **Issue 4: Password Too Short**
**Error:** "Password must be at least 6 characters"

**Solution:**
Use at least 6 characters (longer is better):
```
✅ CORRECT:  MyPassword123 (13 chars)
✅ CORRECT:  SecurePass1 (11 chars)
✅ CORRECT:  Test1234 (8 chars)
✅ CORRECT:  Abc123456 (9 chars)
❌ WRONG:    12345 (5 chars)
❌ WRONG:    abc (3 chars)
```

---

### **Issue 5: Employee ID Already Exists**
**Error:** "Employee ID [number] already exists"

**Solution:**
The ID you entered is already in the database. Use a different number:
```
If error says "Employee ID 1001 already exists":
Try: 1002, 1003, 1004, etc.
```

Check existing IDs: Go to "Employee List" tab to see which IDs are taken.

---

### **Issue 6: Employee ID Must Be a Number**
**Error:** "Employee ID must be a positive number"

**Solution:**
Only use numbers for Employee ID:
```
✅ CORRECT:  1001, 1002, 999, 42
❌ WRONG:    EMP001, ABC-123, john123@
```

---

### **Issue 7: Database Connection Error**
**Error:** "Database connection failed"

**Solution:**
1. Make sure XAMPP is running:
   - MySQL service is running (should show green arrow)
2. Restart XAMPP:
   - Stop all services
   - Start MySQL again
   - Refresh the page

To check MySQL status:
```
XAMPP Control Panel → MySQL Status → Should be "Running"
```

---

### **Issue 8: Nothing Happens When Clicking Add**
**No error message, page just refreshes**

**Solution:**
1. Check browser console for errors:
   - Press F12 (Developer Tools)
   - Click "Console" tab
   - Look for red error messages
2. Try a different Employee ID
3. Make sure all fields are filled
4. Clear browser cache and refresh

---

### **Issue 9: Password Won't Submit**
**Form doesn't submit even with all fields**

**Solution:**
- Check that password is actually 6+ characters
- Try a simple password: `Test1234`
- Make sure no extra spaces
- Check that number keys work on keyboard

---

## ✅ Step-by-Step Test

Try this exact example:

```
1. Go to: http://localhost/admin/manage-employees.php

2. Click "Add Employee" tab

3. Enter EXACTLY:
   Employee ID:  1001
   First Name:   Test
   Last Name:    User
   Email:        test@lgu.gov.ph
   Password:     Test1234
   Role:         Employee

4. Click "Add Employee" button

5. You should see: "✅ Employee 'Test User' (ID: 1001) added successfully!"
```

If this works:
- ✅ Your system is working!
- Try adding real employees with different IDs

If this doesn't work:
- Check the error message
- See which section below applies

---

## 🔧 Technical Troubleshooting

### Check Database is Working
Run this test:

1. Open phpMyAdmin
2. Go to: http://localhost/phpmyadmin
3. Select database: `ipms_lgu`
4. Select table: `employees`
5. Click "Browse"
6. You should see employee list

If empty, database table exists but is empty (that's OK, you're adding to it now).

### Check MySQL User Permissions
The database uses:
- **Host:** localhost
- **User:** ipms_root
- **Password:** G3P+JANpr2GK6fax
- **Database:** ipms_lgu

Make sure this user has INSERT permissions:
1. phpMyAdmin → User Accounts
2. Find user: ipms_root
3. Check "Data" → All (should be checked)

### Check Table Structure
Run this SQL query:
```sql
DESCRIBE employees;
```

Should show columns:
```
id              INT
first_name      VARCHAR(50)
last_name       VARCHAR(50)
email           VARCHAR(100)
password        VARCHAR(255)
role            VARCHAR(50)
created_at      TIMESTAMP
```

---

## 📋 Minimum Requirements

To successfully add an employee, you need:

```
✅ XAMPP running (MySQL service active)
✅ Database ipms_lgu created
✅ Table employees exists
✅ All form fields filled
✅ Valid email format (contains @)
✅ Password 6+ characters
✅ Unique Employee ID
✅ Browser JavaScript enabled
```

---

## 💻 Browser Requirements

```
✅ JavaScript Enabled (required)
✅ Cookies Enabled (required for session)
✅ Clear cache if having issues
✅ Try different browser if nothing works
```

To test if JavaScript works:
- Open browser console (F12)
- Type: `document.getElementById('emp_id')`
- Should show the input element (not an error)

---

## 🆘 Still Having Issues?

Check these files:
1. **Database file:** `/database.php`
   - Make sure credentials match
2. **Employee management:** `/admin/manage-employees.php`
   - Make sure file exists and isn't corrupted
3. **Session auth:** `/session-auth.php`
   - Make sure user is logged in

Try these steps:
1. Clear browser cache (Ctrl+Shift+Delete)
2. Close and reopen browser
3. Restart XAMPP MySQL service
4. Reload the page (Ctrl+F5)
5. Try the exact test example above

---

## ✨ Success Signs

✅ Green success message appears
✅ "✅ Employee '[name]' (ID: [number]) added successfully!"
✅ Form clears
✅ Employee appears in "Employee List" tab
✅ Employee can login with 2FA

---

## 📞 Still Stuck?

Tell me:
1. **What error message do you see?** (exact text)
2. **What happens after you click "Add Employee"?** (page refreshes? error? nothing?)
3. **What did you enter in the form?** (the values)
4. **Is XAMPP running?** (check MySQL service)

With this information, I can help you fix the specific issue!

---

**The system is now more robust with better error handling. Try again and let me know what message you get!**
