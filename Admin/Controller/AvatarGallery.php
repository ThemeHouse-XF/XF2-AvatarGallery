<?php

namespace ThemeHouse\AvatarGallery\Admin\Controller;

use ThemeHouse\AvatarGallery\Entity\AvatarCategory;
use ThemeHouse\AvatarGallery\Repository\Avatar;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\Toggle;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Util\File;

/**
 * Class AvatarGallery
 * @package ThemeHouse\AvatarGallery\Admin\Controller
 */
class AvatarGallery extends AbstractController
{
    /**
     * @return \XF\Mvc\Reply\View
     */
    public function actionIndex()
    {
        $repo = $this->getAvatarRepo();

        $categories = $repo->findAvatarCategories(false)->fetch();
        $avatarFinder = $repo->findAvatars(false);

        $viewParams = [
            'categories' => $categories,
            'avatars' => $avatarFinder->fetch()->groupBy('avatar_category_id'),
            'total' => $avatarFinder->total()
        ];

        return $this->view('ThemeHouse\AvatarGallery:List', 'th_avatar_gallery_list', $viewParams);
    }

    /**
     *
     */
    public function actionCategoryAdd()
    {
        /** @var AvatarCategory $category */
        $category = $this->em()->create('ThemeHouse\AvatarGallery:AvatarCategory');
        return $this->categoryAddEdit($category);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionCategoryEdit(ParameterBag $params)
    {
        $category = $this->assertCategoryExists($params['avatar_category_id']);
        return $this->categoryAddEdit($category);
    }

    /**
     * @param AvatarCategory $category
     * @return \XF\Mvc\Reply\View
     */
    protected function categoryAddEdit(AvatarCategory $category)
    {
        $viewParams = [
            'category' => $category
        ];

        return $this->view('ThemeHouse\AvatarGallery:Category\Edit', 'th_avatar_gallery_category_edit', $viewParams);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionCategorySave(ParameterBag $params)
    {
        if ($params['avatar_category_id']) {
            $category = $this->assertCategoryExists($params['avatar_category_id']);
        } else {
            /** @var AvatarCategory $category */
            $category = $this->em()->create('ThemeHouse\AvatarGallery:AvatarCategory');
        }

        $this->categorySaveProcess($category)->run();

        return $this->redirect($this->buildLink('th-avatar-gallery'));
    }

    /**
     * @param AvatarCategory $category
     * @return \XF\Mvc\FormAction
     */
    protected function categorySaveProcess(AvatarCategory $category)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'display_order' => 'uint',
            'active' => 'bool'
        ]);

        $form->basicEntitySave($category, $input);

        $title = $this->filter('title', 'str');

        $form->validate(function (FormAction $form) use ($title) {
            if ($title === '') {
                $form->logError(\XF::phrase('please_enter_valid_title'), 'title');
            }
        });

        $form->apply(function () use ($category, $title) {
            $masterTitle = $category->getMasterPhrase();
            $masterTitle->phrase_text = $title;
            $masterTitle->save();
        });

        return $form;
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionCategoryDelete(ParameterBag $params)
    {
        $category = $this->assertCategoryExists($params['avatar_category_id']);

        if (!$category->canDelete()) {
            return $this->notFound();
        }

        /** @var \XF\ControllerPlugin\Delete $plugin */
        $plugin = $this->plugin('XF:Delete');
        return $plugin->actionDelete(
            $category,
            $this->buildLink('th-avatar-gallery/category/delete', $category),
            $this->buildLink('th-avatar-gallery/category/edit', $category),
            $this->buildLink('th-avatar-gallery'),
            $category->title
        );
    }

    /**
     * @return mixed
     */
    public function actionAvatarAdd()
    {
        /** @var \ThemeHouse\AvatarGallery\Entity\Avatar $avatar */
        $avatar = $this->em()->create('ThemeHouse\AvatarGallery:Avatar');

        if ($id = $this->filter('category', 'uint')) {
            $avatar->avatar_category_id = $id;
        }

        return $this->avatarAddEdit($avatar);
    }

    /**
     * @param ParameterBag $params
     * @return mixed
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionAvatarEdit(ParameterBag $params)
    {
        $avatar = $this->assertAvatarExists($params['avatar_id']);
        return $this->avatarAddEdit($avatar);
    }

    /**
     * @param \ThemeHouse\AvatarGallery\Entity\Avatar $avatar
     * @return \XF\Mvc\Reply\View
     */
    protected function avatarAddEdit(\ThemeHouse\AvatarGallery\Entity\Avatar $avatar)
    {
        $userCriteria = $this->app->criteria('XF:User', $avatar->user_criteria);

        $repo = $this->getAvatarRepo();
        $categories = $repo->findAvatarCategories(false)->fetch();

        $viewParams = [
            'avatar' => $avatar,
            'categories' => $categories,
            'userCriteria' => $userCriteria
        ];

        return $this->view('ThemeHouse\AvatarGallery:Avatar\Edit', 'th_avatar_gallery_avatar_edit', $viewParams);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionAvatarSave(ParameterBag $params)
    {
        if ($params['avatar_id']) {
            $avatar = $this->assertAvatarExists($params['avatar_id']);
        } else {
            /** @var \ThemeHouse\AvatarGallery\Entity\Avatar $avatar */
            $avatar = $this->em()->create('ThemeHouse\AvatarGallery:Avatar');
        }

        $this->avatarSaveProcess($avatar)->run();

        return $this->redirect($this->buildLink('th-avatar-gallery'));
    }

    /**
     * @param \ThemeHouse\AvatarGallery\Entity\Avatar $avatar
     * @return FormAction
     */
    protected function avatarSaveProcess(\ThemeHouse\AvatarGallery\Entity\Avatar $avatar)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'display_order' => 'uint',
            'active' => 'bool',
            'avatar_category_id' => 'uint',
            'user_criteria' => 'array'
        ]);

        $avatarUrl = $this->filter('avatar_url', 'str');
        $input['avatar_url'] = $avatarUrl ? $avatarUrl : null;

        $form->basicEntitySave($avatar, $input);

        $title = $this->filter('title', 'str');

        $form->validate(function (FormAction $form) use ($title) {
            if ($title === '') {
                $form->logError(\XF::phrase('please_enter_valid_title'), 'title');
            }
        });

        $form->apply(function () use ($avatar, $title) {
            $masterTitle = $avatar->getMasterPhrase();
            $masterTitle->phrase_text = $title;
            $masterTitle->save();
        });

        $file = $this->request()->getFile('upload');

        $form->validate(function (FormAction $form) use ($avatar, $file, $avatarUrl) {
            if ($avatar->isInsert()) {
                if (!$file && !$avatarUrl) {
                    $form->logError(\XF::phrase('thavatargallery_please_define_a_valid_image'), 'title');
                }
            }

            if ($file && !$file->isImage()) {
                $form->logError(\XF::phrase('thavatargallery_please_define_a_valid_image'), 'title');
            }
        });

        $form->apply(function () use ($avatar, $file) {
            if ($file) {
                $imageManager = $this->app->imageManager();
                $targetSize = $this->app->container('avatarSizeMap')['l'];
                $image = $imageManager->imageFromFile($file->getTempFile());
                $image->resizeAndCrop($targetSize);

                $newTempFile = File::getTempFile();
                $image->save($newTempFile);

                File::copyFileToAbstractedPath($newTempFile, $avatar->getAbstracedAvatarPath());
                $avatar->fastUpdate('avatar_date', \XF::$time);
            }
        });

        return $form;
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionAvatarDelete(ParameterBag $params)
    {
        $avatar = $this->assertAvatarExists($params['avatar_id']);

        /** @var \XF\ControllerPlugin\Delete $plugin */
        $plugin = $this->plugin('XF:Delete');
        return $plugin->actionDelete(
            $avatar,
            $this->buildLink('th-avatar-gallery/avatar/delete', $avatar),
            $this->buildLink('th-avatar-gallery/avatar/edit', $avatar),
            $this->buildLink('th-avatar-gallery'),
            $avatar->title
        );
    }

    /**
     * @return \XF\Mvc\Reply\Message
     */
    public function actionAvatarToggle()
    {
        /** @var Toggle $plugin */
        $plugin = $this->plugin('XF:Toggle');
        return $plugin->actionToggle('ThemeHouse\AvatarGallery:Avatar');
    }

    public function actionSort()
    {
        if ($this->isPost())
        {
            $avatars = $this->finder('ThemeHouse\AvatarGallery:Avatar')->fetch();

            foreach ($this->filter('avatars', 'array-json-array') AS $avatarInCategory)
            {
                $lastOrder = 0;
                foreach ($avatarInCategory AS $key => $avatarValue)
                {
                    if (!isset($avatarValue['id']) || !isset($avatars[$avatarValue['id']]))
                    {
                        continue;
                    }

                    $lastOrder += 10;

                    /** @var \ThemeHouse\AvatarGallery\Entity\Avatar $avatar */
                    $avatar = $avatars[$avatarValue['id']];
                    $avatar->avatar_category_id = $avatarValue['parent_id'];
                    $avatar->display_order = $lastOrder;
                    $avatar->saveIfChanged();
                }
            }

            return $this->redirect($this->buildLink('th-avatar-gallery'));
        }
        else
        {
            $repo = $this->getAvatarRepo();
            $categories = $repo->findAvatarCategories(false)->fetch();
            $avatarFinder = $repo->findAvatars(false);

            $viewParams = [
                'categories' => $categories,
                'avatars' => $avatarFinder->fetch()->groupBy('avatar_category_id'),
            ];

            return $this->view('ThemeHouse\AvatarGallery:Avatar\Sort', 'th_avatar_gallery_avatar_sort', $viewParams);
        }
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     * @return \XF\Mvc\Entity\Entity|\ThemeHouse\AvatarGallery\Entity\Avatar
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertAvatarExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('ThemeHouse\AvatarGallery:Avatar', $id, $with, $phraseKey);
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     * @return \XF\Mvc\Entity\Entity|AvatarCategory
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertCategoryExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('ThemeHouse\AvatarGallery:AvatarCategory', $id, $with, $phraseKey);
    }

    /**
     * @return \XF\Mvc\Entity\Repository|Avatar
     */
    protected function getAvatarRepo()
    {
        return $this->repository('ThemeHouse\AvatarGallery:Avatar');
    }
}
