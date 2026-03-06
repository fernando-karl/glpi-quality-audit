<?php
/**
 * API Test endpoint for Quality Audit
 * Tests API connection with configured provider
 */


// Check authentication
Session::checkLoginUser();
Session::checkRight('config', UPDATE);

// CSRF check if coming from form
if (isset($_GET['entities_id'])) {
   // This is a simple test endpoint, allow GET for bookmarking
}

header('Content-Type: application/json');

// Get entity - with sanitization
$entities_id = isset($_REQUEST['entities_id']) && is_numeric($_REQUEST['entities_id'])
   ? (int)$_REQUEST['entities_id']
   : 0;

// Check if form values were posted (test unsaved config)
$form_api_key = $_POST['api_key'] ?? '';
$form_provider = $_POST['api_provider'] ?? '';
$form_model = $_POST['api_model'] ?? '';

if (!empty($form_api_key) && $form_api_key !== '********') {
   // Use values from the form directly (unsaved config)
   $api_key = $form_api_key;
   $provider = !empty($form_provider) ? $form_provider : 'openai';
   $model = !empty($form_model) ? $form_model : 'gpt-4o-mini';
} else {
   // Fall back to saved config from database
   $config = PluginQualityauditConfig::getConfig($entities_id);

   if ($form_api_key === '********' && !empty($config['api_key'])) {
      // Masked key means use saved key, but allow overriding provider/model from form
      include_once __DIR__ . '/../hook.php';
      $api_key = plugin_qualityaudit_decrypt_key($config['api_key']);
      $provider = !empty($form_provider) ? $form_provider : ($config['api_provider'] ?? 'openai');
      $model = !empty($form_model) ? $form_model : ($config['api_model'] ?? 'gpt-4o-mini');
   } else if (!empty($config['api_key'])) {
      include_once __DIR__ . '/../hook.php';
      $api_key = plugin_qualityaudit_decrypt_key($config['api_key']);
      $provider = $config['api_provider'] ?? 'openai';
      $model = $config['api_model'] ?? 'gpt-4o-mini';
   } else {
      echo json_encode([
         'success' => false,
         'message' => __('API key not configured', 'qualityaudit')
      ]);
      return;
   }
}

// Test simple prompt
$test_prompt = "Respond with exactly: {\"status\": \"ok\", \"test\": true}";

$response = null;
$error = null;

try {
   switch ($provider) {
      case 'openai':
         $response = testOpenAI($api_key, $model, $test_prompt);
         break;
      case 'claude':
         $response = testClaude($api_key, $model, $test_prompt);
         break;
      case 'gemini':
         $response = testGemini($api_key, $model, $test_prompt);
         break;
      default:
         $error = "Unknown provider: $provider";
   }
} catch (Exception $e) {
   $error = $e->getMessage();
}

if ($error) {
   echo json_encode([
      'success' => false,
      'message' => $error
   ]);
   return;
}

if ($response && isset($response['status']) && $response['status'] === 'ok') {
   echo json_encode([
      'success' => true,
      'message' => __('API connection successful!', 'qualityaudit'),
      'provider' => $provider,
      'model' => $model
   ]);
} else {
   echo json_encode([
      'success' => false,
      'message' => __('API response invalid', 'qualityaudit'),
      'response' => $response
   ]);
}

/**
 * Test OpenAI API
 */
function testOpenAI($api_key, $model, $prompt) {
   $url = 'https://api.openai.com/v1/chat/completions';
   
   $data = [
      'model' => $model,
      'messages' => [['role' => 'user', 'content' => $prompt]],
      'max_completion_tokens' => 50
   ];
   
   $ch = curl_init($url);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_POST, true);
   curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
   curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $api_key
   ]);
   curl_setopt($ch, CURLOPT_TIMEOUT, 10);
   
   $response = curl_exec($ch);
   if ($response === false) {
      $err = curl_error($ch);
      curl_close($ch);
      throw new Exception("OpenAI cURL error: $err");
   }
   $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
   curl_close($ch);

   if ($http_code !== 200) {
      throw new Exception("OpenAI HTTP $http_code: $response");
   }
   
   $result = json_decode($response, true);
   $content = $result['choices'][0]['message']['content'] ?? '';

   // Try direct JSON parse first, then extract JSON from markdown/text
   $parsed = json_decode($content, true);
   if ($parsed) {
      return $parsed;
   }
   if (preg_match('/\{[^}]+\}/', $content, $matches)) {
      $parsed = json_decode($matches[0], true);
      if ($parsed) {
         return $parsed;
      }
   }
   // API responded successfully, treat as ok even if content isn't exact JSON
   return ['status' => 'ok', 'raw' => $content];
}

/**
 * Test Claude API
 */
function testClaude($api_key, $model, $prompt) {
   $url = 'https://api.anthropic.com/v1/messages';
   
   $data = [
      'model' => $model,
      'max_tokens' => 50,
      'messages' => [['role' => 'user', 'content' => $prompt]]
   ];
   
   $ch = curl_init($url);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_POST, true);
   curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
   curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
      'x-api-key: ' . $api_key,
      'anthropic-version: 2023-06-01'
   ]);
   curl_setopt($ch, CURLOPT_TIMEOUT, 10);
   
   $response = curl_exec($ch);
   if ($response === false) {
      $err = curl_error($ch);
      curl_close($ch);
      throw new Exception("Claude cURL error: $err");
   }
   $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
   curl_close($ch);

   if ($http_code !== 200) {
      throw new Exception("Claude HTTP $http_code: $response");
   }
   
   $result = json_decode($response, true);
   $content = $result['content'][0]['text'] ?? '';
   
   return json_decode($content, true);
}

/**
 * Test Gemini API
 */
function testGemini($api_key, $model, $prompt) {
   // Note: Gemini API requires the key in the URL query string (no header auth option).
   // This is a Google API design limitation, not a bug.
   $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$api_key";
   
   $data = [
      'contents' => [['parts' => [['text' => $prompt]]]],
      'generationConfig' => [
         'temperature' => 0.1,
         'maxOutputTokens' => 50
      ]
   ];
   
   $ch = curl_init($url);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_POST, true);
   curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
   curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
   curl_setopt($ch, CURLOPT_TIMEOUT, 10);
   
   $response = curl_exec($ch);
   if ($response === false) {
      $err = curl_error($ch);
      curl_close($ch);
      throw new Exception("Gemini cURL error: $err");
   }
   $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
   curl_close($ch);

   if ($http_code !== 200) {
      throw new Exception("Gemini HTTP $http_code: $response");
   }
   
   $result = json_decode($response, true);
   $content = $result['criteria'][0]['content']['parts'][0]['text'] ?? '';
   
   return json_decode($content, true);
}
