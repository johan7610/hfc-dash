<?php

declare(strict_types=1);

namespace Tests\Feature\Docuperfect\SigningView;

use Tests\TestCase;

/**
 * The shared `_document-body` partial is the single visual contract
 * across Step 4 / Step 5 / signing view. This test pins:
 *
 *   • the partial renders for each of the three viewer contexts
 *   • the CSS link is emitted
 *   • the outer wrapper carries the context class so view-specific
 *     CSS rules can target it
 *   • the Alpine-binding path (used by all three live views) emits
 *     a host div with the requested x-html expression
 *   • for recipient_signing with a $currentRecipient, server-side
 *     stamping of `data-your-block="true"` lands on the matching
 *     instance in the supplied body
 */
final class SharedDocumentBodyPartialTest extends TestCase
{
    public function test_partial_renders_for_all_three_viewer_contexts(): void
    {
        $contexts = [
            'wizard_preview'    => 'wizard-preview-context',
            'wizard_fill'       => 'wizard-fill-context',
            'recipient_signing' => 'recipient-signing-context',
        ];

        foreach ($contexts as $context => $expectedClass) {
            $html = view('docuperfect.shared._document-body', [
                'viewerContext'    => $context,
                'body'             => '<div data-recipient-instance="seller_1"><span data-field="seller_address">test</span></div>',
                'currentRecipient' => null,
            ])->render();

            $this->assertStringContainsString('docuperfect-recipient-blocks.css', $html,
                "Partial must load the shared CSS for context [{$context}]");
            $this->assertStringContainsString($expectedClass, $html,
                "Partial must apply context class [{$expectedClass}] for context [{$context}]");
            $this->assertStringContainsString('data-viewer-context="' . $context . '"', $html,
                "Partial must stamp data-viewer-context for context [{$context}]");
            $this->assertStringContainsString('data-recipient-instance="seller_1"', $html,
                "Partial must render the supplied body for context [{$context}]");
        }
    }

    public function test_partial_emits_alpine_host_when_alpine_x_html_is_passed(): void
    {
        $html = view('docuperfect.shared._document-body', [
            'viewerContext' => 'recipient_signing',
            'alpineXHtml'   => 'webTemplateHtml',
            'alpineRef'     => 'webDocContent',
        ])->render();

        $this->assertStringContainsString('x-html="webTemplateHtml"', $html);
        $this->assertStringContainsString('x-ref="webDocContent"', $html);
        $this->assertStringContainsString('data-recipient-block-host="1"', $html);
    }

    public function test_partial_stamps_your_block_on_current_recipient_instance_for_signing(): void
    {
        // Server-rendered body path — the partial post-processes the
        // supplied HTML to mark the current recipient's instance.
        $recipient = new \App\Models\Docuperfect\SignatureRequest();
        $recipient->party_role = 'seller';
        $recipient->role_index = 2;

        $body = '<div data-recipient-instance="seller_1">A</div>'
              . '<div data-recipient-instance="seller_2">B</div>'
              . '<div data-recipient-instance="seller_3">C</div>';

        $html = view('docuperfect.shared._document-body', [
            'viewerContext'    => 'recipient_signing',
            'body'             => $body,
            'currentRecipient' => $recipient,
        ])->render();

        $this->assertStringContainsString(
            'data-recipient-instance="seller_2" data-your-block="true"',
            $html,
            'The current recipient seller_2 must be flagged data-your-block="true".',
        );
        // The other instances must NOT carry the flag.
        $this->assertDoesNotMatchRegularExpression(
            '/data-recipient-instance="seller_1"[^>]*data-your-block/',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-recipient-instance="seller_3"[^>]*data-your-block/',
            $html,
        );
    }
}
