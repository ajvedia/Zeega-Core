<?php

namespace Zeega\DataBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\EmbeddedDocument
 */
class Frame
{
    /**
     * @MongoDB\Id(strategy="auto")
     */
    protected $id;

    /**
     * @MongoDB\Int
     */
    protected $sequenceIndex;

    /**
     * @MongoDB\Hash
     */
    protected $layers;

    /**
     * @MongoDB\Hash
     */
    protected $attr;

    /**
     * @MongoDB\String
     */
    protected $thumbnailUrl;

    /**
     * @MongoDB\Boolean
     */
    protected $enabled = true;

    /**
     * @MongoDB\Boolean
     */
    protected $controllable;

    /**
     * Get id
     *
     * @return id $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set sequenceIndex
     *
     * @param int $sequenceIndex
     * @return \Frame
     */
    public function setSequenceIndex($sequenceIndex)
    {
        $this->sequenceIndex = $sequenceIndex;
        return $this;
    }

    /**
     * Get sequenceIndex
     *
     * @return int $sequenceIndex
     */
    public function getSequenceIndex()
    {
        return $this->sequenceIndex;
    }

    /**
     * Set layers
     *
     * @param hash $layers
     * @return \Frame
     */
    public function setLayers($layers)
    {
        $this->layers = $layers;
        return $this;
    }

    /**
     * Get layers
     *
     * @return hash $layers
     */
    public function getLayers()
    {
        return $this->layers;
    }

    /**
     * Set attr
     *
     * @param hash $attr
     * @return \Frame
     */
    public function setAttr($attr)
    {
        $this->attr = $attr;
        return $this;
    }

    /**
     * Get attr
     *
     * @return hash $attr
     */
    public function getAttr()
    {
        return $this->attr;
    }

    /**
     * Set thumbnailUrl
     *
     * @param string $thumbnailUrl
     * @return \Frame
     */
    public function setThumbnailUrl($thumbnailUrl)
    {
        $this->thumbnailUrl = $thumbnailUrl;
        return $this;
    }

    /**
     * Get thumbnailUrl
     *
     * @return string $thumbnailUrl
     */
    public function getThumbnailUrl()
    {
        return $this->thumbnailUrl;
    }

    /**
     * Set enabled
     *
     * @param boolean $enabled
     * @return \Frame
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Get enabled
     *
     * @return boolean $enabled
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * Set controllable
     *
     * @param boolean $controllable
     * @return \Frame
     */
    public function setControllable($controllable)
    {
        $this->controllable = $controllable;
        return $this;
    }

    /**
     * Get controllable
     *
     * @return boolean $controllable
     */
    public function getControllable()
    {
        return $this->controllable;
    }
}