<?php

require_once __DIR__ . '/../../config/smtp_config.php';
require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender
{
    private bool $development;
    private string $logPath;

    public function __construct()
    {
        $this->development = (APP_ENV !== 'production');
        $this->logPath = EMAIL_LOG_PATH;

        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /* ----------------------------------------
       Core email sender
    ---------------------------------------- */
    public function sendEmail($to, $subject, $body)
    {
        $safeTo = filter_var($to, FILTER_VALIDATE_EMAIL);
        if (!$safeTo) {
            return ['success' => false, 'message' => 'Invalid email'];
        }

        if ($this->development) {
            $this->logEmail($safeTo, $subject, strip_tags($body));
            return ['success' => true, 'message' => 'Logged (development mode)'];
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($safeTo);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            $this->logEmail($safeTo, $subject, 'SUCCESS');

            return ['success' => true];

        } catch (Exception $e) {
            $this->logEmail($safeTo, $subject, 'ERROR: '.$e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function logEmail($to, $subject, $body)
    {
        $line = json_encode([
            'time' => date('c'),
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ]) . PHP_EOL;

        @file_put_contents($this->logPath, $line, FILE_APPEND);
    }

    /* ----------------------------------------
       Existing Emails
    ---------------------------------------- */

   public function sendWelcomeEmail($email, $name, $password)
{
    $body = "
        <h3>Welcome to Notes Platform</h3>
        <p>Hi $name,</p>
        <p>Your account has been created successfully. Here are your login details:</p>
        <p><strong>Username:</strong> $email<br>
        <strong>Password:</strong> $password</p>
        <p>Please keep this information safe.</p>
    ";

    return $this->sendEmail($email, "Welcome to Notes Platform", $body);
}

    public function sendPasswordResetEmail($email, $name, $token)
    {
        $resetLink = BASE_URL . "/public/reset_password.php?token=" . urlencode($token);

        $body = "
            <h2>Password Reset Request</h2>
            <p>Hello $name,</p>
            <p>Click below to reset your password:</p>
            <p><a href='$resetLink'>Reset Password</a></p>
        ";
        return $this->sendEmail($email, "Password Reset Request", $body);
    }

    /* ----------------------------------------
       NEW EMAIL EVENTS
    ---------------------------------------- */

    // 1. Notify all users when any user uploads new notes
    public function sendNewNotesNotification($emails, $noteTitle, $uploadedBy)
    {
        $subject = "New Notes Uploaded – $noteTitle";
        $body = "
            <h3>New Notes Added</h3>
            <p><strong>$uploadedBy</strong> uploaded new notes:</p>
            <p><strong>Title:</strong> $noteTitle</p>
        ";

        foreach ($emails as $email) {
            $this->sendEmail($email, $subject, $body);
        }
    }

    // 2. Notify teacher uploads (notes/papers/assessments)
    public function sendTeacherUploadNotification($email, $name, $noteTitle, $type)
    {
        $typeName = ucfirst($type);

        $body = "
            <h3>Your Upload Was Successful</h3>
            <p>Hello $name,</p>
            <p>You uploaded a new <strong>$typeName</strong>:</p>
            <p><strong>$noteTitle</strong></p>
        ";

        return $this->sendEmail($email, "Upload Confirmation", $body);
    }

    public function sendNewAssessmentNotification($email, $teacherName, $title)
{
    $body = "
        <h3>New Assessment Uploaded</h3>
        <p><strong>$teacherName</strong> uploaded a new assessment:</p>
        <p><strong>$title</strong></p>
        <p>Login to view or download it.</p>
    ";

    return $this->sendEmail($email, "New Assessment Uploaded", $body);
}
public function sendNewQuestionPaperNotification($email, $teacherName, $title)
{
    $body = "
        <h3>New Question Paper Uploaded</h3>
        <p><strong>$teacherName</strong> uploaded a new question paper:</p>
        <p><strong>$title</strong></p>
        <p>Login to view or download it.</p>
    ";

    return $this->sendEmail($email, "New Question Paper Uploaded", $body);
}

    // 3. Admin Approves Notes
    public function sendAdminApprovalEmail($email, $name, $noteTitle)
    {
        $body = "
            <h3>Your Notes Have Been Approved</h3>
            <p>Hi $name,</p>
            <p>Your notes titled <strong>$noteTitle</strong> have been approved by the admin.</p>
        ";

        return $this->sendEmail($email, "Notes Approved", $body);
    }

    // 4. Admin Rejects Notes
    public function sendAdminRejectionEmail($email, $name, $noteTitle)
    {
        $body = "
            <h3>Your Notes Were Rejected</h3>
            <p>Hi $name,</p>
            <p>Your notes titled <strong>$noteTitle</strong> were rejected by the admin.</p>
        ";

        return $this->sendEmail($email, "Notes Rejected", $body);
    }

    // 5. Admin Deletes Notes
    public function sendAdminDeletionEmail($email, $name, $noteTitle)
    {
        $body = "
            <h3>Your Notes Were Deleted</h3>
            <p>Hi $name,</p>
            <p>Your notes titled <strong>$noteTitle</strong> were deleted by the admin.</p>
        ";

        return $this->sendEmail($email, "Notes Deleted", $body);
    }

    // 6. Admin creates a new user
    public function sendAdminCreatedUserEmail($email, $name, $username, $password)
    {
        $body = "
            <h3>Welcome! Your Account Is Ready</h3>
            <p>Hello $name,</p>
            <p>An admin created your account.</p>
            <p><strong>Login Email:</strong> $username<br>
               <strong>Password:</strong> $password</p>
        ";

        return $this->sendEmail($email, "Your Account Details", $body);
    }
}
