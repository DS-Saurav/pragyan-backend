<?php

namespace App\Support;

use App\Services\Gallery\GalleryVisibility;
use App\Services\News\NewsVisibility;
use App\Services\PublicPages\PublicPageVisibility;
use App\Services\PublicTeam\PublicTeamVisibility;

/**
 * The public site's single navigation registry. Desktop navigation, mobile
 * navigation and the footer derive their links from this class so labels can
 * never drift into duplicate or placeholder destinations.
 */
class PublicNavigation
{
    /** @return array<int, array<string, mixed>> */
    public static function groups(): array
    {
        $publicPages = app(PublicPageVisibility::class);
        $publicTeam = app(PublicTeamVisibility::class);
        $schoolLifeLinks = [
            ['label' => 'Notices', 'route' => 'public.notices', 'routes' => ['public.notices', 'public.notices.archive', 'public.notices.show']],
            ['label' => 'Calendar', 'route' => 'public.calendar', 'routes' => ['public.calendar']],
        ];

        if (app(GalleryVisibility::class)->hasPublished()) {
            $schoolLifeLinks[] = [
                'label' => 'Gallery',
                'route' => 'public.gallery',
                'routes' => ['public.gallery', 'public.gallery.archive', 'public.gallery.show'],
            ];
        }

        if (app(NewsVisibility::class)->hasPublished()) {
            $schoolLifeLinks[] = [
                'label' => 'News',
                'route' => 'public.news',
                'routes' => ['public.news', 'public.news.archive', 'public.news.show'],
            ];
        }

        $groups = [
            [
                'label' => 'Home',
                'route' => 'home',
                'routes' => ['home'],
                'footer_group' => 'Explore',
            ],
        ];

        $discoverLinks = [];
        if ($publicPages->hasPublished('about')) {
            $discoverLinks[] = ['label' => 'About', 'route' => 'public.about', 'routes' => ['public.about']];
        }
        if ($publicTeam->hasPublished()) {
            $discoverLinks[] = ['label' => 'Leadership and Team', 'route' => 'public.team', 'routes' => ['public.team']];
        }
        if ($discoverLinks !== []) {
            $groups[] = [
                'label' => 'Discover Pragyan',
                'footer_group' => 'Explore',
                'links' => $discoverLinks,
            ];
        }

        if ($publicPages->hasPublished('academics')) {
            $groups[] = [
                'label' => 'Learning',
                'footer_group' => 'Explore',
                'links' => [
                    ['label' => 'Academics', 'route' => 'public.academics', 'routes' => ['public.academics']],
                ],
            ];
        }

        $groups[] = [
            'label' => 'School Life',
            'footer_group' => 'School Life',
            'links' => $schoolLifeLinks,
        ];
        $groups[] = [
            'label' => 'Admissions',
            'route' => 'public.admissions',
            'routes' => ['public.admissions'],
            'footer_group' => 'Get in touch',
        ];
        $groups[] = [
            'label' => 'Contact',
            'route' => 'public.contact',
            'routes' => ['public.contact'],
            'footer_group' => 'Get in touch',
        ];

        return $groups;
    }

    /** @return array<int, array{label: string, links: array<int, array<string, mixed>>}> */
    public static function footerGroups(): array
    {
        $footerGroups = [];

        foreach (self::groups() as $group) {
            $label = $group['footer_group'];
            $footerGroups[$label] ??= ['label' => $label, 'links' => []];

            foreach (self::links($group) as $link) {
                $footerGroups[$label]['links'][] = $link;
            }
        }

        return array_values($footerGroups);
    }

    /** @param array<string, mixed> $group
     * @return array<int, array<string, mixed>>
     */
    public static function links(array $group): array
    {
        if (isset($group['links'])) {
            return $group['links'];
        }

        return [[
            'label' => $group['label'],
            'route' => $group['route'],
            'routes' => $group['routes'],
        ]];
    }

    /** @param array<string, mixed> $group */
    public static function isActive(array $group): bool
    {
        foreach (self::links($group) as $link) {
            if (request()->routeIs($link['routes'])) {
                return true;
            }
        }

        return false;
    }
}
