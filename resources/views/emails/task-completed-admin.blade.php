<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Task completed</title>
    </head>
    <body style="font-family: Arial, Helvetica, sans-serif; color: #0f172a; background: #f8fbff; padding: 24px;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td align="center">
                    <table width="620" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border:1px solid #dce6f2;border-radius:16px;padding:24px;">
                        <tr>
                            <td style="font-size:20px;font-weight:700;padding-bottom:8px;">Task completed</td>
                        </tr>
                        <tr>
                            <td style="font-size:14px;line-height:1.6;color:#334155;">
                                <p>A staff task has been marked as completed.</p>
                                <p><strong>Task:</strong> {{ $task->title }}</p>
                                <p><strong>Staff member:</strong> {{ $staff?->name }} ({{ $staff?->email }})</p>
                                @if ($job)
                                    <p><strong>Linked job:</strong> #{{ $job->id }} — {{ $job->description }}</p>
                                @endif
                                @if ($customer)
                                    <p><strong>Customer:</strong> {{ $customer->name }}</p>
                                @endif
                                <p><strong>Time logged:</strong> {{ (int) $task->hours }}h {{ str_pad((string) ((int) $task->minutes), 2, '0', STR_PAD_LEFT) }}m</p>
                                @if ($task->staff_notes)
                                    <p><strong>Staff notes:</strong><br>{{ $task->staff_notes }}</p>
                                @endif
                                <p><strong>Completed at:</strong> {{ $task->completed_at?->format('M j, Y H:i') }}</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
