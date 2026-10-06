<?php
/**
 * Google Gemini 3.8 Client for SquirrelMail AI Agent
 *
 * @copyright 2026 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage ai_agent
 */

#[\AllowDynamicProperties]
class SquirrelMailGeminiClient
{
    private $apiKey;
    private $model;

    public function __construct($apiKey = '', $model = '')
    {
        global $gemini_api_key, $gemini_model, $data_dir, $username;

        // 1. Passed in argument
        $this->apiKey = $apiKey;

        // 2. Session cache
        if (empty($this->apiKey) && !empty($_SESSION['ai_agent_api_key'])) {
            $this->apiKey = $_SESSION['ai_agent_api_key'];
        }

        // 3. User preference override
        if (empty($this->apiKey) && !empty($data_dir) && !empty($username) && function_exists('getPref')) {
            $userKey = getPref($data_dir, $username, 'ai_agent_api_key', '');
            if (!empty($userKey)) {
                $this->apiKey = $userKey;
            }
        }

        // 4. Local persistent config file
        if (empty($this->apiKey) && file_exists(__DIR__ . '/config_local.php')) {
            @include(__DIR__ . '/config_local.php');
            if (!empty($gemini_api_key)) {
                $this->apiKey = $gemini_api_key;
            }
        }

        // 5. Data dir persistent key file
        if (empty($this->apiKey)) {
            $dataDir = !empty($data_dir) ? $data_dir : (defined('SM_PATH') ? SM_PATH . 'data' : __DIR__ . '/../../data');
            $keyFile = rtrim($dataDir, '/') . '/ai_gemini_key.dat';
            if (file_exists($keyFile) && is_readable($keyFile)) {
                $this->apiKey = trim((string)@file_get_contents($keyFile));
            }
        }

        // 6. Plugin config or environment
        if (empty($this->apiKey)) {
            $this->apiKey = !empty($gemini_api_key) ? $gemini_api_key : (getenv('GEMINI_API_KEY') ?: '');
        }

        // Cache into session for speed and stability
        if (!empty($this->apiKey) && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['ai_agent_api_key'] = $this->apiKey;
        }

        // Model
        if (!empty($model)) {
            $this->model = $model;
        } elseif (!empty($_SESSION['ai_agent_model'])) {
            $this->model = $_SESSION['ai_agent_model'];
        } elseif (!empty($data_dir) && !empty($username) && function_exists('getPref')) {
            $userModel = getPref($data_dir, $username, 'ai_agent_model', '');
            $this->model = !empty($userModel) ? $userModel : (!empty($gemini_model) ? $gemini_model : 'gemini-3.8-flash');
        } else {
            $this->model = !empty($gemini_model) ? $gemini_model : 'gemini-3.8-flash';
        }

        if (!empty($this->model) && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['ai_agent_model'] = $this->model;
        }
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }

    public function getModel()
    {
        return $this->model;
    }

    /**
     * Low-level API call to Google Generative Language endpoint
     */
    public function callGemini($prompt, $systemInstruction = '', $jsonMode = false, $temperature = 0.3)
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error'   => 'Gemini API key is not configured. Please enter your API key in Options -> AI Agent & Assistant or set GEMINI_API_KEY in config.'
            ];
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($this->model) . ':generateContent?key=' . urlencode($this->apiKey);

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'     => $temperature,
                'maxOutputTokens' => 2048,
            ]
        ];

        if ($jsonMode) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
        }

        if (!empty($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ];
        }

        $jsonPayload = json_encode($payload);

        // Make HTTP Request via cURL if available, else stream context
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                return ['success' => false, 'error' => 'cURL error connecting to Gemini API: ' . $curlError];
            }
        } else {
            $opts = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
                    'content' => $jsonPayload,
                    'timeout' => 30,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => true
                ]
            ];
            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return ['success' => false, 'error' => 'HTTP request failed connecting to Gemini API.'];
            }
            $httpCode = 200;
            if (isset($http_response_header) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
                $httpCode = intval($m[1]);
            }
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $msg = isset($data['error']['message']) ? $data['error']['message'] : ("HTTP error code $httpCode from Gemini API");
            return ['success' => false, 'error' => $msg];
        }

        if (empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            return ['success' => false, 'error' => 'No content returned from Gemini model.'];
        }

        $text = trim($data['candidates'][0]['content']['parts'][0]['text']);

        return [
            'success' => true,
            'text'    => $text,
            'model'   => $this->model
        ];
    }

    /**
     * AI Compose Assistant: Drafts subject and body based on user prompts
     */
    public function generateCompose($prompt, $tone = 'professional', $context = '')
    {
        $system = "You are an expert AI email writing assistant. "
                . "Your goal is to write a well-structured, clear, polite, and persuasive email based on the user's instructions. "
                . "Tone style: {$tone}. "
                . "Return a valid JSON object with exactly two keys: 'subject' (a concise, compelling subject line) and 'body' (the full email body, formatted with clean paragraphs).";

        $userPrompt = "Prompt: {$prompt}\n";
        if (!empty($context)) {
            $userPrompt .= "Context / Notes: {$context}\n";
        }
        $userPrompt .= "Please generate the email.";

        $res = $this->callGemini($userPrompt, $system, true, 0.4);
        if (!$res['success']) return $res;

        $parsed = json_decode($res['text'], true);
        if (!$parsed || !isset($parsed['body'])) {
            return [
                'success' => true,
                'data' => [
                    'subject' => 'Draft Email',
                    'body'    => $res['text']
                ]
            ];
        }

        return ['success' => true, 'data' => $parsed];
    }

    /**
     * AI Reply Assistant: Generates a contextual reply to an incoming email
     */
    public function generateReply($incomingSubject, $incomingBody, $replyInstruction, $sender = '')
    {
        $system = "You are an executive email assistant drafting a reply to an incoming email. "
                . "Write a natural, polite, and directly relevant reply adhering to the user's response instruction. "
                . "Return a valid JSON object with two keys: 'subject' (e.g. Re: ...) and 'body' (the reply text).";

        $cleanBody = mb_substr(strip_tags($incomingBody), 0, 3000);
        $userPrompt = "Incoming Subject: {$incomingSubject}\n"
                    . "From: {$sender}\n"
                    . "Incoming Email Content:\n\"\"\"\n{$cleanBody}\n\"\"\"\n\n"
                    . "My reply intent / instructions: {$replyInstruction}\n"
                    . "Draft my response.";

        $res = $this->callGemini($userPrompt, $system, true, 0.4);
        if (!$res['success']) return $res;

        $parsed = json_decode($res['text'], true);
        if (!$parsed || !isset($parsed['body'])) {
            return [
                'success' => true,
                'data' => [
                    'subject' => 'Re: ' . preg_replace('/^(Re:\s*)+/i', '', $incomingSubject),
                    'body'    => $res['text']
                ]
            ];
        }

        return ['success' => true, 'data' => $parsed];
    }

    /**
     * AI Summarizer: TL;DR, Action Items, Deadlines
     */
    public function summarizeEmail($subject, $from, $body)
    {
        $system = "You are an executive email analyst. Summarize this email quickly and accurately. "
                . "Return a JSON object with the following keys:\n"
                . "- 'tldr': A concise 1-2 sentence executive summary.\n"
                . "- 'action_items': An array of strings representing specific tasks or questions requiring action (empty array if none).\n"
                . "- 'key_dates': An array of dates, deadlines, or timelines mentioned (empty array if none).\n"
                . "- 'urgency': One of 'High', 'Medium', or 'Low'.";

        $cleanBody = mb_substr(strip_tags($body), 0, 4000);
        $prompt = "Subject: {$subject}\nFrom: {$from}\nEmail Body:\n\"\"\"\n{$cleanBody}\n\"\"\"";

        $res = $this->callGemini($prompt, $system, true, 0.2);
        if (!$res['success']) return $res;

        $parsed = json_decode($res['text'], true);
        if (!$parsed) {
            return [
                'success' => true,
                'data' => [
                    'tldr'         => $res['text'],
                    'action_items' => [],
                    'key_dates'    => [],
                    'urgency'      => 'Normal'
                ]
            ];
        }

        return ['success' => true, 'data' => $parsed];
    }

    /**
     * AI Email Translator
     */
    public function translateEmail($body, $targetLanguage)
    {
        $system = "You are a professional email translator. Translate the following email text into {$targetLanguage}. "
                . "Preserve the original formatting, polite tone, line breaks, and professional nuance. Return only the translated text.";

        $cleanBody = mb_substr(strip_tags($body), 0, 5000);
        $res = $this->callGemini($cleanBody, $system, false, 0.2);
        if (!$res['success']) return $res;

        return ['success' => true, 'translation' => $res['text']];
    }

    /**
     * AI Scam & Phishing Detection
     */
    public function checkScamPhishing($sender, $subject, $headers, $body)
    {
        $system = "You are a cybersecurity and anti-fraud email security analyst. "
                . "Analyze the email for phishing, scam, impersonation, CEO fraud, suspicious urgency, spoofing, wire fraud, or malicious links. "
                . "Return a JSON object with keys:\n"
                . "- 'risk_level': 'Safe', 'Suspicious', or 'Dangerous Scam'\n"
                . "- 'risk_score': Integer from 0 (completely safe) to 100 (critical scam)\n"
                . "- 'verdict': A concise 1-sentence verdict.\n"
                . "- 'red_flags': Array of strings detailing specific warning signs or suspicious indicators found.\n"
                . "- 'recommendation': Concrete security advice for the recipient.";

        $sample = "Sender: {$sender}\nSubject: {$subject}\n"
                . "Key Headers:\n" . mb_substr($headers, 0, 800) . "\n"
                . "Email Body:\n" . mb_substr(strip_tags($body), 0, 3000);

        $res = $this->callGemini($sample, $system, true, 0.1);
        if (!$res['success']) return $res;

        $parsed = json_decode($res['text'], true);
        if (!$parsed) {
            return [
                'success' => true,
                'data' => [
                    'risk_level'     => 'Unknown',
                    'risk_score'     => 50,
                    'verdict'        => $res['text'],
                    'red_flags'      => [],
                    'recommendation' => 'Proceed with caution and verify sender before clicking links.'
                ]
            ];
        }

        return ['success' => true, 'data' => $parsed];
    }

    /**
     * Combined Analysis for Server-Side Cron: Spam detection, auto-labeling, and draft generation
     */
    public function analyzeForServerCron($sender, $subject, $body)
    {
        $system = "You are an automated email classifier and assistant for a mail server. "
                . "Analyze the incoming email and return a JSON object with:\n"
                . "- 'is_spam': boolean (true if email is spam, scam, phishing, or bulk unsolicited junk)\n"
                . "- 'spam_score': int (0-100)\n"
                . "- 'spam_reason': string (short reason if spam)\n"
                . "- 'category': one of 'Work', 'Finance', 'Personal', 'Newsletter', 'Notifications', 'Urgent'\n"
                . "- 'needs_reply': boolean (true if this is a personal or business message requiring a response from the recipient)\n"
                . "- 'suggested_reply': string (if needs_reply is true, draft a courteous, context-aware reply suitable for saving to Drafts; otherwise empty string).";

        $cleanBody = mb_substr(strip_tags($body), 0, 3000);
        $prompt = "Sender: {$sender}\nSubject: {$subject}\nContent:\n{$cleanBody}";

        $res = $this->callGemini($prompt, $system, true, 0.2);
        if (!$res['success']) return $res;

        $parsed = json_decode($res['text'], true);
        if (!$parsed) {
            return ['success' => false, 'error' => 'Failed to parse model classification output.'];
        }

        return ['success' => true, 'data' => $parsed];
    }
}
