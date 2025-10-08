<?php
if (file_exists('../digiriskdolibarr.main.inc.php')) {
    require_once __DIR__.'/../digiriskdolibarr.main.inc.php';
} elseif (file_exists('../../digiriskdolibarr.main.inc.php')) {
    require_once __DIR__.'/../../digiriskdolibarr.main.inc.php';
} else {
    die('Include of digiriskdolibarr main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once __DIR__.'/../../class/digiai_gateway.class.php';

header('Content-Type: application/json');

global $conf, $langs, $db;

$langs->load('digiriskdolibarr@digiriskdolibarr');

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid payload',
    ]);
    exit;
}

try {
    $gateway = new DigiaiGateway($db, $conf, $langs);
    $messages = build_chat_messages($data);

    $schemaDescription = $langs->transnoentities('DigiAiChatbotSchemaDescription');

    $payload = $gateway->run($messages, [
        'purpose' => 'chatbot',
        'temperature' => 0.4,
        'schema_description' => $schemaDescription,
    ]);

    echo json_encode([
        'success' => true,
        'data' => $payload,
    ]);
} catch (Exception $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $exception->getMessage(),
    ]);
}

/**
 * Builds the prompt for the chatbot endpoint.
 *
 * @param array $data
 *
 * @return array
 */
function build_chat_messages(array $data)
{
    global $langs;

    $history = isset($data['messages']) && is_array($data['messages']) ? $data['messages'] : [];
    $context = isset($data['context']) && is_array($data['context']) ? $data['context'] : [];

    $messages = [];
    $messages[] = [
        'role' => 'system',
        'content' => $langs->transnoentities('DigiAiChatbotSystemPrompt'),
    ];

    if (!empty($context['summary'])) {
        $messages[] = [
            'role' => 'system',
            'content' => $langs->transnoentities('DigiAiChatbotContextPrefix').$context['summary'],
        ];
    }

    foreach ($history as $entry) {
        if (!isset($entry['role']) || !isset($entry['content'])) {
            continue;
        }
        $messages[] = [
            'role' => $entry['role'],
            'content' => $entry['content'],
        ];
    }

    if (empty($history)) {
        $messages[] = [
            'role' => 'user',
            'content' => $langs->transnoentities('DigiAiChatbotDefaultQuestion'),
        ];
    }

    return $messages;
}
