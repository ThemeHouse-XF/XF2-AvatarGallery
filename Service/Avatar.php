<?php

namespace ThemeHouse\AvatarGallery\Service;

use XF\App;
use XF\Http\Upload;
use XF\Image\AbstractDriver;
use XF\Service\AbstractService;
use XF\Util\File;

/**
 * Class Avatar
 * @package ThemeHouse\AvatarGallery\Service
 */
class Avatar extends AbstractService
{
    /** @var \ThemeHouse\AvatarGallery\Entity\Avatar */
    protected $avatar;

    /**
     * @var bool
     */
    protected $logIp = true;
    /**
     * @var bool
     */
    protected $logChange = true;

    /**
     * @var
     */
    protected $fileName;

    /**
     * @var
     */
    protected $width;

    /**
     * @var
     */
    protected $height;

    /**
     * @var
     */
    protected $cropX;
    /**
     * @var
     */
    protected $cropY;

    /**
     * @var
     */
    protected $type;

    /**
     * @var null
     */
    protected $error = null;

    /**
     * @var array
     */
    protected $allowedTypes = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG];

    /**
     * @var mixed|\XF\Container
     */
    protected $sizeMap;

    /**
     * @var bool
     */
    protected $throwErrors = true;

    /**
     *
     */
    const HIGH_DPI_THRESHOLD = 384;

    /**
     * Avatar constructor.
     * @param App $app
     * @param \ThemeHouse\AvatarGallery\Entity\Avatar $avatar
     */
    public function __construct(App $app, \ThemeHouse\AvatarGallery\Entity\Avatar $avatar)
    {
        parent::__construct($app);
        $this->setAvatar($avatar);

        $this->sizeMap = $this->app->container('avatarSizeMap');
    }

    /**
     * @param \ThemeHouse\AvatarGallery\Entity\Avatar $avatar
     */
    protected function setAvatar(\ThemeHouse\AvatarGallery\Entity\Avatar $avatar)
    {
        $this->avatar = $avatar;
    }

    /**
     * @param $logIp
     */
    public function logIp($logIp)
    {
        $this->logIp = $logIp;
    }

    /**
     * @param $logChange
     */
    public function logChange($logChange)
    {
        $this->logChange = $logChange;
    }

    /**
     * @return null
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param $runSilent
     */
    public function silentRunning($runSilent)
    {
        $this->throwErrors = !$runSilent;
    }

    /**
     * @param $fileName
     * @return bool
     * @throws \Exception
     */
    public function setImage($fileName)
    {
        if (!$this->validateImageAsAvatar($fileName, $error))
        {
            $this->error = $error;
            $this->fileName = null;
            return false;
        }

        $this->fileName = $fileName;
        return true;
    }

    /**
     * @param Upload $upload
     * @return bool
     * @throws \Exception
     */
    public function setImageFromUpload(Upload $upload)
    {
        $upload->requireImage();

        if (!$upload->isValid($errors))
        {
            $this->error = reset($errors);
            return false;
        }

        return $this->setImage($upload->getTempFile());
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function setImageFromExisting()
    {
        $path = $this->avatar->getAbstracedAvatarPath('o');
        if (!$this->app->fs()->has($path))
        {
            return $this->throwException(new \InvalidArgumentException("User does not have an 'o' avatar ($path)"));
        }

        $tempFile = File::copyAbstractedPathToTempFile($path);
        return $this->setImage($tempFile);
    }

    /**
     * Sets the cropping values. These coordinates must be scaled to the medium size avatar (default 96px)!
     * Using null will automatically crop at the middle.
     *
     * @param int|null $x
     * @param int|null $y
     */
    public function setCrop($x, $y)
    {
        if ($x === null || $y === null)
        {
            $this->cropX = $x;
            $this->cropY = $y;
        }
        else
        {
            $this->cropX = intval($x);
            $this->cropY = intval($y);
        }
    }

    /**
     * @return array
     */
    public function getCrop()
    {
        return [$this->cropX, $this->cropY];
    }

    /**
     * @param $fileName
     * @param null $error
     * @return bool
     * @throws \Exception
     */
    public function validateImageAsAvatar($fileName, &$error = null)
    {
        $error = null;

        if (!file_exists($fileName))
        {
            return $this->throwException(new \InvalidArgumentException("Invalid file '$fileName' passed to avatar service"));
        }
        if (!is_readable($fileName))
        {
            return $this->throwException(new \InvalidArgumentException("'$fileName' passed to avatar service is not readable"));
        }

        $imageInfo = filesize($fileName) ? @getimagesize($fileName) : false;
        if (!$imageInfo)
        {
            $error = \XF::phrase('provided_file_is_not_valid_image');
            return false;
        }

        $type = $imageInfo[2];
        if (!in_array($type, $this->allowedTypes))
        {
            $error = \XF::phrase('provided_file_is_not_valid_image');
            return false;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        if (!$this->app->imageManager()->canResize($width, $height))
        {
            $error = \XF::phrase('uploaded_image_is_too_big');
            return false;
        }

        $this->width = $width;
        $this->height = $height;
        $this->type = $type;

        return true;
    }

    /**
     * @return bool
     * @throws \XF\PrintableException
     * @throws \Exception
     */
    public function updateAvatar()
    {
        if (!$this->fileName)
        {
            return $this->throwException(new \LogicException("No source file for avatar set"));
        }
        if (!$this->avatar->exists())
        {
            return $this->throwException(new \LogicException("User does not exist, cannot update avatar"));
        }

        $imageManager = $this->app->imageManager();

        $outputFiles = [];
        $baseFile = $this->fileName;

        $origSize = $this->sizeMap['o'];
        $shortSide = min($this->width, $this->height);

        if ($shortSide > $origSize)
        {
            $image = $imageManager->imageFromFile($this->fileName);
            if (!$image)
            {
                return false;
            }

            $image->resizeShortEdge($origSize);

            $newTempFile = File::getTempFile();
            if ($newTempFile && $image->save($newTempFile, null, 95))
            {
                $outputFiles['o'] = $newTempFile;
                $baseFile = $newTempFile;
            }
            else
            {
                return $this->throwException(new \RuntimeException("Failed to save image to temporary file; check internal_data/data permissions"));
            }

            unset($image);
        }
        else
        {
            $outputFiles['o'] = $this->fileName;
        }

        $crop = [
            'm' => [0, 0]
        ];

        foreach ($this->sizeMap AS $code => $size)
        {
            if (isset($outputFiles[$code]))
            {
                continue;
            }

            $image = $imageManager->imageFromFile($baseFile);
            if (!$image)
            {
                continue;
            }

            $crop[$code] = $this->resizeAvatarImage($image, $size);

            $newTempFile = File::getTempFile();
            if ($newTempFile && $image->save($newTempFile))
            {
                $outputFiles[$code] = $newTempFile;
            }
            unset($image);
        }

        if (count($outputFiles) != count($this->sizeMap))
        {
            return $this->throwException(new \RuntimeException("Failed to save image to temporary file; image may be corrupt or check internal_data/data permissions"));
        }

        foreach ($outputFiles AS $code => $file)
        {
            $dataFile = $this->avatar->getAbstracedAvatarPath($code);
            File::copyFileToAbstractedPath($file, $dataFile);
        }

        $avatar = $this->avatar;
        $avatar->resized = true;
        $avatar->save();

        return true;
    }

    /**
     * @param AbstractDriver $image
     * @param $size
     * @return array
     */
    protected function resizeAvatarImage(AbstractDriver $image, $size)
    {
        $sizeMap = $this->sizeMap;

        $cropX = $this->cropX;
        $cropY = $this->cropY;
        $cropScaleRef = $sizeMap['m'];
        if ($cropX === null || $cropY === null)
        {
            $cropScale = $sizeMap['o'] / $cropScaleRef;
            $width = $image->getWidth();
            $height = $image->getHeight();

            $cropX = floor(
                (($width - $sizeMap['o']) / 2) / $cropScale
            );
            $cropY = floor(
                (($height - $sizeMap['o']) / 2) / $cropScale
            );
        }

        $cropX = max($cropX, 0);
        $cropY = max($cropY, 0);

        $image->resizeShortEdge($size, true);

        $cropScale = $size / $cropScaleRef;
        $thisCropX = floor($cropScale * $cropX);
        $thisCropY = floor($cropScale * $cropY);

        $widthOverage = $image->getWidth() - $size;
        if ($widthOverage)
        {
            $thisCropX = min($thisCropX, $widthOverage);
        }

        $heightOverage = $image->getHeight() - $size;
        if ($heightOverage)
        {
            $thisCropY = min($thisCropY, $heightOverage);
        }

        $image->crop($size, $size, $thisCropX, $thisCropY);

        return [$thisCropX, $thisCropY];
    }

    /**
     * @return bool
     * @throws \League\Flysystem\FileExistsException
     */
    public function createOSizeAvatarFromL()
    {
        $avatar = $this->avatar;

        $l = $avatar->getAbstracedAvatarPath('l');
        $o = $avatar->getAbstracedAvatarPath('o');
        $fs = $this->app->fs();

        if (!$fs->has($l) || $fs->has($o))
        {
            return true;
        }

        $fs->copy($l, $o);

        $imageManager = $this->app->imageManager();
        $lSize = $this->sizeMap['l'];

        // temp file has original L image content
        $tempFile = File::copyAbstractedPathToTempFile($l);

        $success = false;

        try
        {
            $image = $imageManager->imageFromFile($tempFile);
            if ($image)
            {
                $this->resizeAvatarImage($image, $lSize);
                $image->save($tempFile);
                // temp file has new L image content
                $success = true;
            }
            else
            {
                // have to remove the avatar
                $success = false;
            }
        }
        catch (\Exception $e)
        {
            \XF::logException($e, false, "Failed to update avatar for avatar {$avatar->avatar_id}: ");
        }

        if ($success)
        {
            File::copyFileToAbstractedPath($tempFile, $l);
        }

        return true;
    }

    /**
     *
     */
    public function deleteAvatar()
    {
        $this->deleteAvatarFiles();
    }

    /**
     *
     */
    protected function deleteAvatarFiles()
    {
        if ($this->avatar->resized)
        {
            foreach ($this->sizeMap AS $code => $size)
            {
                File::deleteFromAbstractedPath($this->avatar->getAbstracedAvatarPath($code));
            }
        }
    }

    /**
     * @param \Exception $error
     *
     * @return bool
     * @throws \Exception
     */
    protected function throwException(\Exception $error)
    {
        if ($this->throwErrors)
        {
            throw $error;
        }
        else
        {
            return false;
        }
    }
}