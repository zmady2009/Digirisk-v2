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

global $conf, $langs, $db;

$langs->load('digiriskdolibarr@digiriskdolibarr');

$action = GETPOST('action', 'alpha');

header('Content-Type: application/json');

try {
    $gateway = new DigiaiGateway($db, $conf, $langs);
    $messages = build_digiai_messages($action);

    $schemaDescription = $langs->transnoentities('DigiAiSchemaDescription');

    $payload = $gateway->run($messages, [
        'purpose' => $action,
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
 * Builds the prompt messages depending on the requested action.
 *
 * @param string $action
 *
 * @return array
 */
function build_digiai_messages($action)
{
    global $langs;

    $messages = [];

    $systemContext = $langs->transnoentities('DigiAiSystemPrompt');
    $messages[] = [
        'role' => 'system',
        'content' => $systemContext,
    ];

    if ($action === 'analyze_image') {
        if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException($langs->transnoentities('ErrorDigiAiNoImageProvided'));
        }

        $imagePath = $_FILES['image_file']['tmp_name'];
        $imageData = base64_encode(file_get_contents($imagePath));
        $imageMimeType = mime_content_type($imagePath);

        $messages[] = [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => 'data:'.$imageMimeType.';base64,'.$imageData,
                    ],
                ],
                [
                    'type' => 'text',
                    'text' => $langs->transnoentities('DigiAiPromptRiskTaxonomy'),
                ],
            ],
        ];
    } elseif ($action === 'analyze_text') {
        $textAnalysis = trim(GETPOST('analysis_text', 'restricthtml'));
        if (empty($textAnalysis)) {
            throw new InvalidArgumentException($langs->transnoentities('ErrorDigiAiNoTextProvided'));
        }

        $messages[] = [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $langs->transnoentities('DigiAiPromptTextIntro').$textAnalysis,
                ],
            ],
        ];
    } else {
        throw new InvalidArgumentException($langs->transnoentities('ErrorDigiAiUnknownAction'));
    }

    return $messages;
}
