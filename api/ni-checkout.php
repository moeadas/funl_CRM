<?php
/**
 * White Label CRM - Network International / Mastercard Gateway Checkout API
 * 
 * Handles server-side session creation and payment confirmation.
 * The actual card collection is done client-side via checkout.min.js hosted by Mastercard.
 * 
 * Gateway URL format (discovered via testing):
 *   POST /api/rest/version/{version}/merchant/{merchantId}/session
 *   GET  /api/rest/version/{version}/merchant/{merchantId}/session/{sessionId}
 *   POST /api/rest/version/{version}/merchant/{merchantId}/transaction
 * 
 * Docs: https://test-gateway.mastercard.com/api/documentation/integrationGuidelines/
 */
// Load config first so Database singleton can connect
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ni-checkout-helpers.php';

header('Content-Type: application/json');
startSecureSession();

// Load NI gateway settings from platform-level settings


/**
 * Make an authenticated request to the Network International Gateway.
 * Uses HTTP Basic Auth with merchant credentials.
 */
function niGatewayRequest($method, $endpoint, $body = null) {
    $settings = getNIGatewaySettings();
    
    $baseUrl = $settings['ni_gateway_url'] ?? 'https://test-network.mtf.gateway.mastercard.com/api';
    $merchantId = $settings['ni_merchant_id'] ?? '';
    $apiUsername = $settings['ni_api_username'] ?? '';
    $apiPasswordRaw = $settings['ni_api_password'] ?? '';
    $apiVersion = $settings['ni_api_version'] ?? '100';
    
    // Decrypt stored password (H-4 fix).
    // If the stored value was never encrypted (legacy/plaintext), decryptToken
    // returns garbage (the raw value encrypted as AES). In that case, use the
    // raw value directly since that's what the gateway expects.
    $decrypted = decryptToken($apiPasswordRaw);
    if ($decrypted !== false && $decrypted !== '' && strlen($decrypted) === strlen($apiPasswordRaw)) {
        $apiPassword = $decrypted; // properly encrypted value
    } else {
        $apiPassword = $apiPasswordRaw; // legacy plaintext fallback
    }
    
    if (empty($apiUsername) || empty($apiPassword)) {
        return ['success' => false, 'error' => 'NI Gateway not configured. Ask super admin to set credentials in super-admin settings.'];
    }
    
    $url = $baseUrl . '/rest/version/' . $apiVersion . '/merchant/' . $merchantId . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $apiUsername . ':' . $apiPassword);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    // Use system default CA bundle for SSL verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    // Fall back to system's default CA bundle if our cert file doesn't exist
    $caBundle = __DIR__ . '/../certs/ca-bundle.crt';
    if (file_exists($caBundle)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
    }
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body) {
            $jsonBody = json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'merchantId: ' . $merchantId,
            ]);
        }
    } else {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['merchantId: ' . $merchantId]);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => 'Gateway cURL error: ' . $error];
    }
    
    $data = json_decode($response, true);
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'data' => $data,
        'raw_response' => $response,
    ];
}

// ─── Actions ─────────────────────────────────────────────────────────────────

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// M-7: CSRF protection for state-changing operations
if (in_array($action, ['create_session', 'process_payment', 'verify_payment', 'create_order', 'create_checkout_session'])) {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRFToken($token)) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Refresh and try again.']);
        exit;
    }
}

try {
    switch ($action) {
        // ── Create Checkout Session ──────────────────────────────────────────
        // POST /api/ni-checkout.php?action=create_session
        // Body: { amount, currency, order_id, description, return_url, company_id }
        case 'create_session':
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'error' => 'Login required']);
                exit;
            }
            
            $amount = floatval($_POST['amount'] ?? 0);
            $currency = strtoupper(sanitizeInput($_POST['currency'] ?? 'USD'));
            $orderId = sanitizeInput($_POST['order_id'] ?? 'ORD-' . time() . '-' . random_int(1000, 9999));
            $description = sanitizeInput($_POST['description'] ?? 'CRM Subscription');
            $returnUrl = filter_var($_POST['return_url'] ?? '', FILTER_VALIDATE_URL) ?: '';
            $companyId = intval($_POST['company_id'] ?? ($_SESSION['company_id'] ?? 0));
            
            if ($amount <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid amount']);
                exit;
            }
            
            if (!$companyId) {
                echo json_encode(['success' => false, 'error' => 'No company context']);
                exit;
            }
            
            // Build return URLs
            $appUrl = rtrim(getenv('APP_URL') ?: 'https://app.funl.online', '/');
            $successUrl = $appUrl . '/pages/settings.php?ni=success&order_id=' . urlencode($orderId);
            $failureUrl = $appUrl . '/pages/settings.php?ni=failed&order_id=' . urlencode($orderId);
            
            // Create checkout session via NI Gateway
            $result = niGatewayRequest('POST', '/session', [
                'apiOperation' => 'CREATE_CHECKOUT_SESSION',
            ]);
            
            if (!$result['success']) {
                // Log and return error
                error_log('NI create_session failed: ' . json_encode($result));
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to create checkout session: ' . ($result['error'] ?? 'Gateway error'),
                    'gateway_response' => $result['data'] ?? null,
                ]);
                exit;
            }
            
            $sessionData = $result['data'] ?? [];
            $sessionId = $sessionData['session']['id'] ?? '';
            $aesKey = $sessionData['session']['aes256Key'] ?? '';
            
            if (!$sessionId) {
                echo json_encode(['success' => false, 'error' => 'No session ID returned from gateway']);
                exit;
            }
            
            // Store pending payment in DB for later verification
            $db = Database::getInstance();
            try {
                $db->query("
                    INSERT INTO payment_transactions (company_id, order_id, session_id, aes_key, amount, currency, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ON DUPLICATE KEY UPDATE session_id = VALUES(session_id), aes_key = VALUES(aes_key), amount = VALUES(amount), updated_at = NOW()
                ", [$companyId, $orderId, $sessionId, $aesKey, $amount, $currency]);
            } catch (Exception $e) {
                // payment_transactions table might not exist yet - create it
                try {
                    $db->query("
                        CREATE TABLE IF NOT EXISTS payment_transactions (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            company_id INT NOT NULL,
                            order_id VARCHAR(100) NOT NULL,
                            session_id VARCHAR(100) NOT NULL,
                            aes_key VARCHAR(255),
                            amount DECIMAL(10,2) NOT NULL,
                            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
                            status ENUM('pending','completed','failed','cancelled') DEFAULT 'pending',
                            gateway_response TEXT,
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            UNIQUE KEY unique_order (company_id, order_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                    $db->query("
                        INSERT INTO payment_transactions (company_id, order_id, session_id, aes_key, amount, currency, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ", [$companyId, $orderId, $sessionId, $aesKey, $amount, $currency]);
                } catch (Exception $e2) {
                    error_log('Failed to create payment_transactions table: ' . $e2->getMessage());
                }
            }
            
            echo json_encode([
                'success' => true,
                'session_id' => $sessionId,
                'aes_key' => $aesKey,
                'order_id' => $orderId,
                'gateway_url' => 'https://test-gateway.mastercard.com/static/checkout/checkout.min.js',
            ]);
            break;
            
        // ── Verify Payment (after return from checkout) ──────────────────────
        // GET /api/ni-checkout.php?action=verify_payment&session_id=xxx
        case 'verify_payment':
            $sessionId = sanitizeInput($_GET['session_id'] ?? '');
            $orderId = sanitizeInput($_GET['order_id'] ?? '');
            
            if (!$sessionId) {
                echo json_encode(['success' => false, 'error' => 'Session ID required']);
                exit;
            }
            
            // Retrieve session from gateway
            $result = niGatewayRequest('GET', '/session/' . urlencode($sessionId));
            
            if (!$result['success']) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to retrieve session from gateway',
                    'gateway_response' => $result['data'] ?? null,
                ]);
                exit;
            }
            
            $sessionData = $result['data'] ?? [];
            $updateStatus = $sessionData['session']['updateStatus'] ?? '';
            
            // If session was updated with card data (interaction.complete), 
            // we need to process the payment server-side
            if ($updateStatus === 'SUCCESS') {
                // Card data has been collected. Now we need to AUTHORIZE or PAY.
                // For hosted checkout, we typically AUTHORIZE the payment.
                // Get the order from our DB
                $db = Database::getInstance();
                $tx = $db->query(
                    "SELECT * FROM payment_transactions WHERE session_id = ? LIMIT 1",
                    [$sessionId]
                )->fetch(PDO::FETCH_ASSOC);
                
                if ($tx) {
                    // Update status
                    $db->query(
                        "UPDATE payment_transactions SET status = 'completed', gateway_response = ?, updated_at = NOW() WHERE session_id = ?",
                        [json_encode($sessionData), $sessionId]
                    );
                    
                    // Update company's subscription
                    $companyId = $tx['company_id'];
                    $db->query(
                        "UPDATE companies SET subscription_status = 'active', updated_at = NOW() WHERE company_id = ?",
                        [$companyId]
                    );
                    
                    echo json_encode([
                        'success' => true,
                        'status' => 'completed',
                        'order_id' => $tx['order_id'],
                        'amount' => $tx['amount'],
                        'currency' => $tx['currency'],
                        'session_update_status' => $updateStatus,
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'status' => 'completed',
                        'session_update_status' => $updateStatus,
                        'warning' => 'Transaction not found in local DB',
                    ]);
                }
            } else {
                // Session not yet complete
                echo json_encode([
                    'success' => true,
                    'status' => 'pending',
                    'session_update_status' => $updateStatus,
                ]);
            }
            break;
            
        // ── Get Session (for client-side checkout.js) ───────────────────────
        // GET /api/ni-checkout.php?action=get_session&session_id=xxx
        case 'get_session':
            $sessionId = sanitizeInput($_GET['session_id'] ?? '');
            if (!$sessionId) {
                echo json_encode(['success' => false, 'error' => 'Session ID required']);
                exit;
            }
            
            $result = niGatewayRequest('GET', '/session/' . urlencode($sessionId));
            
            if (!$result['success']) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to retrieve session',
                    'gateway_response' => $result['data'] ?? null,
                ]);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'session' => $result['data']['session'] ?? [],
            ]);
            break;
            
        // ── Health Check ─────────────────────────────────────────────────────
        case 'health':
            // Try to create a test session to verify connectivity + credentials
            $result = niGatewayRequest('POST', '/session', ['apiOperation' => 'CREATE_CHECKOUT_SESSION']);
            echo json_encode([
                'success' => $result['success'],
                'configured' => !empty(getNIGatewaySettings()['ni_api_username']),
                'settings' => !empty(getNIGatewaySettings()['ni_api_username']),
                'gateway_result' => $result['success'] ? 'OK' : ($result['data']['error']['explanation'] ?? 'Unknown error'),
            ]);
            break;
            
        // ── Create Checkout Session (for billing.php / 3DS flow) ───────────────
        case 'create_checkout_session':
            requireCSRF();
            $db = Database::getInstance();
            $planKey = $_POST['plan_key'] ?? '';
            $billingCycle = $_POST['billing_cycle'] ?? 'monthly';
            $returnUrl = $_POST['return_url'] ?? 'https://app.funl.online/pages/billing.php';
            $companyId = (int)($_POST['company_id'] ?? 0);
            
            // Validate company context (tenant can only create session for own company)
            if ($companyId !== (int)getCurrentCompanyId() && !isSuperAdmin()) {
                echo json_encode(['success' => false, 'error' => 'Invalid company context']);
                break;
            }
            
            // Look up plan price
            $plan = $db->query("SELECT * FROM plans WHERE plan_key = ? AND is_active = 1", [$planKey])->fetch(PDO::FETCH_ASSOC);
            if (!$plan) {
                echo json_encode(['success' => false, 'error' => 'Plan not found']);
                break;
            }
            
            $amount = ($billingCycle === 'yearly') ? (float)$plan['yearly_price'] : (float)$plan['monthly_price'];
            $currency = 'USD';
            $orderId = 'ORD-' . strtoupper(bin2hex(random_bytes(6))) . '-' . $companyId;
            
            // Create checkout session at NI Gateway
            $result = niGatewayRequest('POST', '/session', [
                'apiOperation' => 'CREATE_CHECKOUT_SESSION',
                'session' => [
                    'authenticationLimit' => 5,                ],
            ]);
            
            if (!$result['success']) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to create checkout session',
                    'gateway_error' => $result['data']['error']['explanation'] ?? 'Unknown error',
                ]);
                break;
            }
            
            $niSessionId = $result['data']['session']['id'] ?? '';
            $aesKey = $result['data']['session']['aes256Key'] ?? '';
            
            // Store order in payment_transactions for audit trail
            $stmt = $db->prepare("INSERT INTO payment_transactions (company_id, order_id, session_id, plan_key, amount, currency, billing_cycle, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$companyId, $orderId, $niSessionId, $planKey, $amount, $currency, $billingCycle]);
            $txnId = dbLastInsertId();
            $db->query("UPDATE payment_transactions SET aes_key = ? WHERE id = ?", [$aesKey, $txnId]);
            
            echo json_encode([
                'success' => true,
                'session_id' => $niSessionId,
                'aes_key' => $aesKey,
                'order_id' => $orderId,
                'amount' => $amount,
                'currency' => $currency,
                'gateway_url' => getNIGatewaySettings()['ni_gateway_url'] ?? '',
                'return_url' => $returnUrl . '?status=success&session_id=' . $niSessionId,
            ]);
            break;
        
        // ── Create Order + Redirect to hosted checkout ──────────────────────────
        case 'create_order':
            try {
            requireCSRF();
            $db = Database::getInstance();
            $planKey = $_POST['plan_key'] ?? $_GET['plan_key'] ?? '';
            $billingCycle = $_POST['billing_cycle'] ?? $_GET['billing_cycle'] ?? 'monthly';
            $companyId = (int)($_POST['company_id'] ?? $_GET['company_id'] ?? getCurrentCompanyId());
            
            if (!$planKey || !$companyId) {
                if (isApiRequest()) {
                    echo json_encode(['success' => false, 'error' => 'Missing plan_key or company_id']);
                } else {
                    header('Location: /pages/billing.php?error=missing_params');
                }
                exit;
            }
            
            $plan = $db->query("SELECT * FROM plans WHERE plan_key = ? AND is_active = 1", [$planKey])->fetch(PDO::FETCH_ASSOC);
            if (!$plan) {
                if (isApiRequest()) {
                    echo json_encode(['success' => false, 'error' => 'Plan not found']);
                } else {
                    header('Location: /pages/billing.php?error=plan_not_found');
                }
                exit;
            }
            
            $amount = ($billingCycle === 'yearly') ? (float)$plan['yearly_price'] : (float)$plan['monthly_price'];
            $orderId = 'ORD-' . strtoupper(bin2hex(random_bytes(6))) . '-' . $companyId;
            
            // Create checkout session at NI Gateway
            $result = niGatewayRequest('POST', '/session', [
                'apiOperation' => 'CREATE_CHECKOUT_SESSION',
                'session' => [
                    'authenticationLimit' => 5,                ],
            ]);
            
            if (!$result['success']) {
                $err = $result['data']['error']['explanation'] ?? 'Failed to create checkout session';
                if (isApiRequest()) {
                    echo json_encode(['success' => false, 'error' => $err]);
                } else {
                    header('Location: /pages/billing.php?error=' . urlencode($err));
                }
                exit;
            }
            
            $niSessionId = $result['data']['session']['id'] ?? '';
            $aesKey = $result['data']['session']['aes256Key'] ?? '';
            
            // Store in payment_transactions
            $stmt = $db->prepare("INSERT INTO payment_transactions (company_id, order_id, session_id, plan_key, amount, currency, billing_cycle, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$companyId, $orderId, $niSessionId, $planKey, $amount, 'USD', $billingCycle]);
            $txnId = dbLastInsertId();
            $db->query("UPDATE payment_transactions SET aes_key = ? WHERE id = ?", [$aesKey, $txnId]);
            
            // Redirect to hosted checkout page
            $returnUrl = urlencode('https://app.funl.online/pages/billing.php?status=success&session_id=' . $niSessionId);
            $redirectUrl = 'https://app.funl.online/pages/ni-payment.php?session_id=' . urlencode($niSessionId) . '&order_id=' . urlencode($orderId) . '&return_url=' . $returnUrl;
            
            if (isApiRequest()) {
                echo json_encode([
                    'success' => true,
                    'redirect' => $redirectUrl,
                    'session_id' => $niSessionId,
                    'order_id' => $orderId,
                ]);
            } else {
                header('Location: ' . $redirectUrl);
            }
            exit;
        
            } catch (Exception $e) {
                file_put_contents('/tmp/ni_error.txt', '[' . date('Y-m-d H:i:s') . '] create_order error: ' . $e->getMessage() . "\n", FILE_APPEND);
                echo json_encode(['success' => false, 'error' => 'create_order failed: ' . $e->getMessage()]);
                break;
            }
        
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action. Use: create_session, verify_payment, get_session, health, create_checkout_session, create_order']);
    }
} catch (Exception $e) {
    safeJsonError($e, 'NI Gateway error: ');
}
