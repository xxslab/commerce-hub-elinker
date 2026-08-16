<?php

namespace App\Services\Licensing;

use App\Models\CompanyBillingAuditLog;

/**
 * Writes to company_billing_audit_logs. Never pass a token (plaintext or
 * hashed) or any other secret in $context — this log is readable by the
 * company's own admins via Ustawienia -> Plan i billing, and per CLAUDE.md
 * rule 2 nothing that could be replayed or that identifies a credential
 * belongs in a log a customer can read.
 */
class BillingAuditLogger
{
    public function log(string $event, int $companyId, ?string $workspaceId, ?string $ipAddress, array $context = []): void
    {
        CompanyBillingAuditLog::query()->create([
            'company_id' => $companyId,
            'event' => $event,
            'workspace_id' => $workspaceId,
            'ip_address' => $ipAddress,
            'context' => $context,
        ]);
    }
}
