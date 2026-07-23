<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Revenue opportunity follow-up</title></head>
<body style="font-family:Arial,Helvetica,sans-serif;color:#0f172a;background:#f8fbff;padding:24px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr><td align="center">
<table width="620" cellpadding="0" cellspacing="0" role="presentation" style="background:#fff;border:1px solid #dce6f2;border-radius:16px;padding:24px;">
<tr><td style="font-size:20px;font-weight:700;padding-bottom:8px;">Revenue opportunity follow-up</td></tr>
<tr><td style="font-size:14px;line-height:1.6;color:#334155;">
<p>This is your reminder to follow up on a revenue opportunity.</p>
<p><strong>Customer:</strong> {{ $task->revenueOpportunity?->customer?->name }}</p>
<p><strong>Opportunity:</strong> {{ $task->revenueOpportunity?->title }}</p>
<p><strong>Due date:</strong> {{ $task->due_date?->format('j F Y') }}</p>
<p><strong>Assigned to:</strong> {{ $task->assignedTo?->name }}</p>
@if ($task->description)<p><strong>Notes:</strong><br>{!! nl2br(e($task->description)) !!}</p>@endif
<p>Open the CRM Tasks or Revenue Opportunities page to review the follow-up.</p>
</td></tr></table></td></tr></table>
</body>
</html>
