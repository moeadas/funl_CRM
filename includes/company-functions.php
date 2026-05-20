<?php
/**
 * White Label CRM - Tenant / Company Management Functions
 * Multi-tenant helper functions
 */

// Get current company ID from session
function getCurrentCompanyId() {
    return $_SESSION['company_id'] ?? null;
}

// Get company details
function getCompany($companyId = null) {
    if (!$companyId) $companyId = getCurrentCompanyId();
    if (!$companyId) return null;
    
    try {
        $db = Database::getInstance();
        return $db->findOne('companies', ['company_id' => $companyId]);
    } catch (Exception $e) {
        error_log("getCompany error: " . $e->getMessage());
        return null;
    }
}

// Get company settings
function getCompanySettings($companyId = null) {
    if (!$companyId) $companyId = getCurrentCompanyId();
    if (!$companyId) return [];
    
    try {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE company_id = ?", [$companyId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        error_log("getCompanySettings error: " . $e->getMessage());
        return [];
    }
}

// Check if company is active
function isCompanyActive($companyId = null) {
    if (!$companyId) $companyId = getCurrentCompanyId();
    if (!$companyId) return false;
    
    $company = getCompany($companyId);
    if (!$company) return false;
    
    if ($company['status'] !== 'active') return false;
    
    // Check trial expiration
    if ($company['subscription_status'] === 'trial' && $company['trial_ends_at']) {
        if (strtotime($company['trial_ends_at']) < time()) {
            return false; // Trial expired
        }
    }
    
    return true;
}

// Get current user count for company
function getCompanyUserCount($companyId = null) {
    if (!$companyId) $companyId = getCurrentCompanyId();
    if (!$companyId) return 0;
    
    try {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE company_id = ? AND status = 'Active'", [$companyId]);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// Check if company can add more users
function canAddUser($companyId = null) {
    if (!$companyId) $companyId = getCurrentCompanyId();
    if (!$companyId) return false;
    
    $company = getCompany($companyId);
    if (!$company) return false;
    
    $currentUsers = getCompanyUserCount($companyId);
    $limit = $company['plan_user_limit'] ?? 1;
    
    return $currentUsers < $limit;
}

// Get available user slots
function getRemainingUserSlots($companyId = null) {
    if (!$companyId) $companyId = getCurrentCompanyId();
    if (!$companyId) return 0;
    
    $company = getCompany($companyId);
    if (!$company) return 0;
    
    $currentUsers = getCompanyUserCount($companyId);
    $limit = $company['plan_user_limit'] ?? 1;
    
    return max(0, $limit - $currentUsers);
}

// Get plan details
function getPlan($planKey) {
    try {
        $db = Database::getInstance();
        return $db->findOne('plans', ['plan_key' => $planKey]);
    } catch (Exception $e) {
        return null;
    }
}

// Get all active plans
function getActivePlans() {
    try {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Get subscription status text
function getSubscriptionStatusText($company = null) {
    if (!$company) $company = getCompany();
    if (!$company) return 'Unknown';
    
    $status = $company['subscription_status'] ?? 'trial';
    
    switch ($status) {
        case 'trial':
            if ($company['trial_ends_at'] && strtotime($company['trial_ends_at']) > time()) {
                $daysLeft = ceil((strtotime($company['trial_ends_at']) - time()) / 86400);
                return "Trial ($daysLeft days left)";
            }
            return 'Trial Expired';
        case 'active':
            return 'Active';
        case 'past_due':
            return 'Past Due';
        case 'cancelled':
            return 'Cancelled';
        case 'suspended':
            return 'Suspended';
        default:
            return ucfirst($status);
    }
}

// Check if email is verified
function isEmailVerified($userId = null) {
    if (!$userId) $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) return false;
    
    try {
        $db = Database::getInstance();
        $user = $db->findOne('users', ['user_id' => $userId]);
        return !empty($user['email_verified_at']);
    } catch (Exception $e) {
        return false;
    }
}


// Verify email token
function verifyEmailToken($token) {
    try {
        $db = Database::getInstance();
        
        $verification = $db->query(
            "SELECT * FROM email_verifications WHERE token = ? AND expires_at > datetime('now')",
            [$token]
        )->fetch(PDO::FETCH_ASSOC);
        
        if (!$verification) {
            return ['success' => false, 'message' => 'Invalid or expired verification link.'];
        }
        
        // Update user
        $db->query(
            "UPDATE users SET email_verified_at = datetime('now') WHERE user_id = ?",
            [$verification['user_id']]
        );
        
        // Mark verification as used
        $db->query(
            "UPDATE email_verifications SET verified_at = datetime('now') WHERE token = ?",
            [$token]
        );
        
        return ['success' => true, 'message' => 'Email verified successfully!'];
        
    } catch (Exception $e) {
        error_log("verifyEmailToken error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Verification failed.'];
    }
}

// Generate password reset token
function createPasswordReset($email) {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    try {
        $db = Database::getInstance();
        
        // Delete existing resets for this email
        $db->query("DELETE FROM password_resets WHERE email = ?", [$email]);
        
        // Insert new reset
        $db->insert('password_resets', [
            'email' => $email,
            'token' => $token,
            'expires_at' => $expires,
        ]);
        
        $resetUrl = APP_URL . '/reset-password.php?token=' . urlencode($token);
        error_log("Password reset for $email: $resetUrl");
        
        return ['success' => true, 'token' => $token, 'url' => $resetUrl];
        
    } catch (Exception $e) {
        error_log("createPasswordReset error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Validate password reset token
function validateResetToken($token) {
    try {
        $db = Database::getInstance();
        
        $reset = $db->query(
            "SELECT * FROM password_resets WHERE token = ? AND expires_at > datetime('now') AND used_at IS NULL",
            [$token]
        )->fetch(PDO::FETCH_ASSOC);
        
        return $reset ?: null;
        
    } catch (Exception $e) {
        return null;
    }
}

// Get company documents (Document Library)
function getCompanyDocuments($companyId = null, $category = null) {
    if (!$companyId) $companyId = getCurrentCompanyId();
    if (!$companyId) return [];
    
    try {
        $db = Database::getInstance();
        
        if ($category && $category !== 'all') {
            $stmt = $db->query(
                "SELECT cd.*, u.full_name as uploaded_by_name 
                 FROM company_documents cd
                 LEFT JOIN users u ON cd.uploaded_by = u.user_id
                 WHERE cd.company_id = ? AND cd.category = ?
                 ORDER BY cd.created_at DESC",
                [$companyId, $category]
            );
        } else {
            $stmt = $db->query(
                "SELECT cd.*, u.full_name as uploaded_by_name 
                 FROM company_documents cd
                 LEFT JOIN users u ON cd.uploaded_by = u.user_id
                 WHERE cd.company_id = ?
                 ORDER BY cd.created_at DESC",
                [$companyId]
            );
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("getCompanyDocuments error: " . $e->getMessage());
        return [];
    }
}

// Get document categories
function getDocumentCategories() {
    return [
        'general' => 'General',
        'sales' => 'Sales',
        'marketing' => 'Marketing',
        'legal' => 'Legal',
        'training' => 'Training',
        'other' => 'Other',
    ];
}
