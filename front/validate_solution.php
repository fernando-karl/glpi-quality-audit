<?php
/**
 * AJAX endpoint for real-time solution validation
 * Returns: JSON with score, analysis, suggestion
 */


// CORS for AJAX
header('Content-Type: application/json');

// Check authentication
Session::checkLoginUser();

// Shared markdown-to-HTML utility
include_once Plugin::getPhpDir('qualityaudit') . '/inc/utils.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['solution_content']) || !isset($input['ticket_id'])) {
    echo json_encode(['error' => 'Missing required fields']);
    return;
}

$solution_content = trim($input['solution_content']);
$ticket_id = (int)$input['ticket_id'];
$itemtype = $input['itemtype'] ?? 'Ticket';

// Whitelist allowed item types to prevent unsafe dynamic instantiation
$allowed_types = ['Ticket', 'Change', 'Problem'];
if (!in_array($itemtype, $allowed_types)) {
    echo json_encode(['error' => 'Invalid item type']);
    return;
}

// Empty check only — let AI evaluate even short texts and provide suggestions
if (strlen($solution_content) < 2) {
    echo json_encode([
        'valid' => false,
        'score' => 0,
        'status' => 'RECUSADO',
        'analysis' => __('Solution text is empty.', 'qualityaudit'),
        'suggestion' => '',
        'criteria' => ['ortografia' => 0, 'completude' => 0, 'resolucao' => 0, 'clareza' => 0, 'tecnica' => 0]
    ]);
    return;
}

// Get ticket data
$item = new $itemtype();
if (!$item->getFromDB($ticket_id)) {
    echo json_encode(['error' => 'Ticket not found']);
    return;
}

// Check entity access
if (!Session::haveAccessToEntity($item->fields['entities_id'])) {
    echo json_encode(['error' => 'Access denied']);
    return;
}

// Get configuration for this entity
$config = PluginQualityauditConfig::getConfig($item->fields['entities_id']);

if (empty($config['api_key'])) {
    echo json_encode([
        'valid' => false,
        'error' => __('Quality Audit not configured for this entity', 'qualityaudit'),
        'bypass' => true // Allow saving without validation
    ]);
    return;
}

// Build ticket context
$ticket_data = [
    'itemtype'    => $itemtype,
    'items_id'    => $ticket_id,
    'entities_id' => $item->fields['entities_id'],
    'title'       => $item->fields['name'] ?? '',
    'description' => $item->fields['content'] ?? '',
    'solution'    => $solution_content,
    'tech_id'     => Session::getLoginUserID()
];

// Get threshold
$threshold = (int)($config['approval_threshold'] ?? 80);

// Build prompt
$prompt = PluginQualityauditAudit::buildPrompt($ticket_data, $threshold);

// Call AI with retry
$ai_response = PluginQualityauditAudit::callAIWithRetry($prompt, $config, 2); // Max 2 retries for real-time

// Log AI response for debugging
Toolbox::logInFile('qualityaudit', "Validate ticket $ticket_id | AI response: " . json_encode($ai_response, JSON_UNESCAPED_UNICODE));

if (!$ai_response) {
    // FAILSAFE MODE: AI service unavailable
    // Log the failure
    Toolbox::logInFile('qualityaudit',"Quality Audit: AI service unavailable for ticket $ticket_id. Allowing bypass.");
    
    // Perform basic validation (fallback)
    $basic_validation = performBasicValidation($solution_content, $ticket_data);
    
    echo json_encode([
        'valid' => false,
        'error' => __('AI quality audit service is temporarily unavailable.', 'qualityaudit'),
        'failsafe' => true,
        'bypass' => true, // Allow saving without full validation
        'basic_score' => $basic_validation['score'],
        'basic_feedback' => $basic_validation['feedback'],
        'criteria' => $basic_validation['criteria'] ?? [
            'ortografia' => 0,
            'completude' => 0,
            'resolucao' => 0,
            'clareza' => 0,
            'tecnica' => 0
        ],
        'retry_available' => true
    ]);
    return;
}

// Parse response
$score = (int)($ai_response['nota'] ?? 0);
$status = ($score >= $threshold) ? 'APROVADO' : 'RECUSADO';

// Get raw text for storage
$analysis_raw = $ai_response['analise'] ?? '';
$suggestion_raw = $ai_response['sugestao_melhoria'] ?? '';

// Convert markdown to HTML for display
$analysis_html = qualityaudit_markdown_to_html($analysis_raw);
$suggestion_html = qualityaudit_markdown_to_html($suggestion_raw);

// Return validation result
echo json_encode([
    'valid' => ($status === 'APROVADO'),
    'score' => $score,
    'threshold' => $threshold,
    'status' => $status,
    'analysis' => $analysis_raw,
    'analysis_html' => $analysis_html,
    'suggestion' => $suggestion_raw,
    'suggestion_html' => $suggestion_html,
    'criteria' => $ai_response['criterios'] ?? [
        'ortografia' => 0,
        'completude' => 0,
        'resolucao' => 0,
        'clareza' => 0,
        'tecnica' => 0
    ],
    'failsafe' => false
]);

/**
 * Basic validation fallback (when AI is unavailable)
 * Returns simple heuristic-based score
 */
function performBasicValidation($solution_content, $ticket_data) {
    $score = 0;
    $feedback = [];
    $content_lower = strtolower(trim($solution_content));
    
    // CRITICAL: Check for "no solution" patterns first
    $no_solution_patterns = ['sem solução', 'encerrado sem solução', 'cancelado', 'não resolvido', 
                              'no solution', 'closed without solution', 'cancelled', 'not resolved',
                              'encerrado', 'nao resolvido'];
    
    $is_no_solution = false;
    foreach ($no_solution_patterns as $pattern) {
        if (strpos($content_lower, $pattern) !== false) {
            $is_no_solution = true;
            $feedback[] = __('Solution indicates problem was not resolved. Cannot provide improvement suggestion.', 'qualityaudit');
            break;
        }
    }
    
    if ($is_no_solution) {
        return [
            'score' => 0,
            'feedback' => implode("\n", $feedback),
            'criteria' => [
                'ortografia' => 0,
                'completude' => 0,
                'resolucao' => 0,
                'clareza' => 0,
                'tecnica' => 0
            ]
        ];
    }
    
    // Length check
    $length = strlen($solution_content);
    if ($length < 20) {
        $feedback[] = __('Solution is very short (less than 20 characters)', 'qualityaudit');
    } elseif ($length < 50) {
        $score += 10;
        $feedback[] = __('Solution is short. Consider adding more details.', 'qualityaudit');
    } elseif ($length < 100) {
        $score += 20;
    } else {
        $score += 30;
    }
    
    // Common bad patterns (only if not already marked as no solution)
    $bad_patterns = ['resolvido', 'ok', 'feito', 'pronto', 'done', 'fixed'];
    
    $has_bad_pattern = false;
    foreach ($bad_patterns as $pattern) {
        if ($content_lower === $pattern || preg_match('/^' . preg_quote($pattern, '/') . '\s*$/i', $content_lower)) {
            $has_bad_pattern = true;
            $feedback[] = sprintf(__('Avoid generic responses like "%s". Explain what was done.', 'qualityaudit'), $pattern);
            break;
        }
    }
    
    if ($has_bad_pattern) {
        $score = max(0, $score - 20);
    }
    
    // Check for sentence structure (at least 2 sentences)
    $sentences = preg_split('/[.!?]+/', $solution_content, -1, PREG_SPLIT_NO_EMPTY);
    if (count($sentences) < 2) {
        $feedback[] = __('Solution should have at least 2 sentences explaining the actions taken.', 'qualityaudit');
    } else {
        $score += 15;
    }
    
    // Check for technical terms (positive indicator)
    $tech_terms = ['instalado', 'configurado', 'reiniciado', 'verificado', 'testado', 'corrigido', 
                   'atualizado', 'replaced', 'configured', 'restarted', 'verified', 'tested'];
    $has_tech_term = false;
    foreach ($tech_terms as $term) {
        if (stripos($content_lower, $term) !== false) {
            $has_tech_term = true;
            break;
        }
    }
    
    if ($has_tech_term) {
        $score += 15;
        $feedback[] = __('Good: Contains technical action words.', 'qualityaudit');
    } else {
        $feedback[] = __('Consider adding specific actions taken (e.g., "configured", "restarted", "verified").', 'qualityaudit');
    }
    
    // Check for politeness (greeting/closing)
    $polite_words = ['prezado', 'caro', 'obrigado', 'atenciosamente', 'dear', 'thank', 'regards', 'sincerely'];
    $has_polite = false;
    foreach ($polite_words as $word) {
        if (stripos($content_lower, $word) !== false) {
            $has_polite = true;
            break;
        }
    }
    
    if ($has_polite) {
        $score += 10;
    }
    
    // Cap score at 60 (basic validation cannot give high scores)
    $score = min(60, $score);
    
    return [
        'score' => $score,
        'feedback' => implode("\n", $feedback),
        'criteria' => [
            'ortografia' => min(100, $score + 10),
            'completude' => $score,
            'resolucao' => $has_tech_term ? $score : max(0, $score - 15),
            'clareza' => min(100, $score + ($has_polite ? 15 : 0)),
            'tecnica' => $has_tech_term ? $score : max(0, $score - 10)
        ]
    ];
}
