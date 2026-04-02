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

   public function sendAdminApprovalEmail($email, $name, $title, $type = 'notes')
    {
    $typeName = ucfirst(str_replace('_', ' ', $type));

    $body = "
        <h3>Your {$typeName} Has Been Approved</h3>
        <p>Hi $name,</p>
        <p>Your <strong>{$typeName}</strong> titled <strong>$title</strong> has been approved by the admin.</p>
    ";

    return $this->sendEmail($email, "{$typeName} Approved", $body);
   }
    public function sendAdminRejectionEmail($email, $name, $noteTitle)
    {
        $body = "
            <h3>Your Notes Were Rejected</h3>
            <p>Hi $name,</p>
            <p>Your notes titled <strong>$noteTitle</strong> were rejected by the admin.</p>
        ";

        return $this->sendEmail($email, "Notes Rejected", $body);
    }

    public function sendAdminDeletionEmail($email, $name, $noteTitle)
    {
        $body = "
            <h3>Your Notes Were Deleted</h3>
            <p>Hi $name,</p>
            <p>Your notes titled <strong>$noteTitle</strong> were deleted by the admin.</p>
        ";

        return $this->sendEmail($email, "Notes Deleted", $body);
    }

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

    /* ----------------------------------------
       NEW: Assessment Review Email
    ---------------------------------------- */

    public function sendSubmissionReviewedEmail($studentEmail, $studentName, $assessmentTitle, $status, $remarks = '')
    {
        $statusText = $status === 'approved' ? 'Approved' : 'Rejected';
        $statusColor = $status === 'approved' ? '#10B981' : '#EF4444';
        $statusMessage = $status === 'approved' 
            ? 'Your submission has been approved! Great work!'
            : 'Your submission has been reviewed and needs improvement.';

        $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #14B8A6, #0d9488); padding: 20px; text-align: center; border-radius: 12px 12px 0 0;'>
                    <h2 style='color: white;'>Assessment Review Result</h2>
                </div>
                <div style='background: white; padding: 25px; border: 1px solid #e2e8f0; border-top: none;'>
                    <p>Hello <strong>" . htmlspecialchars($studentName) . "</strong>,</p>
                    <p>Your submission for <strong>" . htmlspecialchars($assessmentTitle) . "</strong> has been reviewed.</p>

                    <div style='text-align:center; margin:20px 0;'>
                        <span style='background: {$statusColor}; color:white; padding:8px 20px; border-radius:20px;'>
                            {$statusText}
                        </span>
                        <p>{$statusMessage}</p>
                    </div>";

        if (!empty($remarks)) {
            $body .= "
                <div style='background:#fef3c7; padding:10px;'>
                    <strong>Teacher's Remarks:</strong>
                    <p>" . nl2br(htmlspecialchars($remarks)) . "</p>
                </div>";
        }

        $body .= "
                    <p style='text-align:center;'>
                        <a href='" . BASE_URL . "/public/view_assessments.php'>View Assessments</a>
                    </p>
                </div>
            </div>
        ";

        return $this->sendEmail($studentEmail, "Assessment Result: {$statusText}", $body);
    }
}