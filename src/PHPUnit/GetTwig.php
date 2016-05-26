<?php

namespace BBC\iPlayerRadio\WebserviceKit\PHPUnit;

trait GetTwig
{
    /**
     * @var     \Twig_Loader_Filesystem
     */
    protected $twigLoader;

    /**
     * @var     \Twig_Environment
     */
    protected $twigEnvironment;

    /**
     * @param   string|array        $paths      Paths to the templates
     * @param   array               $options    Twig_Environment options
     * @return  \Twig_Environment
     */
    public function getTwig($paths, array $options = [])
    {
        if (!isset($this->twigEnvironment)) {
            $this->twigLoader = new \Twig_Loader_Filesystem($paths);
            $this->twigEnvironment = new \Twig_Environment($this->twigLoader, $options);
        }
        return $this->twigEnvironment;
    }
}
