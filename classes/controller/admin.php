<?php

namespace Foolz\Foolframe\Controller\Admin\Plugins;

use Foolz\Foolframe\Model\Validation\ActiveConstraint\Trim;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SpamGuard extends \Foolz\Foolframe\Controller\Admin
{
    protected $purge_service;

    public function before()
    {
        parent::before();

        $this->param_manager->setParam('controller_title', 'Spam Guard');
    }

    public function security()
    {
        return $this->getAuth()->hasAccess('maccess.mod');
    }

    function structure()
    {
        return [
            'open' => [
                'type' => 'open',
            ],
            'foolfuuka.plugins.spam_guard.akismet_key' => [
                'preferences' => true,
                'type' => 'input',
                'label' => _i('Akismet API Key'),
                'class' => 'span3',
                'validation' => [new Trim()]
            ],
            'foolfuuka.plugins.spam_guard.akismet_url' => [
                'preferences' => true,
                'type' => 'input',
                'label' => _i('Hostname'),
                'class' => 'span3',
                'validation' => [new Trim()]
            ],
            'foolfuuka.plugins.spam_guard.enable_akismet' => [
                'preferences' => true,
                'type' => 'checkbox',
                'help' => _i('Check the comment data against Akismet.')
            ],
            'foolfuuka.plugins.spam_guard.enable_stopforumspam' => [
                'preferences' => true,
                'type' => 'checkbox',
                'help' => _i('Check the comment data against StopForumSpam.')
            ],
            'foolfuuka.plugins.spam_guard.enable_spooky' => [
                'preferences' => true,
                'type' => 'checkbox',
                'help' => _i('Enforce Captcha on first ghost post.')
            ],
            'submit' => [
                'type' => 'submit',
                'class' => 'btn-primary',
                'value' => _i('Submit')
            ],
            'close' => [
                'type' => 'close'
            ],
        ];
    }

    function action_manage()
    {
        $this->param_manager->setParam('method_title', 'Manage');

        $data['form'] = $this->structure();

        $this->preferences->submit_auto($this->getRequest(), $data['form'], $this->getPost());
        $this->builder->createPartial('body', 'form_creator')->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }
}
