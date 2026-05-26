<?php

declare(strict_types=1);

namespace App\Services\Docuperfect;

use App\Models\Contact;
use App\Models\Docuperfect\SignatureRequest;
use App\Models\Docuperfect\Template;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Recipient Loop Engine — B2 + B2.5 expansion pass.
 *
 * Two entry points:
 *
 *   stampIdentities()    — B2 path. Regex-based single-pass stamping
 *                          for the simple/legacy case (no DOM rewrite).
 *
 *   expandWithLooping()  — B2.5 path. DOMDocument-based pipeline that
 *                          stamps identities AND, where the template uses a
 *                          single-block authoring style with multiple
 *                          recipients, duplicates the block N times and
 *                          pre-fills each clone from its recipient's contact.
 *
 * Four cases drive `expandWithLooping`'s behaviour per role-block:
 *
 *   A. Single-block, N recipients (N>1): duplicate the block's LCA N times,
 *      stamp each clone with role_n identity, mangle data-field for DOM
 *      uniqueness (suffix `__r{n}`), prepend a section header, pre-fill
 *      fields from recipient n's contact.
 *   B. Multi-block hardcoded, K instance indexes == N recipients: stamp
 *      existing fields in place (no duplication). Today's HFC templates
 *      (e.g. template 111 with seller_1_phone..seller_4_phone) hit this.
 *   C. Single-block, 1 recipient: stamp the single block; no header index.
 *   D. Mismatched K hardcoded vs N recipients:
 *        D.1  K > N: stamp first N, mark instances N+1..K orphan.
 *        D.2  K < N (with K>1): stamp K, clone the idx=K sub-block
 *             (if isolable) for instances K+1..N. If the idx=K sub-block
 *             cannot be isolated (no per-instance container in template),
 *             log a structural warning and stop after stamping K.
 *
 * Per-instance DOM uniqueness: cloned fields rename their `data-field`
 * attribute to `{original}__r{n}` so multiple identical names never collide
 * in the DOM. Downstream code reads `data-recipient-identity` as the
 * authoritative identity; `data-field`'s `__r{n}` suffix is purely a DOM
 * uniqueness device.
 *
 * Per-instance pre-fill: when a clone is generated, each field span's text
 * content is overwritten using the recipient's contact record. The mapping
 * is sub-name → contact column:
 *
 *   first_name / last_name        → contact->first_name / ->last_name
 *   name / full_name              → "{first_name} {last_name}" concat
 *   name_surname_id               → "{first_name} {last_name} (ID: {id_number})"
 *   id / id_number                → contact->id_number
 *   email                         → contact->email
 *   phone / cell_phone / mobile   → contact->phone
 *   address / address_1
 *     / address_line_1            → contact->address
 *
 * Unmapped sub-names leave the cloned span's text untouched (i.e. inherit
 * whatever the original WebTemplateDataService merge produced).
 *
 * Stamping-only legacy path (stampIdentities) is preserved for templates
 * that don't need DOM rewriting and for unit tests that validate the
 * regex-level behaviour independent of DOMDocument.
 *
 * Walks the rendered HTML body, parses each `data-field` attribute to recover
 * its role-base + instance-index, and stamps two new attributes onto the
 * field's opening tag:
 *
 *   data-recipient-identity="{role_base}_{instance_index}"
 *   data-role-token="{role_base}"
 *
 * The identity matches `SignatureRequest::role_identity` (B1 accessor) so the
 * signing-view JS in B3 can filter fields by "is this me" without parsing
 * field names client-side.
 *
 * Orphan handling — when a hardcoded numbered field references an instance
 * index beyond the actual recipient count for that role (e.g. template has
 * `seller_3_phone` but the document only has 2 seller recipients), the field
 * gets `data-orphan-recipient="1"` so downstream code can hide/no-op it
 * without crashing. A structural warning is logged but rendering never
 * blocks (templates may legitimately over-provision fields).
 *
 * Backward compat: templates with one recipient per role stamp `role_1` on
 * every matching field, which is exactly what the single-recipient path
 * already implicitly assumed — no behavioural change for legacy documents.
 */
final class RoleBlockExpansionService
{
    public function __construct(
        private readonly RoleBlockDetectionService $detector,
    ) {}

    /**
     * Stamp identity + role-token attributes onto every `data-field` element
     * in the supplied HTML body.
     *
     * @param  string                            $html           Rendered HTML body (post-letterhead, post-block-render).
     * @param  Collection<int, SignatureRequest> $recipients     All signature_requests for this template (any party_role).
     * @param  int|null                          $templateId     Optional — used only for log context when warnings fire.
     * @return string                                            Rewritten HTML body with identity stamps.
     */
    public function stampIdentities(
        string $html,
        Collection $recipients,
        ?int $templateId = null,
    ): string {
        if ($html === '' || trim($html) === '') {
            return $html;
        }

        // Bucket recipients by canonical role-base key. Wizard tokens
        // (seller, lessor, lessee, landlord, tenant) and canonical tokens
        // (owner_party, acquiring_party) coexist on signature_requests.party_role,
        // so we normalise both into the same lookup map for max-instance
        // resolution.
        $countsByRole = $this->buildRecipientCountsByRole($recipients);

        // Single-pass rewrite: match each opening tag carrying data-field="..."
        // and append the two new attributes (plus the orphan flag when
        // applicable) just before the closing `>`. This avoids running-offset
        // bookkeeping that would be needed with index-based splicing.
        $orphanLog = [];
        $pattern   = '/<([a-zA-Z][a-zA-Z0-9]*)(\s[^>]*?)data-field="([^"]+)"([^>]*)>/i';

        $stamped = preg_replace_callback(
            $pattern,
            function (array $m) use ($countsByRole, &$orphanLog): string {
                [$full, $tag, $preAttrs, $fieldName, $postAttrs] = $m;

                $parsed   = $this->detector->parseFieldName($fieldName);
                $roleBase = $parsed['role_base'];
                $idx      = $parsed['instance_index'];

                if ($roleBase === null) {
                    // Field name doesn't map to any known role base — leave
                    // the tag untouched (singleton metadata fields like
                    // "additional_information" or "purchase_price").
                    return $full;
                }

                $identity        = $roleBase . '_' . $idx;
                $recipientCount  = $countsByRole[$roleBase] ?? 0;
                $isOrphan        = $recipientCount > 0 && $idx > $recipientCount;

                if ($isOrphan) {
                    $orphanLog[] = [
                        'field'    => $fieldName,
                        'role'     => $roleBase,
                        'index'    => $idx,
                        'have'     => $recipientCount,
                    ];
                }

                $extra = sprintf(
                    ' data-recipient-identity="%s" data-role-token="%s"%s',
                    e($identity),
                    e($roleBase),
                    $isOrphan ? ' data-orphan-recipient="1"' : '',
                );

                return '<' . $tag . $preAttrs . 'data-field="' . $fieldName . '"' . $postAttrs . $extra . '>';
            },
            $html,
        );

        if ($stamped === null) {
            // preg_replace_callback returns null on PCRE failure — fall back
            // to the original HTML so signing never blocks on a stamping
            // glitch.
            Log::warning('RoleBlockExpansionService: PCRE failure during stamping', [
                'template_id'    => $templateId,
                'preg_last_error' => preg_last_error(),
            ]);
            return $html;
        }

        if (!empty($orphanLog)) {
            Log::info('RoleBlockExpansionService: orphan recipient fields detected', [
                'template_id' => $templateId,
                'orphans'     => $orphanLog,
            ]);
        }

        return $stamped;
    }

    /**
     * Build a {role_base => count} map from the recipient collection.
     *
     * Wizard's raw role aliases collapse onto their canonical owner_party /
     * acquiring_party twins so a field named with EITHER vocabulary resolves
     * to the same recipient count:
     *
     *   seller / lessor / landlord  → also counted as owner_party
     *   buyer / lessee / tenant     → also counted as acquiring_party
     *
     * This keeps templates authored with the raw wizard tokens
     * (`seller_1_phone`) interoperable with documents whose recipients were
     * stored under the canonical token (`party_role = 'owner_party'`).
     *
     * @param  Collection<int, SignatureRequest> $recipients
     * @return array<string, int>
     */
    private function buildRecipientCountsByRole(Collection $recipients): array
    {
        $counts = [];

        foreach ($recipients as $r) {
            $role = strtolower((string) ($r->party_role ?? ''));
            if ($role === '') {
                continue;
            }
            $counts[$role] = ($counts[$role] ?? 0) + 1;

            // Mirror under the canonical twin so lookups by either token
            // resolve to the same count.
            $twin = $this->canonicalTwin($role);
            if ($twin !== null && $twin !== $role) {
                $counts[$twin] = ($counts[$twin] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Map a wizard raw token to its canonical twin (or vice-versa).
     */
    private function canonicalTwin(string $role): ?string
    {
        return match ($role) {
            'seller', 'lessor', 'landlord' => 'owner_party',
            'buyer', 'lessee', 'tenant'    => 'acquiring_party',
            'owner_party'                  => 'seller',
            'acquiring_party'              => 'buyer',
            default                        => null,
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // B2.5 — DOM-based loop expansion
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Full B2.5 pipeline: detect role-blocks, decide per-block case, apply
     * stamping (and duplication + pre-fill for the single-block path).
     *
     * @param  Template|null                     $template    Optional — used for category resolution + log context.
     * @param  string                            $html        Rendered HTML body (post-letterhead, post-block-render).
     * @param  Collection<int, SignatureRequest> $recipients  All signature_requests for this template.
     * @return string                                         Rewritten HTML body.
     */
    public function expandWithLooping(
        ?Template $template,
        string $html,
        Collection $recipients,
    ): string {
        if (trim($html) === '') {
            return $html;
        }

        $dom = $this->detector->loadFragment($html);
        if ($dom === null) {
            Log::warning('RoleBlockExpansionService: DOM parse failure, falling back to stamping', [
                'template_id' => $template?->id,
            ]);
            return $this->stampIdentities($html, $recipients, $template?->id);
        }
        $xpath = new DOMXPath($dom);

        // Re-detect against the SAME DOMDocument so node references stay
        // valid through the mutation loop.
        $boundaries     = $this->detectBoundariesOnDom($dom);
        $recipsByRole   = $this->groupRecipientsByRole($recipients);
        $isSales        = $template?->isSalesDocument() ?? true;
        $structuralLog  = [];

        foreach ($boundaries as $boundary) {
            $this->applyBoundary(
                $dom,
                $xpath,
                $boundary,
                $recipsByRole,
                $isSales,
                $structuralLog,
            );
        }

        if (!empty($structuralLog)) {
            Log::info('RoleBlockExpansionService: structural notes during expansion', [
                'template_id' => $template?->id,
                'notes'       => $structuralLog,
            ]);
        }

        return $this->detector->serializeFragment($dom);
    }

    /**
     * Re-run detection against an existing DOMDocument (so we share node
     * references with the mutation pass). Returns the same shape as
     * RoleBlockDetectionService::detectBlockBoundaries().
     */
    private function detectBoundariesOnDom(DOMDocument $dom): Collection
    {
        // The detector exposes detectBlockBoundaries(Template, string), which
        // re-loads the HTML. Here we want to share the in-memory DOM, so
        // serialise → re-load isn't needed — we just call the same logic
        // with a no-op fragment-load by re-using the document. Cheaper:
        // serialise once and pass through (DOMDocument re-parse is fast for
        // bodies under ~500KB).
        return $this->detector->detectBlockBoundaries(null, $this->detector->serializeFragment($dom));
    }

    /**
     * Apply the appropriate per-boundary case (A/B/C/D) to the DOM in place.
     *
     * @param  array<string, mixed>                $boundary
     * @param  array<string, Collection<int, SignatureRequest>> $recipsByRole
     * @param  list<array<string, mixed>>          $structuralLog
     */
    private function applyBoundary(
        DOMDocument $dom,
        DOMXPath $xpath,
        array $boundary,
        array $recipsByRole,
        bool $isSales,
        array &$structuralLog,
    ): void {
        $role            = $boundary['role_token'];
        $maxIdx          = $boundary['max_instance_index'];
        $instanceGroups  = $boundary['instance_groups'];
        $blockNode       = $boundary['block_node'];
        $blockXpath      = $boundary['block_xpath'];
        $totalClusters   = $boundary['total_clusters_for_role'];

        // Re-resolve block_node against THIS dom (the boundary may have
        // come from a serialise→reload roundtrip, so its DOMElement refs
        // are dead. The xpath fragment is the stable handle.)
        $blockNodeLive = null;
        if ($blockXpath !== null) {
            $found = $xpath->query($blockXpath);
            if ($found !== false && $found->length > 0 && $found->item(0) instanceof DOMElement) {
                $blockNodeLive = $found->item(0);
            }
        }
        // Live field nodes per instance, also via xpath re-resolve.
        $liveInstanceGroups = $this->rehydrateInstanceGroups($dom, $instanceGroups, $role);

        $recipients = $recipsByRole[$role] ?? collect();
        $recipientCount = $recipients->count();

        // Case C: no recipients OR single-block + single-recipient → just stamp.
        if ($recipientCount === 0) {
            // No recipients for this role — every field is orphan.
            foreach ($liveInstanceGroups as $idx => $fields) {
                foreach ($fields as $f) {
                    $this->stampFieldNode($f['node'], $role, $idx, isOrphan: true);
                }
            }
            $structuralLog[] = ['role' => $role, 'case' => 'no-recipients', 'fields' => array_sum(array_map('count', $liveInstanceGroups))];
            return;
        }

        // Single-block authoring: max idx in cluster == 1.
        if ($maxIdx === 1) {
            // Case A or C — but only safe to auto-loop when this is the
            // ROLE's only cluster. If the role has multiple disjoint
            // clusters in the document, duplicating just this one risks
            // breaking the page structure (e.g. a stray "Seller name"
            // header field sitting alongside a fuller hardcoded multi-
            // instance block elsewhere). Stamp only in that case.
            if ($totalClusters > 1) {
                foreach ($liveInstanceGroups[1] ?? [] as $f) {
                    $this->stampFieldNode($f['node'], $role, 1, isOrphan: false);
                }
                $structuralLog[] = [
                    'role' => $role, 'case' => 'A-skipped-multi-cluster',
                    'reason' => 'Role has multiple disjoint clusters; single-block duplication skipped to preserve structure.',
                    'cluster_ordinal' => $boundary['cluster_ordinal'],
                    'total_clusters'  => $totalClusters,
                ];
                return;
            }
            if ($recipientCount === 1) {
                // Case C — stamp the existing block with role_1.
                foreach ($liveInstanceGroups[1] ?? [] as $f) {
                    $this->stampFieldNode($f['node'], $role, 1, isOrphan: false);
                }
                return;
            }
            // Case A — duplicate the LCA N times.
            if ($blockNodeLive === null) {
                // No clean LCA → cannot auto-loop. Stamp existing as role_1
                // and orphan-flag the recipient gap with a log.
                foreach ($liveInstanceGroups[1] ?? [] as $f) {
                    $this->stampFieldNode($f['node'], $role, 1, isOrphan: false);
                }
                $structuralLog[] = [
                    'role'   => $role,
                    'case'   => 'A-no-clean-lca',
                    'reason' => 'No wrapping container found for single-block role; auto-loop skipped — template author should wrap the block in a <div data-role-block="…"> or similar.',
                    'recipients' => $recipientCount,
                ];
                return;
            }
            $this->duplicateBlockForRecipients(
                $dom,
                $blockNodeLive,
                $role,
                $recipients,
                $isSales,
                $recipientCount,
            );
            return;
        }

        // max_idx > 1 → hardcoded multi-instance template (Case B or D).
        $K = $maxIdx;
        $N = $recipientCount;

        // Stamp every existing instance in place. Orphan-mark instances
        // whose idx exceeds N (Case D.1 = K > N).
        foreach ($liveInstanceGroups as $idx => $fields) {
            $isOrphan = ($idx > $N);
            foreach ($fields as $f) {
                $this->stampFieldNode($f['node'], $role, $idx, isOrphan: $isOrphan);
            }
        }

        if ($K === $N) {
            // Case B — fully matched, nothing more to do.
            return;
        }
        if ($K > $N) {
            // Case D.1 — already stamped + orphan-marked above. Log it.
            $orphans = 0;
            for ($i = $N + 1; $i <= $K; $i++) {
                $orphans += count($liveInstanceGroups[$i] ?? []);
            }
            $structuralLog[] = [
                'role' => $role, 'case' => 'D.1-overprovision',
                'hardcoded' => $K, 'recipients' => $N, 'orphan_fields' => $orphans,
            ];
            return;
        }

        // Case D.2 — K < N: template has fewer hardcoded blocks than
        // recipients. Try to find the per-instance subtree for idx=K and
        // duplicate it (N-K) times for the missing recipients.
        $kSubTree = $this->findInstanceSubtree($dom, $liveInstanceGroups, $K, $role);
        if ($kSubTree === null) {
            $structuralLog[] = [
                'role' => $role, 'case' => 'D.2-no-instance-subtree',
                'reason' => 'Template has hardcoded ' . $K . ' instance(s) but ' . $N . ' recipients; could not isolate idx=' . $K . ' subtree for duplication. Template author should move to single-block style or add more hardcoded instances.',
                'hardcoded' => $K, 'recipients' => $N,
            ];
            return;
        }
        $structuralLog[] = [
            'role' => $role, 'case' => 'D.2-auto-fill',
            'hardcoded' => $K, 'recipients' => $N, 'duplicated' => $N - $K,
        ];
        $this->duplicateSubtreeForIndices(
            $dom,
            $kSubTree,
            $role,
            $recipients,
            $isSales,
            fromIndex: $K + 1,
            toIndex: $N,
            totalInstances: $N,
        );
    }

    /**
     * Group recipients by canonical role-token (with twin aliasing).
     * The map keys are populated for BOTH the raw and canonical tokens so
     * lookups by either resolve to the same list.
     *
     * @param  Collection<int, SignatureRequest>                  $recipients
     * @return array<string, Collection<int, SignatureRequest>>
     */
    private function groupRecipientsByRole(Collection $recipients): array
    {
        $byRole = [];
        foreach ($recipients as $r) {
            $role = strtolower((string) ($r->party_role ?? ''));
            if ($role === '') {
                continue;
            }
            $byRole[$role] ??= collect();
            $byRole[$role]->push($r);
            $twin = $this->canonicalTwin($role);
            if ($twin !== null && $twin !== $role) {
                $byRole[$twin] ??= collect();
                $byRole[$twin]->push($r);
            }
        }
        // Sort each bucket by role_index so duplication respects ordering.
        foreach ($byRole as $role => $col) {
            $byRole[$role] = $col->sortBy(fn(SignatureRequest $r) => $r->role_index ?? 1)->values();
        }
        return $byRole;
    }

    /**
     * After a detect→serialise→reload roundtrip the DOMElement refs in the
     * boundary's instance_groups are stale. Re-resolve them against the
     * supplied DOMDocument by matching `data-field` attribute.
     *
     * @param  array<int, list<array{field_name:string,sub_name:?string,node:?DOMElement}>> $groups
     * @return array<int, list<array{field_name:string,sub_name:?string,node:DOMElement}>>
     */
    private function rehydrateInstanceGroups(DOMDocument $dom, array $groups, string $role): array
    {
        $xpath = new DOMXPath($dom);
        $out = [];
        foreach ($groups as $idx => $fields) {
            $out[$idx] = [];
            foreach ($fields as $f) {
                // Use first-occurrence semantics; if the same field name
                // appears more than once (rare but possible), the boundary
                // logic still operates on the cluster as a unit.
                $name = $f['field_name'];
                $nodes = $xpath->query('//*[@data-field="' . str_replace('"', '', $name) . '"]');
                if ($nodes === false || $nodes->length === 0) {
                    continue;
                }
                $node = $nodes->item(0);
                if (!$node instanceof DOMElement) {
                    continue;
                }
                $out[$idx][] = [
                    'field_name' => $name,
                    'sub_name'   => $f['sub_name'],
                    'node'       => $node,
                ];
            }
        }
        return $out;
    }

    /**
     * Find the smallest DOMElement that contains EVERY field with idx=$idx
     * in $instanceGroups AND no field of any other idx for the same role.
     */
    private function findInstanceSubtree(
        DOMDocument $dom,
        array $instanceGroups,
        int $idx,
        string $role,
    ): ?DOMElement {
        $targets = $instanceGroups[$idx] ?? [];
        if (count($targets) === 0) {
            return null;
        }
        $xpath = new DOMXPath($dom);

        // LCA of all idx=$idx nodes.
        $lca = $targets[0]['node'];
        for ($i = 1; $i < count($targets); $i++) {
            $a = $lca;
            $b = $targets[$i]['node'];
            $ancestors = [];
            $cur = $a;
            while ($cur instanceof DOMElement) {
                $ancestors[spl_object_hash($cur)] = $cur;
                $parent = $cur->parentNode;
                $cur = $parent instanceof DOMElement ? $parent : null;
            }
            $cur = $b;
            $found = null;
            while ($cur instanceof DOMElement) {
                if (isset($ancestors[spl_object_hash($cur)])) {
                    $found = $cur;
                    break;
                }
                $parent = $cur->parentNode;
                $cur = $parent instanceof DOMElement ? $parent : null;
            }
            if ($found === null) {
                return null;
            }
            $lca = $found;
        }
        // Ensure the LCA doesn't contain any other-idx field for the same role.
        $allFields = $xpath->query('.//*[@data-field]', $lca);
        if ($allFields === false) {
            return null;
        }
        foreach ($allFields as $f) {
            if (!$f instanceof DOMElement) {
                continue;
            }
            $parsed = $this->detector->parseFieldName($f->getAttribute('data-field'));
            if ($parsed['role_base'] !== $role) {
                continue; // foreign-role field outside our concern, OK
            }
            if ($parsed['instance_index'] !== $idx) {
                return null; // contains another instance, not isolable
            }
        }
        // Bail if LCA is the body wrapper.
        if ($lca->nodeName === 'body' || $lca->getAttribute('id') === RoleBlockDetectionService::ROOT_ID) {
            return null;
        }
        return $lca;
    }

    /**
     * Case A path — duplicate the LCA block once per recipient, stamp each
     * clone with its identity + per-instance data-field suffix, prepend a
     * section header, pre-fill from the recipient's contact, then replace
     * the original block with the concatenated clones.
     *
     * @param  Collection<int, SignatureRequest> $recipients
     */
    private function duplicateBlockForRecipients(
        DOMDocument $dom,
        DOMElement $blockNode,
        string $role,
        Collection $recipients,
        bool $isSales,
        int $totalInstances,
    ): void {
        $parent = $blockNode->parentNode;
        if (!$parent instanceof DOMNode) {
            return;
        }
        $clones = [];
        $n = 0;
        foreach ($recipients as $recipient) {
            $n++;
            $clone = $blockNode->cloneNode(true);
            if (!$clone instanceof DOMElement) {
                continue;
            }
            $this->mutateCloneForInstance(
                $dom,
                $clone,
                $role,
                $n,
                $totalInstances,
                $recipient,
                $isSales,
                strippingForeignIndices: false,
            );
            $clones[] = $clone;
        }
        // Insert each clone immediately before the original, then remove
        // the original.
        foreach ($clones as $clone) {
            $parent->insertBefore($clone, $blockNode);
        }
        $parent->removeChild($blockNode);
    }

    /**
     * Case D.2 path — duplicate the idx=K subtree for instances K+1..N.
     *
     * @param  Collection<int, SignatureRequest> $recipients   (all recipients, ordered by role_index)
     */
    private function duplicateSubtreeForIndices(
        DOMDocument $dom,
        DOMElement $subtree,
        string $role,
        Collection $recipients,
        bool $isSales,
        int $fromIndex,
        int $toIndex,
        int $totalInstances,
    ): void {
        $parent = $subtree->parentNode;
        if (!$parent instanceof DOMNode) {
            return;
        }
        $insertAfter = $subtree;
        for ($n = $fromIndex; $n <= $toIndex; $n++) {
            $recipient = $recipients->get($n - 1);
            $clone = $subtree->cloneNode(true);
            if (!$clone instanceof DOMElement) {
                continue;
            }
            $this->mutateCloneForInstance(
                $dom,
                $clone,
                $role,
                $n,
                $totalInstances,
                $recipient,
                $isSales,
                strippingForeignIndices: false,
            );
            // Insert after the previous block (subtree or last clone).
            if ($insertAfter->nextSibling !== null) {
                $parent->insertBefore($clone, $insertAfter->nextSibling);
            } else {
                $parent->appendChild($clone);
            }
            $insertAfter = $clone;
        }
    }

    /**
     * Apply per-instance mutations to a cloned block: rewrite data-field
     * names for DOM uniqueness, stamp identity attrs, prepend the header,
     * pre-fill from contact.
     */
    private function mutateCloneForInstance(
        DOMDocument $dom,
        DOMElement $clone,
        string $role,
        int $instanceIndex,
        int $totalInstances,
        ?SignatureRequest $recipient,
        bool $isSales,
        bool $strippingForeignIndices,
    ): void {
        $xpath = new DOMXPath($dom);
        // descendant-or-self so a clone whose root IS the field element
        // (single-field cluster edge case) still gets stamped.
        $fields = $xpath->query('descendant-or-self::*[@data-field]', $clone);
        if ($fields === false) {
            return;
        }
        $contact = $this->resolveContact($recipient);
        foreach ($fields as $f) {
            if (!$f instanceof DOMElement) {
                continue;
            }
            $origName = $f->getAttribute('data-field');
            $parsed = $this->detector->parseFieldName($origName);
            if ($parsed['role_base'] === null) {
                continue;
            }
            // Mangle the data-field for DOM uniqueness across clones.
            $f->setAttribute('data-field', $origName . '__r' . $instanceIndex);
            $f->setAttribute('data-original-field', $origName);
            // Stamp identity.
            $f->setAttribute('data-recipient-identity', $role . '_' . $instanceIndex);
            $f->setAttribute('data-role-token', $role);
            // Pre-fill from contact if mapping recognised.
            if ($contact !== null && $parsed['sub_name'] !== null) {
                $value = $this->resolveContactValue($contact, $parsed['sub_name']);
                if ($value !== null) {
                    $this->replaceTextContent($f, $value);
                }
            }
        }
        $this->prependSectionHeader($dom, $clone, $role, $instanceIndex, $totalInstances, $isSales, $recipient);
    }

    /**
     * Prepend a recipient-block header so the rendered signing surface
     * shows "Seller 1: James" / "Lessor 2" etc. above each instance.
     */
    private function prependSectionHeader(
        DOMDocument $dom,
        DOMElement $blockEl,
        string $role,
        int $instanceIndex,
        int $totalInstances,
        bool $isSales,
        ?SignatureRequest $recipient,
    ): void {
        $label = Template::roleDisplayLabel($role, $isSales, $instanceIndex, $totalInstances);
        if ($recipient !== null && !empty($recipient->signer_name)) {
            $label .= ': ' . $recipient->signer_name;
        }
        $h = $dom->createElement('h4');
        $h->setAttribute('class', 'recipient-block-header');
        $h->setAttribute('data-recipient-identity', $role . '_' . $instanceIndex);
        $h->appendChild($dom->createTextNode($label));
        if ($blockEl->firstChild !== null) {
            $blockEl->insertBefore($h, $blockEl->firstChild);
        } else {
            $blockEl->appendChild($h);
        }
    }

    /**
     * Replace a span/element's visible text content while preserving any
     * non-text child structure (rare, but defensive).
     */
    private function replaceTextContent(DOMElement $el, string $value): void
    {
        // Remove all current children, append a single text node.
        while ($el->firstChild !== null) {
            $el->removeChild($el->firstChild);
        }
        $el->appendChild($el->ownerDocument->createTextNode($value));
    }

    /**
     * Stamp a single field node in place (no duplication, no pre-fill).
     */
    private function stampFieldNode(DOMElement $node, string $role, int $idx, bool $isOrphan): void
    {
        $node->setAttribute('data-recipient-identity', $role . '_' . $idx);
        $node->setAttribute('data-role-token', $role);
        if ($isOrphan) {
            $node->setAttribute('data-orphan-recipient', '1');
        }
    }

    /**
     * Resolve a recipient's linked Contact, or null when none.
     */
    private function resolveContact(?SignatureRequest $recipient): ?Contact
    {
        if ($recipient === null || empty($recipient->contact_id)) {
            return null;
        }
        return Contact::find($recipient->contact_id);
    }

    /**
     * Map a field's sub-name to a contact column. Returns null when the
     * sub-name isn't recognised — caller leaves the original span text.
     */
    private function resolveContactValue(Contact $contact, string $subName): ?string
    {
        $key = strtolower($subName);
        switch ($key) {
            case 'first_name':
                return (string) $contact->first_name;
            case 'last_name':
            case 'surname':
                return (string) $contact->last_name;
            case 'name':
            case 'full_name':
                return trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
            case 'name_surname_id':
                $full = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
                $id = (string) ($contact->id_number ?? '');
                return $id !== '' ? ($full . ' (ID: ' . $id . ')') : $full;
            case 'id':
            case 'id_number':
                return (string) $contact->id_number;
            case 'email':
                return (string) $contact->email;
            case 'phone':
            case 'cell_phone':
            case 'mobile':
                return (string) $contact->phone;
            case 'address':
            case 'address_1':
            case 'address_line_1':
            case 'physical_address':
                return (string) $contact->address;
        }
        return null;
    }
}
