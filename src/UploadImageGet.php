<?php

namespace Olegars\imageHandler;


class UploadImageGet
{
    /**
     * Image name.
     */
    protected $imageName;

    /**
     * Image URL.
     */
    protected $imageUrl;

    /**
     * Image path to disk.
     */
    protected $imagePath;
    protected $size;

    /**
     * UploadImageGet constructor.
     *
     * @param $imageName string image name
     * @param $imageUrl string url to image (/image/upload/image.jpg)
     * @param $imagePath string path image on the disk
     * @param $imageSize array images  size
     */
    function __construct($imageName, $imageUrl, $imagePath, $size)
    {
        $this->imageName = $imageName;
        $this->imageUrl = $imageUrl;
        $this->imagePath = $imagePath;
        $this->size = $size;
    }

    /**
     * Get image name
     *
     * @return string
     */
    public function getImageName()
    {
        return $this->imageName;
    }

    /**
     * Get image Url to image (/image/upload/image.jpg)
     *
     * @return string
     */
    public function getImageUrl()
    {
        return $this->imageUrl;
    }

    /**
     * Get image path on the disk with image name
     *
     * @return string
     */
    public function getImagePath()
    {
        return $this->imagePath;
    }
    public function getImageSize()
    {
        return $this->size;
    }
    public function getNameSize()
    {
        return ['name'=>$this->imageName, 'size'=>$this->size];
    }
}
