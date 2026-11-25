<?php

namespace App\Services\PublicTeam;

use App\Models\PublicTeamProfile;
use App\Support\PublicTeamProfileView;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublicTeamVisibility
{
    public function __construct(private PublicTeamMarkdown $markdown) {}

    /** @return Builder<PublicTeamProfile> */
    public function displayableQuery(?CarbonInterface $now = null): Builder
    {
        if (! $this->schemaIsReady()) {
            return PublicTeamProfile::query()->fromSub(
                DB::query()->selectRaw('1 as id')->whereRaw('1 = 0'),
                'public_team_profiles',
            );
        }

        $time = $this->atSchoolTime($now);

        return PublicTeamProfile::query()
            ->where('publication_state', 'published')
            ->whereNotNull('published_revision_id')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $time)
            ->whereNull('withdrawn_at')
            ->whereNull('archived_at')
            ->whereHas('publishedRevision', function (Builder $query): void {
                $query->where('workflow_state', 'published')
                    ->whereHas('staffMember', function (Builder $staffQuery): void {
                        $staffQuery->where('status', 'active');
                    });
            });
    }

    /** @return Collection<int, PublicTeamProfileView> */
    public function profiles(?CarbonInterface $now = null): Collection
    {
        return $this->displayableQuery($now)
            ->with(['publishedRevision.portrait.asset'])
            ->get()
            ->map(function (PublicTeamProfile $profile): ?PublicTeamProfileView {
                $revision = $profile->publishedRevision;

                return $revision === null ? null : PublicTeamProfileView::fromRevision($revision, $this->markdown);
            })
            ->filter()
            ->sortBy(function (PublicTeamProfileView $profile): string {
                $groupOrder = array_flip(array_keys(PublicTeamProfile::TEAM_GROUPS));

                return sprintf(
                    '%02d-%05d-%s',
                    $groupOrder[$profile->teamGroup] ?? 99,
                    $profile->displayOrder,
                    mb_strtolower($profile->displayName),
                );
            })
            ->values();
    }

    public function leadership(?CarbonInterface $now = null): ?PublicTeamProfileView
    {
        return $this->profiles($now)
            ->first(fn (PublicTeamProfileView $profile): bool => $profile->teamGroup === 'leadership');
    }

    public function hasPublished(?CarbonInterface $now = null): bool
    {
        return $this->displayableQuery($now)->exists();
    }

    private function atSchoolTime(?CarbonInterface $value): CarbonImmutable
    {
        return $value === null
            ? CarbonImmutable::now(PublicTeamProfilePublisher::TIMEZONE)->utc()
            : CarbonImmutable::instance($value)->setTimezone(PublicTeamProfilePublisher::TIMEZONE)->utc();
    }

    private function schemaIsReady(): bool
    {
        return Schema::hasTable('public_team_profiles')
            && Schema::hasTable('public_team_profile_revisions');
    }
}
