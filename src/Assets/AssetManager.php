<?php namespace Flarum\Assets;

use RuntimeException;

class AssetManager
{
    protected $less;

    protected $js;

    public function __construct(CompilerInterface $js, CompilerInterface $less)
    {
        $this->js = $js;
        $this->less = $less;
    }

    public function addFile($file)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        switch ($ext) {
            case 'js':
                $this->js->addFile($file);
                break;

            case 'css':
            case 'less':
                $this->less->addFile($file);
                break;

            default:
                throw new RuntimeException('Unsupported asset type: '.$ext);
        }
    }

    public function addFiles(array $files)
    {
        array_walk($files, [$this, 'addFile']);
    }

    public function addLess($string)
    {
        $this->less->addString($string);
    }

    public function addJs($strings)
    {
        $this->js->addString($string);
    }

    public function getCssFile()
    {
        return $this->less->getFile();
    }

    public function getJsFile()
    {
        return $this->js->getFile();
    }
}