<?php

declare(strict_types=1);

namespace GroupScholar\CaseworkLedger;

final class CaseworkSeed
{
    public static function notes(): array
    {
        return [
            [
                'scholar_name' => 'Avery Johnson',
                'note_type' => 'attendance',
                'priority' => 'high',
                'tags' => 'attendance,check-in',
                'note_body' => 'Missed two sessions this week, needs outreach to confirm schedule.',
                'follow_up_on' => '2026-02-12',
            ],
            [
                'scholar_name' => 'Maya Chen',
                'note_type' => 'financial',
                'priority' => 'medium',
                'tags' => 'fafsa,documents',
                'note_body' => 'Waiting on FAFSA verification docs; follow-up planned.',
                'follow_up_on' => '2026-02-15',
            ],
            [
                'scholar_name' => 'Luis Ramirez',
                'note_type' => 'academic',
                'priority' => 'low',
                'tags' => 'midterm,progress',
                'note_body' => 'Reported improved midterm scores after tutoring sessions.',
                'follow_up_on' => null,
            ],
            [
                'scholar_name' => 'Jordan Patel',
                'note_type' => 'wellness',
                'priority' => 'medium',
                'tags' => 'check-in,resources',
                'note_body' => 'Connected with campus counseling resources; follow-up completed.',
                'follow_up_on' => '2026-02-05',
                'status' => 'resolved',
                'completed_at' => '2026-02-05 16:30:00',
            ],
        ];
    }
}
