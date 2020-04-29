//<?php namespace acdd1bdfd2e7062f1195bab0730e7ae73;

use IPS\toolbox\DevCenter\Headerdoc;
use IPS\toolbox\DevFolder\Applications;
use Exception;

if (!defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER[ 'SERVER_PROTOCOL' ] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}


/**
 * Class toolbox_hook_Application
 * @mixin \IPS\Application
 */
class toolbox_hook_Application extends _HOOK_CLASS_
{
    public $skip = false;


    /**
     * @inheritdoc
     */
    public function assignNewVersion($long, $human)
    {
        parent::assignNewVersion($long, $human);
        if (static::appIsEnabled('toolbox')) {
            $this->version = $human;
            Headerdoc::i()->process($this);
        }
    }

    /**
     * @inheritdoc
     */
    public function build()
    {
        if (static::appIsEnabled('toolbox')) {
            Headerdoc::i()->addIndexHtml($this);
        }
        parent::build();
    }

    public function buildHooks()
    {
        if ($this->skip === false) {
            (new \IPS\toolbox\GitHooks([$this->directory]))->removeSpecialHooks(true);
        }
        parent::buildHooks();
    }

    /**
     * @inheritdoc
     */
    public function installOther()
    {
        if (\IPS\IN_DEV) {
            $dir = \IPS\ROOT_PATH . '/applications/' . $this->directory . '/dev/';
            if (!\file_exists($dir)) {
                try {
                    $app = new Applications($this);
                    $app->addToStack = \true;
                    $app->email();
                    $app->javascript();
                    $app->language();
                    $app->templates();
                } catch (Exception $e) {
                }
            }
        }

        parent::installOther();
    }
}
