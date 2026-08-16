<?php

namespace EventFlow\Application\Authorization;

final class RoleCapabilityPolicy
{
    /** @var array<string, list<Capability>> */
    private const ROLE_CAPABILITIES = [
        'owner' => [
            Capability::VIEW_EVENT,
            Capability::EDIT_EVENT,
            Capability::ACTIVATE_EVENT,
            Capability::COMPLETE_EVENT,
            Capability::MANAGE_STAFF_MEMBERSHIPS,
            Capability::MANAGE_INVITATIONS,
            Capability::ROTATE_INVITATION_TOKEN,
            Capability::MANAGE_ATTENDEES,
            Capability::MANAGE_SEATING,
            Capability::OVERRIDE_REQUIRED_GROUP,
            Capability::CHECK_IN,
            Capability::REVERSE_CHECK_IN,
            Capability::MANAGE_TEMPLATES,
            Capability::QUEUE_CAMPAIGN,
            Capability::MANAGE_IMPORTS,
            Capability::VIEW_AUDIT,
            Capability::VIEW_REPORTS,
            Capability::EXPORT_PII,
        ],
        'organizer' => [
            Capability::VIEW_EVENT,
            Capability::EDIT_EVENT,
            Capability::MANAGE_INVITATIONS,
            Capability::ROTATE_INVITATION_TOKEN,
            Capability::MANAGE_ATTENDEES,
            Capability::MANAGE_SEATING,
            Capability::OVERRIDE_REQUIRED_GROUP,
            Capability::CHECK_IN,
            Capability::REVERSE_CHECK_IN,
            Capability::MANAGE_TEMPLATES,
            Capability::QUEUE_CAMPAIGN,
            Capability::MANAGE_IMPORTS,
            Capability::VIEW_AUDIT,
            Capability::VIEW_REPORTS,
        ],
        'coordinator' => [
            Capability::VIEW_EVENT,
            Capability::MANAGE_INVITATIONS,
            Capability::MANAGE_ATTENDEES,
            Capability::MANAGE_SEATING,
            Capability::CHECK_IN,
            Capability::VIEW_REPORTS,
        ],
        'reception' => [
            Capability::CHECK_IN,
        ],
        'reporting' => [
            Capability::VIEW_EVENT,
            Capability::VIEW_REPORTS,
        ],
    ];

    /** @var list<Capability> */
    private array $primaryOwnerCapabilities;

    public function __construct()
    {
        $this->primaryOwnerCapabilities = Capability::cases();
    }

    public function grants(MembershipSnapshot $membership, Capability $capability): bool
    {
        if ($membership->isPrimaryOwner) {
            return in_array($capability, $this->primaryOwnerCapabilities, true);
        }

        return in_array($capability, self::ROLE_CAPABILITIES[$membership->role->value] ?? [], true);
    }
}
