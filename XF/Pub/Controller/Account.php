<?php

namespace ThemeHouse\AvatarGallery\XF\Pub\Controller;

use ThemeHouse\AvatarGallery\Repository\Avatar;
use ThemeHouse\AvatarGallery\XF\Entity\User;
use XF\Mvc\Reply\View;

/**
 * Class Account
 * @package ThemeHouse\AvatarGallery\XF\Pub\Controller
 */
class Account extends XFCP_Account
{
    /**
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|View
     * @throws \XF\PrintableException
     */
    public function actionAvatar()
    {
        if ($this->isPost()) {
            $useCustom = $this->filter('use_custom', 'str');
            /** @var User $visitor */
            $visitor = \XF::visitor();

            if ($this->filter('delete_avatar', 'bool')) {
                $visitor->fastUpdate('thag_avatar_id', 0);
            } elseif ($useCustom === 'th_avatar_gallery') {

                if (!$visitor->canThSelectAvatarFromGallery($error)) {
                    return $this->noPermission($error);
                }

                $id = $this->filter('th_avatar_gallery_avatar_id', 'uint');
                /** @var \ThemeHouse\AvatarGallery\Entity\Avatar $avatar */
                $avatar = $this->em()->find('ThemeHouse\AvatarGallery:Avatar', $id);

                if (!$avatar) {
                    return $this->error(\XF::phrase('thavatargallery_avatar_not_found'));
                }

                $criteria = \XF::app()->criteria('XF:User', $avatar->user_criteria);
                $criteria->setMatchOnEmpty(true);
                if (!$criteria->isMatched($visitor)) {
                    return $this->error(\XF::phrase('thavatargallery_avatar_not_found'));
                }

                $visitor->thag_avatar_id = $id;
                $visitor->avatar_date = \XF::$time;
                $visitor->save();

                if ($this->filter('_xfWithData', 'bool')) {
                    return $this->view('XF:Account\AvatarUpdate', '');
                } else {
                    return $this->redirect($this->buildLink('account/avatar'));
                }
            } elseif ($useCustom == 1) {
                /** @var User $visitor */
                $visitor = \XF::visitor();
                $upload = $this->request->getFile('upload', false, false);

                if ($upload && !$visitor->canThUploadAvatar($error)) {
                    return $this->noPermission($error);
                }
            }
        }

        $response = parent::actionAvatar();

        if (!$this->isPost() && $response instanceof View) {
            $repo = $this->getAvatarRepo();

            $categories = $repo->findAvatarCategories()->fetch();
            $avatars = $repo->findAvatars()->fetch();

            $visitor = \XF::visitor();
            foreach ($avatars as $avatarId => $avatar) {
                /** @var \ThemeHouse\AvatarGallery\Entity\Avatar $avatar */
                $criteria = \XF::app()->criteria('XF:User', $avatar->user_criteria);
                $criteria->setMatchOnEmpty(true);
                if (!$criteria->isMatched($visitor)) {
                    $avatars->offsetUnset($avatarId);
                }
            }

            $avatars = $avatars->groupBy('avatar_category_id');
            $categoryIds = array_keys($avatars);
            foreach ($categories as $categoryId => $category) {
                if (!in_array($categoryId, $categoryIds)) {
                    $categories->offsetUnset($categoryId);
                }
            }

            $response->setParam('thGalleryAvatars', $avatars);
            $response->setParam('thGalleryCategories', $categories);
        }

        return $response;
    }

    /**
     * @return \XF\Mvc\Entity\Repository|Avatar
     */
    protected function getAvatarRepo()
    {
        return $this->repository('ThemeHouse\AvatarGallery:Avatar');
    }
}
