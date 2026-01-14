<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');  // Change this to your MySQL username
define('DB_PASS', 'admin');      // Change this to your MySQL password
define('DB_NAME', 'robotics_attendance');

// Timezone configuration
date_default_timezone_set('America/Los_Angeles');  // Change to your timezone

// Attendance configuration
define('GRACE_PERIOD_MINUTES', 5);  // Minutes after window start to still count as "on time"
?>