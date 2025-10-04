<?php
/* Copyright (C) 2024 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../class/digiriskticketwebhook.class.php';

class DigiriskTicketWebhookUnitTest extends TestCase
{
    public function testBuildPayloadWithInjectedContext(): void
    {
        global $db, $conf, $langs;

        $ticket = new Ticket($db);
        $ticket->id = 42;
        $ticket->ref = 'TK-00042';
        $ticket->track_id = 'tic42';
        $ticket->fk_project = 1;
        $ticket->statut = Ticket::STATUS_NOT_READ;
        $ticket->severity_code = 'NORMAL';
        $ticket->severity_label = 'Normal';
        $ticket->category_code = 'OTHER';
        $ticket->category_label = 'Autre';
        $ticket->message = 'Test payload';
        $ticket->origin_email = 'demo@example.com';
        $ticket->entity = 1;
        $ticket->array_options = [
            'options_digiriskdolibarr_ticket_firstname' => 'Alice',
            'options_digiriskdolibarr_ticket_lastname' => 'Martin',
            'options_digiriskdolibarr_ticket_phone' => '+33102030405',
        ];

        $service = new DigiriskTicketWebhook($db, $conf, $langs, function () {
            return ['success' => true];
        });

        $payload = $service->buildPayload($ticket, [
            'categories' => [['id' => 1, 'label' => 'Accident', 'config' => []]],
            'attachments' => [['name' => 'photo.jpg', 'download_url' => 'https://example', 'size' => 123]],
            'signature' => ['status' => 'signed'],
        ]);

        $this->assertSame('Alice', $payload['reporter']['firstname']);
        $this->assertSame('photo.jpg', $payload['attachments'][0]['name']);
        $this->assertSame('signed', $payload['signature']['status']);
        $this->assertSame(DigiriskTicketWebhook::VERSION, $payload['meta']['webhook_version']);
        $this->assertSame('TK-00042', $payload['ticket']['ref']);
    }

    public function testDispatchComputesSignatureHeader(): void
    {
        global $db, $conf, $langs;

        $captured = [];
        $service = new DigiriskTicketWebhook($db, $conf, $langs, function ($request) use (&$captured) {
            $captured = $request;
            return ['success' => true, 'status' => 200, 'response' => '{}'];
        });

        $payload = ['meta' => ['entity' => 1], 'ticket' => ['ref' => 'TK-00042']];
        $result = $service->dispatch($payload, [
            'endpoint' => 'https://example.com/webhook',
            'secret' => 'my-secret',
            'timeout' => 5,
            'retry' => 0,
        ]);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($captured['headers']);
        $signatureHeader = array_values(array_filter($captured['headers'], static function ($header) {
            return strpos($header, 'X-Digirisk-Signature:') === 0;
        }));
        $this->assertNotEmpty($signatureHeader);
        $this->assertSame('https://example.com/webhook', $captured['endpoint']);
    }
}
