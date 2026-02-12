    <section class="main-content">
        <div class="dash-header">
            <h1>User Settings</h1>
            <p>Manage your account information and change your password.</p>
        </div>
        <div class="settings-container">
            <div class="user-info-box">
                <h2>Account Information</h2>
                <table class="user-info-table">
                    <tr><th>Name:</th><td><?php echo htmlspecialchars($user_name); ?></td></tr>
                    <tr><th>Email:</th><td><?php echo htmlspecialchars($user['email']); ?></td></tr>
                    <tr><th>Gender:</th><td><?php echo htmlspecialchars($gender_display); ?></td></tr>
                    <tr><th>Civil Status:</th><td><?php echo htmlspecialchars($civil_status_display); ?></td></tr>
                </table>
            </div>
            <div class="password-change-box">
                <h2>Change Password</h2>
                <form method="post" action="">
                    <div class="input-box">
                        <label for="currentPassword">Current Password</label>
                        <input type="password" id="currentPassword" name="currentPassword" required>
                    </div>
                    <div class="input-box">
                        <label for="newPassword">New Password</label>
                        <input type="password" id="newPassword" name="newPassword" required>
                    </div>
                    <div class="input-box">
                        <label for="confirmPassword">Confirm New Password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" required>
                    </div>
                    <button type="submit" class="submit-btn">Change Password</button>
                </form>
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <?php foreach ($errors as $err): ?>
                            <div><?php echo htmlspecialchars($err); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
        document.addEventListener('DOMContentLoaded', function() {
            window.setupLogoutConfirmation && window.setupLogoutConfirmation();
        });
        </script>
    </aside>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('navbar');
        const burgerBtn = document.getElementById('sidebarBurgerBtn');
        if (sidebar && burgerBtn) {
            burgerBtn.addEventListener('click', function() {
                sidebar.classList.toggle('sidebar-open');
                if (sidebar.classList.contains('sidebar-open')) {
                    sidebar.style.transform = 'translateX(0)';
                } else {
                    sidebar.style.transform = 'translateX(-110%)';
                }
            });
            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target) && !burgerBtn.contains(e.target)) {
                    sidebar.classList.remove('sidebar-open');
                    sidebar.style.transform = 'translateX(-110%)';
                }
            });
        }
        sidebar && (sidebar.style.transform = 'translateX(0)');
    });
    </script>
</body>
</html>






