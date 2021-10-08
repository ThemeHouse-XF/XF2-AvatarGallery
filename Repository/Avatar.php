<?php

namespace ThemeHouse\AvatarGallery\Repository;

use XF\Mvc\Entity\Repository;

/**
 * Class Avatar
 * @package ThemeHouse\AvatarGallery\Repository
 */
class Avatar extends Repository
{
    /**
     * @param bool $visibilityCheck
     * @return \XF\Mvc\Entity\Finder
     */
    public function findAvatarCategories($visibilityCheck = true)
    {
        $finder = $this->finder('ThemeHouse\AvatarGallery:AvatarCategory');

        if ($visibilityCheck) {
            $finder->where('active', '=', 1);
        }

        $finder->setDefaultOrder('display_order', 'ASC');

        return $finder;
    }

    /**
     * @param array $categoryIds
     * @param bool $visibilityCheck
     * @return \XF\Mvc\Entity\Finder
     */
    public function findAvatars($visibilityCheck = true)
    {
        $finder = $this->finder('ThemeHouse\AvatarGallery:Avatar');

        if ($visibilityCheck) {
            $finder->where('active', '=', 1);
        }

        $finder->setDefaultOrder('display_order', 'ASC');

        return $finder;
    }
}