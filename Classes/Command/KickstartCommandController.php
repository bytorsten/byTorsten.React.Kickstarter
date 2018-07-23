<?php
namespace byTorsten\React\Kickstarter\Command;

use byTorsten\React\Kickstarter\Service\GeneratorService;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Package\Package;
use Neos\Flow\Package\PackageManagerInterface;

class KickstartCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var PackageManagerInterface
     */
    protected $packageManager;

    /**
     * @Flow\Inject
     * @var GeneratorService
     */
    protected $generatorService;

    /**
     * Kickstart the react view integration in a flow package
     *
     * @param string $reactPath path inside Resources/Private where the react js will be bootstrapped
     * @param string $packageKey
     */
    public function reactCommand(string $reactPath = 'React', string $packageKey = null)
    {
        $choices = ['not listed'];

        $requiredPackageName = $this->packageManager->getPackage('byTorsten.React')->getComposerManifest('name');

        if ($packageKey !== null) {
            $package = $this->packageManager->getPackage($packageKey);
            $requires = array_keys($package->getComposerManifest('require') ?: []);
            if (!in_array($requiredPackageName, $requires)) {
                $this->outputLine();
                $this->outputFormatted('Please ensure <info>%s</info> is required in your package\'s composer.json', [$requiredPackageName]);
                $this->outputLine();
                return;
            }

            $selectedPackageName = $packageKey;
        } else {
            /** @var Package $package */
            foreach ($this->packageManager->getAvailablePackages() as $package) {
                if ($package->getPackageKey() === 'byTorsten.React.Kickstarter') {
                    continue;
                }

                $requires = array_keys($package->getComposerManifest('require') ?: []);
                if (in_array($requiredPackageName, $requires)) {
                    $choices[] = $package->getPackageKey();
                }
            }

            $selectedPackageName = $this->output->select('In which package do you want to kickstart the react view?', $choices, 0);

            if ($selectedPackageName === $choices[0]) {
                $this->outputLine();
                $this->outputFormatted('Your package may not have been listed because you don\'t require <info>%s</info> in your composer json.', [$requiredPackageName]);
                $this->outputLine();
                $this->outputFormatted('Please ensure <info>%s</info> is required in your package\'s composer.json', [$requiredPackageName]);
                $this->outputLine();
                $this->sendAndExit(1);
            }
        }


        $package = $this->packageManager->getPackage($selectedPackageName);
        $this->generatorService->generate($package, $reactPath);
    }
}
