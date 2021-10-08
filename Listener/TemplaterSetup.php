<?php

namespace ThemeHouse\AvatarGallery\Listener;

use XF\Container;
use XF\Template\Templater;

class TemplaterSetup
{
    public static function templaterSetup(Container $container, Templater &$templater)
    {
        /** @var \XFRM\Template\TemplaterSetup $templaterSetup */
        $class = \XF::extendClass('ThemeHouse\AvatarGallery\Template\TemplaterSetup');
        $templaterSetup = new $class();

        $templater->addFunction('th_avatar_gallery_avatar', [$templaterSetup, 'fnThAvatarGalleryAvatar']);
    }
}
