<?php

namespace App\Services\Content;

class ContentRenderer
{
    public function render(?string $html): string
    {
        $safe = app(HtmlSanitizer::class)->clean($html);

        return preg_replace_callback('~<p>\s*\[youtube:([A-Za-z0-9_-]{11})\]\s*</p>~i', function ($match) {
            $id = $match[1];

            return '<div class="youtube-placeholder" data-youtube="'.$id.'"><img src="https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg" alt="Pratinjau video YouTube" loading="lazy" width="480" height="360"><button type="button" aria-label="Putar video YouTube">▶ Putar video</button></div>';
        }, $safe);
    }
}
