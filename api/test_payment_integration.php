<?php
// File: api/test_payment_integration.php
header('Content-Type: application/json');
require_once '../config/db.php';
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $results = [];
    
    // Test database connection
    if ($conn->ping()) {
        $results[] = [
            'method' => 'Database Connection',
            'status' => 'success',
            'message' => 'Connected successfully'
        ];
    } else {
        $results[] = [
            'method' => 'Database Connection',
            'status' => 'failed',
            'message' => 'Connection failed'
        ];
    }
    
    // Test cash payments (always available)
    $results[] = [
        'method' => 'Cash Payments',
        'status' => 'success',
        'message' => 'Available for all branches'
    ];
    
    // Test card payments settings
    $settings_sql = "SELECT setting_value FROM settings WHERE setting_key = 'payment_enable_card'";
    $settings_result = $conn->query($settings_sql);
    $card_enabled = $settings_result->fetch_assoc()['setting_value'] ?? '0';
    
    if ($card_enabled === '1') {
        $card_provider_sql = "SELECT setting_value FROM settings WHERE setting_key = 'payment_card_provider'";
        $card_provider_result = $conn->query($card_provider_sql);
        $card_provider = $card_provider_result->fetch_assoc()['setting_value'] ?? 'manual';
        
        if ($card_provider === 'manual') {
            $results[] = [
                'method' => 'Card Payments (Manual)',
                'status' => 'success',
                'message' => 'Manual card entry enabled'
            ];
        } else {
            // Test API key existence
            $api_key = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'payment_{$card_provider}_api_key'")->fetch_assoc();
            if (!empty($api_key['setting_value'])) {
                $results[] = [
                    'method' => "Card Payments ({$card_provider})",
                    'status' => 'success',
                    'message' => 'API key configured'
                ];
            } else {
                $results[] = [
                    'method' => "Card Payments ({$card_provider})",
                    'status' => 'failed',
                    'message' => 'API key not configured'
                ];
            }
        }
    } else {
        $results[] = [
            'method' => 'Card Payments',
            'status' => 'inactive',
            'message' => 'Disabled in settings'
        ];
    }
    
    // Test mobile money
    $mobile_sql = "SELECT setting_value FROM settings WHERE setting_key = 'payment_enable_mobile'";
    $mobile_result = $conn->query($mobile_sql);
    $mobile_enabled = $mobile_result->fetch_assoc()['setting_value'] ?? '0';
    
    if ($mobile_enabled === '1') {
        $mobile_providers_sql = "SELECT setting_value FROM settings WHERE setting_key = 'payment_mobile_providers'";
        $mobile_providers_result = $conn->query($mobile_providers_sql);
        $mobile_providers = $mobile_providers_result->fetch_assoc()['setting_value'] ?? '';
        
        if (!empty($mobile_providers)) {
            $results[] = [
                'method' => 'Mobile Money',
                'status' => 'success',
                'message' => 'Providers: ' . $mobile_providers
            ];
        } else {
            $results[] = [
                'method' => 'Mobile Money',
                'status' => 'warning',
                'message' => 'No providers configured'
            ];
        }
    } else {
        $results[] = [
            'method' => 'Mobile Money',
            'status' => 'inactive',
            'message' => 'Disabled in settings'
        ];
    }
    
    // Test credit sales
    $credit_sql = "SELECT setting_value FROM settings WHERE setting_key = 'payment_enable_credit'";
    $credit_result = $conn->query($credit_sql);
    $credit_enabled = $credit_result->fetch_assoc()['setting_value'] ?? '0';
    
    if ($credit_enabled === '1') {
        $results[] = [
            'method' => 'Credit Sales',
            'status' => 'success',
            'message' => 'Enabled with approval required'
        ];
    } else {
        $results[] = [
            'method' => 'Credit Sales',
            'status' => 'inactive',
            'message' => 'Disabled in settings'
        ];
    }
    
    // Test recent transactions
    $transactions_sql = "SELECT COUNT(*) as count FROM payment_transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $transactions_result = $conn->query($transactions_sql);
    $transaction_count = $transactions_result->fetch_assoc()['count'] ?? 0;
    
    $results[] = [
        'method' => 'Transaction History',
        'status' => 'success',
        'message' => "{$transaction_count} transactions in last 7 days"
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment integration test completed',
        'results' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Test failed: ' . $e->getMessage()
    ]);
}
?>