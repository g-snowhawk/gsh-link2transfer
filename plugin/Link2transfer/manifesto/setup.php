<?php
/**
 * @see \Gsnowhawk\PackageSetup
 */

namespace plugin\Link2transfer;

use Gsnowhawk\Common\Lang;
use Gsnowhawk\App;
use Gsnowhawk\PackageSetup;

/**
 * Application install class.
 */
final class Setup extends PackageSetup
{
    /**
     * Current version number.
     */
    public const VERSION = '1.0.0';

    /**
     * Object constructor.
     *
     * @param Gsnowhawk\App $app
     * @param string  $installed_version
     */
    public function __construct(App $app, $installed_version)
    {
        $this->app = $app;
        $this->installed_version = $installed_version;
    }

    /**
     * Namespace of this package.
     *
     * @return string
     */
    public static function getNameSpace()
    {
        return __NAMESPACE__;
    }

    /**
     * Description of this package.
     *
     * @see Gsnowhawk\Common\Lang::translate()
     *
     * @return string
     */
    public static function getDescription()
    {
        return Lang::translate('APP_DETAIL', __CLASS__);
    }

    /**
     * Execute install/update package.
     *
     * @param array &$configuration
     *
     * @return bool
     */
    public function update(&$configuration)
    {
        $result = false;
        if (false !== $this->updateDatabase(__DIR__)
         && false !== $this->updateConfiguration($configuration)
        ) {
            $result = true;
        }

        $key = ($result) ? 'SUCCESS_SETUP' : 'FAILED_SETUP';
        $this->message = Lang::translate($key);

        return $result;
    }

    /**
     * Update configuration.
     *
     * @param array &$configuration
     *
     * @return bool
     */
    private function updateConfiguration(&$configuration)
    {
        $class = __NAMESPACE__;
        if (!isset($configuration['plugins'])) {
            $configuration['plugins'] = ['paths' => []];
        }
        $configuration['plugins']['paths'][] = $class;

        return true;
    }
}
