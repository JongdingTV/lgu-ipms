$ErrorActionPreference = 'Stop'

function Assert-Contains {
    param(
        [string]$Path,
        [string]$Pattern
    )
    $content = Get-Content $Path -Raw
    if ($content -notmatch $Pattern) {
        throw "Assertion failed: '$Pattern' not found in $Path"
    }
}

Write-Host "Running PHP syntax checks..."
$php = "C:\xampp\php\php.exe"
$files = @(
    "session-auth.php",
    "user-dashboard/user-feedback.php",
    "user-dashboard/user-dashboard.php",
    "user-dashboard/user-settings.php",
    "user-dashboard/user-login.php",
    "user-dashboard/user-forgot-password.php",
    "user-dashboard/user-notifications-api.php",
    "user-dashboard/feedback-photo.php"
)
foreach ($f in $files) {
    & $php -l $f | Out-Host
}

Write-Host "Checking hardening markers..."
Assert-Contains -Path "session-auth.php" -Pattern "function is_user_rate_limited"
Assert-Contains -Path "session-auth.php" -Pattern "function record_user_attempt"
Assert-Contains -Path "user-dashboard/user-feedback.php" -Pattern "feedback_table_has_user_id"
Assert-Contains -Path "user-dashboard/user-feedback.php" -Pattern "user_feedback_submit"
Assert-Contains -Path "user-dashboard/user-notifications-api.php" -Pattern "user_notification_state"
Assert-Contains -Path "user-dashboard/user-feedback.js" -Pattern "feedbackInboxSearch"
Assert-Contains -Path "user-dashboard/user-feedback.js" -Pattern "draftKey"
Assert-Contains -Path "user-dashboard/feedback-photo.php" -Pattern "Photo Attachment Private"
Assert-Contains -Path "db_setup.sql" -Pattern "user_notification_state"
Assert-Contains -Path "lgu_ipms.sql" -Pattern "user_notification_state"

Write-Host "All user-side smoke checks passed."
