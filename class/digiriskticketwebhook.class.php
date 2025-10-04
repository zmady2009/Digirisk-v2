<?php
/**
 * Service for building and dispatching ticket webhooks to n8n.
 */
declare(strict_types=1);

require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

/**
 * Digirisk ticket webhook service.
 */
class DigiriskTicketWebhook
{
    public const VERSION = '1.0.0';

    /** @var DoliDB */
    private $db;

    /** @var Conf */
    private $conf;

    /** @var Translate */
    private $langs;

    /** @var callable */
    private $httpClient;

    /**
     * @param callable|null $httpClient Custom HTTP client (mainly for testing).
     */
    public function __construct(DoliDB $db, Conf $conf, Translate $langs, ?callable $httpClient = null)
    {
        $this->db = $db;
        $this->conf = $conf;
        $this->langs = $langs;
        $this->httpClient = $httpClient ?: [$this, 'defaultHttpClient'];
    }

    /**
     * Build the payload that will be sent to the webhook endpoint.
     *
     * @param Ticket $ticket
     * @param array  $context Additional resolved data (attachments, categories, ...).
     *
     * @return array<string, mixed>
     */
    public function buildPayload(Ticket $ticket, array $context = []): array
    {
        $categories = $context['categories'] ?? $this->resolveCategories($ticket);
        $attachments = $context['attachments'] ?? $this->resolveFiles($ticket);
        $signature = array_key_exists('signature', $context) ? $context['signature'] : $this->resolveSignature($ticket);

        $reporter = [
            'firstname' => $ticket->array_options['options_digiriskdolibarr_ticket_firstname'] ?? null,
            'lastname'  => $ticket->array_options['options_digiriskdolibarr_ticket_lastname'] ?? null,
            'email'     => $ticket->origin_email ?? null,
            'phone'     => $ticket->array_options['options_digiriskdolibarr_ticket_phone'] ?? null,
        ];

        $ticketData = [
            'id'            => (int) $ticket->id,
            'ref'           => $ticket->ref,
            'track_id'      => $ticket->track_id,
            'project_id'    => $ticket->fk_project,
            'status'        => $ticket->statut,
            'severity'      => [
                'code'  => $ticket->severity_code ?? null,
                'label' => $ticket->severity_label ?? null,
            ],
            'category'      => [
                'code'  => $ticket->category_code ?? null,
                'label' => $ticket->category_label ?? null,
            ],
            'message'       => $ticket->message,
            'location'      => $ticket->array_options['options_digiriskdolibarr_ticket_location'] ?? null,
            'declaration_date' => $this->formatDate($ticket->array_options['options_digiriskdolibarr_ticket_date'] ?? null),
            'origin_email'  => $ticket->origin_email,
            'extra_fields'  => $ticket->array_options,
            'category_path' => $categories,
            'public_success_url' => dol_buildpath('/custom/digiriskdolibarr/public/ticket/ticket_success.php?track_id=' . urlencode((string) $ticket->track_id), 1),
            'date_created'  => $this->formatDate($ticket->datec),
            'date_updated'  => $this->formatDate($ticket->tms),
        ];

        return [
            'ticket'      => $ticketData,
            'reporter'    => $reporter,
            'attachments' => $attachments,
            'signature'   => $signature,
            'meta'        => [
                'sent_at'         => $this->formatDate(dol_now()),
                'webhook_version' => self::VERSION,
                'entity'          => (int) ($ticket->entity ?? $this->conf->entity),
            ],
        ];
    }

    /**
     * Send the payload using the configured webhook endpoint.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function dispatch(array $payload, array $config): array
    {
        $endpoint = trim((string) ($config['endpoint'] ?? ''));
        $timeout = (int) ($config['timeout'] ?? 5);
        $retry = (int) ($config['retry'] ?? 0);
        $secret = (string) ($config['secret'] ?? '');

        if (empty($endpoint)) {
            return ['success' => false, 'status' => 0, 'error' => 'Missing endpoint', 'attempts' => 0];
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['success' => false, 'status' => 0, 'error' => json_last_error_msg(), 'attempts' => 0];
        }

        $headers = [
            'Content-Type: application/json',
            'User-Agent: DigiriskDolibarr/' . ($this->conf->global->DIGIRISKDOLIBARR_VERSION ?? 'unknown'),
            'X-Digirisk-Entity: ' . (int) ($payload['meta']['entity'] ?? 0),
        ];

        if ($secret !== '') {
            $headers[] = 'X-Digirisk-Signature: sha256=' . $this->computeSignature($body, $secret);
        }

        $attempt = 0;
        $result = [];

        do {
            $attempt++;
            $result = call_user_func($this->httpClient, [
                'endpoint' => $endpoint,
                'body' => $body,
                'headers' => $headers,
                'timeout' => $timeout,
            ]);

            if (! empty($result['success'])) {
                break;
            }
        } while ($attempt <= $retry);

        $result['attempts'] = $attempt;

        dol_syslog(__METHOD__ . ' webhook dispatch', LOG_DEBUG, 0, json_encode([
            'endpoint' => $endpoint,
            'status'   => $result['status'] ?? 0,
            'attempts' => $attempt,
        ]));

        return $result;
    }

    /**
     * Default HTTP client relying on cURL.
     *
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function defaultHttpClient(array $request): array
    {
        if (! function_exists('curl_init')) {
            return ['success' => false, 'status' => 0, 'error' => 'cURL extension not available'];
        }

        $ch = curl_init($request['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $request['body'],
            CURLOPT_HTTPHEADER     => $request['headers'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) $request['timeout'],
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success'  => ($raw !== false && $status >= 200 && $status < 300),
            'status'   => $status ?: 0,
            'response' => $raw !== false ? $raw : '',
            'error'    => $raw === false ? $error : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveCategories(Ticket $ticket): array
    {
        $result = [];
        $categoryIds = $ticket->getCategoriesCommon('ticket');
        if (! is_array($categoryIds)) {
            return $result;
        }

        $category = new Categorie($this->db);
        foreach ($categoryIds as $categoryId) {
            if ($category->fetch((int) $categoryId) > 0) {
                $config = json_decode($category->array_options['options_ticket_category_config'] ?? '[]', true) ?: [];
                $result[] = [
                    'id'     => (int) $category->id,
                    'label'  => $category->label,
                    'config' => $config,
                ];
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveFiles(Ticket $ticket): array
    {
        $files = [];
        $entity = isset($ticket->entity) ? (int) $ticket->entity : (int) $this->conf->entity;
        $baseDir = rtrim($this->conf->ticket->multidir_output[$entity] ?? '', '/') . '/' . $ticket->ref;

        if (empty($baseDir) || ! is_dir($baseDir)) {
            return $files;
        }

        $list = dol_dir_list($baseDir, 'files', 0, '', 'thumbs');
        if (! is_array($list)) {
            return $files;
        }

        foreach ($list as $file) {
            $relative = $ticket->ref . '/' . $file['name'];
            $files[] = [
                'name'         => $file['name'],
                'download_url' => dol_buildpath('/document.php?modulepart=ticket&entity=' . $entity . '&attachment=1&file=' . urlencode($relative), 1),
                'size'         => (int) $file['size'],
                'mime_type'    => dol_mimetype($file['name']),
            ];
        }

        return $files;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSignature(Ticket $ticket): ?array
    {
        if (! class_exists('SaturneSignature')) {
            $signatureFile = __DIR__ . '/../saturne/class/saturnesignature.class.php';
            if (is_readable($signatureFile)) {
                require_once $signatureFile;
            }
        }

        if (! class_exists('SaturneSignature')) {
            return null;
        }

        $signature = new SaturneSignature($this->db, 'digiriskdolibarr', $ticket->element);
        $records = $signature->fetchAll('', '', 1, 0, ['customsql' => 'fk_object = ' . ((int) $ticket->id) . " AND object_type = 'ticket'"], 'DESC');
        if (empty($records)) {
            return null;
        }

        /** @var SaturneSignature $record */
        $record = array_shift($records);

        return [
            'status'    => $record->status,
            'fullname'  => trim(($record->firstname ?? '') . ' ' . ($record->lastname ?? '')),
            'signed_at' => $this->formatDate($record->signature_date ?? null),
            'image'     => $record->signature,
        ];
    }

    /**
     * @param int|string|null $ts
     */
    private function formatDate($ts): ?string
    {
        if (empty($ts)) {
            return null;
        }

        return dol_print_date($ts, 'standard');
    }

    private function computeSignature(string $body, string $secret): string
    {
        return hash_hmac('sha256', $body, $secret);
    }
}
