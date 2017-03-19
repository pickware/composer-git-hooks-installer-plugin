<?php
namespace VIISON\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * @copyright Copyright (c) 2017 VIISON GmbH
 */
class GitHooksInstallerPlugin implements PluginInterface
{
    /**
     * @inheritdoc
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new GitHooksInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }
}
