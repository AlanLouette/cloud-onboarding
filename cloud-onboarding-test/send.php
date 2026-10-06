<?php
/**
 * 4BS Cloud Migration Mailer - Email Sending Handler (WordPress Integration)
 */

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON responses
ini_set('log_errors', 1);

// Start session first
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Load WordPress FIRST (before config, before functions)
if (!function_exists('wp_mail')) {
    $wp_load_path = dirname(__FILE__);
    $max_depth = 10;
    $depth = 0;
    
    while ($depth < $max_depth) {
        if (file_exists($wp_load_path . '/wp-load.php')) {
            require_once $wp_load_path . '/wp-load.php';
            error_log("WordPress loaded successfully from: " . $wp_load_path);
            break;
        }
        $wp_load_path = dirname($wp_load_path);
        $depth++;
    }
    
    if (!function_exists('wp_mail')) {
        error_log("CRITICAL ERROR: Could not load WordPress wp_mail()");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'WordPress not loaded. Check error logs.'], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}

// Now load config and functions
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

function sendJson($payload, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');

    $json = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        $json = json_encode([
            'success' => false,
            'message' => 'Failed to encode server response'
        ]);
    }

    echo $json;
}

// Check authentication
if (!isAuthenticated()) {
    sendJson(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Log the action
error_log("Send.php action: " . $action);

switch ($action) {
    case 'send_single':
        handleSendSingle();
        break;
        
    case 'preview':
        handlePreview();
        break;
        
    case 'upload_csv':
        handleUploadCSV();
        break;
        
    case 'send_bulk':
        handleSendBulk();
        break;
        
    default:
        sendJson(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Handle single email send
 */
function handleSendSingle() {
    error_log("handleSendSingle() called");

    $templateId = (int)($_POST['template_id'] ?? 0);
    $template = getTemplateById($templateId);
    if (!$template) {
        sendJson(['success' => false, 'message' => 'Invalid template selected']);
        return;
    }

    $variables = [];
    foreach (($_POST['variables'] ?? []) as $key => $value) {
        $variables[strtoupper($key)] = trim($value);
    }

    $toggles = [];
    foreach (($_POST['toggles'] ?? []) as $key => $value) {
        $toggles[strtoupper($key)] = (int)$value === 1;
    }

    $data = [
        'email' => sanitize($_POST['email'] ?? ''),
        'language' => strtolower(sanitize($_POST['language'] ?? 'nl')),
        'variables' => $variables,
        'toggles' => $toggles,
        'template_id' => $templateId
    ];
    
    error_log("Email data: " . json_encode($data));
    
    // Validate required fields
    if (empty($data['email'])) {
        sendJson(['success' => false, 'message' => 'Recipient email is required']);
        return;
    }
    
    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        sendJson(['success' => false, 'message' => 'Invalid email address']);
        return;
    }
    
    $fields = getTemplateFields($template, $data['language']);
    foreach ($fields['variables'] as $field) {
        if (empty($data['variables'][$field])) {
            sendJson(['success' => false, 'message' => "Missing required field: {$field}"]);
            return;
        }
    }
    
    // Verify wp_mail is available
    if (!function_exists('wp_mail')) {
        error_log("CRITICAL: wp_mail() not available in handleSendSingle()");
        sendJson(['success' => false, 'message' => 'WordPress mail function not available']);
        return;
    }
    
    error_log("Calling sendEmail() function...");
    
    // Send email
    $result = sendEmail($data, $template);

    logSendOperation(getCurrentUsername(), 'single', $templateId, 1, $result['success'] ? 1 : 0, $result['success'] ? 0 : 1);
    
    error_log("sendEmail() result: " . json_encode($result));
    
    sendJson($result);
}

/**
 * Handle email preview
 */
function handlePreview() {
    $templateId = (int)($_POST['template_id'] ?? 0);
    $template = getTemplateById($templateId);
    if (!$template) {
        http_response_code(400);
        echo 'Invalid template';
        exit;
    }

    $variables = [];
    foreach (($_POST['variables'] ?? []) as $key => $value) {
        $variables[strtoupper($key)] = trim($value);
    }

    $toggles = [];
    foreach (($_POST['toggles'] ?? []) as $key => $value) {
        $toggles[strtoupper($key)] = (int)$value === 1;
    }

    $data = [
        'email' => 'preview@example.com',
        'language' => strtolower(sanitize($_POST['language'] ?? 'nl')),
        'variables' => $variables,
        'toggles' => $toggles,
        'template_id' => $templateId
    ];
    
    $rendered = renderEmailTemplate($template, $data);
    $html = $rendered['html'];
    
    // Return as HTML for iframe
    header('Content-Type: text/html');
    echo $html;
    exit;
}

/**
 * Handle CSV upload and validation
 */
function handleUploadCSV() {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        sendJson(['success' => false, 'message' => 'File upload failed']);
        return;
    }
    
    // Check file type
    $fileInfo = pathinfo($_FILES['csv_file']['name']);
    if (strtolower($fileInfo['extension']) !== 'csv') {
        sendJson(['success' => false, 'message' => 'Only CSV files are allowed']);
        return;
    }
    
    // Move uploaded file
    $uploadFile = UPLOAD_DIR . uniqid('csv_') . '.csv';
    if (!move_uploaded_file($_FILES['csv_file']['tmp_name'], $uploadFile)) {
        sendJson(['success' => false, 'message' => 'Failed to save uploaded file']);
        return;
    }
    
    $templateId = (int)($_POST['template_id'] ?? 0);
    $template = getTemplateById($templateId);
    if (!$template) {
        sendJson(['success' => false, 'message' => 'Invalid template selected']);
        return;
    }

    // Process CSV
    $results = processCSV($uploadFile, $template);
    
    if (isset($results['error'])) {
        sendJson(['success' => false, 'message' => $results['error']]);
        return;
    }
    
    // Store valid emails in session for bulk send
    $_SESSION['bulk_emails'] = $results['valid'];
    $_SESSION['bulk_template_id'] = $templateId;
    $_SESSION['csv_file'] = $uploadFile;
    $_SESSION['bulk_progress'] = [
        'total' => count($results['valid']),
        'sent' => 0,
        'failed' => 0,
        'results' => []
    ];
    $_SESSION['bulk_index'] = 0;
    unset($_SESSION['bulk_next_send_at']);
    
    sendJson([
        'success' => true,
        'valid' => $results['valid'],
        'invalid' => $results['invalid'],
        'total_valid' => count($results['valid']),
        'total_invalid' => count($results['invalid'])
    ]);
}

/**
 * Handle bulk email send
 */
function handleSendBulk() {
    if (!isset($_SESSION['bulk_emails']) || empty($_SESSION['bulk_emails'])) {
        sendJson(['success' => false, 'message' => 'No emails to send. Please upload CSV first.']);
        return;
    }

    $templateId = (int)($_SESSION['bulk_template_id'] ?? 0);
    $template = getTemplateById($templateId);
    if (!$template) {
        sendJson(['success' => false, 'message' => 'Template not found for bulk send.']);
        return;
    }
    
    if (!isset($_SESSION['bulk_progress']) || !is_array($_SESSION['bulk_progress'])) {
        $_SESSION['bulk_progress'] = [
            'total' => count($_SESSION['bulk_emails']),
            'sent' => 0,
            'failed' => 0,
            'results' => []
        ];
    }

    $batchSize = max(1, min(10, (int)($_POST['batch_size'] ?? 1)));
    $emails = $_SESSION['bulk_emails'];
    $totalEmails = count($emails);
    $currentIndex = (int)($_SESSION['bulk_index'] ?? 0);
    $progress = &$_SESSION['bulk_progress'];
    $processedThisBatch = 0;

    error_log("Starting bulk send batch. Current index: {$currentIndex}, total: {$totalEmails}, batch size: {$batchSize}");

    while ($currentIndex < $totalEmails && $processedThisBatch < $batchSize) {
        $nextSendAt = (int)($_SESSION['bulk_next_send_at'] ?? 0);
        $sleepSeconds = $nextSendAt - time();
        if ($sleepSeconds > 0) {
            sleep($sleepSeconds);
        }

        $emailData = $emails[$currentIndex];
        $emailData['template_id'] = $templateId;
        $result = sendEmail($emailData, $template);

        $progress['results'][] = [
            'email' => $emailData['email'],
            'status' => $result['success'] ? 'sent' : 'failed',
            'message' => $result['message']
        ];

        if ($result['success']) {
            $progress['sent']++;
        } else {
            $progress['failed']++;
        }

        $processedThisBatch++;
        $currentIndex++;
        $_SESSION['bulk_index'] = $currentIndex;
        $_SESSION['bulk_next_send_at'] = time() + RATE_LIMIT_DELAY;
    }

    $current = $progress['sent'] + $progress['failed'];
    $complete = $currentIndex >= $totalEmails;

    if ($complete) {
        error_log("Bulk send complete. Sent: {$progress['sent']}, Failed: {$progress['failed']}");

        logSendOperation(getCurrentUsername(), 'bulk', $templateId, $progress['total'], $progress['sent'], $progress['failed']);

        if (isset($_SESSION['csv_file']) && file_exists($_SESSION['csv_file'])) {
            unlink($_SESSION['csv_file']);
        }

        $finalResponse = [
            'success' => true,
            'complete' => true,
            'current' => $current,
            'total' => $progress['total'],
            'sent' => $progress['sent'],
            'failed' => $progress['failed'],
            'results' => $progress['results']
        ];

        unset($_SESSION['bulk_emails']);
        unset($_SESSION['bulk_template_id']);
        unset($_SESSION['csv_file']);
        unset($_SESSION['bulk_progress']);
        unset($_SESSION['bulk_index']);
        unset($_SESSION['bulk_next_send_at']);

        sendJson($finalResponse);
        return;
    }

    sendJson([
        'success' => true,
        'complete' => false,
        'current' => $current,
        'total' => $progress['total'],
        'sent' => $progress['sent'],
        'failed' => $progress['failed']
    ]);
}
