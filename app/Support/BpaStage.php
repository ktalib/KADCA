<?php

namespace App\Support;

/**
 * Where a Building Plan Approval file is (briefing 2026-11-22): application and bill, payment, BP file
 * commissioned, Director (Arch.) recommendation, Deputy Director (Arch.) recommendation, Zonal Office.
 * Like KLAES Cadastral: a desk Recommends (the file moves on) or does Not recommend (a reason is
 * required and the file goes back one desk).
 */
class BpaStage
{
    public const ASSESSMENT = 'assessment';
    public const ARCHITECTURE = 'architecture';
    public const DIRECTOR = 'director_arch';
    public const DEPUTY_DIRECTOR = 'dd_arch';
    public const ZONAL_OFFICE = 'zonal_office';

    public const LABELS = [
        self::ASSESSMENT => 'Assessment Stage',
        self::ARCHITECTURE => 'Architecture',
        self::DIRECTOR => 'Director (Arch.)',
        self::DEPUTY_DIRECTOR => 'Deputy Director (Arch.)',
        self::ZONAL_OFFICE => 'Zonal Office',
    ];

    /**
     * The recommendation desks: URL slug => [stage, title, where Recommend sends the file, where Not recommended sends it].
     */
    public const DESKS = [
        'director' => [self::DIRECTOR, 'Director (Arch.)', self::DEPUTY_DIRECTOR, self::ARCHITECTURE],
        'deputy-director' => [self::DEPUTY_DIRECTOR, 'Deputy Director (Arch.)', self::ZONAL_OFFICE, self::DIRECTOR],
    ];

    public const RECOMMENDED = 'recommended';
    public const NOT_RECOMMENDED = 'not_recommended';

    /** The status line shown for a file at each stage. */
    public static function status(string $stage): string
    {
        return match ($stage) {
            self::DIRECTOR => 'With Director (Arch.)',
            self::DEPUTY_DIRECTOR => 'With Deputy Director (Arch.)',
            self::ZONAL_OFFICE => 'With Zonal Office',
            self::ARCHITECTURE => 'Returned to Architecture',
            default => self::LABELS[$stage] ?? $stage,
        };
    }

    public static function label(?string $stage): string
    {
        return self::LABELS[$stage] ?? (string) $stage;
    }

    /** @return array{0: string, 1: string, 2: string, 3: string} */
    public static function desk(string $slug): array
    {
        abort_unless(isset(self::DESKS[$slug]), 404);

        return self::DESKS[$slug];
    }
}
