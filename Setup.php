<?php

namespace ThemeHouse\AvatarGallery;

use ThemeHouse\AvatarGallery\Entity\AvatarCategory;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\Phrase;

/**
 * Class Setup
 * @package ThemeHouse\AvatarGallery
 */
class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    /**
     *
     */
    public function installStep1()
    {
        $this->schemaManager()->createTable('xf_th_avatar_gallery_avatar', function (Create $table) {
            $table->addColumn('avatar_id', 'int')->autoIncrement();
            $table->addColumn('avatar_date', 'int')->setDefault(0);
            $table->addColumn('avatar_category_id', 'int')->setDefault(0);
            $table->addColumn('display_order', 'int')->setDefault(100);
            $table->addColumn('active', 'tinyint')->setDefault(1);
            $table->addColumn('avatar_url', 'text')->nullable();
            $table->addColumn('user_criteria', 'blob');
            $table->addColumn('resized', 'bool')->setDefault(0);
        });
    }

    /**
     *
     */
    public function installStep2()
    {
        $this->schemaManager()->createTable('xf_th_avatar_gallery_avatar_category',
            function (Create $table) {
                $table->addColumn('avatar_category_id', 'int')->unsigned(false)->autoIncrement();
                $table->addColumn('display_order', 'int')->setDefault(100);
                $table->addColumn('active', 'tinyint')->setDefault(1);
            });
    }

    /**
     * @throws \XF\PrintableException
     */
    public function installStep3()
    {
        /** @var AvatarCategory $category */
        $category = \XF::em()->create('ThemeHouse\AvatarGallery:AvatarCategory');
        $category->save();

        /** @var Phrase $title */
        $title = $category->getMasterPhrase();
        $title->phrase_text = 'Uncategorized';
        $title->save();
    }

    /**
     *
     */
    public function installStep4()
    {
        $this->schemaManager()->alterTable('xf_user', function (Alter $table) {
            $table->addColumn('thag_avatar_id', 'int')->setDefault(0);
        });
    }

    /**
     * @param array $stateChanges
     */
    public function postInstall(array &$stateChanges)
    {
        $this->applyGlobalPermission('th_avatargallery', 'select');
        $this->applyGlobalPermission('th_avatargallery', 'upload');

        $this->app()->jobManager()->enqueueUnique('languageRebuild', 'XF:Atomic', [
            'execute' => ['XF:PhraseRebuild', 'XF:TemplateRebuild']
        ]);
    }

    /**
     *
     */
    public function upgrade1000110Step1()
    {
        $this->schemaManager()->alterTable('xf_user', function (Alter $table) {
            $table->addColumn('thag_avatar_id', 'int')->setDefault(0);
        });
    }

    /**
     *
     */
    public function upgrade1000110Step2()
    {
        $this->schemaManager()->alterTable('xf_th_avatar_gallery_avatar', function (Alter $table) {
            $table->addColumn('avatar_url', 'text')->nullable();
        });
    }

    public function upgrade1000351Step1()
    {
        $this->schemaManager()->alterTable('xf_th_avatar_gallery_avatar', function (Alter $table) {
            $table->addColumn('resized', 'bool')->setDefault(0);
        });
    }

    /**
     *
     */
    public function uninstallStep1()
    {
        $this->schemaManager()->dropTable('xf_th_avatar_gallery_avatar');
    }

    /**
     *
     */
    public function uninstallStep2()
    {
        $this->schemaManager()->dropTable('xf_th_avatar_gallery_avatar_category');
    }

    public function uninstallStep3()
    {
        \XF::db()->update('xf_user', ['avatar_date' => 0], 'thag_avatar_id > 0');
    }

    /**
     *
     */
    public function uninstallStep4()
    {
        $this->schemaManager()->alterTable('xf_user', function (Alter $table) {
            $table->dropColumns(['thag_avatar_id']);
        });
    }
}