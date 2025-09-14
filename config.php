<?php
// config.php

// --- TinyMCE API Key ---
// Replace 'YOUR_API_KEY_HERE' with your actual TinyMCE API key.
// You can get a free key from https://www.tiny.cloud/
define('TINYMCE_API_KEY', 'YOUR_API_KEY_HERE');

// --- Email (SMTP) Settings ---
// These settings are for a service like Mailtrap.io or a real SMTP server.
// It's highly recommended to use environment variables for sensitive data in a production environment.
define('SMTP_HOST', 'smtp.mailtrap.io');
define('SMTP_USERNAME', 'your_smtp_username');
define('SMTP_PASSWORD', 'your_smtp_password');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // Use 'tls' for STARTTLS or 'ssl' for SMTPS

// --- Email Sender Details ---
define('SMTP_FROM_EMAIL', 'no-reply@oldschool.edu');
define('SMTP_FROM_NAME', 'Old Model School');