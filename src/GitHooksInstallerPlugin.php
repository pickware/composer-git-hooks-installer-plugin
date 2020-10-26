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
     * @var GitHooksInstaller|null
     */
    private $installer = null;

    /**
     * @inheritdoc
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->installer = new GitHooksInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->installer);
    }

    /**
     * @inheritdoc
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
        $composer->getInstallationManager()->removeInstaller($this->installer);
    }

    /**
     * @inheritdoc
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
        // Nothing to do
    }
}
