<?php

namespace ThemeHouse\AvatarGallery\Template;

use ThemeHouse\AvatarGallery\Entity\Avatar;
use XF\Template\Templater;

class TemplaterSetup
{
    public function fnThAvatarGalleryAvatar(Templater $templater, &$escape, Avatar $avatar, $size = 'm', $href = '')
    {
        $escape = false;

        if ($href) {
            $tag = 'a';
            $hrefAttr = 'href="' . htmlspecialchars($href) . '"';
        } else {
            $tag = 'span';
            $hrefAttr = '';
        }

        if (!$avatar->avatar_date) {
            return '';
        } else {
            $src = $avatar->getAvatarUrl($size);

            return "<{$tag} {$hrefAttr} class=\"avatar avatar--{$size}\" data-xf-init='tooltip' title='" . $templater->filterForAttr($templater, $avatar->title, $escape) . "'>"
                . '<img src="' . $src . '" alt="' . $templater->filterForAttr($templater, $avatar->title, $escape) . '" />'
                . "</{$tag}>";
        }
    }
}