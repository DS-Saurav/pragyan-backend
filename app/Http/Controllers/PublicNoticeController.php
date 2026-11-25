<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Services\Notices\NoticeMarkdown;
use App\Services\Notices\NoticeVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\HtmlString;

class PublicNoticeController extends Controller
{
    public function __construct(
        private NoticeVisibility $noticeVisibility,
        private NoticeMarkdown $noticeMarkdown,
    ) {}

    public function index(): View
    {
        return view('frontend.notices', [
            'notices' => $this->noticeVisibility->displayableQuery()
                ->with('publishedRevision')
                ->orderByDesc('date')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(12)
                ->withQueryString(),
            'isArchive' => false,
        ]);
    }

    public function archive(): View
    {
        return view('frontend.notices', [
            'notices' => $this->noticeVisibility->displayableQuery()
                ->with('publishedRevision')
                ->orderByDesc('date')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString(),
            'isArchive' => true,
        ]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $result = $this->noticeVisibility->findDisplayableSlug($slug);

        if ($result['redirect'] instanceof Notice) {
            return redirect()->route('public.notices.show', ['slug' => $result['redirect']->slug], 301);
        }

        abort_unless($result['notice'] instanceof Notice, 404);
        $notice = $result['notice'];
        $revision = $notice->publishedRevision;
        $isLegacy = $revision === null;

        return view('frontend.notice-show', [
            'notice' => $notice,
            'revision' => $revision,
            'isLegacy' => $isLegacy,
            'bodyHtml' => $this->bodyHtml($notice),
            'metaDescription' => $this->noticeMarkdown->excerpt((string) ($revision?->content ?? $notice->content), 160),
        ]);
    }

    private function bodyHtml(Notice $notice): HtmlString
    {
        if ($notice->publishedRevision !== null) {
            return $this->noticeMarkdown->toHtml((string) $notice->publishedRevision->content);
        }

        return new HtmlString(nl2br(e((string) $notice->content)));
    }
}
