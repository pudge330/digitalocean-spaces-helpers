<?php
namespace BAG\Spaces\Console;

use BAG\Spaces\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\NullOutput;

class SpacesCommand extends Command {
	protected static $defaultName = 'spaces:do';
	protected static $defaultDescription = 'DigitalOcean spaces api command. Provides basic insert, update, delete, list functionality.';
	protected $client;
	protected $customName;

	public function __construct(Client $client, string $name = null) {
		$this->client = $client;
		$this->customName = $name;
		parent::__construct();
	}

	protected function configure(): void {
		// For older versions of Symfony Console
		$this
			->setName($this->customName ?: self::$defaultName)
			->setDescription(self::$defaultDescription)
		;

		$this->addArgument('action', InputArgument::REQUIRED, 'Primary action. Determines additional input arguments/options. (<fg=yellow>space-create, space-delete, space-list, space-exists, list, upload, delete, download, size</>)');
		$commandName = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : null; 
		$action = null;
		if (preg_match('/^' . self::$defaultName . '$/', $commandName)) {
			$action = isset($_SERVER['argv'][2]) ? $_SERVER['argv'][2] : null;
		}

		switch ($action) {
			case 'space-create':
				$this->addArgument('name', InputArgument::REQUIRED, 'Name of new space.');
				break;
			case 'space-delete':
				$this->addArgument('name', InputArgument::REQUIRED, 'Name of new space.');
				break;
			case 'space-list':
				break;
			case 'space-exists':
				$this->addArgument('name', InputArgument::REQUIRED, 'Name of new space.');
				break;
			case 'list':
				$this
					->addArgument('space', InputArgument::OPTIONAL, 'Object prefix/filter.')
					->addArgument('prefix', InputArgument::OPTIONAL, 'Object prefix/filter.')
				;
				break;
			case 'upload':

				break;
			case 'delete':

				break;
			case 'download':

				break;
			case 'size':

				break;
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$action = $input->getArgument('action');
		$returnCode = Command::FAILURE;
		switch ($action) {
			case 'space-create':
				$returnCode = $this->executeSpaceCreateAction($input, $output);
				break;
			case 'space-delete':
				$returnCode = $this->executeSpaceDeleteAction($input, $output);
				break;
			case 'space-list':
				$returnCode = $this->executeSpaceListAction($input, $output);
				break;
			case 'space-exists':
				$returnCode = $this->executeSpaceExistsAction($input, $output);
				break;
			case 'list':
				$returnCode = $this->executeListAction($input, $output);
				break;
			case 'upload':
				$returnCode = $this->executeUploadAction($input, $output);
				break;
			case 'delete':
				$returnCode = $this->executeDeleteAction($input, $output);
				break;
			case 'download':
				$returnCode = $this->executeDownloadAction($input, $output);
				break;
			case 'size':
				$returnCode = $this->executeSizeAction($input, $output);
				break;
		}
		return $returnCode;
	}

	protected function executeSpaceCreateAction(InputInterface $input, OutputInterface $output): int {
		$name = $input->getArgument('name');
		if (!$this->client->spaceExists($name)) {
			if ($this->client->createSpace($name)) {
				$output->writeln("Space <fg=white>'{$name}'</> created.", OutputInterface::VERBOSITY_VERBOSE);
			}
			else {
				$output->writeln("<fg=red>Failed creating space <fg=white>'{$name}'</>.</>", OutputInterface::VERBOSITY_VERBOSE);
				return Command::FAILURE;
			}
		}
		else {
			$output->writeln("Space <fg=white>'{$name}'</> already exists.", OutputInterface::VERBOSITY_VERBOSE);
		}
		return Command::SUCCESS;
	}

	protected function executeSpaceDeleteAction(InputInterface $input, OutputInterface $output): int {
		$name = $input->getArgument('name');
		if ($this->client->spaceExists($name)) {
			if ($this->client->deleteSpace($name)) {
				$output->writeln("Space <fg=white>'{$name}'</> deleted.", OutputInterface::VERBOSITY_VERBOSE);
			}
			else {
				$output->writeln("<fg=red>Failed deleting space <fg=white>'{$name}'</>.</>", OutputInterface::VERBOSITY_VERBOSE);
				return Command::FAILURE;
			}
		}
		else {
			$output->writeln("Space <fg=white>'{$name}'</> doesn't exists.", OutputInterface::VERBOSITY_VERBOSE);
		}
		return Command::SUCCESS;
	}

	protected function executeSpaceListAction(InputInterface $input, OutputInterface $output): int {
		$spaces = $this->client->listSpaces();
		foreach ($spaces as $space) {
			if ($output->isVerbose()) {
				$output->writeln($space['Name'] . ' <fg=magenta>' . $space['CreationDate']->format('F j, Y g:i:s A') . ' UTC</> <fg=yellow>(Creation Date)</>');
			}
			else {
				$output->writeln($space['Name']);
			}
		}
		return Command::SUCCESS;
	}

	protected function executeSpaceExistsAction(InputInterface $input, OutputInterface $output): int {
		$name = $input->getArgument('name');
		if ($this->client->spaceExists($name)) {
			$output->writeln("Space <fg=white>'{$name}'</> exists.");
		}
		else {
			$output->writeln("Space <fg=white>'{$name}'</> doesn't exists.");
		}
		return Command::SUCCESS;
	}

	protected function executeListAction(InputInterface $input, OutputInterface $output): int {
		$space = $input->getArgument('space');
		$prefix = $input->getArgument('prefix');
		return Command::SUCCESS;
	}

	protected function executeUploadAction(InputInterface $input, OutputInterface $output): int {
		return Command::SUCCESS;
	}

	protected function executeDeleteAction(InputInterface $input, OutputInterface $output): int {
		return Command::SUCCESS;
	}

	protected function executeDownloadAction(InputInterface $input, OutputInterface $output): int {
		return Command::SUCCESS;
	}

	protected function executeSizeAction(InputInterface $input, OutputInterface $output): int {
		return Command::SUCCESS;
	}
}