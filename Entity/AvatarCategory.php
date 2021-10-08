<?php

namespace ThemeHouse\AvatarGallery\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * Class AvatarCategory
 * @package ThemeHouse\AvatarGallery\Entity
 *
 * COLUMNS
 * @property integer avatar_category_id
 * @property integer display_order
 * @property bool active
 *
 * GETTERS
 * @property Phrase|string title
 *
 * RELATIONS
 * @property Phrase MasterTitle
 */
class AvatarCategory extends Entity
{
    /**
     * @return bool
     */
    public function canDelete()
    {
        return $this->avatar_category_id > 1;
    }

    /**
     * @return string
     */
    public function getPhraseName()
    {
        return 'thavatargallery_category_title.' . $this->avatar_category_id;
    }

    /**
     * @return mixed|null|Entity|\XF\Entity\Phrase
     */
    public function getMasterPhrase()
    {
        $phrase = $this->MasterTitle;
        if (!$phrase) {
            /** @var \XF\Entity\Phrase $phrase */
            $phrase = $this->_em->create('XF:Phrase');
            $phrase->title = $this->_getDeferredValue(function () {
                return $this->getPhraseName();
            }, 'save');
            $phrase->language_id = 0;
            $phrase->addon_id = '';
        }

        return $phrase;
    }

    /**
     * @return Phrase
     */
    public function getTitle()
    {
        return \XF::phrase($this->getPhraseName());
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_avatar_gallery_avatar_category';
        $structure->primaryKey = 'avatar_category_id';
        $structure->shortName = 'ThemeHouse\AvatarGallery:AvatarCategory';

        $structure->columns = [
            'avatar_category_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'display_order' => ['type' => self::UINT, 'default' => 100],
            'active' => ['type' => self::BOOL, 'default' => 1],
        ];

        $structure->getters = [
            'title' => true
        ];

        $structure->relations = [
            'MasterTitle' => [
                'entity' => 'XF:Phrase',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', 0],
                    ['title', '=', 'thavatargallery_category_title.', '$avatar_category_id']
                ]
            ]
        ];

        return $structure;
    }
}