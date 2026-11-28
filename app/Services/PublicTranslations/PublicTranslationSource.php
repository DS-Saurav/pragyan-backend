<?php

namespace App\Services\PublicTranslations;

use App\Models\AdmissionCycle;
use App\Models\GalleryAlbum;
use App\Models\NewsStory;
use App\Models\Notice;
use App\Models\PublicContentTranslationRevision;
use App\Models\PublicPage;
use App\Models\PublicTeamProfile;
use App\Models\SchoolEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The explicit allowlist between translated payloads and the existing English
 * public-content models. Translation records intentionally hold only text
 * entered for the selected locale; this adapter never supplies source text as
 * a payload default.
 */
final class PublicTranslationSource
{
    public const ADMISSION_CYCLE = 'admission_cycle';

    public const GALLERY_ALBUM = 'gallery_album';

    public const NEWS_STORY = 'news_story';

    public const NOTICE = 'notice';

    public const PUBLIC_PAGE = 'public_page';

    public const PUBLIC_TEAM_PROFILE = 'public_team_profile';

    public const SCHOOL_EVENT = 'school_event';

    /** @var array<int, string> */
    public const TYPES = [
        self::ADMISSION_CYCLE,
        self::GALLERY_ALBUM,
        self::NEWS_STORY,
        self::NOTICE,
        self::PUBLIC_PAGE,
        self::PUBLIC_TEAM_PROFILE,
        self::SCHOOL_EVENT,
    ];

    /**
     * @return class-string<Model>
     */
    public function modelClass(string $type): string
    {
        return match ($type) {
            self::ADMISSION_CYCLE => AdmissionCycle::class,
            self::GALLERY_ALBUM => GalleryAlbum::class,
            self::NEWS_STORY => NewsStory::class,
            self::NOTICE => Notice::class,
            self::PUBLIC_PAGE => PublicPage::class,
            self::PUBLIC_TEAM_PROFILE => PublicTeamProfile::class,
            self::SCHOOL_EVENT => SchoolEvent::class,
            default => throw new InvalidArgumentException('Unsupported public translation source type.'),
        };
    }

    public function sourceFor(string $type, int $id): Model
    {
        if ($id < 1) {
            throw new InvalidArgumentException('A public translation source id must be positive.');
        }

        $model = $this->modelClass($type);

        return $model::query()->findOrFail($id);
    }

    public function typeFor(Model $source): string
    {
        return match (true) {
            $source instanceof AdmissionCycle => self::ADMISSION_CYCLE,
            $source instanceof GalleryAlbum => self::GALLERY_ALBUM,
            $source instanceof NewsStory => self::NEWS_STORY,
            $source instanceof Notice => self::NOTICE,
            $source instanceof PublicPage => self::PUBLIC_PAGE,
            $source instanceof PublicTeamProfile => self::PUBLIC_TEAM_PROFILE,
            $source instanceof SchoolEvent => self::SCHOOL_EVENT,
            default => throw new InvalidArgumentException('Unsupported public translation source model.'),
        };
    }

    /**
     * A revisioned English item is current only while its published revision
     * remains the one that the Nepali revision was prepared from. Direct
     * content uses a deterministic source fingerprint instead.
     */
    public function currentRevisionId(Model $source): ?int
    {
        if (! in_array($this->typeFor($source), [
            self::GALLERY_ALBUM,
            self::NEWS_STORY,
            self::NOTICE,
            self::PUBLIC_PAGE,
            self::PUBLIC_TEAM_PROFILE,
        ], true)) {
            return null;
        }

        $id = (int) $source->getAttribute('published_revision_id');

        return $id > 0 ? $id : null;
    }

    public function fingerprint(string $type, Model $source): string
    {
        $this->assertTypeMatches($type, $source);

        $encoded = json_encode(
            $this->sourceSnapshot($type, $source),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return hash('sha256', $encoded);
    }

    public function isCurrentSnapshot(
        string $type,
        Model $source,
        PublicContentTranslationRevision $translationRevision,
    ): bool {
        $this->assertTypeMatches($type, $source);

        $sourceRevisionId = $this->currentRevisionId($source);
        if ($sourceRevisionId !== null) {
            return (int) $translationRevision->source_revision_id === $sourceRevisionId
                && hash_equals($this->fingerprint($type, $source), (string) $translationRevision->source_fingerprint);
        }

        $fingerprint = (string) $translationRevision->source_fingerprint;

        return $fingerprint !== '' && hash_equals($this->fingerprint($type, $source), $fingerprint);
    }

    public function sourceLabel(string $type, Model $source): string
    {
        $this->assertTypeMatches($type, $source);

        return match ($type) {
            self::ADMISSION_CYCLE => trim((string) $source->getAttribute('label')) ?: 'Admissions cycle #'.$source->getKey(),
            self::GALLERY_ALBUM => trim((string) $source->getAttribute('title')) ?: 'Gallery album #'.$source->getKey(),
            self::NEWS_STORY => trim((string) $this->publishedAttribute($source, 'title')) ?: 'News story #'.$source->getKey(),
            self::NOTICE => trim((string) $this->publishedAttribute($source, 'title')) ?: 'Notice #'.$source->getKey(),
            self::PUBLIC_PAGE => match ($source->getAttribute('type')) {
                PublicPage::TYPE_ABOUT => 'About page',
                PublicPage::TYPE_ACADEMICS => 'Academics page',
                default => 'Public page #'.$source->getKey(),
            },
            self::PUBLIC_TEAM_PROFILE => trim((string) $this->publishedAttribute($source, 'display_name')) ?: 'Team profile #'.$source->getKey(),
            self::SCHOOL_EVENT => trim((string) $source->getAttribute('title')) ?: 'Calendar event #'.$source->getKey(),
        };
    }

    /**
     * A deliberately read-only, compact reference for the private editor.
     * This is separate from the translation payload so no English text can be
     * accidentally submitted as Nepali copy.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function sourceFields(string $type, Model $source): array
    {
        $this->assertTypeMatches($type, $source);

        $snapshot = $this->sourceSnapshot($type, $source);
        $fields = match ($type) {
            self::NEWS_STORY => [
                'Title' => $snapshot['title'] ?? null,
                'Summary' => $snapshot['summary'] ?? null,
                'Article body' => $snapshot['body_markdown'] ?? null,
                'Category' => $snapshot['category'] ?? null,
            ],
            self::GALLERY_ALBUM => [
                'Album title' => $snapshot['title'] ?? null,
                'Album summary' => $snapshot['summary'] ?? null,
                'Category' => $snapshot['category'] ?? null,
            ],
            self::NOTICE => [
                'Title' => $snapshot['title'] ?? null,
                'Notice content' => $snapshot['content'] ?? null,
            ],
            self::PUBLIC_PAGE => [
                'Page title' => $snapshot['title'] ?? null,
                'Introduction' => $snapshot['introduction_markdown'] ?? null,
                'History' => $snapshot['history_markdown'] ?? null,
                'Mission' => $snapshot['mission_markdown'] ?? null,
                'Vision' => $snapshot['vision_markdown'] ?? null,
                'Facilities' => $snapshot['facilities_markdown'] ?? null,
            ],
            self::PUBLIC_TEAM_PROFILE => [
                'Display name' => $snapshot['display_name'] ?? null,
                'Public role' => $snapshot['public_role'] ?? null,
                'Qualification' => $snapshot['qualification'] ?? null,
                'Biography' => $snapshot['bio_markdown'] ?? null,
            ],
            self::ADMISSION_CYCLE => [
                'Cycle label' => $snapshot['label'] ?? null,
                'Public status' => $snapshot['public_status'] ?? null,
                'Admissions introduction' => $snapshot['public_intro'] ?? null,
                'Fee summary' => $snapshot['fee_summary'] ?? null,
            ],
            self::SCHOOL_EVENT => [
                'Event title' => $snapshot['title'] ?? null,
                'Event description' => $snapshot['description'] ?? null,
            ],
        };

        return collect($fields)
            ->map(fn (mixed $value, string $label): array => [
                'label' => $label,
                'value' => $this->referenceValue($value),
            ])
            ->filter(fn (array $field): bool => $field['value'] !== '')
            ->values()
            ->all();
    }

    /**
     * Form metadata uses neutral labels and current source item identifiers.
     * It deliberately does not expose English values as input defaults.
     *
     * @return array{fields: array<int, array<string, mixed>>, collections: array<int, array<string, mixed>>}
     */
    public function payloadSchema(string $type, Model $source): array
    {
        $this->assertTypeMatches($type, $source);

        return match ($type) {
            self::NEWS_STORY => $this->schema([
                $this->field('title', 'Title', 180, true),
                $this->field('summary', 'Summary', 500, true, 'textarea'),
                $this->field('body_markdown', 'Article body', 50000, true, 'textarea'),
                $this->field('category', 'Category', 80),
                $this->field('public_attribution', 'Public attribution', 255),
            ]),
            self::GALLERY_ALBUM => $this->schema([
                $this->field('title', 'Album title', 180, true),
                $this->field('summary', 'Album summary', 500, true, 'textarea'),
                $this->field('category', 'Category', 80),
            ]),
            self::NOTICE => $this->schema([
                $this->field('title', 'Notice title', 180, true),
                $this->field('content', 'Notice content', 50000, true, 'textarea'),
            ]),
            self::PUBLIC_PAGE => $this->publicPageSchema($source),
            self::PUBLIC_TEAM_PROFILE => $this->schema([
                $this->field('display_name', 'Display name', 140, true),
                $this->field('public_role', 'Public role', 140, true),
                $this->field('qualification', 'Qualification', 255),
                $this->field('bio_markdown', 'Biography', 10000, false, 'textarea'),
            ]),
            self::ADMISSION_CYCLE => $this->admissionSchema($source),
            self::SCHOOL_EVENT => $this->schema([
                $this->field('title', 'Event title', 255, true),
                $this->field('description', 'Event description', 10000, false, 'textarea'),
            ]),
        };
    }

    /**
     * Validate and normalise only the typed Nepali fields for a source. Draft
     * payloads may be incomplete; publication validation belongs to the
     * workflow service so authors can save a genuine in-progress translation.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validatePayload(string $type, Model $source, array $payload): array
    {
        $this->assertTypeMatches($type, $source);
        $schema = $this->payloadSchema($type, $source);
        $allowedKeys = array_column($schema['fields'], 'key');
        $allowedKeys = array_merge($allowedKeys, array_column($schema['collections'], 'key'));
        $this->rejectUnexpectedKeys($payload, $allowedKeys);

        return match ($type) {
            self::ADMISSION_CYCLE => $this->validateAdmissionPayload($source, $payload),
            self::PUBLIC_PAGE => $this->validatePublicPagePayload($source, $payload),
            default => $this->validateScalarPayload($schema['fields'], $payload),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceSnapshot(string $type, Model $source): array
    {
        return match ($type) {
            self::NEWS_STORY => $this->revisionSnapshot($source, [
                'title', 'summary', 'body_markdown', 'category', 'public_attribution',
            ]),
            self::GALLERY_ALBUM => [
                'title' => $source->getAttribute('title'),
                'summary' => $source->getAttribute('description'),
                'album_date' => $source->getAttribute('event_date')?->format('Y-m-d'),
                'category' => $source instanceof GalleryAlbum ? $source->category?->name : null,
            ],
            self::NOTICE => $this->noticeSnapshot($source),
            self::PUBLIC_PAGE => $this->publicPageSnapshot($source),
            self::PUBLIC_TEAM_PROFILE => $this->revisionSnapshot($source, [
                'display_name', 'public_role', 'team_group', 'qualification', 'bio_markdown', 'display_order',
            ]),
            self::ADMISSION_CYCLE => $this->admissionSnapshot($source),
            self::SCHOOL_EVENT => [
                'id' => $source->getKey(),
                'title' => $source->getAttribute('title'),
                'date' => $this->dateString($source->getAttribute('date')),
                'type' => $source->getAttribute('type'),
                'description' => $source->getAttribute('description'),
                'is_public' => (bool) $source->getAttribute('is_public'),
                'updated_at' => $this->dateString($source->getAttribute('updated_at')),
            ],
        };
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function revisionSnapshot(Model $source, array $fields): array
    {
        $source->loadMissing('publishedRevision');
        $revision = $source->getRelation('publishedRevision');
        $snapshot = ['id' => $source->getKey(), 'published_revision_id' => $source->getAttribute('published_revision_id')];

        foreach ($fields as $field) {
            $snapshot[$field] = $revision?->getAttribute($field);
        }

        return $snapshot;
    }

    /** @return array<string, mixed> */
    private function noticeSnapshot(Model $source): array
    {
        $source->loadMissing('publishedRevision');
        $revision = $source->getRelation('publishedRevision');

        if ($revision !== null) {
            return [
                'id' => $source->getKey(),
                'published_revision_id' => $source->getAttribute('published_revision_id'),
                'title' => $revision->getAttribute('title'),
                'content' => $revision->getAttribute('content'),
                'audience' => $revision->getAttribute('audience'),
                'date' => $this->dateString($revision->getAttribute('date')),
            ];
        }

        return [
            'id' => $source->getKey(),
            'published_revision_id' => null,
            'title' => $source->getAttribute('title'),
            'content' => $source->getAttribute('content'),
            'audience' => $source->getAttribute('audience'),
            'date' => $this->dateString($source->getAttribute('date')),
            'updated_at' => $this->dateString($source->getAttribute('updated_at')),
        ];
    }

    /** @return array<string, mixed> */
    private function publicPageSnapshot(Model $source): array
    {
        $source->loadMissing('publishedRevision.academicStages');
        $revision = $source->getRelation('publishedRevision');
        $stages = $revision?->getRelation('academicStages') ?? collect();

        return [
            'id' => $source->getKey(),
            'type' => $source->getAttribute('type'),
            'published_revision_id' => $source->getAttribute('published_revision_id'),
            'title' => $revision?->getAttribute('title'),
            'introduction_markdown' => $revision?->getAttribute('introduction_markdown'),
            'history_markdown' => $revision?->getAttribute('history_markdown'),
            'mission_markdown' => $revision?->getAttribute('mission_markdown'),
            'vision_markdown' => $revision?->getAttribute('vision_markdown'),
            'approach_values' => $revision?->getAttribute('approach_values'),
            'facilities_markdown' => $revision?->getAttribute('facilities_markdown'),
            'stages' => $stages->map(fn (Model $stage): array => [
                'id' => $stage->getKey(),
                'title' => $stage->getAttribute('title'),
                'stage_label' => $stage->getAttribute('stage_label'),
                'description_markdown' => $stage->getAttribute('description_markdown'),
                'key_points' => $stage->getAttribute('key_points'),
                'display_order' => $stage->getAttribute('display_order'),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function admissionSnapshot(Model $source): array
    {
        $source->loadMissing(['grades', 'steps', 'requirements', 'faqs']);

        return [
            'id' => $source->getKey(),
            'label' => $source->getAttribute('label'),
            'public_status' => $source->getAttribute('public_status'),
            'public_intro' => $source->getAttribute('public_intro'),
            'display_starts_on' => $this->dateString($source->getAttribute('display_starts_on')),
            'display_ends_on' => $this->dateString($source->getAttribute('display_ends_on')),
            'public_phone' => $source->getAttribute('public_phone'),
            'public_email' => $source->getAttribute('public_email'),
            'primary_action_label' => $source->getAttribute('primary_action_label'),
            'primary_action_url' => $source->getAttribute('primary_action_url'),
            'fee_summary' => $source->getAttribute('fee_summary'),
            'grades' => $source->grades->map(fn (Model $row): array => [
                'id' => $row->getKey(), 'grade' => $row->getAttribute('grade'), 'sort_order' => $row->getAttribute('sort_order'),
            ])->values()->all(),
            'steps' => $source->steps->map(fn (Model $row): array => [
                'id' => $row->getKey(), 'title' => $row->getAttribute('title'), 'body' => $row->getAttribute('body'), 'sort_order' => $row->getAttribute('sort_order'),
            ])->values()->all(),
            'requirements' => $source->requirements->map(fn (Model $row): array => [
                'id' => $row->getKey(), 'label' => $row->getAttribute('label'), 'detail' => $row->getAttribute('detail'), 'sort_order' => $row->getAttribute('sort_order'),
            ])->values()->all(),
            'faqs' => $source->faqs->map(fn (Model $row): array => [
                'id' => $row->getKey(), 'question' => $row->getAttribute('question'), 'answer' => $row->getAttribute('answer'), 'sort_order' => $row->getAttribute('sort_order'),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array{fields: array<int, array<string, mixed>>, collections: array<int, array<string, mixed>>}
     */
    private function schema(array $fields, array $collections = []): array
    {
        return ['fields' => $fields, 'collections' => $collections];
    }

    private function field(string $key, string $label, int $max, bool $requiredForPublish = false, string $type = 'text'): array
    {
        return compact('key', 'label', 'max', 'requiredForPublish', 'type');
    }

    /** @return array{fields: array<int, array<string, mixed>>, collections: array<int, array<string, mixed>>} */
    private function admissionSchema(Model $source): array
    {
        $source->loadMissing(['grades', 'steps', 'requirements', 'faqs']);

        return $this->schema([
            $this->field('label', 'Admissions cycle label', 120, true),
            $this->field('public_status_message', 'Public status message', 255, true, 'textarea'),
            $this->field('public_intro', 'Admissions introduction', 2000, false, 'textarea'),
            $this->field('primary_action_label', 'Primary action label', 60),
            $this->field('fee_summary', 'Fee summary', 4000, false, 'textarea'),
        ], [
            $this->collection('grades', 'Eligible grades', $source->grades, [$this->field('label', 'Grade label', 120, true)]),
            $this->collection('steps', 'Application steps', $source->steps, [
                $this->field('title', 'Step title', 160, true),
                $this->field('body', 'Step detail', 2000, false, 'textarea'),
            ]),
            $this->collection('requirements', 'Requirements', $source->requirements, [
                $this->field('label', 'Requirement', 160, true),
                $this->field('detail', 'Requirement detail', 2000, false, 'textarea'),
            ]),
            $this->collection('faqs', 'Frequently asked questions', $source->faqs, [
                $this->field('question', 'Question', 255, true),
                $this->field('answer', 'Answer', 4000, true, 'textarea'),
            ]),
        ]);
    }

    /** @return array{fields: array<int, array<string, mixed>>, collections: array<int, array<string, mixed>>} */
    private function publicPageSchema(Model $source): array
    {
        $source->loadMissing('publishedRevision.academicStages');
        $stages = $source->publishedRevision?->academicStages ?? collect();

        return $this->schema([
            $this->field('title', 'Page title', 180, true),
            $this->field('introduction_markdown', 'Introduction', 50000, false, 'textarea'),
            $this->field('history_markdown', 'History', 50000, false, 'textarea'),
            $this->field('mission_markdown', 'Mission', 50000, false, 'textarea'),
            $this->field('vision_markdown', 'Vision', 50000, false, 'textarea'),
            $this->field('approach_values', 'Approach and values', 5000, false, 'lines'),
            $this->field('facilities_markdown', 'Facilities', 50000, false, 'textarea'),
        ], $source->getAttribute('type') === PublicPage::TYPE_ACADEMICS ? [
            $this->collection('stages', 'Academic stages', $stages, [
                $this->field('title', 'Stage title', 180, true),
                $this->field('stage_label', 'Stage label', 100),
                $this->field('description_markdown', 'Stage description', 50000, false, 'textarea'),
                $this->field('key_points', 'Key points', 5000, false, 'lines'),
            ]),
        ] : []);
    }

    /**
     * @param  iterable<int, Model>  $sourceItems
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function collection(string $key, string $label, iterable $sourceItems, array $fields): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'fields' => $fields,
            'source_ids' => collect($sourceItems)->map(fn (Model $item): int => (int) $item->getKey())->values()->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateScalarPayload(array $fields, array $payload): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $rules[$field['key']] = ['nullable', 'string', 'max:'.$field['max']];
        }

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($payload, $rules)->validate();

        foreach ($fields as $field) {
            $key = $field['key'];
            $validated[$key] = $this->nullableString($validated[$key] ?? null);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateAdmissionPayload(Model $source, array $payload): array
    {
        $source->loadMissing(['grades', 'steps', 'requirements', 'faqs']);
        $base = $this->validateScalarPayload($this->admissionSchema($source)['fields'], $payload);

        return $base + [
            'grades' => $this->validateKeyedRows($this->arrayPayloadValue($payload, 'grades'), $source->grades, [
                'label' => ['nullable', 'string', 'max:120'],
            ]),
            'steps' => $this->validateKeyedRows($this->arrayPayloadValue($payload, 'steps'), $source->steps, [
                'title' => ['nullable', 'string', 'max:160'],
                'body' => ['nullable', 'string', 'max:2000'],
            ]),
            'requirements' => $this->validateKeyedRows($this->arrayPayloadValue($payload, 'requirements'), $source->requirements, [
                'label' => ['nullable', 'string', 'max:160'],
                'detail' => ['nullable', 'string', 'max:2000'],
            ]),
            'faqs' => $this->validateKeyedRows($this->arrayPayloadValue($payload, 'faqs'), $source->faqs, [
                'question' => ['nullable', 'string', 'max:255'],
                'answer' => ['nullable', 'string', 'max:4000'],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validatePublicPagePayload(Model $source, array $payload): array
    {
        $schema = $this->publicPageSchema($source);
        $scalarFields = array_values(array_filter(
            $schema['fields'],
            fn (array $field): bool => $field['key'] !== 'approach_values',
        ));
        $base = $this->validateScalarPayload($scalarFields, $payload);
        $base['approach_values'] = $this->validateLines($payload['approach_values'] ?? [], 12, 500);

        if ($source->getAttribute('type') !== PublicPage::TYPE_ACADEMICS) {
            return $base;
        }

        $source->loadMissing('publishedRevision.academicStages');
        $base['stages'] = $this->validateKeyedRows(
            $this->arrayPayloadValue($payload, 'stages'),
            $source->publishedRevision?->academicStages ?? [],
            [
                'title' => ['nullable', 'string', 'max:180'],
                'stage_label' => ['nullable', 'string', 'max:100'],
                'description_markdown' => ['nullable', 'string', 'max:50000'],
                'key_points' => ['nullable', 'array', 'max:12'],
            ],
            ['key_points' => [12, 500]],
        );

        return $base;
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @param  iterable<int, Model>  $sourceRows
     * @param  array<string, array<int, string>>  $rules
     * @param  array<string, array{0: int, 1: int}>  $lineFields
     * @return array<string, array<string, mixed>>
     */
    private function validateKeyedRows(array $rows, iterable $sourceRows, array $rules, array $lineFields = []): array
    {
        $allowedIds = collect($sourceRows)->mapWithKeys(fn (Model $row): array => [(string) $row->getKey() => true])->all();
        $normalised = [];

        foreach ($rows as $id => $row) {
            $id = (string) $id;
            if (! isset($allowedIds[$id])) {
                throw ValidationException::withMessages(['payload' => 'A translation row no longer belongs to the current English source.']);
            }
            if (! is_array($row)) {
                throw ValidationException::withMessages(['payload.'.$id => 'Each translation row must be an object.']);
            }

            $this->rejectUnexpectedKeys($row, array_keys($rules));
            /** @var array<string, mixed> $validated */
            $validated = Validator::make($row, $rules)->validate();
            foreach (array_keys($rules) as $key) {
                if (isset($lineFields[$key])) {
                    $limits = $lineFields[$key];
                    $validated[$key] = $this->validateLines($validated[$key] ?? [], $limits[0], $limits[1]);
                } else {
                    $validated[$key] = $this->nullableString($validated[$key] ?? null);
                }
            }
            $normalised[$id] = $validated;
        }

        return $normalised;
    }

    private function validateLines(mixed $value, int $maxItems, int $maxLength): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }
        if (! is_array($value)) {
            throw ValidationException::withMessages(['payload' => 'A list translation must be an array.']);
        }
        if (count($value) > $maxItems) {
            throw ValidationException::withMessages(['payload' => 'The translation contains too many list entries.']);
        }

        $lines = [];
        foreach ($value as $line) {
            if (! is_string($line)) {
                throw ValidationException::withMessages(['payload' => 'Each list entry must be text.']);
            }
            $line = trim($line);
            if ($line !== '') {
                if (mb_strlen($line) > $maxLength) {
                    throw ValidationException::withMessages(['payload' => 'A list entry is too long.']);
                }
                $lines[] = $line;
            }
        }

        return array_values($lines);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $allowedKeys
     */
    private function rejectUnexpectedKeys(array $payload, array $allowedKeys): void
    {
        $unexpected = array_diff(array_keys($payload), $allowedKeys);
        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'payload' => 'Unsupported translation fields were submitted.',
            ]);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int|string, mixed>
     */
    private function arrayPayloadValue(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];
        if (! is_array($value)) {
            throw ValidationException::withMessages([
                'payload.'.$key => 'This translation section must be an array.',
            ]);
        }

        return $value;
    }

    private function publishedAttribute(Model $source, string $attribute): mixed
    {
        $source->loadMissing('publishedRevision');

        return $source->publishedRevision?->getAttribute($attribute) ?? $source->getAttribute($attribute);
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return method_exists($value, 'toAtomString')
            ? $value->toAtomString()
            : (string) $value;
    }

    private function referenceValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode("\n", array_map(fn (mixed $item): string => $this->referenceValue($item), $value));
        }

        return trim((string) $value);
    }

    private function assertTypeMatches(string $type, Model $source): void
    {
        if ($this->typeFor($source) !== $type) {
            throw new InvalidArgumentException('The public translation source does not match the requested type.');
        }
    }
}
