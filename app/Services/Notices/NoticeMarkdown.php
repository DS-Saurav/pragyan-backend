<?php

namespace App\Services\Notices;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class NoticeMarkdown
{
    public function toHtml(string $markdown): HtmlString
    {
        return new HtmlString(Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }

    public function excerpt(string $markdown, int $limit = 180): string
    {
        $plainText = html_entity_decode(
            strip_tags($this->toHtml($markdown)->toHtml()),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        return Str::limit(Str::squish($plainText), $limit);
    }
}
