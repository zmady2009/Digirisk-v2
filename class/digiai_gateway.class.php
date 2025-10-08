<?php
/*
 * Copyright (C) 2024
 *
 * This file is part of Digirisk Dolibarr.
 *
 * Digirisk Dolibarr is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Digirisk Dolibarr is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Digirisk Dolibarr. If not, see <https://www.gnu.org/licenses/>.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

/**
 * Gateway service responsible for orchestrating every interaction with the
 * OpenAI API for DigiAI.
 */
class DigiaiGateway
{
    /** @var DoliDB */
    private $db;

    /** @var Conf */
    private $conf;

    /** @var Translate */
    private $langs;

    /** @var int */
    private $cacheTtl;

    /** @var string */
    private $logDirectory;

    /** @var array<string, array{expires_at:int, payload:array}> */
    private static $runtimeCache = [];

    /**
     * Constructor.
     *
     * @param DoliDB    $db    Database handler.
     * @param Conf      $conf  Global Dolibarr configuration object.
     * @param Translate $langs Translator.
     */
    public function __construct($db, Conf $conf, Translate $langs)
    {
        $this->db = $db;
        $this->conf = $conf;
        $this->langs = $langs;
        $this->cacheTtl = (int) ($conf->global->DIGIRISKDOLIBARR_DIGIAI_CACHE_TTL ?? 30);
        if ($this->cacheTtl < 5) {
            $this->cacheTtl = 30;
        }

        $this->logDirectory = rtrim(DOL_DATA_ROOT, '/').'/digiai/logs';
    }

    /**
     * Calls OpenAI with the provided payload and returns the validated response.
     *
     * @param array $messages  Conversation payload compliant with the OpenAI chat API.
     * @param array $options   Additional options (model, temperature, max_tokens, purpose).
     *
     * @throws Exception If the interaction fails.
     *
     * @return array Structured response data validated against the DigiAI schema.
     */
    public function run(array $messages, array $options = [])
    {
        $model = $options['model'] ?? ($this->conf->global->DIGIRISKDOLIBARR_DIGIAI_MODEL ?? 'gpt-4o');
        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : (float) ($this->conf->global->DIGIRISKDOLIBARR_DIGIAI_TEMPERATURE ?? 0.2);
        $maxTokens = isset($options['max_tokens']) ? (int) $options['max_tokens'] : (int) ($this->conf->global->DIGIRISKDOLIBARR_DIGIAI_MAX_TOKENS ?? 2000);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        $cacheKey = $this->computeCacheKey($payload);
        if (($cached = $this->getCache($cacheKey)) !== null) {
            $this->logInteraction('cache-hit', $payload, $cached, ['source' => 'runtime']);
            return $cached;
        }

        $attempts = 0;
        $maxAttempts = 2;
        $exception = null;
        do {
            $attempts++;
            $startTime = microtime(true);
            $responseData = $this->performHttpRequest($payload);
            $duration = microtime(true) - $startTime;

            try {
                $structuredPayload = $this->extractStructuredContent($responseData);
                $validPayload = $this->validateResponse($structuredPayload, $options['purpose'] ?? 'risk');
                $this->setCache($cacheKey, $validPayload);
                $this->logInteraction('success', $payload, $validPayload, [
                    'latency_ms' => (int) round($duration * 1000),
                    'model' => $model,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                    'attempt' => $attempts,
                ]);

                return $validPayload;
            } catch (Exception $e) {
                $exception = $e;
                $this->logInteraction('validation-error', $payload, ['error' => $e->getMessage()], ['attempt' => $attempts]);
                if ($attempts < $maxAttempts) {
                    $payload['messages'] = $this->buildRepromptMessages($messages, $options);
                }
            }
        } while ($attempts < $maxAttempts);
        throw $exception ?: new Exception($this->langs->transnoentities('ErrorDigiAiGatewayUnknown'));
    }

    /**
     * Builds a reprompt message set when the first attempt fails.
     *
     * @param array $messages
     * @param array $options
     *
     * @return array
     */
    private function buildRepromptMessages(array $messages, array $options)
    {
        $systemInstruction = $this->langs->transnoentities('DigiAiRepromptInstruction');
        $messages[] = [
            'role' => 'system',
            'content' => $systemInstruction,
        ];

        if (!empty($options['schema_description'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $options['schema_description'],
            ];
        }

        return $messages;
    }

    /**
     * Performs a HTTP request against the OpenAI API using cURL.
     *
     * @param array $payload
     *
     * @throws Exception On network or authentication issues.
     *
     * @return array
     */
    private function performHttpRequest(array $payload)
    {
        $apiKey = trim((string) ($this->conf->global->DIGIRISKDOLIBARR_CHATGPT_API_KEY ?? ''));
        if (empty($apiKey)) {
            throw new Exception($this->langs->transnoentities('ErrorNoDigiAiApiKeyConfigured'));
        }

        $url = $this->conf->global->DIGIRISKDOLIBARR_DIGIAI_ENDPOINT ?: 'https://api.openai.com/v1/chat/completions';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$apiKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            $this->logInteraction('network-error', $payload, ['error' => $curlError], ['status' => $statusCode]);
            throw new Exception($this->langs->transnoentities('ErrorDigiAiGatewayCurl', $curlError));
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            $this->logInteraction('invalid-json', $payload, ['response' => $response], ['status' => $statusCode]);
            throw new Exception($this->langs->transnoentities('ErrorDigiAiGatewayInvalidJson'));
        }

        if ($statusCode >= 400) {
            $this->logInteraction('http-error', $payload, $decoded, ['status' => $statusCode]);
            $message = $decoded['error']['message'] ?? 'HTTP '.$statusCode;
            throw new Exception($this->langs->transnoentities('ErrorDigiAiGatewayHttp', $message));
        }

        return $decoded;
    }

    /**
     * Extracts the assistant message content as JSON from the OpenAI API response.
     *
     * @param array $responseData
     *
     * @throws Exception When the expected content is not found.
     *
     * @return array
     */
    private function extractStructuredContent(array $responseData)
    {
        if (empty($responseData['choices'][0]['message']['content'])) {
            throw new Exception($this->langs->transnoentities('ErrorDigiAiGatewayEmptyResponse'));
        }

        $content = $responseData['choices'][0]['message']['content'];

        if (is_array($content)) {
            $textParts = [];
            foreach ($content as $block) {
                if (is_array($block) && ($block['type'] ?? '') === 'text' && isset($block['text'])) {
                    $textParts[] = $block['text'];
                }
            }
            $content = implode("\n", $textParts);
        }

        $content = trim((string) $content);

        $jsonString = $this->extractJsonString($content);
        $decoded = json_decode($jsonString, true);
        if ($decoded === null) {
            throw new Exception($this->langs->transnoentities('ErrorDigiAiGatewayInvalidJsonContent'));
        }

        return $decoded;
    }

    /**
     * Attempts to extract a JSON block from the provided raw content.
     *
     * @param string $content
     *
     * @return string
     */
    private function extractJsonString($content)
    {
        $firstBracket = strpos($content, '[');
        $firstBrace = strpos($content, '{');

        $start = false;
        if ($firstBracket !== false && ($firstBracket < $firstBrace || $firstBrace === false)) {
            $start = $firstBracket;
        } elseif ($firstBrace !== false) {
            $start = $firstBrace;
        }

        if ($start === false) {
            return $content;
        }

        $json = substr($content, $start);

        $end = strrpos($json, ']');
        $endObject = strrpos($json, '}');
        if ($end !== false && ($end > $endObject || $endObject === false)) {
            $json = substr($json, 0, $end + 1);
        } elseif ($endObject !== false) {
            $json = substr($json, 0, $endObject + 1);
        }

        return $json;
    }

    /**
     * Validates the structured response against an internal schema.
     *
     * @param array  $payload
     * @param string $purpose
     *
     * @throws Exception If the payload does not match the expected schema.
     *
     * @return array
     */
    private function validateResponse(array $payload, $purpose)
    {
        $isChatbot = ($purpose === 'chatbot');

        if (!isset($payload['metadata']) || !is_array($payload['metadata'])) {
            $payload['metadata'] = [];
        }

        if (!isset($payload['recommendations']) || !is_array($payload['recommendations'])) {
            $payload['recommendations'] = [];
        }
        if (!isset($payload['summaries']) || !is_array($payload['summaries'])) {
            $payload['summaries'] = [];
        }

        if ($isChatbot) {
            if (!isset($payload['messages']) || !is_array($payload['messages'])) {
                $payload['messages'] = [];
            }
        } else {
            if (!isset($payload['risks']) || !is_array($payload['risks'])) {
                $payload['risks'] = [];
            }

            $normalizedRisks = [];
            foreach ($payload['risks'] as $index => $item) {
                if (!is_array($item)) {
                    $this->logInteraction('schema-warning', ['purpose' => $purpose], ['index' => $index], ['reason' => 'non-array-risk']);
                    continue;
                }
                $normalizedRisks[] = $this->normaliseRiskItem($item);
            }

            $payload['risks'] = $normalizedRisks;
        }

        return $payload;
    }

    /**
     * Normalises a single risk item to guarantee required keys.
     *
     * @param array $item
     *
     * @return array
     */
    private function normaliseRiskItem(array $item)
    {
        $title = '';
        foreach (['title', 'label', 'category'] as $key) {
            if (!empty($item[$key]) && is_string($item[$key])) {
                $title = $item[$key];
                break;
            }
        }
        if ($title === '') {
            $title = 'generic';
        }

        $description = '';
        foreach (['description', 'details', 'summary', 'text'] as $key) {
            if (!empty($item[$key]) && is_string($item[$key])) {
                $description = $item[$key];
                break;
            }
        }

        $cotation = 0;
        foreach (['cotation', 'score', 'gravity', 'level'] as $key) {
            if (isset($item[$key])) {
                $value = $item[$key];
                if (is_array($value)) {
                    $value = array_shift($value);
                }
                if (is_numeric($value)) {
                    $cotation = (int) round($value);
                    break;
                }
            }
        }

        $actions = [];
        foreach (['actions', 'prevention_actions', 'recommendations'] as $key) {
            if (isset($item[$key])) {
                if (is_array($item[$key])) {
                    $actions = $item[$key];
                } elseif (is_string($item[$key]) && dol_strlen($item[$key])) {
                    $stringValue = str_replace("\r", '', $item[$key]);
                    $actions = preg_split('/\n+|[\x{2022}•]/u', $stringValue);
                    if ($actions === false) {
                        $actions = [];
                    }
                }
                break;
            }
        }

        $actions = array_values(array_filter(array_map(function ($entry) {
            if (is_array($entry)) {
                $entry = implode(' ', $entry);
            }
            return trim((string) $entry);
        }, $actions)));

        $normalized = $item;
        $normalized['title'] = $title;
        $normalized['description'] = $description;
        $normalized['cotation'] = $cotation;
        $normalized['actions'] = $actions;
        $normalized['prevention_actions'] = $actions;

        return $normalized;
    }

    /**
     * Stores runtime cache.
     *
     * @param string $key
     * @param array  $payload
     *
     * @return void
     */
    private function setCache($key, array $payload)
    {
        self::$runtimeCache[$key] = [
            'expires_at' => time() + $this->cacheTtl,
            'payload' => $payload,
        ];
    }

    /**
     * Returns cached payload if available.
     *
     * @param string $key
     *
     * @return array|null
     */
    private function getCache($key)
    {
        if (!isset(self::$runtimeCache[$key])) {
            return null;
        }

        if (self::$runtimeCache[$key]['expires_at'] < time()) {
            unset(self::$runtimeCache[$key]);
            return null;
        }

        return self::$runtimeCache[$key]['payload'];
    }

    /**
     * Computes a hash for a payload.
     *
     * @param array $payload
     *
     * @return string
     */
    private function computeCacheKey(array $payload)
    {
        return hash('sha256', json_encode($payload));
    }

    /**
     * Logs any interaction to the DigiAI audit log.
     *
     * @param string $status
     * @param array  $request
     * @param array  $response
     * @param array  $context
     *
     * @return void
     */
    private function logInteraction($status, array $request, array $response = [], array $context = [])
    {
        dol_syslog('DigiAI gateway '.$status, LOG_INFO);

        if (!is_dir($this->logDirectory)) {
            dol_mkdir($this->logDirectory);
        }

        $logFile = $this->logDirectory.'/digiai-'.date('Y-m-d').'.log';
        $entry = [
            'timestamp' => date('c'),
            'status' => $status,
            'context' => $context,
            'request' => $request,
            'response' => $response,
        ];
        file_put_contents($logFile, json_encode($entry).PHP_EOL, FILE_APPEND);
    }
}
