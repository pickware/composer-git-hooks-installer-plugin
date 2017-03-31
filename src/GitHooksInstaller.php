<?php

namespace VIISON\Composer;

use Composer\Installer\LibraryInstaller;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Silencer;

/**
 * @copyright Copyright (c) 2017 VIISON GmbH
 */
class GitHooksInstaller extends LibraryInstaller
{
    /**
     * The name of the 'extra' field that must be used in packages of type
     * 'viison-git-hooks' to specify the paths of available git hooks.
     */
    const GIT_HOOKS_PACKAGE_EXTRA_AVAILABLE_HOOKS = 'available-viison-git-hooks';

    /**
     * The package type that can be installed by this class.
     */
    const GIT_HOOKS_PACKAGE_TYPE = 'viison-git-hooks';

    /**
     * The path to the git hooks directory.
     */
    const GIT_HOOKS_PATH = '.git/hooks';

    /**
     * The name of the 'extra' field that must be used in root packages (packages
     * that require viison-git-hooks packages) to specify the required hooks of each
     * hooks package.
     */
    const ROOT_PACKAGE_EXTRA_REQUIRED_HOOKS = 'required-viison-git-hooks';

    /**
     * @inheritdoc
     */
    public function supports($packageType)
    {
        return $packageType === self::GIT_HOOKS_PACKAGE_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);
        $this->installGitHooks($package);
    }

    /**
     * @inheritdoc
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        $this->uninstallGitHooks($initial);
        parent::update($repo, $initial, $target);
        $this->installGitHooks($target);
    }

    /**
     * @inheritdoc
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $this->uninstallGitHooks($package);
        parent::uninstall($repo, $package);
    }

    /**
     * Checks the composer root package for any git hooks required from the given
     * $package and, if found, tries to find the required hooks in the $package.
     * The required hooks are inserted into the collection of already installed
     * hooks, which is then saved to disk to actiave all required hooks.
     *
     * @param PackageInterface $package
     * @throws \Exception If any of the hooks required from the given $package is not available.
     */
    protected function installGitHooks(PackageInterface $package)
    {
        if (!$this->packageHasInstallableGitHooks($package) || !$this->ensureGitHooksDir()) {
            return;
        }

        // Check the root package for required git hooks
        $rootPackageExtra = $this->composer->getPackage()->getExtra();
        if (!isset($rootPackageExtra[self::ROOT_PACKAGE_EXTRA_REQUIRED_HOOKS][$package->getName()])) {
            return;
        }
        $requiredHooks = $rootPackageExtra[self::ROOT_PACKAGE_EXTRA_REQUIRED_HOOKS][$package->getName()];

        // Update the list of installed git hooks by adding all hooks required from
        // the given package
        $availableHooks = $this->findPackageGitHooks($package);
        $installedHooks = $this->getInstalledGitHooks();
        foreach ($requiredHooks as $hookName) {
            if (!isset($availableHooks[$hookName])) {
                throw new \Exception(
                    sprintf('The package "%s" does not provide the required git hook "%s".', $package->getName(), $hookName)
                );
            }

            // Insert the package hook into the hook collection
            foreach ($availableHooks[$hookName] as $hookFilePath) {
                $hookType = basename($hookFilePath);
                if (!isset($installedHooks[$hookType])) {
                    $installedHooks[$hookType] = array();
                }
                if (!isset($installedHooks[$hookType][$package->getName()])) {
                    $installedHooks[$hookType][$package->getName()] = array();
                }

                // Find the shortest, relative path from the git hooks directory to the hook file
                $relativeHookPath = $this->filesystem->findShortestPath(($this->vendorDir . '/../' . self::GIT_HOOKS_PATH), dirname($hookFilePath), true);
                $installedHooks[$hookType][$package->getName()][$hookName] = $relativeHookPath . '/' . $hookType;
            }
        }

        // Save the updateg hook collection to disk and active hook files
        $this->saveGitHooks($installedHooks);
    }

    /**
     * Loas the currently saved list of git hooks and removes all hooks of
     * the given $package from the list. If removing the package hooks results
     * in an obsolete git hook, the respective file is removed. Finally the
     * updated hook collection is saved to disk.
     *
     * @param PackageInterface $package
     */
    protected function uninstallGitHooks(PackageInterface $package)
    {
        if (!$this->ensureGitHooksDir()) {
            return;
        }

        // Remove all hooks of this package from the collection of installed hooks
        $installedHooks = $this->getInstalledGitHooks();
        foreach ($installedHooks as $hookType => &$hooks) {
            unset($hooks[$package->getName()]);
            if (count($hooks) === 0) {
                // Remove the respective hook file
                $hookPath = self::GIT_HOOKS_PATH . '/' . $hookType;
                $this->filesystem->remove($hookPath);
            }
        }
        $this->saveGitHookCollection(array_filter($installedHooks));
    }

    /**
     * First saves the hooks collection to disk, before loading the hook template
     * and saving it to the git hooks directory for each type contained in $gitHooks.
     *
     * @param array $gitHooks
     */
    protected function saveGitHooks(array $gitHooks)
    {
        if (!$this->ensureGitHooksDir()) {
            return;
        }
        $this->saveGitHookCollection($gitHooks);
        // Generate required executable hook files
        $hookFileTemplate = file_get_contents(__DIR__ . '/../res/git-hook-template.php');
        foreach ($gitHooks as $hookType => $hooks) {
            // Replace the git hook file of the current type
            $hookPath = self::GIT_HOOKS_PATH . '/' . $hookType;
            $this->filesystem->remove($hookPath);
            file_put_contents($hookPath, $hookFileTemplate);
            // Make file executable
            Silencer::call('chmod', $hookPath, 0755);
        }
    }

    /**
     * If the given $gitHooks contains at least one element, $gitHooks is written
     * to the JSON hooks collection. Otherwise the collection file is removed, if
     * it exists.
     *
     * @param array $gitHooks
     */
    protected function saveGitHookCollection(array $gitHooks)
    {
        if (!$this->ensureGitHooksDir()) {
            return;
        }
        $hookCollectionFilePath = self::GIT_HOOKS_PATH . '/viison-hooks.json';
        if (count($gitHooks) > 0) {
            // Save the git hooks collection on disk
            $jsonFile = new JsonFile($hookCollectionFilePath, null, $this->io);
            $jsonFile->write($gitHooks);
        } else {
            // Remove git hook collection from disk
            $this->filesystem->remove($hookCollectionFilePath);
        }
    }

    /**
     * @param PackageInterface $package
     * @return boolean
     */
    protected function packageHasInstallableGitHooks(PackageInterface $package)
    {
        $extra = $package->getExtra();

        return isset($extra[self::GIT_HOOKS_PACKAGE_EXTRA_AVAILABLE_HOOKS])
            && is_array($extra[self::GIT_HOOKS_PACKAGE_EXTRA_AVAILABLE_HOOKS])
            && count($extra[self::GIT_HOOKS_PACKAGE_EXTRA_AVAILABLE_HOOKS]) > 0;
    }

    /**
     * Checks the given $package for the extra git hooks field and, if it exists,
     * checks the specified paths for git hook files and collects them in an array,
     * which is returned. Otherwise an empty array is returned.
     *
     * @param PackageInterface $package
     * @return array
     */
    protected function findPackageGitHooks(PackageInterface $package)
    {
        if (!$this->packageHasInstallableGitHooks($package)) {
            return array();
        }

        // Collect all hook paths
        $extra = $package->getExtra();
        foreach ($extra[self::GIT_HOOKS_PACKAGE_EXTRA_AVAILABLE_HOOKS] as $groupName => $hookPath) {
            $hookFilePaths[$groupName] = self::listHookFilesRecursively($this->getInstallPath($package) . '/' . $hookPath);
        }

        return $hookFilePaths;
    }

    /**
     * Tries to read the hooks collection file from the git hooks path and
     * returns its parsed JSON contend. If no such file exists, an empty array
     * is returned.
     *
     * @return array
     */
    protected function getInstalledGitHooks()
    {
        if (!$this->ensureGitHooksDir()) {
            return null;
        }

        // Check for a hooks file
        $filePath = self::GIT_HOOKS_PATH . '/viison-hooks.json';
        $jsonFile = new JsonFile($filePath, null, $this->io);
        if (!$jsonFile->exists()) {
            return array();
        }

        return $jsonFile->read();
    }

    /**
     * First checks the current path (the project path) for a .git directory and,
     * if not found, throws an exception. Otherwise the filesystem util is used to
     * create the .git/hooks directory, if it does not already exist.
     *
     * @throws \Exception If the current path does not contain a .git directory.
     */
    protected function ensureGitHooksDir()
    {
        // Validate git environment
        if (!is_dir('.git')) {
            return false;
        }

        $this->filesystem->ensureDirectoryExists(self::GIT_HOOKS_PATH);
        return true;
    }

    /**
     * Recursively traverses the given $path and collects the paths to all valid git
     * hook files. That is, file whose name is contained in the git hook whitelist.
     * Finally the collected array is retuend.
     *
     * @param string $path
     * @return array
     */
    protected static function listHookFilesRecursively($path)
    {
        $whitelist = self::getGitHookWhitelist();
        $filePaths = array_diff(scandir($path), array('.', '..'));
        $hookFilePaths = array();
        foreach ($filePaths as $filename) {
            $filePath = realpath($path . '/' . $filename);
            if (is_dir($filePath)) {
                $hookFilePaths = array_merge($hookFilePaths, self::listHookFilesRecursively($filePath));
            } elseif (is_file($filePath) && in_array($filename, $whitelist)) {
                $hookFilePaths[] = $filePath;
            }
        }

        return $hookFilePaths;
    }

    /**
     * Returns an array containing the types/names of all valid git hooks.
     *
     * @return string[]
     */
    protected static function getGitHookWhitelist()
    {
        return array(
            'applypatch-msg',
            'commit-msg',
            'post-update',
            'pre-applypatch',
            'pre-commit',
            'pre-push',
            'pre-rebase',
            'pre-receive',
            'prepare-commit-msg',
            'update'
        );
    }
}
