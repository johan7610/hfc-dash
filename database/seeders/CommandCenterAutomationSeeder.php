<?php

namespace Database\Seeders;

use App\Models\CommandCenter\AutomationRule;
use Illuminate\Database\Seeder;

class CommandCenterAutomationSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'name'              => 'New Listing → Expect Mandate',
                'description'       => 'When a sale property is created, create a task to upload the signed mandate within 48 hours.',
                'is_system'         => true,
                'trigger_model'     => 'Property',
                'trigger_event'     => 'created',
                'trigger_conditions' => json_encode(['field' => 'listing_type', 'operator' => '=', 'value' => 'sale']),
                'action_type'       => 'create_task',
                'action_config'     => json_encode([
                    'title_template' => 'Upload signed mandate — {property.address}',
                    'task_type'      => 'document_upload',
                    'priority'       => 'high',
                    'due_offset'     => '+48h',
                    'assign_to'      => 'property.agent',
                ]),
            ],
            [
                'name'              => 'New Listing → Expect Owner ID',
                'description'       => 'Create a task to upload owner ID copy when a property is listed.',
                'is_system'         => true,
                'trigger_model'     => 'Property',
                'trigger_event'     => 'created',
                'trigger_conditions' => null,
                'action_type'       => 'create_task',
                'action_config'     => json_encode([
                    'title_template' => 'Upload owner ID copy — {property.address}',
                    'task_type'      => 'document_upload',
                    'priority'       => 'high',
                    'due_offset'     => '+72h',
                    'assign_to'      => 'property.agent',
                ]),
            ],
            [
                'name'              => 'New Listing → Proof of Ownership',
                'description'       => 'Create a task to upload proof of ownership (title deed).',
                'is_system'         => true,
                'trigger_model'     => 'Property',
                'trigger_event'     => 'created',
                'trigger_conditions' => null,
                'action_type'       => 'create_task',
                'action_config'     => json_encode([
                    'title_template' => 'Upload proof of ownership — {property.address}',
                    'task_type'      => 'document_upload',
                    'priority'       => 'normal',
                    'due_offset'     => '+72h',
                    'assign_to'      => 'property.agent',
                ]),
            ],
            [
                'name'              => 'Deal Created → FICA for All Parties',
                'description'       => 'When a deal is created, create FICA tasks for each party.',
                'is_system'         => true,
                'trigger_model'     => 'DealV2',
                'trigger_event'     => 'created',
                'trigger_conditions' => null,
                'action_type'       => 'create_task',
                'action_config'     => json_encode([
                    'title_template' => 'Complete FICA for {contact.name} — {deal.reference}',
                    'task_type'      => 'compliance',
                    'priority'       => 'high',
                    'due_offset'     => '+7d',
                    'assign_to'      => 'deal.agent',
                    'per_party'      => true,
                ]),
            ],
            [
                'name'              => 'Deal Created → Bond Application',
                'description'       => 'For sale deals, create a task to submit bond application within 3 days.',
                'is_system'         => true,
                'trigger_model'     => 'DealV2',
                'trigger_event'     => 'created',
                'trigger_conditions' => json_encode(['field' => 'deal_type', 'operator' => 'contains', 'value' => 'sale']),
                'action_type'       => 'create_task',
                'action_config'     => json_encode([
                    'title_template' => 'Submit bond application — {deal.reference}',
                    'task_type'      => 'deal_action',
                    'priority'       => 'high',
                    'due_offset'     => '+3d',
                    'assign_to'      => 'deal.agent',
                ]),
            ],
            [
                'name'              => 'Property Idle > 14 Days',
                'description'       => 'Flag properties with no activity for 14+ days.',
                'is_system'         => true,
                'trigger_model'     => 'Property',
                'trigger_event'     => 'idle',
                'trigger_conditions' => json_encode(['field' => 'last_activity_at', 'operator' => 'older_than', 'value' => '14 days']),
                'action_type'       => 'create_task',
                'action_config'     => json_encode([
                    'title_template' => 'Review property — no activity in 14+ days — {property.address}',
                    'task_type'      => 'review',
                    'priority'       => 'high',
                    'due_offset'     => '+2d',
                    'assign_to'      => 'property.agent',
                ]),
            ],
            [
                'name'              => 'Property Idle > 30 Days (Critical)',
                'description'       => 'Urgent flag for properties neglected 30+ days. Escalates to BM.',
                'is_system'         => true,
                'trigger_model'     => 'Property',
                'trigger_event'     => 'idle',
                'trigger_conditions' => json_encode(['field' => 'last_activity_at', 'operator' => 'older_than', 'value' => '30 days']),
                'action_type'       => 'create_task',
                'action_config'     => json_encode([
                    'title_template' => 'URGENT: Property neglected 30+ days — {property.address}',
                    'task_type'      => 'review',
                    'priority'       => 'critical',
                    'due_offset'     => '+1d',
                    'assign_to'      => 'property.agent',
                    'escalate_to'    => 'bm',
                ]),
            ],
            [
                'name'              => 'New Contact → Follow-up',
                'description'       => 'When a buyer/tenant contact is created, schedule a follow-up task.',
                'is_system'         => true,
                'trigger_model'     => 'Contact',
                'trigger_event'     => 'created',
                'trigger_conditions' => json_encode(['field' => 'contact_type', 'operator' => 'in', 'value' => ['buyer', 'tenant']]),
                'action_type'       => 'create_task',
                'action_config'     => json_encode([
                    'title_template' => 'Initial follow-up with {contact.name}',
                    'task_type'      => 'follow_up',
                    'priority'       => 'normal',
                    'due_offset'     => '+48h',
                    'assign_to'      => 'contact.created_by',
                ]),
            ],
            [
                'name'              => 'Signing Overdue > 48h',
                'description'       => 'Create chase task when a document signing has been pending over 48 hours.',
                'is_system'         => true,
                'trigger_model'     => 'SignatureRequest',
                'trigger_event'     => 'idle',
                'trigger_conditions' => json_encode(['field' => 'status', 'operator' => '=', 'value' => 'pending']),
                'action_type'       => 'create_task',
                'action_config'     => json_encode([
                    'title_template' => 'Chase signature from {party.name} — {document.name}',
                    'task_type'      => 'follow_up',
                    'priority'       => 'high',
                    'due_offset'     => '+24h',
                    'assign_to'      => 'document.creator',
                ]),
            ],
            [
                'name'              => 'Monthly Trust Interest Capture',
                'description'       => 'Recurring monthly task to capture trust account interest.',
                'is_system'         => true,
                'trigger_model'     => 'System',
                'trigger_event'     => 'recurring',
                'trigger_conditions' => json_encode(['schedule' => 'monthly', 'day' => 1]),
                'action_type'       => 'create_task',
                'action_config'     => json_encode([
                    'title_template' => 'Capture trust interest for {month}',
                    'task_type'      => 'compliance',
                    'priority'       => 'normal',
                    'due_offset'     => '+5d',
                    'assign_to'      => 'role:bookkeeper',
                ]),
            ],
        ];

        foreach ($rules as $i => $rule) {
            AutomationRule::updateOrCreate(
                ['name' => $rule['name']],
                array_merge($rule, [
                    'is_active'   => true,
                    'sort_order'  => $i + 1,
                ])
            );
        }

        $this->command->info('Seeded ' . count($rules) . ' default automation rules.');
    }
}
