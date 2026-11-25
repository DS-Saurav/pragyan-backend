<?php

namespace App\Support;

use App\Models\MediaAttachment;
use App\Models\PublicTeamProfileRevision;
use App\Services\PublicTeam\PublicTeamMarkdown;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Narrow public projection for Team pages and the homepage leadership band.
 * It deliberately contains no staff identifiers, employment data, or model
 * relationship; public callers cannot accidentally render those attributes.
 */
final readonly class PublicTeamProfileView
{
    public function __construct(
        public string $displayName,
        public string $publicRole,
        public string $teamGroup,
        public ?string $qualification,
        public HtmlString $bioHtml,
        public string $bioExcerpt,
        public int $displayOrder,
        public string $initials,
        public ?string $portraitUrl,
        public string $portraitAlt,
    ) {}

    public static function fromRevision(
        PublicTeamProfileRevision $revision,
        PublicTeamMarkdown $markdown,
    ): self {
        $displayName = trim((string) $revision->display_name);
        $portrait = $revision->portrait;
        $asset = $portrait?->asset;
        $portraitIsSafe = $asset !== null
            && $asset->status === 'published'
            && $asset->archived_at === null
            && in_array($asset->consent_state, ['not_required', 'confirmed'], true)
            && $asset->consent_withdrawn_at === null;
        $altText = self::portraitAlt($portrait, $portraitIsSafe);
        $hasMeaningfulAlternative = $asset?->is_decorative || $altText !== '';

        return new self(
            displayName: $displayName,
            publicRole: trim((string) $revision->public_role),
            teamGroup: trim((string) $revision->team_group),
            qualification: self::nullableString($revision->qualification),
            bioHtml: $markdown->toHtml((string) $revision->bio_markdown),
            bioExcerpt: $markdown->excerpt((string) $revision->bio_markdown),
            displayOrder: (int) $revision->display_order,
            initials: self::initials($displayName),
            portraitUrl: $portraitIsSafe && $hasMeaningfulAlternative
                ? route('media.published', ['id' => $asset->getKey()])
                : null,
            portraitAlt: $asset?->is_decorative ? '' : $altText,
        );
    }

    private static function portraitAlt(?MediaAttachment $portrait, bool $portraitIsSafe): string
    {
        if (! $portraitIsSafe || $portrait === null || $portrait->asset === null) {
            return '';
        }

        return trim((string) ($portrait->alt_text ?: $portrait->asset->default_alt_text));
    }

    private static function initials(string $displayName): string
    {
        return collect(explode(' ', $displayName))
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->implode('') ?: '?';
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

}
