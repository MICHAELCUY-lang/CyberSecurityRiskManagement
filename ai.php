<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();

$pageTitle   = 'AI Advisor';
$currentPage = 'ai';
$db = getDB();

$aiResponse   = '';
$aiError      = '';
$userQuestion = '';

// System prompt shared by both providers
$systemPrompt = 'You are a cybersecurity audit advisor for an OCTAVE Allegro risk assessment platform. '
              . 'Explain risks in clear business language. Always provide: '
              . '(1) What the risk is, '
              . '(2) The potential business impact, '
              . '(3) A concrete, actionable recommendation. '
              . 'Keep responses factual, structured, and concise. Do not use emojis.';

// ============================================================
// HANDLE POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userQuestion = trim($_POST['question'] ?? '');

    if (empty($userQuestion)) {
        $aiError = 'Please enter a question.';

    } elseif (empty(AI_API_KEY) || AI_API_KEY === 'your_openai_api_key_here') {
        $aiError = 'AI API key is not configured. Add AI_API_KEY to your .env file.';

    } else {

        // ---- Build payload based on provider ----
        if (AI_PROVIDER === 'gemini') {
            // Google Gemini REST API format
            $payload = json_encode([
                'system_instruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ],
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [['text' => $userQuestion]]
                    ]
                ],
                'generationConfig' => [
                    'temperature'     => 0.4,
                    'maxOutputTokens' => 800
                ]
            ]);
            $headers = ['Content-Type: application/json'];

        } else {
            // OpenAI Chat Completions format
            $payload = json_encode([
                'model'    => AI_MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userQuestion]
                ],
                'temperature' => 0.4,
                'max_tokens'  => 800
            ]);
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . AI_API_KEY
            ];
        }

        // ---- cURL request ----
        $ch = curl_init(AI_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result    = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $aiError = 'Connection error: ' . htmlspecialchars($curlError);

        } elseif ($httpCode !== 200) {
            $decoded = json_decode($result, true);
            // Gemini surfaces errors differently
            $errMsg = $decoded['error']['message']
                   ?? $decoded['error']['status']
                   ?? 'Unknown error.';
            $aiError = 'API Error (' . $httpCode . '): ' . htmlspecialchars($errMsg);

        } else {
            $decoded = json_decode($result, true);

            if (AI_PROVIDER === 'gemini') {
                // Gemini response path: candidates[0].content.parts[0].text
                $aiResponse = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? 'No response received.';
            } else {
                // OpenAI response path
                $aiResponse = $decoded['choices'][0]['message']['content'] ?? 'No response received.';
            }
        }
    }
}

// Suggested questions
$suggestions = [
    'What are the highest risk vulnerabilities in a web application?',
    'How do I implement an effective patch management program?',
    'What does a Broken Access Control finding mean for our business?',
    'How should we respond to a SQL Injection vulnerability?',
    'What is multi-factor authentication and why is it critical?',
    'Explain the OCTAVE Allegro risk assessment methodology.',
    'How do we calculate a compliance score for an OCTAVE audit?',
    'What should be included in an incident response plan?',
];

$providerLabel = match(AI_PROVIDER) {
    'gemini' => 'Google Gemini (' . AI_MODEL . ')',
    'groq'   => 'Groq (' . AI_MODEL . ')',
    default  => 'OpenAI (' . AI_MODEL . ')',
};
$keyConfigured = !empty(AI_API_KEY) && AI_API_KEY !== 'your_openai_api_key_here';

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>AI Advisor</h1>
        <span class="breadcrumb">OCTAVE Allegro / AI Advisor</span>
    </div>
    <div class="content-area">

        <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;">
            <div>
                <!-- Question Form -->
                <div class="card">
                    <div class="card-title">Cybersecurity Audit Query</div>
                    <form method="POST" action="ai.php">
                        <div class="form-group mb-2">
                            <label for="question">Your Question</label>
                            <textarea id="question" name="question" rows="4"
                                      placeholder="e.g. What is the business impact of a SQL Injection vulnerability?"
                                      required><?= htmlspecialchars($userQuestion) ?></textarea>
                        </div>
                        <button type="submit" class="btn">Ask AI Advisor</button>
                    </form>
                </div>

                <!-- Error -->
                <?php if ($aiError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($aiError) ?></div>
                <?php endif; ?>

                <!-- AI Response -->
                <?php if ($aiResponse): ?>
                <div class="card">
                    <div class="card-title">AI Advisor Response
                        <span class="text-muted" style="font-weight:400;font-size:10px;margin-left:8px;">
                            via <?= htmlspecialchars($providerLabel) ?>
                        </span>
                    </div>
                    <div style="border-left:3px solid var(--border-light);padding-left:16px;">
                        <div style="font-size:10px;color:var(--text-muted);margin-bottom:6px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
                            Question
                        </div>
                        <p style="font-size:13px;margin-bottom:16px;color:var(--text-muted);">
                            <?= htmlspecialchars($userQuestion) ?>
                        </p>
                        <div style="font-size:10px;color:var(--text-muted);margin-bottom:8px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">
                            Response
                        </div>
                        <div style="font-size:13px;line-height:1.75;white-space:pre-wrap;"><?= htmlspecialchars($aiResponse) ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Sidebar -->
            <div>
                <!-- Provider Status -->
                <div class="card">
                    <div class="card-title">Provider Status</div>
                    <div style="font-size:12px;margin-bottom:10px;">
                        <span class="text-muted">Provider:</span>
                        <strong style="margin-left:6px;"><?= htmlspecialchars($providerLabel) ?></strong>
                    </div>
                    <?php if ($keyConfigured): ?>
                    <div class="alert alert-info" style="font-size:11px;">
                        API key configured. Advisor is active.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-error" style="font-size:11px;">
                        API key not set. Add <code>AI_API_KEY=...</code> to <code>.env</code>.
                    </div>
                    <?php endif; ?>
                    <div style="font-size:11px;color:var(--text-dim);margin-top:10px;line-height:1.6;">
                        To change provider, set in <code style="background:var(--bg-elevated);padding:1px 4px;border-radius:2px;">.env</code>:<br><br>
                        <code style="display:block;background:var(--bg-elevated);padding:6px 8px;border-radius:3px;font-size:10px;line-height:1.8;">
                            AI_PROVIDER=gemini<br>
                            AI_MODEL=gemini-2.0-flash<br>
                            AI_API_KEY=AIzaSy...
                        </code>
                    </div>
                </div>

                <!-- Suggested Questions -->
                <div class="card">
                    <div class="card-title">Suggested Questions</div>
                    <?php foreach ($suggestions as $s): ?>
                    <div style="padding:8px 0;border-bottom:1px solid var(--border);">
                        <a href="#" onclick="document.getElementById('question').value=<?= json_encode($s) ?>;document.getElementById('question').focus();return false;"
                           style="font-size:12px;color:var(--text-muted);text-decoration:none;display:block;line-height:1.5;">
                            <?= htmlspecialchars($s) ?>
                        </a>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>

    </div>
</div>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
