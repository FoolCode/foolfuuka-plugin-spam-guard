<?php

namespace Foolz\Foolfuuka\Plugins\SpamGuard\Console;

use Foolz\Inet\Inet;
use Foolz\Foolframe\Model\Context;
use Foolz\Foolframe\Model\DoctrineConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Console extends Command
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var DoctrineConnection
     */
    protected $dc;

    public function __construct(Context $context)
    {
        parent::__construct();
        
        $this->config = $context->getService('config');
        $this->dc = $context->getService('doctrine');
    }

    protected function configure()
    {
        $this
            ->setName('spam_guard:generate')
            ->setDescription('Generates a dump to import into the SpamGuard table.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = realpath(__DIR__.'/../../').'/tmp';
        $file = $path.'/stopforumspam.zip';
        if (!file_exists($path)) {
            mkdir($path);
        }

        if (file_exists($file) && filemtime($file) + 86400 > time()) {
            unlink($path.'/stopforumspam.zip');
        }

        if (!file_exists($file)) {
            $resource = fopen($file, 'w+');
            $curl = curl_init('http://www.stopforumspam.com/downloads/bannedips.zip');
            curl_setopt($curl, CURLOPT_TIMEOUT, 60);
            curl_setopt($curl, CURLOPT_FILE, $resource);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($curl);
            curl_close($curl);
            fclose($resource);
        }

        $zip = new \ZipArchive;
        $res = $zip->open($file);
        if (true === $res) {
            $zip->extractTo($path);
            $zip->close();
        }

        $table = $this->dc->p('plugin_fu_spam_guard_sfs');
        $this->dc->getConnection()
            ->query('TRUNCATE TABLE '.$table);

        if (false !== ($resource = fopen($path.'/bannedips.csv', 'r'))) {
            $info = fopen($path.'/bannedips.sql', 'w');
            while (false !== ($data = fgetcsv($resource))) {
                for ($row = 0, $len = count($data); $row < $len; $row++) {
                    if ($data[$row]) {
                        fwrite($info, "INSERT INTO ".$table." (ip_addr_a, ip_addr_n) VALUES ('".$data[$row]."', ".Inet::ptod($data[$row]).");\n");
                    }
                }
            }

            fclose($info);
            fclose($resource);
        }

        $config = $this->config->get('foolz/foolframe', 'db', 'default');
        echo 'SpamGuard'.PHP_EOL;
        echo '---------'.PHP_EOL;
        echo 'In order to complete the process, run the following command from your shell:'.PHP_EOL;
        echo ' ## mysql -h '.$config['host'].' -P '.$config['port'].' -u '.$config['user'].' -p '.$config['dbname'].' < '.$path.'/bannedips.sql'.PHP_EOL;
    }
}
