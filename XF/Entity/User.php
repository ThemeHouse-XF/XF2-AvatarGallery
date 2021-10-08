<?php

namespace ThemeHouse\AvatarGallery\XF\Entity;

use ThemeHouse\AvatarGallery\Entity\Avatar;
use XF\Mvc\Entity\Structure;

/**
 * Class User
 * @package ThemeHouse\AvatarGallery\XF\Entity
 *
 * @property integer thag_avatar_id
 * @property Avatar THAvatar
 */
class User extends XFCP_User
{
    /**
     * @param null $error
     * @return bool
     */
    public function canThSelectAvatarFromGallery(&$error = null)
    {
        return $this->hasPermission('th_avatargallery', 'select');
    }

    /**
     * @param null $error
     * @return bool
     */
    public function canThUploadAvatar(&$error = null)
    {
        return $this->hasPermission('th_avatargallery', 'upload');
    }

    /**
     * @param $sizeCode
     * @param null $forceType
     * @param bool $canonical
     * @return mixed|null|string
     * @throws \XF\PrintableException
     */
    public function getAvatarUrl($sizeCode, $forceType = null, $canonical = false)
    {
        if ($this->thag_avatar_id) {
            return $this->THAvatar->getAvatarUrl($canonical, $sizeCode);
        }

        return parent::getAvatarUrl($sizeCode, $forceType, $canonical);
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns += [
            'thag_avatar_id' => ['type' => self::UINT, 'default' => 0]
        ];

        $structure->relations += [
            'THAvatar' => [
                'type' => self::TO_ONE,
                'entity' => 'ThemeHouse\AvatarGallery:Avatar',
                'conditions' => [['avatar_id', '=', '$thag_avatar_id']]
            ]
        ];

        $structure->defaultWith[] = 'THAvatar';

        return $structure;
    }
}
