<?php
/**
 * 4BS Cloud Migration Mailer - Main Interface
 */

// Start session first
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Handle logout
if (isset($_GET['logout'])) {
    logout();
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (authenticate($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'Invalid username or password';
    }
}

// Check authentication
if (!isAuthenticated()) {
    ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>4BS Cloud Mailer - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <img src="https://4bs.com/wp-content/uploads/2022/06/4BS-logo-transp.png" alt="4BS" style="max-width: 150px;">
                            <h3 class="mt-3">Cloud Mailer</h3>
                            <p class="text-muted">Sign in to manage templates and sends.</p>
                        </div>

                        <?php if (isset($loginError)): ?>
                        <div class="alert alert-danger"><?= $loginError ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
<?php
    exit;
}

// Handle template actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['template_action'])) {
    $action = $_POST['template_action'];

    if ($action === 'save_template') {
        $templateId = (int)($_POST['template_id'] ?? 0) ?: null;
        $existingTemplate = $templateId ? getTemplateById($templateId) : null;
    $data = [
        'name' => sanitize($_POST['name'] ?? ''),
        'description' => sanitize($_POST['description'] ?? ''),
        'base_language' => sanitize($_POST['base_language'] ?? 'nl'),
        'enable_en' => !empty($_POST['enable_en']) ? 1 : 0,
        'enable_fr' => !empty($_POST['enable_fr']) ? 1 : 0,
        'subject_nl' => isset($_POST['subject_nl']) ? trim($_POST['subject_nl']) : ($existingTemplate['subject_nl'] ?? ''),
        'subject_en' => isset($_POST['subject_en']) ? trim($_POST['subject_en']) : ($existingTemplate['subject_en'] ?? ''),
        'subject_fr' => isset($_POST['subject_fr']) ? trim($_POST['subject_fr']) : ($existingTemplate['subject_fr'] ?? ''),
        'body_nl' => isset($_POST['body_nl']) ? sanitizeTemplateHtml($_POST['body_nl']) : ($existingTemplate['body_nl'] ?? ''),
        'body_en' => isset($_POST['body_en']) ? sanitizeTemplateHtml($_POST['body_en']) : ($existingTemplate['body_en'] ?? ''),
        'body_fr' => isset($_POST['body_fr']) ? sanitizeTemplateHtml($_POST['body_fr']) : ($existingTemplate['body_fr'] ?? '')
    ];

        if (empty($data['name'])) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Template name is required.'];
        } else {
            $savedId = saveTemplate($data, $templateId);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Template saved successfully.'];
            header('Location: index.php?tab=templates&edit=' . $savedId);
            exit;
        }
    }

    if ($action === 'delete_template') {
        $templateId = (int)($_POST['template_id'] ?? 0);
        if ($templateId) {
            deleteTemplate($templateId);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Template deleted.'];
            header('Location: index.php?tab=templates');
            exit;
        }
    }
}

$templates = getTemplates();
$stats = getEmailStats();
$sendHistory = getSendOperations();
$activeTab = $_GET['tab'] ?? 'dashboard';
$editTemplate = null;
if (!empty($_GET['edit'])) {
    $editTemplate = getTemplateById((int)$_GET['edit']);
}

$templateNames = [];
foreach ($templates as $template) {
    $templateNames[$template['id']] = $template['name'];
}

$templateData = [];
foreach ($templates as $template) {
    $fields = getTemplateFields($template);
    $languages = [];
    $languages[] = 'nl';
    if (!empty($template['enable_en'])) {
        $languages[] = 'en';
    }
    if (!empty($template['enable_fr'])) {
        $languages[] = 'fr';
    }

    $templateData[$template['id']] = [
        'id' => $template['id'],
        'name' => $template['name'],
        'description' => $template['description'],
        'base_language' => $template['base_language'],
        'enable_en' => (int)($template['enable_en'] ?? 0),
        'enable_fr' => (int)($template['enable_fr'] ?? 0),
        'fields' => $fields,
        'languages' => $languages
    ];
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>4BS Cloud Mailer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="bg-light">
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-envelope-fill"></i> 4BS Cloud Mailer
            </span>
            <div class="d-flex align-items-center gap-3">
                <span class="text-white-50 small">Secure template manager</span>
                <a href="?logout=1" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4">
        <div id="alert-container"></div>
        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show mt-4">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Navigation tabs -->
        <ul class="nav nav-pills mt-4" id="mainTabs">
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'dashboard' ? 'active' : '' ?>" href="?tab=dashboard">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'templates' ? 'active' : '' ?>" href="?tab=templates">
                    <i class="bi bi-files"></i> Templates
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'send' ? 'active' : '' ?>" href="?tab=send">
                    <i class="bi bi-send"></i> Send
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'history' ? 'active' : '' ?>" href="?tab=history">
                    <i class="bi bi-clock-history"></i> History
                </a>
            </li>
        </ul>

        <?php if ($activeTab === 'dashboard'): ?>
        <section class="mt-4">
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-uppercase text-muted small mb-1">Templates</p>
                                    <h3 class="mb-0"><?= count($templates) ?></h3>
                                </div>
                                <div class="stat-icon bg-primary-subtle text-primary">
                                    <i class="bi bi-files"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-uppercase text-muted small mb-1">Total sent</p>
                                    <h3 class="mb-0"><?= $stats['total_sent'] ?></h3>
                                </div>
                                <div class="stat-icon bg-success-subtle text-success">
                                    <i class="bi bi-send"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-uppercase text-muted small mb-1">Sent today</p>
                                    <h3 class="mb-0"><?= $stats['sent_today'] ?></h3>
                                </div>
                                <div class="stat-icon bg-info-subtle text-info">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-uppercase text-muted small mb-1">Sent this week</p>
                                    <h3 class="mb-0"><?= $stats['sent_week'] ?></h3>
                                </div>
                                <div class="stat-icon bg-warning-subtle text-warning">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Email Templates</h5>
                        <a class="btn btn-sm btn-primary" href="?tab=templates">
                            <i class="bi bi-plus-circle"></i> New Template
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($templates)): ?>
                    <p class="text-muted">No templates yet. Create one to start sending emails.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Languages</th>
                                    <th>Variables</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $template): ?>
                                <?php $fields = getTemplateFields($template); ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($template['name']) ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($template['description']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border text-uppercase me-1">nl</span>
                                        <?php if (!empty($template['enable_en'])): ?>
                                        <span class="badge bg-light text-dark border text-uppercase me-1">en</span>
                                        <?php endif; ?>
                                        <?php if (!empty($template['enable_fr'])): ?>
                                        <span class="badge bg-light text-dark border text-uppercase me-1">fr</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary">
                                            <?= count($fields['variables']) ?> text
                                        </span>
                                        <span class="badge bg-secondary-subtle text-secondary">
                                            <?= count($fields['toggles']) ?> toggles
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="?tab=templates&edit=<?= $template['id'] ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($activeTab === 'templates'): ?>
        <section class="mt-4">
            <form method="POST" id="templateForm">
                <input type="hidden" name="template_action" value="save_template">
                <input type="hidden" name="template_id" value="<?= $editTemplate['id'] ?? '' ?>">
                <input type="hidden" name="base_language" value="nl">

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><?= $editTemplate ? 'Edit Template' : 'Create Template' ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Template Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($editTemplate['name'] ?? '') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($editTemplate['description'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <h5 class="mb-0">Email Content</h5>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input language-checkbox" type="checkbox" checked disabled>
                                        <label class="form-check-label">Dutch (default)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input language-checkbox" type="checkbox" name="enable_en" value="en" id="lang_en" <?= !empty($editTemplate['enable_en']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="lang_en">English</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input language-checkbox" type="checkbox" name="enable_fr" value="fr" id="lang_fr" <?= !empty($editTemplate['enable_fr']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="lang_fr">French</label>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php
                                    $enabledLanguages = ['nl'];
                                    if (!empty($editTemplate['enable_en'])) {
                                        $enabledLanguages[] = 'en';
                                    }
                                    if (!empty($editTemplate['enable_fr'])) {
                                        $enabledLanguages[] = 'fr';
                                    }
                                    ?>
                                <ul class="nav nav-tabs language-tabs mb-3" id="languageTabs" role="tablist">
                                    <?php foreach ($enabledLanguages as $index => $language): ?>
                                    <li class="nav-item" role="presentation" data-lang="<?= $language ?>">
                                        <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" id="tab-<?= $language ?>" data-bs-toggle="tab" data-bs-target="#content-<?= $language ?>" type="button" role="tab">
                                            <?= strtoupper($language) ?>
                                        </button>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>

                                <div class="tab-content" id="languageTabContent">
                                    <?php foreach ($enabledLanguages as $index => $language): ?>
                                    <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" id="content-<?= $language ?>" role="tabpanel" data-lang="<?= $language ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Email Subject <span class="text-danger">*</span></label>
                                            <input type="text" name="subject_<?= $language ?>" class="form-control subject-field" value="<?= htmlspecialchars($editTemplate['subject_' . $language] ?? '') ?>" placeholder="Use **VARIABLE** for dynamic content" data-lang="<?= $language ?>">
                                            <small class="text-muted">Example: Your migration on **DATE**</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Email Body <span class="text-danger">*</span></label>
                                            <div class="editor-toolbar">
                                                <div class="editor-picker">
                                                    <select class="form-select form-select-sm variable-select" data-lang="<?= $language ?>" data-variable-select data-placeholder="Select variable"></select>
                                                    <button type="button" class="btn btn-sm btn-outline-primary insert-variable-inline" data-lang="<?= $language ?>"><i class="bi bi-code-square"></i> Variable</button>
                                                </div>
                                                <div class="editor-picker">
                                                    <select class="form-select form-select-sm conditional-select" data-lang="<?= $language ?>" data-conditional-select data-placeholder="Select conditional"></select>
                                                    <button type="button" class="btn btn-sm btn-outline-info insert-conditional-inline" data-lang="<?= $language ?>"><i class="bi bi-info-square"></i> Conditional (Blue)</button>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary insert-info-box" data-lang="<?= $language ?>" title="Insert blue info box"><i class="bi bi-info-circle"></i> Info Box</button>
                                                <button type="button" class="btn btn-sm btn-outline-warning insert-alert-box" data-lang="<?= $language ?>" title="Insert yellow warning box"><i class="bi bi-exclamation-triangle"></i> Alert Box</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary insert-variable-link" data-lang="<?= $language ?>"><i class="bi bi-link-45deg"></i> Link from variable</button>
                                                <button type="button" class="btn btn-sm btn-outline-success insert-variable-button" data-lang="<?= $language ?>"><i class="bi bi-app"></i> Button from variable</button>
                                            </div>
                                            <textarea name="body_<?= $language ?>" id="editor_<?= $language ?>" class="form-control rich-editor" data-lang="<?= $language ?>"><?= $editTemplate['body_' . $language] ?? '' ?></textarea>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <button type="submit" class="btn btn-success w-100 mb-2">
                                    <i class="bi bi-check-circle"></i> Save Template
                                </button>
                                <?php if ($editTemplate): ?>
                                <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteTemplateModal">
                                    <i class="bi bi-trash"></i> Delete Template
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Variables</h6>
                                <button type="button" class="btn btn-sm btn-success" id="addVariableBtn"><i class="bi bi-plus"></i> Add</button>
                            </div>
                            <div class="card-body">
                                <p class="small text-muted">Use variables like <code>**NAME**</code> to create input fields.</p>
                                <div class="row g-2 mb-3">
                                    <div class="col-5">
                                        <input type="text" class="form-control form-control-sm" id="newVariableName" placeholder="NAME">
                                    </div>
                                    <div class="col-7">
                                        <input type="text" class="form-control form-control-sm" id="newVariableLabel" placeholder="Label">
                                    </div>
                                </div>
                                <div id="variablesList" class="variable-list">
                                    <?php foreach (getTemplateFields($editTemplate ?? ['body_nl' => ''])['variables'] as $var): ?>
                                    <div class="variable-item" data-var-name="<?= htmlspecialchars($var) ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="variable-tag type-text">**<?= htmlspecialchars($var) ?>**</span>
                                            <button type="button" class="btn btn-sm btn-danger remove-variable"><i class="bi bi-x"></i></button>
                                        </div>
                                        <input type="text" class="form-control form-control-sm variable-label" value="<?= htmlspecialchars($var) ?>" placeholder="Label">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Yes/No Sections</h6>
                                <button type="button" class="btn btn-sm btn-warning" id="addConditionalBtn"><i class="bi bi-plus"></i> Add</button>
                            </div>
                            <div class="card-body">
                                <p class="small text-muted">Conditional blocks appear only when checked during send.</p>
                                <div class="row g-2 mb-3">
                                    <div class="col-5">
                                        <input type="text" class="form-control form-control-sm" id="newConditionalName" placeholder="SINGLE_APP_ONLY">
                                    </div>
                                    <div class="col-7">
                                        <input type="text" class="form-control form-control-sm" id="newConditionalLabel" placeholder="Checkbox label">
                                    </div>
                                </div>
                                <div id="conditionalsList" class="variable-list">
                                    <?php foreach (getTemplateFields($editTemplate ?? ['body_nl' => ''])['toggles'] as $toggle): ?>
                                    <div class="variable-item" data-cond-name="<?= htmlspecialchars($toggle) ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="variable-tag type-conditional">[[IF <?= htmlspecialchars($toggle) ?>]]</span>
                                            <button type="button" class="btn btn-sm btn-danger remove-conditional"><i class="bi bi-x"></i></button>
                                        </div>
                                        <input type="text" class="form-control form-control-sm conditional-label" value="<?= htmlspecialchars($toggle) ?>" placeholder="Checkbox label">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <?php if ($editTemplate): ?>
            <div class="modal fade" id="deleteTemplateModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Delete template?</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            This action cannot be undone.
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <form method="POST">
                                <input type="hidden" name="template_action" value="delete_template">
                                <input type="hidden" name="template_id" value="<?= $editTemplate['id'] ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($activeTab === 'send'): ?>
        <section class="mt-4">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Send single email</h5>
                        </div>
                        <div class="card-body">
                            <form id="singleEmailForm">
                                <div class="mb-3">
                                    <label class="form-label">Template</label>
                                    <select name="template_id" id="singleTemplateSelect" class="form-select" required>
                                        <option value="">Select template</option>
                                        <?php foreach ($templates as $template): ?>
                                        <option value="<?= $template['id'] ?>"><?= htmlspecialchars($template['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Recipient email</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Language</label>
                                    <select name="language" id="singleLanguageSelect" class="form-select" required>
                                        <?php foreach (SUPPORTED_LANGUAGES as $language): ?>
                                        <option value="<?= $language ?>"><?= strtoupper($language) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div id="singleDynamicFields"></div>

                                <div class="d-flex gap-2 mt-4">
                                    <button type="button" class="btn btn-outline-primary" id="previewSingleBtn">
                                        <i class="bi bi-eye"></i> Preview
                                    </button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-send"></i> Send email
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Bulk send via CSV</h5>
                        </div>
                        <div class="card-body">
                            <form id="csvUploadForm" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">Template</label>
                                    <select name="template_id" id="bulkTemplateSelect" class="form-select" required>
                                        <option value="">Select template</option>
                                        <?php foreach ($templates as $template): ?>
                                        <option value="<?= $template['id'] ?>"><?= htmlspecialchars($template['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="alert alert-info small" id="bulkColumnsHint">
                                    Select a template to see the required CSV columns.
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Upload CSV file</label>
                                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload"></i> Upload & Preview
                                </button>
                            </form>

                            <div id="csvPreviewContainer" class="mt-4" style="display: none;">
                                <hr>
                                <h6>Preview & Confirm</h6>
                                <div id="csvValidationMessages"></div>
                                <div id="csvPreviewTable"></div>

                                <div class="mt-3" id="bulkSendControls" style="display: none;">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="confirmBulkSend">
                                        <label class="form-check-label" for="confirmBulkSend">
                                            <strong>I confirm the data is correct and ready to send</strong>
                                        </label>
                                    </div>
                                    <button type="button" class="btn btn-success" id="sendBulkBtn" disabled>
                                        <i class="bi bi-send-fill"></i> Send all emails
                                    </button>
                                </div>

                                <div id="bulkSendProgress" class="mt-3" style="display: none;">
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <div id="progressStatus" class="mt-2 text-center"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">Preview</h5>
                    <p class="text-muted small mb-0">Use preview to validate layout before sending.</p>
                </div>
                <div class="card-body">
                    <div id="previewContainer" class="ratio ratio-16x9">
                        <iframe id="previewFrame" title="Email preview" style="border: 1px solid #e5e7eb; border-radius: 12px;"></iframe>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($activeTab === 'history'): ?>
        <section class="mt-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-0">Send history</h5>
                    <p class="text-muted small mb-0">Audit trail of single and bulk sends by logged-in users.</p>
                </div>
                <div class="card-body">
                    <?php if (empty($sendHistory)): ?>
                    <p class="text-muted">No send history yet.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Template</th>
                                    <th>Total</th>
                                    <th>Sent</th>
                                    <th>Failed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sendHistory as $entry): ?>
                                <tr>
                                    <td><?= htmlspecialchars($entry['created_at']) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($entry['username']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $entry['action'] === 'bulk' ? 'primary' : 'secondary' ?>">
                                            <?= htmlspecialchars(ucfirst($entry['action'])) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($templateNames[$entry['template_id']] ?? 'Unknown') ?></td>
                                    <td><?= (int)$entry['total_count'] ?></td>
                                    <td><?= (int)$entry['success_count'] ?></td>
                                    <td><?= (int)$entry['failed_count'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="linkVariableModal" tabindex="-1" aria-labelledby="linkVariableModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="linkVariableModalLabel">Insert Link from Variable</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Link text</label>
                        <input type="text" class="form-control" id="linkTextInput" placeholder="Enter link text">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Link variable</label>
                        <select class="form-select" id="linkVariableSelect" data-variable-select data-placeholder="Choose variable"></select>
                        <small class="text-muted d-block mt-2">Uses <code>**VARIABLE**</code> as the URL.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="insertVariableLinkBtn">Insert link</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="buttonVariableModal" tabindex="-1" aria-labelledby="buttonVariableModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="buttonVariableModalLabel">Insert Button from Variable</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Button text</label>
                        <input type="text" class="form-control" id="buttonTextInput" placeholder="Enter button text">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Button link variable</label>
                        <select class="form-select" id="buttonVariableSelect" data-variable-select data-placeholder="Choose variable"></select>
                        <small class="text-muted d-block mt-2">Uses <code>**VARIABLE**</code> as the button URL.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="insertVariableButtonBtn">Insert button</button>
                </div>
            </div>
        </div>
    </div>

    <script id="templates-data" type="application/json">
        <?= json_encode($templateData) ?>

    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/super-build/ckeditor.js"></script>
    <script src="js/state.js?v=<?= filemtime(__DIR__ . '/js/state.js') ?>"></script>
    <script src="js/utils.js?v=<?= filemtime(__DIR__ . '/js/utils.js') ?>"></script>
    <script src="js/templates.js?v=<?= filemtime(__DIR__ . '/js/templates.js') ?>"></script>
    <script src="js/editor.js?v=<?= filemtime(__DIR__ . '/js/editor.js') ?>"></script>
    <script src="js/variables.js?v=<?= filemtime(__DIR__ . '/js/variables.js') ?>"></script>
    <script src="js/languages.js?v=<?= filemtime(__DIR__ . '/js/languages.js') ?>"></script>
    <script src="js/single.js?v=<?= filemtime(__DIR__ . '/js/single.js') ?>"></script>
    <script src="js/bulk.js?v=<?= filemtime(__DIR__ . '/js/bulk.js') ?>"></script>
    <script src="js/main.js?v=<?= filemtime(__DIR__ . '/js/main.js') ?>"></script>
</body>

</html>
