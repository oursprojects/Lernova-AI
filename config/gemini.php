<?php
/*
 * LernovaAI - Gemini API Service (Upgraded)
 * This file contains the function to call the Google Gemini API.
 * It can now handle both 'json' and 'text' responses.
 */

// Load Composer's autoloader to use Guzzle and Dotenv
require_once __DIR__ . '/../vendor/autoload.php';

// Load the .env file from the project root
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (Exception $e) {
    die("Error loading .env file: " . $e->getMessage());
}

/**
 * Calls the Gemini API with a specific text-based prompt.
 *
 * @param string $text_prompt The full prompt to send to the AI.
 * @param string $format The desired output format: 'json' or 'text'.
 * @return string The AI's response text.
 * @throws Exception If the API call fails.
 */
function callGemini($text_prompt, $format = 'json') { // Default to 'json'
    
    // Get the API key from the environment variables
    $api_key = $_ENV['GEMINI_API_KEY'];
    if (empty($api_key)) {
        throw new Exception("GEMINI_API_KEY not found in .env file.");
    }

    $api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=" . $api_key;

    // For XAMPP: Disable SSL verification to fix cURL errors
    $client = new \GuzzleHttp\Client([
        'timeout' => 60.0,
        'verify' => false
    ]);

    // --- Dynamic Generation Config ---
    $generationConfig = [];
    if ($format == 'json') {
        $generationConfig['responseMimeType'] = 'application/json';
    } else {
        $generationConfig['responseMimeType'] = 'text/plain';
    }
    
    $payload = [
        'contents' => [
            ['parts' => [['text' => $text_prompt]]]
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE']
        ],
        'generationConfig' => $generationConfig // Use the dynamic config
    ];

    try {
        $response = $client->request('POST', $api_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $payload
        ]);

        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        } else {
            throw new Exception("No valid content returned from API. Response: " . $body);
        }

    } catch (\GuzzleHttp\Exception\RequestException $e) {
        $error_message = $e->getMessage();
        if ($e->hasResponse()) {
            $error_message .= ": " . $e->getResponse()->getBody()->getContents();
        }
        throw new Exception("API Request Error: " . $error_message);
    } catch (Exception $e) {
        throw new Exception("General Error: " . $e->getMessage());
    }
}
?>