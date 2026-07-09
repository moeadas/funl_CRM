<?php
/**
 * White Label CRM - Resend Email Service
 * Transactional email via Resend API
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

class ResendEmailService {
    private $apiKey;
    private $fromEmail;
    private $fromName;
    private $enabled;
    
    public function __construct() {
        // The Resend API key is a platform-wide setting, not per-company.
        // Query directly from DB (getSetting is session-scoped by company_id).
        $apiKeySetting = '';
        $fromEmailSetting = '';
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('resend_api_key','resend_from_email') AND setting_value != '' ORDER BY company_id ASC");
            foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                if ($k === 'resend_api_key') $apiKeySetting = $v;
                if ($k === 'resend_from_email') $fromEmailSetting = $v;
            }
        } catch (\Exception $e) {
            error_log('ResendEmailService: DB error loading settings: ' . $e->getMessage());
        }
        
        if ($apiKeySetting) {
            $this->apiKey = function_exists('decryptToken') ? decryptToken($apiKeySetting) : $apiKeySetting;
        } else {
            $this->apiKey = getenv('RESEND_API_KEY') ?: '';
        }
        $this->fromEmail = $fromEmailSetting ?: getenv('RESEND_FROM_EMAIL') ?: '';
        if (empty($this->fromEmail)) {
            // Build from app settings
            $appName = getSetting('app_name', 'FunL CRM');
            $appName = trim($appName);
            if (empty($appName)) $appName = 'FunL CRM';
            $companyEmail = getSetting('company_email', '');
            if ($companyEmail && strpos($companyEmail, '@') !== false) {
                $domain = explode('@', $companyEmail, 2)[1];
                $this->fromEmail = "{$appName} <noreply@{$domain}>";
            } else {
                $this->fromEmail = 'White Label CRM <noreply@yourdomain.com>';
            }
        } else {
            // Ensure proper "Name <email>" format for Resend API
            if (strpos($this->fromEmail, '<') === false && filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL)) {
                $appName = getSetting('app_name', '');
                $appName = trim($appName);
                if (empty($appName)) $appName = 'FunL CRM';
                $this->fromEmail = "{$appName} <{$this->fromEmail}>";
            }
        }
        $this->enabled = !empty($this->apiKey);
    }
    
    /**
     * Send an email via Resend API
     */
    public function sendEmail($to, $subject, $html, $text = '') {
        if (!$this->enabled) {
            error_log("Resend: Email skipped (no API key configured)");
            return false;
        }
        
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'from' => $this->fromEmail,
                'to' => $to,
                'subject' => $subject,
                'html' => $html,
                'text' => $text ?: strip_tags($html),
            ]),
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("Resend API curl error: $error");
            return false;
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = json_decode($response, true);
            return ['success' => true, 'email_id' => $responseData['id'] ?? null];
        }
        
        error_log("Resend API error: HTTP $httpCode - $response");
        return false;
    }
    
    /**
     * Send verification email
     */
    public function sendVerificationEmail($to, $fullName, $token) {
        $verifyUrl = getenv('APP_URL') . "/verify-email.php?token=" . urlencode($token);
        $companyName = getSetting('company_name') ?: 'White Label CRM';
        
        $html = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 40px 20px;'>
            <div style='text-align: center; margin-bottom: 40px;'>
                <h1 style='color: #333; margin: 0;'>$companyName</h1>
            </div>
            <div style='background: #f9f9f9; border-radius: 12px; padding: 40px; text-align: center;'>
                <h2 style='color: #333; margin: 0 0 20px;'>Verify Your Email</h2>
                <p style='color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 30px;'>
                    Hi $fullName, thanks for signing up! Please verify your email address by clicking the button below.
                </p>
                <a href='$verifyUrl' style='display: inline-block; background: #007bff; color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 16px;'>
                    Verify Email Address
                </a>
                <p style='color: #999; font-size: 13px; margin: 30px 0 0;'>
                    This link expires in 24 hours. If you didn't create an account, you can safely ignore this email.
                </p>
            </div>
        </div>";
        
        return $this->sendEmail($to, "Verify your $companyName account", $html);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($to, $fullName, $token) {
        $resetUrl = getenv('APP_URL') . "/reset-password.php?token=" . urlencode($token);
        $companyName = getSetting('company_name') ?: 'White Label CRM';
        
        $html = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 40px 20px;'>
            <div style='text-align: center; margin-bottom: 40px;'>
                <h1 style='color: #333; margin: 0;'>$companyName</h1>
            </div>
            <div style='background: #f9f9f9; border-radius: 12px; padding: 40px; text-align: center;'>
                <h2 style='color: #333; margin: 0 0 20px;'>Reset Your Password</h2>
                <p style='color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 30px;'>
                    Hi $fullName, we received a request to reset your password. Click the button below to set a new one.
                </p>
                <a href='$resetUrl' style='display: inline-block; background: #007bff; color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 16px;'>
                    Reset Password
                </a>
                <p style='color: #999; font-size: 13px; margin: 30px 0 0;'>
                    This link expires in 1 hour. If you didn't request a reset, you can safely ignore this email.
                </p>
            </div>
        </div>";
        
        return $this->sendEmail($to, "Reset your $companyName password", $html);
    }
    
    /**
     * Send subscription confirmation email
     */
    public function sendSubscriptionConfirmation($to, $fullName, $planName, $price) {
        $companyName = getSetting('company_name') ?: 'White Label CRM';
        
        $html = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 40px 20px;'>
            <div style='text-align: center; margin-bottom: 40px;'>
                <h1 style='color: #333; margin: 0;'>$companyName</h1>
            </div>
            <div style='background: #f9f9f9; border-radius: 12px; padding: 40px; text-align: center;'>
                <div style='background: #d4edda; color: #155724; padding: 16px; border-radius: 8px; margin-bottom: 30px;'>
                    <strong>&#10003; Subscription Activated</strong>
                </div>
                <h2 style='color: #333; margin: 0 0 20px;'>Thank You, $fullName!</h2>
                <p style='color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 20px;'>
                    Your subscription is now active.
                </p>
                <table style='margin: 0 auto; text-align: left;'>
                    <tr><td style='padding: 8px 0; color: #666;'>Plan:</td><td style='padding: 8px 0 8px 20px; font-weight: 600;'>$planName</td></tr>
                    <tr><td style='padding: 8px 0; color: #666;'>Price:</td><td style='padding: 8px 0 8px 20px; font-weight: 600;'>$$price/mo</td></tr>
                </table>
            </div>
        </div>";
        
        return $this->sendEmail($to, "Subscription activated - $companyName", $html);
    }
    
    /**
     * Send trial expiring soon email
     */
    public function sendTrialExpiringEmail($to, $fullName, $daysLeft) {
        $companyName = getSetting('company_name') ?: 'White Label CRM';
        $billingUrl = getenv('APP_URL') . '/pages/settings.php?tab=billing';
        
        $html = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 40px 20px;'>
            <div style='text-align: center; margin-bottom: 40px;'>
                <h1 style='color: #333; margin: 0;'>$companyName</h1>
            </div>
            <div style='background: #fff3cd; border-radius: 12px; padding: 40px; text-align: center;'>
                <h2 style='color: #856404; margin: 0 0 20px;'>Trial Ending Soon</h2>
                <p style='color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 30px;'>
                    Hi $fullName, your trial expires in <strong>$daysLeft days</strong>. Upgrade now to keep your data and access.
                </p>
                <a href='$billingUrl' style='display: inline-block; background: #007bff; color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 16px;'>
                    Upgrade Now
                </a>
            </div>
        </div>";
        
        return $this->sendEmail($to, "Trial expires in $daysLeft days - $companyName", $html);
    }
    
    public function isEnabled() {
        return $this->enabled;
    }
    
    public function getApiKeyPrefix() {
        if (!$this->apiKey) return '';
        return substr($this->apiKey, 0, 8) . '...';
    }
}

/**
 * Send verification email helper
 */
function sendVerificationEmail($to, $fullName, $token) {
    $service = new ResendEmailService();
    return $service->sendVerificationEmail($to, $fullName, $token);
}

/**
 * Send password reset email helper
 */
function sendPasswordResetEmail($to, $fullName, $token) {
    $service = new ResendEmailService();
    return $service->sendPasswordResetEmail($to, $fullName, $token);
}