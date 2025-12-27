<?php
require_once __DIR__ . '/includes/EmailSender.php';

$mailer = new EmailSender();
print_r(
    $mailer->sendWelcomeEmail("aka0786@gmail.com", "Arvind")
);
