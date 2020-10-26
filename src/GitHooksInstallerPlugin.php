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

    /**
     * @inheritdoc
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
        $installationManager = $composer->getInstallationManager();
        try {
            $installer = $installationManager->getInstaller(GitHooksInstaller::GIT_HOOKS_PACKAGE_TYPE);
        } catch (InvalidArgumentException $e) {
            // Nothing to do if the installer is not registered.
            return;
        }

        if (!($installer instanceof GitHooksInstaller)) {
            // Not the installer that this plugin manages.
            return;
        }

        $installationManager->removeInstaller($installer);
    }

    /**
     * @inheritdoc
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
    }
}
