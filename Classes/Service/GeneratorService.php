<?php
namespace byTorsten\React\Kickstarter\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\ConsoleOutput;
use Neos\Flow\Package\Package;
use Neos\FluidAdaptor\View\StandaloneView;
use Neos\Utility\Files;

/**
 * @Flow\Scope("singleton")
 */
class GeneratorService
{
    /**
     * @var ConsoleOutput
     */
    protected $output;

    /**
     *
     */
    public function __construct()
    {
        $this->output = new ConsoleOutput();
    }

    /**
     * @param string $path
     * @param string $content
     */
    protected function generateFile(string $path, string $content)
    {
        if (!is_dir(dirname($path))) {
            Files::createDirectoryRecursively(dirname($path));
        }

        file_put_contents($path, $content);
    }

    /**
     * @param string $path
     * @param string $sourcePath
     */
    protected function copyFile(string $path, string $sourcePath)
    {
        $this->generateFile($path, file_get_contents($sourcePath));
    }

    /**
     * @param string $path
     * @param array $context
     * @return string
     */
    protected function renderTemplate(string $path, array $context): string
    {
        $standaloneView = new StandaloneView();
        $standaloneView->setTemplatePathAndFilename($path);
        $standaloneView->assignMultiple($context);
        return $standaloneView->render();
    }

    /**
     * @param Package $package
     * @param string $reactPath
     * @return bool
     */
    public function generate(Package $package, string $reactPath): bool
    {
        $targetPath = Files::concatenatePaths([$package->getResourcesPath(), 'Private', $reactPath]);

        if (file_exists($targetPath)) {
            $this->output->outputLine();
            $this->output->outputLine('Path <error>%s</error> already exists', [$targetPath]);
            $this->output->outputLine();
            return false;
        }

        $this->output->outputLine();
        $this->output->output('Generating React package... ');
        Files::createDirectoryRecursively($targetPath);
        $this->output->outputLine('<success>done</success>');

        $this->output->output('Generating package.json... ');
        $this->generatePackageJson($package, $targetPath);
        $this->output->outputLine('<success>done</success>');

        $this->output->output('Generating components... ');
        $this->generateComponents($targetPath);
        $this->output->outputLine('<success>done</success>');

        $this->output->output('Adding dependencies... ');
        $this->addDependencies($targetPath);
        $this->output->outputLine('<success>done</success>');

        $this->output->output('Configuring view... ');
        if ($this->configureView($package, $reactPath)) {
            $this->output->outputLine('<success>done</success>');
        } else {
            $this->output->outputLine();
            $this->output->outputLine('Could not automatically configure views. Please add the following lines to your Views.yaml:');
            $this->output->outputLine();
            $this->output->outputLine('<comment>-</comment>');
            $this->output->outputLine('<comment>  requestFilter: \'isPackage("' . $package->getPackageKey() . '")\'</comment>');
            $this->output->outputLine('<comment>viewObjectName: \'byTorsten\React\Core\View\ReactView\'</comment>');
        }

        $this->output->outputLine();
        return true;
    }

    /**
     * @param Package $package
     * @param string $basePath
     */
    protected function generatePackageJson(Package $package, string $basePath)
    {
        $templatePathAndFilename = 'resource://byTorsten.React.Kickstarter/Private/Generator/JS/package.json.tmpl';

        $jsPackageName = '@' . strtolower($package->getPackageKey());
        $jsPackageName = preg_replace('/\./', '/', $jsPackageName, 1);
        $jsPackageName = str_replace('.', '-', $jsPackageName);

        $fileContent = $this->renderTemplate($templatePathAndFilename, [
            'name' => $jsPackageName
        ]);

        $packageJsonPathAndFilename = Files::concatenatePaths([$basePath, 'package.json']);
        $this->generateFile($packageJsonPathAndFilename, $fileContent);
    }

    /**
     * @param string $basePath
     */
    public function generateComponents(string $basePath)
    {
        $this->copyFile(
            Files::concatenatePaths([$basePath, 'src', 'Html.js']),
            'resource://byTorsten.React.Kickstarter/Private/Generator/JS/src/Html.js.tmpl'
        );

        $this->copyFile(
            Files::concatenatePaths([$basePath, 'src', 'App.js']),
            'resource://byTorsten.React.Kickstarter/Private/Generator/JS/src/App.js.tmpl'
        );

        $this->copyFile(
            Files::concatenatePaths([$basePath, 'src', 'routes', 'Index.js']),
            'resource://byTorsten.React.Kickstarter/Private/Generator/JS/src/routes/Index.js.tmpl'
        );

        $this->copyFile(
            Files::concatenatePaths([$basePath, 'index.client.js']),
            'resource://byTorsten.React.Kickstarter/Private/Generator/JS/index.client.js.tmpl'
        );

        $this->copyFile(
            Files::concatenatePaths([$basePath, 'index.server.js']),
            'resource://byTorsten.React.Kickstarter/Private/Generator/JS/index.server.js.tmpl'
        );
    }

    /**
     * @param string $basePath
     * @throws \Exception
     */
    protected function addDependencies(string $basePath)
    {
        chdir($basePath);
        exec('/usr/bin/env yarn add react react-dom', $output, $return);
        if ($return !== 0) {
            throw new \Exception(implode(PHP_EOL, $output));
        }
    }

    /**
     * @param Package $package
     * @param string $path
     * @return bool
     */
    protected function configureView(Package $package, string $reactPath): bool
    {
        $path = Files::concatenatePaths([$package->getConfigurationPath(), 'Views.yaml']);
        if (file_exists($path)) {
            return false;
        }

        $content = $this->renderTemplate('resource://byTorsten.React.Kickstarter/Private/Generator/Views.yaml.tmpl', [
            'packageKey' => $package->getPackageKey(),
            'path' => $reactPath === 'React' ? null : $reactPath
        ]);

        $this->generateFile($path, $content);
        return true;
    }
}
