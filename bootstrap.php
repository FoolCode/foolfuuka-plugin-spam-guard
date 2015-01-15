<?php

use Foolz\Foolframe\Model\Context;
use Foolz\Plugin\Event;

class HHVM_SpamGuard
{
    public function run()
    {
        Event::forge('Foolz\Plugin\Plugin::execute#foolz/foolfuuka-plugin-spam-guard')
            ->setCall(function ($result) {
                /* @var Context $context */
                $context = $result->getParam('context');

                /** @var Autoloader $autoloader */
                $autoloader = $context->getService('autoloader');
                $autoloader->addClassMap([
                    'Foolz\Foolframe\Controller\Admin\Plugins\SpamGuard' => __DIR__.'/classes/controller/admin.php',
                    'Foolz\Foolfuuka\Plugins\SpamGuard\Console\Console' => __DIR__ . '/classes/console/console.php',
                    'Foolz\Foolfuuka\Plugins\SpamGuard\Model\Validator' => __DIR__.'/classes/model/validator.php'
                ]);

                $context->getContainer()
                    ->register('foolfuuka-plugin.spam_guard_validator', 'Foolz\Foolfuuka\Plugins\SpamGuard\Model\Validator')
                    ->addArgument($context);


                Event::forge('Foolz\Foolframe\Model\Context::handleConsole#obj.app')
                    ->setCall(function ($result) use ($context) {
                        $result->getParam('application')
                            ->add(new \Foolz\Foolfuuka\Plugins\SpamGuard\Console\Console($context));
                    });

                Event::forge('Foolz\Foolframe\Model\Context::handleWeb#obj.afterAuth')
                    ->setCall(function ($result) use ($context) {
                        // don't add the admin panels if the user is not an admin
                        if ($context->getService('auth')->hasAccess('maccess.admin')) {
                            $context->getRouteCollection()->add(
                                'foolfuuka.plugin.spam_guard.admin', new \Symfony\Component\Routing\Route(
                                    '/admin/plugins/spam_guard/{_suffix}',
                                    [
                                        '_suffix' => 'manage',
                                        '_controller' => 'Foolz\Foolframe\Controller\Admin\Plugins\SpamGuard::manage'
                                    ],
                                    [
                                        '_suffix' => '.*'
                                    ]
                                )
                            );

                            Event::forge('Foolz\Foolframe\Controller\Admin::before#var.sidebar')
                                ->setCall(function ($result) {
                                    $sidebar = $result->getParam('sidebar');
                                    $sidebar[]['plugins'] = [
                                        'content' => ['spam_guard/manage' => ['level' => 'admin', 'name' => 'Spam Guard', 'icon' => 'icon-shield']]
                                    ];
                                    $result->setParam('sidebar', $sidebar);
                                });
                        }
                    });

                Event::forge('Foolz\Foolfuuka\Model\CommentInsert::insert#obj.captcha')
                    ->setCall(function ($object) use ($context) {
                        if (!$context->getService('auth')->hasAccess('maccess.mod')) {
                            $context->getService('foolfuuka-plugin.spam_guard_validator')->checkComment($object);
                        }
                    });
            });

        Event::forge('Foolz\Foolframe\Model\Plugin::install#foolz/foolfuuka-plugin-spam-guard')
            ->setCall(function ($result) {
                /** @var Context $context */
                $context = $result->getParam('context');
                /** @var DoctrineConnection $dc */
                $dc = $context->getService('doctrine');

                /** @var Schema $schema */
                $schema = $result->getParam('schema');
                $table = $schema->createTable($dc->p('plugin_fu_spam_guard_sfs'));
                if ($dc->getConnection()->getDriver()->getName() == 'pdo_mysql') {
                    $table->addOption('charset', 'utf8mb4');
                    $table->addOption('collate', 'utf8mb4_general_ci');
                }
                $table->addColumn('ip_addr_a', 'string', ['length' => 64]);
                $table->addColumn('ip_addr_n', 'decimal', ['unsigned' => true, 'precision' => 39, 'scale' => 0, 'default' => 0]);
                $table->setPrimaryKey(['ip_addr_a', 'ip_addr_n']);
            });
    }
}

(new HHVM_SpamGuard())->run();
