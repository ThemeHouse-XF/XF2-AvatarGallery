<?php

namespace ThemeHouse\AvatarGallery\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * Class Avatar
 * @package ThemeHouse\AvatarGallery\Entity
 *
 * COLUMNS
 * @property integer avatar_id
 * @property integer avatar_date
 * @property integer avatar_category_id
 * @property integer display_order
 * @property array user_criteria
 * @property bool active
 * @property string avatar_url
 * @property bool resized
 *
 * GETTERS
 * @property Phrase|string title
 *
 * RELATIONS
 * @property \XF\Entity\Phrase MasterTitle
 * @property AvatarCategory Category
 */
class Avatar extends Entity
{
    /**
     * @param string $size
     * @return string
     */
    public function getAbstracedAvatarPath($size = 'o')
    {
        if ($size == 'o') {
            return sprintf(
                'data://th_avatargallery_avatar/%d.jpg',
                $this->avatar_id
            );
        } else {
            return sprintf(
                'data://th_avatargallery_avatar/%s/%d.jpg',
                $size, $this->avatar_id
            );
        }
    }

    /**
     * @throws \XF\PrintableException
     * @throws \Exception
     */
    protected function resizeImage()
    {
        if ($this->avatar_url) {
            $image = \XF\Util\File::getTempFile();
            $img = file_get_contents($this->avatar_url);
            file_put_contents($image, $img);
        } else {
            $image = \XF\Util\File::copyAbstractedPathToTempFile($this->getAbstracedAvatarPath());
        }

        /** @var \ThemeHouse\AvatarGallery\Service\Avatar $service */
        $service = \XF::service('ThemeHouse\AvatarGallery:Avatar', $this);
        $service->setImage($image);
        $service->updateAvatar();
    }

    /**
     * @param bool $canonical
     * @param string $size
     * @return mixed
     * @throws \XF\PrintableException
     */
    public function getAvatarUrl($canonical = false, $size = 'o')
    {
        switch ($size) {
            case 'o':
            case 's':
            case 'm':
            case 'l':
            case 'h':
                break;

            case 'xxs':
            case 'xs':
                $size = 's';
                break;

            default:
                $size = 'o';
        }

        if ($size == 'o') {
            return $this->avatar_url ?: \XF::app()->applyExternalDataUrl(
                "th_avatargallery_avatar/{$this->avatar_id}.jpg?{$this->avatar_date}",
                $canonical
            );
        } else {
            if (!$this->resized) {
                $this->resizeImage();
            }

            return \XF::app()->applyExternalDataUrl(
                "th_avatargallery_avatar/{$size}/{$this->avatar_id}.jpg?{$this->avatar_date}",
                $canonical
            );
        }
    }

    /**
     * @return string
     */
    public function getPhraseName()
    {
        return 'thavatargallery_avatar_title.' . $this->avatar_id;
    }

    /**
     * @return mixed|null|Entity
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
     *
     */
    protected function _preDelete()
    {
        /** @var \ThemeHouse\AvatarGallery\Service\Avatar $service */
        $service = \XF::service('ThemeHouse\AvatarGallery:Avatar', $this);
        $service->deleteAvatar();
    }

    protected function _preSave()
    {
        if ($this->isChanged('avatar_date') || $this->isChanged('avatar_url')) {
            $this->resized = false;
        }
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_avatar_gallery_avatar';
        $structure->primaryKey = 'avatar_id';
        $structure->shortName = 'ThemeHouse\AvatarGallery:Avatar';

        $structure->columns = [
            'avatar_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'avatar_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'avatar_category_id' => ['type' => self::UINT, 'default' => 1],
            'avatar_url' => ['type' => self::STR, 'nullable' => true],
            'display_order' => ['type' => self::UINT, 'default' => 100],
            'active' => ['type' => self::BOOL, 'default' => 1],
            'user_criteria' => ['type' => self::JSON_ARRAY, 'default' => []],
            'resized' => ['type' => self::BOOL, 'default' => 0]
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
                    ['title', '=', 'thavatargallery_avatar_title.', '$avatar_id']
                ]
            ],
            'Category' => [
                'entity' => 'ThemeHouse\AvatarGallery:AvatarCategory',
                'type' => self::TO_ONE,
                'conditions' => 'avatar_gallery_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}