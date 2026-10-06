<?php
/**
 * 4BS Cloud Migration Mailer - Helper Functions (WordPress Integration)
 * 
 * Note: WordPress is loaded by send.php and index.php before this file
 */

/**
 * Normalize text values to valid UTF-8 so CSV imports from Excel/Windows encodings
 * do not break JSON responses or email rendering.
 */
function normalizeTextEncoding($value) {
    if (!is_string($value) || $value === '') {
        return $value;
    }

    if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
        return $value;
    }

    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
    }

    if (function_exists('iconv')) {
        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        if ($converted !== false) {
            return $converted;
        }
    }

    return $value;
}

function stripUtf8Bom($value) {
    if (!is_string($value)) {
        return $value;
    }

    return preg_replace('/^\xEF\xBB\xBF/', '', $value);
}

function detectCsvDelimiter($filepath) {
    $sample = file_get_contents($filepath, false, null, 0, 4096);
    if ($sample === false || $sample === '') {
        return ',';
    }

    $sample = stripUtf8Bom(normalizeTextEncoding($sample));
    $firstLine = strtok($sample, "\r\n");
    if ($firstLine === false) {
        return ',';
    }

    $commaCount = substr_count($firstLine, ',');
    $semicolonCount = substr_count($firstLine, ';');

    return $semicolonCount > $commaCount ? ';' : ',';
}

function readCsvRow($handle, $delimiter) {
    return fgetcsv($handle, 0, $delimiter, '"', '');
}

/**
 * Get SQLite database connection and ensure schema
 */
function getDb() {
    static $db = null;

    if ($db instanceof SQLite3) {
        return $db;
    }

    $db = new SQLite3(DB_PATH);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL;');

    $db->exec('CREATE TABLE IF NOT EXISTS templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        base_language TEXT NOT NULL,
        enable_en INTEGER DEFAULT 0,
        enable_fr INTEGER DEFAULT 0,
        subject_nl TEXT,
        subject_en TEXT,
        subject_fr TEXT,
        body_nl TEXT,
        body_en TEXT,
        body_fr TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');

    ensureTemplateLanguageColumns($db);

    $db->exec('CREATE TABLE IF NOT EXISTS send_operations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        action TEXT NOT NULL,
        template_id INTEGER,
        total_count INTEGER NOT NULL,
        success_count INTEGER NOT NULL,
        failed_count INTEGER NOT NULL,
        created_at TEXT NOT NULL
    )');

    seedDefaultTemplate($db);

    return $db;
}

/**
 * Ensure template language columns exist for older databases
 */
function ensureTemplateLanguageColumns($db) {
    $columns = [];
    $result = $db->query('PRAGMA table_info(templates)');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }

    if (!in_array('enable_en', $columns, true)) {
        $db->exec('ALTER TABLE templates ADD COLUMN enable_en INTEGER DEFAULT 0');
    }
    if (!in_array('enable_fr', $columns, true)) {
        $db->exec('ALTER TABLE templates ADD COLUMN enable_fr INTEGER DEFAULT 0');
    }
}

/**
 * Seed initial template if none exist
 */
function seedDefaultTemplate($db) {
    $result = $db->querySingle('SELECT COUNT(*) FROM templates');
    if ((int)$result > 0) {
        return;
    }

    $now = date('Y-m-d H:i:s');
    $bodyNl = <<<HTML
<p>Beste <strong>**NAME**</strong>,</p>
<p>Uw 4BSCLOUD migratie staat gepland op <strong>**DATE**</strong>.</p>
<p>U kunt inloggen via deze link: <a href="**LOGIN_LINK**">**LOGIN_LINK**</a></p>
[[IF SINGLE_APP_ONLY]]
<p><strong>Let op:</strong> u gebruikt slechts één applicatie op de cloud.</p>
[[ENDIF]]
<p>Met vriendelijke groeten,<br>Het 4BS Cloud team</p>
HTML;

    $bodyEn = <<<HTML
<p>Hello <strong>**NAME**</strong>,</p>
<p>Your 4BSCLOUD migration is scheduled for <strong>**DATE**</strong>.</p>
<p>You can log in via this link: <a href="**LOGIN_LINK**">**LOGIN_LINK**</a></p>
[[IF SINGLE_APP_ONLY]]
<p><strong>Note:</strong> you only use one application on the cloud.</p>
[[ENDIF]]
<p>Kind regards,<br>The 4BS Cloud team</p>
HTML;

    $bodyFr = <<<HTML
<p>Bonjour <strong>**NAME**</strong>,</p>
<p>Votre migration 4BSCLOUD est planifiée pour le <strong>**DATE**</strong>.</p>
<p>Vous pouvez vous connecter via ce lien : <a href="**LOGIN_LINK**">**LOGIN_LINK**</a></p>
[[IF SINGLE_APP_ONLY]]
<p><strong>Remarque :</strong> vous utilisez une seule application sur le cloud.</p>
[[ENDIF]]
<p>Cordialement,<br>L'équipe 4BS Cloud</p>
HTML;

    $stmt = $db->prepare('
        INSERT INTO templates
        (name, description, base_language, enable_en, enable_fr, subject_nl, subject_en, subject_fr, body_nl, body_en, body_fr, created_at, updated_at)
        VALUES (:name, :description, :base_language, :enable_en, :enable_fr, :subject_nl, :subject_en, :subject_fr, :body_nl, :body_en, :body_fr, :created_at, :updated_at)
    ');
    $stmt->bindValue(':name', '4BS Cloud Migration');
    $stmt->bindValue(':description', 'Default migration template with login link and optional single-app notice.');
    $stmt->bindValue(':base_language', 'nl');
    $stmt->bindValue(':enable_en', 1);
    $stmt->bindValue(':enable_fr', 1);
    $stmt->bindValue(':subject_nl', 'Uw 4BSCLOUD migratie op **DATE**');
    $stmt->bindValue(':subject_en', 'Your 4BSCLOUD migration on **DATE**');
    $stmt->bindValue(':subject_fr', 'Votre migration 4BSCLOUD le **DATE**');
    $stmt->bindValue(':body_nl', $bodyNl);
    $stmt->bindValue(':body_en', $bodyEn);
    $stmt->bindValue(':body_fr', $bodyFr);
    $stmt->bindValue(':created_at', $now);
    $stmt->bindValue(':updated_at', $now);
    $stmt->execute();
}

/**
 * Get templates list
 */
function getTemplates() {
    $db = getDb();
    $result = $db->query('SELECT * FROM templates ORDER BY created_at DESC');
    $templates = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $templates[] = $row;
    }
    return $templates;
}

/**
 * Get template by id
 */
function getTemplateById($templateId) {
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM templates WHERE id = :id');
    $stmt->bindValue(':id', (int)$templateId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $template = $result->fetchArray(SQLITE3_ASSOC);
    return $template ?: null;
}

/**
 * Save template (create or update)
 */
function saveTemplate($data, $templateId = null) {
    $db = getDb();
    $now = date('Y-m-d H:i:s');

    if ($templateId) {
        $stmt = $db->prepare('
            UPDATE templates
            SET name = :name,
                description = :description,
                base_language = :base_language,
                enable_en = :enable_en,
                enable_fr = :enable_fr,
                subject_nl = :subject_nl,
                subject_en = :subject_en,
                subject_fr = :subject_fr,
                body_nl = :body_nl,
                body_en = :body_en,
                body_fr = :body_fr,
                updated_at = :updated_at
            WHERE id = :id
        ');
        $stmt->bindValue(':id', (int)$templateId, SQLITE3_INTEGER);
    } else {
        $stmt = $db->prepare('
        INSERT INTO templates
        (name, description, base_language, enable_en, enable_fr, subject_nl, subject_en, subject_fr, body_nl, body_en, body_fr, created_at, updated_at)
        VALUES (:name, :description, :base_language, :enable_en, :enable_fr, :subject_nl, :subject_en, :subject_fr, :body_nl, :body_en, :body_fr, :created_at, :updated_at)
    ');
        $stmt->bindValue(':created_at', $now);
    }

    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':description', $data['description']);
    $stmt->bindValue(':base_language', $data['base_language']);
    $stmt->bindValue(':enable_en', (int)$data['enable_en'], SQLITE3_INTEGER);
    $stmt->bindValue(':enable_fr', (int)$data['enable_fr'], SQLITE3_INTEGER);
    $stmt->bindValue(':subject_nl', $data['subject_nl']);
    $stmt->bindValue(':subject_en', $data['subject_en']);
    $stmt->bindValue(':subject_fr', $data['subject_fr']);
    $stmt->bindValue(':body_nl', $data['body_nl']);
    $stmt->bindValue(':body_en', $data['body_en']);
    $stmt->bindValue(':body_fr', $data['body_fr']);
    $stmt->bindValue(':updated_at', $now);
    $stmt->execute();

    return $templateId ?: $db->lastInsertRowID();
}

/**
 * Delete template
 */
function deleteTemplate($templateId) {
    $db = getDb();
    $stmt = $db->prepare('DELETE FROM templates WHERE id = :id');
    $stmt->bindValue(':id', (int)$templateId, SQLITE3_INTEGER);
    $stmt->execute();
}

/**
 * Log a send operation for history tracking
 */
function logSendOperation($username, $action, $templateId, $totalCount, $successCount, $failedCount) {
    $db = getDb();
    $stmt = $db->prepare('
        INSERT INTO send_operations
        (username, action, template_id, total_count, success_count, failed_count, created_at)
        VALUES (:username, :action, :template_id, :total_count, :success_count, :failed_count, :created_at)
    ');
    $stmt->bindValue(':username', $username);
    $stmt->bindValue(':action', $action);
    $stmt->bindValue(':template_id', $templateId ? (int)$templateId : null, SQLITE3_INTEGER);
    $stmt->bindValue(':total_count', (int)$totalCount, SQLITE3_INTEGER);
    $stmt->bindValue(':success_count', (int)$successCount, SQLITE3_INTEGER);
    $stmt->bindValue(':failed_count', (int)$failedCount, SQLITE3_INTEGER);
    $stmt->bindValue(':created_at', date('Y-m-d H:i:s'));
    $stmt->execute();
}

/**
 * Get send operation history
 */
function getSendOperations($limit = 50) {
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM send_operations ORDER BY created_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', (int)$limit, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $operations = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $operations[] = $row;
    }
    return $operations;
}

/**
 * Extract variables and toggles from template HTML
 */
function extractTemplateFields($html) {
    $variables = [];
    $toggles = [];

    if ($html) {
        if (preg_match_all('/\*\*([A-Za-z0-9_]+)\*\*/', $html, $matches)) {
            foreach ($matches[1] as $name) {
                $variables[] = strtoupper($name);
            }
        }

        if (preg_match_all('/\[\[IF\s+([A-Za-z0-9_]+)\s*\]\]/', $html, $matches)) {
            foreach ($matches[1] as $name) {
                $toggles[] = strtoupper($name);
            }
        }
    }

    return [
        'variables' => array_values(array_unique($variables)),
        'toggles' => array_values(array_unique($toggles))
    ];
}

/**
 * Get template field definitions from subject and body
 */
function getTemplateFields($template, $language = null) {
    $content = getTemplateContent($template, $language ?? $template['base_language']);
    $combined = ($content['subject'] ?? '') . ' ' . ($content['body'] ?? '');
    return extractTemplateFields($combined);
}

/**
 * Sanitize template HTML (basic)
 */
function sanitizeTemplateHtml($html) {
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
    $html = preg_replace('/on\w+="[^"]*"/i', '', $html);
    $html = preg_replace("/on\w+='[^']*'/i", '', $html);

    $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><a><span><div><h1><h2><h3><h4><table><thead><tbody><tr><td><th>';
    $html = strip_tags($html, $allowedTags);
    return normalizeCssColorsToHex($html);
}

/**
 * Get subject/body for a language with fallback to base language
 */
function getTemplateContent($template, $language) {
    $language = in_array($language, SUPPORTED_LANGUAGES, true) ? $language : $template['base_language'];
    $subjectKey = 'subject_' . $language;
    $bodyKey = 'body_' . $language;
    $baseSubjectKey = 'subject_' . $template['base_language'];
    $baseBodyKey = 'body_' . $template['base_language'];

    return [
        'subject' => $template[$subjectKey] ?: $template[$baseSubjectKey],
        'body' => $template[$bodyKey] ?: $template[$baseBodyKey]
    ];
}

/**
 * Apply conditional blocks and variables to HTML
 */
function applyTemplateData($html, $variables, $toggles) {
    $processed = preg_replace_callback('/\[\[IF\s+([A-Za-z0-9_]+)\s*\]\](.*?)\[\[ENDIF\]\]/s', function ($matches) use ($toggles) {
        $key = strtoupper($matches[1]);
        // Don't wrap in another div - the content already has the styled div inside
        return !empty($toggles[$key]) ? $matches[2] : '';
    }, $html);

    $processed = preg_replace_callback('/\*\*([A-Za-z0-9_]+)\*\*/', function ($matches) use ($variables) {
        $key = strtoupper($matches[1]);
        $value = $variables[$key] ?? '';
        $value = nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
        return $value;
    }, $processed);

    return $processed;
}

/**
 * Render email template with layout
 */
function renderEmailTemplate($template, $data) {
    $content = getTemplateContent($template, $data['language']);
    $subject = $content['subject'];
    $body = $content['body'];
    $body = applyTemplateData($body, $data['variables'], $data['toggles']);

    $layoutFile = __DIR__ . '/templates/email-layout.php';
    $bodyContent = $body;
    $subjectLine = applyTemplateData($subject, $data['variables'], $data['toggles']);

    ob_start();
    include $layoutFile;
    $html = ob_get_clean();
    $html = normalizeCssColorsToHex($html);

    return [
        'subject' => $subjectLine,
        'html' => $html
    ];
}

/**
 * Convert rgb()/rgba()/hsl()/hsla() CSS colors to HEX notation.
 */
function normalizeCssColorsToHex($html) {
    if (
        stripos($html, 'hsl(') === false &&
        stripos($html, 'hsla(') === false &&
        stripos($html, 'rgb(') === false &&
        stripos($html, 'rgba(') === false
    ) {
        return $html;
    }

    $normalizeRgbComponent = function ($value) {
        $value = trim($value);
        if (str_ends_with($value, '%')) {
            $percent = (float)rtrim($value, '%');
            return (int)round(max(0, min(100, $percent)) * 255 / 100);
        }

        return (int)round(max(0, min(255, (float)$value)));
    };

    $toHex = function ($r, $g, $b, $a = null) {
        $hex = sprintf('#%02X%02X%02X', $r, $g, $b);
        if ($a !== null) {
            $alpha = (int)round(max(0.0, min(1.0, $a)) * 255);
            $hex .= sprintf('%02X', $alpha);
        }
        return $hex;
    };

    // rgb/rgba -> hex
    $html = preg_replace_callback(
        '/rgba?\(\s*([^,\)]+)\s*,\s*([^,\)]+)\s*,\s*([^,\)]+)\s*(?:,\s*([0-9]*\.?[0-9]+)\s*)?\)/i',
        function ($matches) use ($normalizeRgbComponent, $toHex) {
            $r = $normalizeRgbComponent($matches[1]);
            $g = $normalizeRgbComponent($matches[2]);
            $b = $normalizeRgbComponent($matches[3]);
            $alpha = isset($matches[4]) && $matches[4] !== '' ? (float)$matches[4] : null;

            return $toHex($r, $g, $b, $alpha);
        },
        $html
    );

    // hsl/hsla -> hex
    return preg_replace_callback(
        '/hsla?\(\s*(-?\d*\.?\d+)\s*(?:deg)?\s*,\s*(-?\d*\.?\d+)%\s*,\s*(-?\d*\.?\d+)%\s*(?:,\s*(-?\d*\.?\d+)\s*)?\)/i',
        function ($matches) use ($toHex) {
            $hue = fmod((float)$matches[1], 360.0);
            if ($hue < 0) {
                $hue += 360.0;
            }

            $sat = max(0.0, min(100.0, (float)$matches[2])) / 100.0;
            $light = max(0.0, min(100.0, (float)$matches[3])) / 100.0;

            $red = $light;
            $green = $light;
            $blue = $light;

            if ($sat > 0) {
                $chroma = (1 - abs(2 * $light - 1)) * $sat;
                $hPrime = $hue / 60.0;
                $x = $chroma * (1 - abs(fmod($hPrime, 2) - 1));

                if ($hPrime >= 0 && $hPrime < 1) {
                    [$red, $green, $blue] = [$chroma, $x, 0];
                } elseif ($hPrime >= 1 && $hPrime < 2) {
                    [$red, $green, $blue] = [$x, $chroma, 0];
                } elseif ($hPrime >= 2 && $hPrime < 3) {
                    [$red, $green, $blue] = [0, $chroma, $x];
                } elseif ($hPrime >= 3 && $hPrime < 4) {
                    [$red, $green, $blue] = [0, $x, $chroma];
                } elseif ($hPrime >= 4 && $hPrime < 5) {
                    [$red, $green, $blue] = [$x, 0, $chroma];
                } else {
                    [$red, $green, $blue] = [$chroma, 0, $x];
                }

                $m = $light - ($chroma / 2);
                $red += $m;
                $green += $m;
                $blue += $m;
            }

            $r = (int)round(max(0, min(255, $red * 255)));
            $g = (int)round(max(0, min(255, $green * 255)));
            $b = (int)round(max(0, min(255, $blue * 255)));
            $alpha = isset($matches[4]) && $matches[4] !== '' ? (float)$matches[4] : null;

            return $toHex($r, $g, $b, $alpha);
        },
        $html
    );
}

/**
 * Send email via WordPress wp_mail() (uses WP Mail SMTP Pro)
 */
function sendEmail($data, $template) {
    try {
        // Verify wp_mail exists
        if (!function_exists('wp_mail')) {
            error_log("FATAL: wp_mail() function does not exist!");
            return ['success' => false, 'message' => 'WordPress not loaded correctly'];
        }
        
        error_log("=== FUNCTIONS.PHP: sendEmail() called ===");
        error_log("Sending to: " . $data['email']);
        error_log("Language: " . $data['language']);
        
        $rendered = renderEmailTemplate($template, $data);
        $subject = $rendered['subject'];
        $templateHtml = $rendered['html'];

        if (stripos($templateHtml, 'hsl(') !== false || stripos($templateHtml, 'hsla(') !== false || stripos($templateHtml, 'rgb(') !== false || stripos($templateHtml, 'rgba(') !== false) {
            error_log('Functional CSS colors detected in email HTML. Converting to HEX fallback for compatibility.');
            $templateHtml = normalizeCssColorsToHex($templateHtml);
        }
        
        error_log("Subject: " . $subject);
        error_log("Template length: " . strlen($templateHtml) . " characters");
        
        // Set email headers for HTML
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
            'Reply-To: ' . REPLY_TO_EMAIL
        ];
        
        error_log("=== Calling wp_mail() ===");
        
        // Send email using WordPress
        $sent = wp_mail(
            $data['email'],
            $subject,
            $templateHtml,
            $headers
        );
        
        error_log("wp_mail() returned: " . ($sent ? 'TRUE' : 'FALSE'));
        
        if ($sent) {
            error_log("✓ Email sent successfully to: " . $data['email']);
            logEmail($data, 'success');
            return ['success' => true, 'message' => 'Email sent successfully'];
        } else {
            // Check for PHPMailer errors
            global $phpmailer;
            $error = 'WordPress wp_mail() returned false.';
            if (isset($phpmailer) && isset($phpmailer->ErrorInfo)) {
                $error .= ' PHPMailer: ' . $phpmailer->ErrorInfo;
            }
            error_log("✗ Email send failed: " . $error);
            logEmail($data, 'failed', $error);
            return ['success' => false, 'message' => $error];
        }
        
    } catch (Exception $e) {
        $errorMsg = 'Exception: ' . $e->getMessage();
        error_log("✗ Email send exception: " . $errorMsg);
        logEmail($data, 'failed', $errorMsg);
        return ['success' => false, 'message' => $errorMsg];
    }
}

/**
 * Get email subject based on language
 */
/**
 * Get basic email stats from log files
 */
function getEmailStats() {
    $stats = [
        'total_sent' => 0,
        'sent_today' => 0,
        'sent_week' => 0
    ];

    $logFiles = glob(LOG_DIR . '*.csv') ?: [];
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));

    foreach ($logFiles as $file) {
        if (($handle = fopen($file, 'r')) === false) {
            continue;
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 5) {
                continue;
            }

            $timestamp = $row[0];
            $status = $row[4];
            if ($status !== 'success') {
                continue;
            }

            $date = substr($timestamp, 0, 10);
            $stats['total_sent']++;
            if ($date === $today) {
                $stats['sent_today']++;
            }
            if ($date >= $weekStart) {
                $stats['sent_week']++;
            }
        }

        fclose($handle);
    }

    return $stats;
}

/**
 * Process and validate CSV file
 */
function processCSV($filepath, $template) {
    $results = [
        'valid' => [],
        'invalid' => [],
        'fixed' => []
    ];
    
    if (!file_exists($filepath)) {
        return ['error' => 'File not found'];
    }
    
    $delimiter = detectCsvDelimiter($filepath);
    $handle = fopen($filepath, 'r');
    $headers = readCsvRow($handle, $delimiter);
    $headers = array_map(function ($header) {
        return trim(stripUtf8Bom(normalizeTextEncoding($header)));
    }, $headers ?: []);

    $fields = getTemplateFields($template);
    $requiredHeaders = array_merge(['email', 'language'], array_map('strtolower', $fields['variables']), array_map('strtolower', $fields['toggles']));
    
    $rowNumber = 1;
    $headerIndex = [];
    foreach ($headers as $index => $header) {
        $headerIndex[strtolower($header)] = $index;
    }

    $missingHeaders = array_diff($requiredHeaders, array_keys($headerIndex));
    if (!empty($missingHeaders)) {
        return ['error' => 'Missing required columns: ' . implode(', ', $missingHeaders)];
    }
    while (($row = readCsvRow($handle, $delimiter)) !== false) {
        $rowNumber++;
        $row = array_map(function ($value) {
            return is_string($value) ? normalizeTextEncoding($value) : $value;
        }, $row);
        
        $data = [
            'email' => trim(strtolower($row[$headerIndex['email']] ?? '')),
            'language' => strtolower(trim($row[$headerIndex['language']] ?? ''))
        ];

        $variables = [];
        foreach ($fields['variables'] as $field) {
            $key = strtolower($field);
            $variables[$field] = trim($row[$headerIndex[$key]] ?? '');
        }

        $toggles = [];
        foreach ($fields['toggles'] as $field) {
            $key = strtolower($field);
            $toggles[$field] = (int)trim($row[$headerIndex[$key]] ?? 0) === 1;
        }
        
        $fixed = [];
        
        // Auto-fix email
        if ($data['email'] !== $row[0]) {
            $fixed[] = 'email trimmed/lowercased';
        }
        
        // Auto-fix language
        if (!in_array($data['language'], SUPPORTED_LANGUAGES)) {
            $data['language'] = 'nl';
            $fixed[] = 'language set to default (nl)';
        }
        
        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $results['invalid'][] = [
                'row' => $rowNumber,
                'data' => $data,
                'reason' => 'Invalid email address'
            ];
            continue;
        }
        
        // Validate required variables
        foreach ($fields['variables'] as $field) {
            if ($variables[$field] === '') {
                $results['invalid'][] = [
                    'row' => $rowNumber,
                    'data' => $data,
                    'reason' => "Missing value for {$field}"
                ];
                continue 2;
            }
        }

        $data['variables'] = $variables;
        $data['toggles'] = $toggles;
        
        $data['fixed'] = $fixed;
        $data['row'] = $rowNumber;
        $results['valid'][] = $data;
    }
    
    fclose($handle);
    return $results;
}

/**
 * Log email send attempt
 */
function logEmail($data, $status, $error = '') {
    $logFile = LOG_DIR . date('Y-m-d') . '.csv';
    $logEntry = [
        date('Y-m-d H:i:s'),
        $data['email'],
        $data['template_id'] ?? '',
        $data['language'] ?? '',
        $status,
        $error
    ];
    
    $fp = fopen($logFile, 'a');
    fputcsv($fp, $logEntry);
    fclose($fp);
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Authenticate user
 */
function authenticate($username, $password) {
    if ($username === ADMIN_USER && password_verify($password, ADMIN_PASS)) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        return true;
    }
    return false;
}

/**
 * Get the current authenticated username
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? 'admin';
}

/**
 * Logout user
 */
function logout() {
    unset($_SESSION['username']);
    session_destroy();
    header('Location: index.php');
    exit;
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Format validation errors for display
 */
function formatValidationErrors($errors) {
    if (empty($errors)) {
        return '';
    }
    
    $html = '<div class="alert alert-warning"><strong>Validation Issues:</strong><ul>';
    foreach ($errors as $error) {
        $html .= '<li>Row ' . $error['row'] . ': ' . $error['reason'] . '</li>';
    }
    $html .= '</ul></div>';
    
    return $html;
}
