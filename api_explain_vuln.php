<?php
/**
 * api_explain_vuln.php — AI Explainer for OWASP Vulnerabilities
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_new.php';
require_once __DIR__ . '/auth.php';
requireLogin();

header('Content-Type: application/json');

$vulnId = (int)($_GET['id'] ?? 0);
if (!$vulnId) {
    echo json_encode(['error' => 'No vulnerability ID provided.']);
    exit;
}

$db = getAuditDB();
$stmt = $db->prepare("SELECT * FROM owasp_library WHERE id = ?");
$stmt->execute([$vulnId]);
$vuln = $stmt->fetch();

if (!$vuln) {
    echo json_encode(['error' => 'Vulnerability not found.']);
    exit;
}

$prompt = "You are an expert cybersecurity advisor. Please explain the following vulnerability in simple terms for a business stakeholder, and also provide a concise technical explanation for an engineer.\n\n"
        . "Vulnerability Name: " . $vuln['vuln_name'] . "\n"
        . "Category: " . $vuln['category'] . "\n"
        . "Mapped Threat: " . $vuln['mapped_threat'] . "\n"
        . "Mapped Impact: " . $vuln['mapped_impact'] . "\n"
        . "Required Control: " . $vuln['required_control'] . "\n\n"
        . "Format the response in well-structured markdown. Keep it under 250 words.";

$data = [
    'model' => AI_MODEL,
    'messages' => [
        ['role' => 'system', 'content' => 'You are an expert cybersecurity auditor.'],
        ['role' => 'user', 'content' => $prompt]
    ],
    'temperature' => 0.5,
    'max_tokens' => 500
];

$ch = curl_init(AI_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . AI_API_KEY
]);

$response = curl_exec($ch);
$err = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    echo json_encode(['error' => "cURL Error: $err"]);
    exit;
}

$resData = json_decode($response, true);
if ($status !== 200) {
    echo json_encode(['error' => "API Error ($status): " . ($resData['error']['message'] ?? $response)]);
    exit;
}

$aiText = $resData['choices'][0]['message']['content'] ?? 'No content returned.';

// Parse minimal markdown for the modal
$aiText = htmlspecialchars($aiText);
$aiText = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $aiText);
$aiText = preg_replace('/### (.*?)\n/', '<h3 style="margin-top:12px;margin-bottom:6px;font-size:14px;color:var(--chart-blue);">$1</h3>', $aiText);
$aiText = preg_replace('/## (.*?)\n/', '<h2 style="margin-top:12px;margin-bottom:6px;font-size:15px;color:var(--chart-blue);">$1</h2>', $aiText);
$aiText = nl2br($aiText);

echo json_encode(['explanation' => $aiText]);
